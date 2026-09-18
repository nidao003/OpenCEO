<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class State
{
    /** @api */
    public function current(): array
    {
        Guard::manager();
        $projects = DB::table('zp_projects')
            ->select(['id', 'name', 'state', 'type', 'parent', 'start', 'end', 'modified'])
            ->where(function ($q) {
                $q->whereNull('active')->orWhere('active', '>', -1);
            })->orderBy('name')->get();

        $observations = collect(DB::table('openceo_project_observations')
            ->orderByDesc('observed_at')->orderByDesc('id')->get())
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->first());

        $corrections = collect(DB::table('openceo_observation_corrections')->orderByDesc('id')->get())
            ->groupBy('observation_id');

        $projectStates = collect($projects)->map(function ($project) use ($observations, $corrections) {
            $row = (array) $project;
            $observation = $observations[$project->id] ?? null;
            $observed = $observation ? (array) $observation : [];
            foreach (['blockers_json', 'risks_json'] as $key) {
                $observed[$key] = $this->decode($observed[$key] ?? null, []);
            }
            if ($observation && isset($corrections[$observation->id])) {
                foreach ($corrections[$observation->id] as $correction) {
                    $observed[$correction->field_name] = $correction->corrected_value;
                }
            }
            return [
                'project' => $row,
                'ai_observation' => $observed,
            ];
        })->values()->all();

        $actions = collect(DB::table('openceo_actions')->where('status', '<>', 'done')->orderBy('due_date')->get())
            ->map(fn ($r) => (array) $r)->all();
        $decisions = collect(DB::table('openceo_decisions')->where('status', 'proposed')->orderByDesc('id')->get())
            ->map(fn ($r) => (array) $r)->all();
        $risks = collect(DB::table('openceo_risks')->where('status', 'open')->orderByDesc('id')->get())
            ->map(fn ($r) => (array) $r)->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'projects' => $projectStates,
            'open_actions' => $actions,
            'pending_decisions' => $decisions,
            'open_risks' => $risks,
            'pending_project_candidates' => DB::table('openceo_project_candidates')->where('status', 'pending')->count(),
            'pending_identity_mappings' => DB::table('openceo_person_identities')->where('status', 'pending')->count(),
        ];
    }

    /** @api */
    public function snapshot(string $periodType, string $periodKey): array
    {
        Guard::manager();
        $state = $this->current();
        DB::table('openceo_company_snapshots')->updateOrInsert(
            ['period_type' => $periodType, 'period_key' => $periodKey],
            [
                'state_json' => json_encode($state, JSON_UNESCAPED_UNICODE),
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        return $state;
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
