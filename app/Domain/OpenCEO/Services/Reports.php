<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class Reports
{
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
        return (int) DB::table('openceo_reports')->insertGetId([
            'report_type' => $reportType,
            'report_batch_key' => $reportBatchKey ?: null,
            'user_id' => $userId ?: null,
            'external_identity_id' => $externalIdentityId ?: null,
            'source_filename' => $sourceFilename,
            'source_hash' => $sourceHash,
            'period_start' => $periodStart ?: null,
            'period_end' => $periodEnd ?: null,
            'raw_text' => $rawText,
            'structured_json' => json_encode($structured, JSON_UNESCAPED_UNICODE),
            'validation_json' => json_encode($validation, JSON_UNESCAPED_UNICODE),
            'status' => 'ingested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
