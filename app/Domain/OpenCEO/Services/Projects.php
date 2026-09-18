<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;
use Leantime\Domain\Projects\Services\Projects as LeantimeProjects;

class Projects
{
    public function __construct(private LeantimeProjects $projects) {}

    /** @api */
    public function registry(): array
    {
        Guard::manager();
        $projects = DB::table('zp_projects')
            ->select(['id', 'name', 'details', 'state', 'type', 'parent', 'start', 'end', 'modified'])
            ->where(function ($q) {
                $q->whereNull('active')->orWhere('active', '>', -1);
            })
            ->orderBy('name')
            ->get();

        $aliases = collect(DB::table('openceo_project_aliases')->get())
            ->groupBy('project_id')
            ->map(fn ($rows) => collect($rows)->pluck('alias')->values()->all());

        return collect($projects)->map(function ($project) use ($aliases) {
            $row = (array) $project;
            $row['aliases'] = $aliases[(string) $project->id] ?? $aliases[$project->id] ?? [];

            return $row;
        })->all();
    }

    /** @api */
    public function addAlias(int $projectId, string $alias, string $source = 'manual'): int
    {
        Guard::manager();
        $normalized = $this->normalizeName($alias);
        $existing = DB::table('openceo_project_aliases')->where('normalized_alias', $normalized)->first();

        if ($existing) {
            if ((int) $existing->project_id !== $projectId) {
                throw new \InvalidArgumentException('Alias is already assigned to a different project.');
            }

            return (int) $existing->id;
        }

        return (int) DB::table('openceo_project_aliases')->insertGetId([
            'project_id' => $projectId,
            'alias' => trim($alias),
            'normalized_alias' => $normalized,
            'source' => $source,
            'confirmed_by' => session('userdata.id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function resolveName(string $name): array
    {
        Guard::manager();
        $normalized = $this->normalizeName($name);

        $project = DB::table('zp_projects')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();

        if ($project) {
            return ['status' => 'matched', 'project_id' => (int) $project->id, 'match_type' => 'canonical'];
        }

        $alias = DB::table('openceo_project_aliases')->where('normalized_alias', $normalized)->first();
        if ($alias) {
            return ['status' => 'matched', 'project_id' => (int) $alias->project_id, 'match_type' => 'alias'];
        }

        $candidate = DB::table('openceo_project_candidates')
            ->where('normalized_name', $normalized)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        return $candidate
            ? ['status' => 'candidate', 'candidate_id' => (int) $candidate->id]
            : ['status' => 'unmatched'];
    }

    /** @api */
    public function createCandidate(string $name, float $confidence = 0.5, array $evidence = []): int
    {
        Guard::manager();
        $normalized = $this->normalizeName($name);
        $existing = DB::table('openceo_project_candidates')
            ->where('normalized_name', $normalized)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existingEvidence = $this->decode($existing->evidence_json ?? null, []);
            $mergedEvidence = array_merge(
                is_array($existingEvidence) ? $existingEvidence : [],
                $evidence
            );

            // Keep candidate evidence idempotent across transport retries.
            $deduped = [];
            foreach ($mergedEvidence as $entry) {
                $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $key = hash('sha256', $encoded === false ? serialize($entry) : $encoded);
                $deduped[$key] = $entry;
            }

            $mergedEvidence = array_slice(array_values($deduped), -100);

            DB::table('openceo_project_candidates')->where('id', $existing->id)->update([
                'confidence' => max((float) $existing->confidence, max(0, min(1, $confidence))),
                'evidence_json' => json_encode($mergedEvidence, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('openceo_project_candidates')->insertGetId([
            'name' => trim($name),
            'normalized_name' => $normalized,
            'status' => 'pending',
            'confidence' => max(0, min(1, $confidence)),
            'evidence_json' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function listCandidates(string $status = 'pending', int $limit = 100): array
    {
        Guard::manager();
        $query = DB::table('openceo_project_candidates')->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        return collect($query->limit(max(1, min($limit, 500)))->get())
            ->map(function ($row) {
                $data = (array) $row;
                $data['evidence_json'] = $this->decode($data['evidence_json'] ?? null, []);

                return $data;
            })->all();
    }

    /** @api */
    public function resolveCandidate(
        int $candidateId,
        string $action,
        int $projectId = 0,
        string $name = '',
        int $parentProjectId = 0,
    ): array {
        Guard::manager();
        $candidate = DB::table('openceo_project_candidates')->where('id', $candidateId)->first();

        if (! $candidate) {
            throw new \InvalidArgumentException('Candidate not found.');
        }

        if ($candidate->status !== 'pending') {
            return [
                'candidate_id' => $candidateId,
                'status' => $candidate->status,
                'project_id' => $candidate->resolved_project_id,
            ];
        }

        if (! in_array($action, ['create', 'link', 'ignore'], true)) {
            throw new \InvalidArgumentException('action must be create, link or ignore.');
        }

        if ($action === 'link' && ($projectId <= 0 || ! DB::table('zp_projects')->where('id', $projectId)->exists())) {
            throw new \InvalidArgumentException('Valid projectId required for link.');
        }

        return DB::transaction(function () use ($candidateId, $candidate, $action, $projectId, $name, $parentProjectId): array {
            $resolvedProjectId = null;
            $status = 'ignored';

            if ($action === 'link') {
                $resolvedProjectId = $projectId;
                $status = 'linked';
            } elseif ($action === 'create') {
                $projectName = trim($name !== '' ? $name : $candidate->name);
                $values = [
                    'name' => $projectName,
                    'details' => 'Created from OpenCEO project candidate #'.$candidateId,
                    'clientId' => 0,
                    'hourBudget' => '0',
                    'assignedUsers' => [],
                    'dollarBudget' => 0,
                    'psettings' => 'all',
                    'parent' => $parentProjectId > 0 ? $parentProjectId : null,
                    'start' => null,
                    'end' => null,
                ];

                $resolvedProjectId = (int) $this->projects->addProject($values);
                if ($resolvedProjectId <= 0) {
                    throw new \RuntimeException('Failed to create Leantime project from candidate.');
                }

                $status = 'created';
            }

            DB::table('openceo_project_candidates')->where('id', $candidateId)->update([
                'status' => $status,
                'resolved_project_id' => $resolvedProjectId,
                'resolved_by' => session('userdata.id') ?: null,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

            if ($resolvedProjectId) {
                $this->addAlias((int) $resolvedProjectId, $candidate->name, 'candidate_confirmation');

                DB::table('openceo_project_events')->where('candidate_id', $candidateId)->update([
                    'project_id' => $resolvedProjectId,
                    'candidate_id' => null,
                    'updated_at' => now(),
                ]);

                DB::table('openceo_report_items')->where('candidate_id', $candidateId)->update([
                    'project_id' => $resolvedProjectId,
                    'candidate_id' => null,
                    'updated_at' => now(),
                ]);
            }

            return [
                'candidate_id' => $candidateId,
                'status' => $status,
                'project_id' => $resolvedProjectId,
            ];
        });
    }

    /** @api */
    public function addEvent(
        string $eventType,
        int $reportId = 0,
        int $projectId = 0,
        int $candidateId = 0,
        array $payload = [],
        string $sourceText = '',
        float $confidence = 1.0,
    ): int {
        Guard::manager();

        if ($projectId <= 0 && $candidateId <= 0) {
            throw new \InvalidArgumentException('projectId or candidateId is required.');
        }

        return (int) DB::table('openceo_project_events')->insertGetId([
            'project_id' => $projectId ?: null,
            'candidate_id' => $candidateId ?: null,
            'report_id' => $reportId ?: null,
            'event_type' => $eventType,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'source_text' => $sourceText ?: null,
            'confidence' => max(0, min(1, $confidence)),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function addObservation(
        int $projectId,
        int $reportId = 0,
        ?float $progressEstimate = null,
        string $health = '',
        string $scheduleState = '',
        string $trend = '',
        array $blockers = [],
        array $risks = [],
        string $summary = '',
        float $confidence = 0.5,
    ): int {
        Guard::manager();

        return (int) DB::table('openceo_project_observations')->insertGetId([
            'project_id' => $projectId,
            'report_id' => $reportId ?: null,
            'progress_estimate' => $progressEstimate,
            'health' => $health ?: null,
            'schedule_state' => $scheduleState ?: null,
            'trend' => $trend ?: null,
            'blockers_json' => json_encode($blockers, JSON_UNESCAPED_UNICODE),
            'risks_json' => json_encode($risks, JSON_UNESCAPED_UNICODE),
            'summary' => $summary,
            'confidence' => max(0, min(1, $confidence)),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Human corrections are stored as project-level overlays. The originating
     * observation remains unchanged and auditable, while the newest correction
     * for each field continues to override future AI observations.
     *
     * @api
     */
    public function correctObservation(
        int $observationId,
        string $fieldName,
        string $correctedValue,
        string $reason = '',
    ): int {
        Guard::manager();
        $allowed = ['progress_estimate', 'health', 'schedule_state', 'trend', 'summary'];

        if (! in_array($fieldName, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported correction field.');
        }

        $observation = DB::table('openceo_project_observations')->where('id', $observationId)->first();
        if (! $observation) {
            throw new \InvalidArgumentException('Observation not found.');
        }

        return (int) DB::table('openceo_observation_corrections')->insertGetId([
            'observation_id' => $observationId,
            'project_id' => (int) $observation->project_id,
            'field_name' => $fieldName,
            'original_value' => (string) ($observation->{$fieldName} ?? ''),
            'corrected_value' => $correctedValue,
            'reason' => $reason,
            'corrected_by' => session('userdata.id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeName(string $name): string
    {
        $value = mb_strtolower(trim($name));
        $value = preg_replace('/[\s\-_—–·•（）()\[\]【】]+/u', '', $value) ?: $value;

        return $value;
    }

    private function decode(mixed $value, mixed $default): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}
