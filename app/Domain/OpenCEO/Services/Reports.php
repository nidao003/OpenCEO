<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class Reports
{
    /**
     * Return an already-completed report id for the same source payload.
     *
     * @api
     */
    public function findProcessedDuplicate(
        string $reportType,
        string $sourceHash,
        int $userId = 0,
        int $externalIdentityId = 0,
    ): int {
        Guard::manager();

        if ($sourceHash === '') {
            return 0;
        }

        $query = DB::table('openceo_reports')
            ->where('report_type', $reportType)
            ->where('source_hash', $sourceHash)
            ->where('status', 'processed');

        if ($externalIdentityId > 0) {
            $query->where('external_identity_id', $externalIdentityId);
        } elseif ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $row = $query->orderByDesc('id')->first();

        return $row ? (int) $row->id : 0;
    }

    /** @api */
    public function ingest(
        string $reportType,
        string $sourceFilename,
        string $sourceHash,
        string $rawText,
        array $structured = [],
        array $validation = [],
        string $reportBatchKey = '',
        int $userId = 0,
        int $externalIdentityId = 0,
        string $periodStart = '',
        string $periodEnd = '',
    ): int {
        Guard::manager();

        // Reuse an unfinished row on retries. A fully processed duplicate is caught
        // by findProcessedDuplicate() before this method is called.
        $existingQuery = DB::table('openceo_reports')
            ->where('report_type', $reportType)
            ->where('source_hash', $sourceHash);

        if ($externalIdentityId > 0) {
            $existingQuery->where('external_identity_id', $externalIdentityId);
        } elseif ($userId > 0) {
            $existingQuery->where('user_id', $userId);
        }

        $existing = $sourceHash !== '' ? $existingQuery->orderByDesc('id')->first() : null;

        $payload = [
            'report_batch_key' => $reportBatchKey ?: null,
            'user_id' => $userId ?: null,
            'external_identity_id' => $externalIdentityId ?: null,
            'source_filename' => $sourceFilename,
            'source_hash' => $sourceHash ?: null,
            'period_start' => $periodStart ?: null,
            'period_end' => $periodEnd ?: null,
            'raw_text' => $rawText,
            'structured_json' => json_encode($structured, JSON_UNESCAPED_UNICODE),
            'validation_json' => json_encode($validation, JSON_UNESCAPED_UNICODE),
            'status' => 'processing',
            'updated_at' => now(),
        ];

        if ($existing && $existing->status !== 'processed') {
            DB::table('openceo_reports')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        $payload += [
            'report_type' => $reportType,
            'created_at' => now(),
        ];

        return (int) DB::table('openceo_reports')->insertGetId($payload);
    }

    /**
     * Remove derived rows from an unfinished prior attempt before reprocessing.
     * Human corrections can only exist on observations; if a processing attempt
     * never completed, those observations are not authoritative and are removed
     * together with any correction rows attached to them.
     *
     * @api
     */
    public function clearDerived(int $reportId): array
    {
        Guard::manager();

        $observationIds = DB::table('openceo_project_observations')
            ->where('report_id', $reportId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $corrections = 0;
        if ($observationIds !== []) {
            $corrections = DB::table('openceo_observation_corrections')
                ->whereIn('observation_id', $observationIds)
                ->delete();
        }

        return [
            'corrections' => $corrections,
            'observations' => DB::table('openceo_project_observations')->where('report_id', $reportId)->delete(),
            'events' => DB::table('openceo_project_events')->where('report_id', $reportId)->delete(),
            'items' => DB::table('openceo_report_items')->where('report_id', $reportId)->delete(),
        ];
    }

    /** @api */
    public function markProcessed(int $reportId): int
    {
        Guard::manager();

        return DB::table('openceo_reports')
            ->where('id', $reportId)
            ->update(['status' => 'processed', 'updated_at' => now()]);
    }

    /** @api */
    public function addItem(
        int $reportId,
        string $itemType,
        string $title = '',
        string $content = '',
        int $projectId = 0,
        int $candidateId = 0,
        ?float $progress = null,
        string $status = '',
        array $metadata = [],
    ): int {
        Guard::manager();

        return (int) DB::table('openceo_report_items')->insertGetId([
            'report_id' => $reportId,
            'project_id' => $projectId ?: null,
            'candidate_id' => $candidateId ?: null,
            'item_type' => $itemType,
            'title' => $title,
            'content' => $content,
            'progress' => $progress,
            'status' => $status ?: null,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function listReports(
        string $reportType = '',
        string $reportBatchKey = '',
        int $limit = 100,
        string $periodStart = '',
        string $periodEnd = '',
    ): array {
        Guard::manager();
        $query = DB::table('openceo_reports')->orderByDesc('id');

        if ($reportType !== '') {
            $query->where('report_type', $reportType);
        }
        if ($reportBatchKey !== '') {
            $query->where('report_batch_key', $reportBatchKey);
        }
        if ($periodStart !== '') {
            $query->where('period_end', '>=', $periodStart);
        }
        if ($periodEnd !== '') {
            $query->where('period_end', '<=', $periodEnd);
        }

        return collect($query->limit(max(1, min($limit, 500)))->get())
            ->map(function ($row) {
                $data = (array) $row;
                foreach (['structured_json', 'validation_json'] as $key) {
                    $data[$key] = $this->decode($data[$key] ?? null, []);
                }

                return $data;
            })->all();
    }

    /** @api */
    public function attachIdentity(int $externalIdentityId, int $userId): int
    {
        Guard::manager();

        return DB::table('openceo_reports')
            ->where('external_identity_id', $externalIdentityId)
            ->whereNull('user_id')
            ->update(['user_id' => $userId, 'updated_at' => now()]);
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
