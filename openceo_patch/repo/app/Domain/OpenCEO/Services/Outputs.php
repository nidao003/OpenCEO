<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class Outputs
{
    /** @api */
    public function save(
        string $outputType,
        string $content,
        string $periodKey = '',
        string $status = 'draft',
        int $parentId = 0,
        array $metadata = [],
    ): array {
        Guard::manager();
        $version = (int) DB::table('openceo_management_outputs')
            ->where('output_type', $outputType)
            ->where('period_key', $periodKey ?: null)
            ->max('version') + 1;

        $id = (int) DB::table('openceo_management_outputs')->insertGetId([
            'output_type' => $outputType,
            'period_key' => $periodKey ?: null,
            'version' => max(1, $version),
            'status' => $status,
            'content' => $content,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'parent_id' => $parentId ?: null,
            'created_by' => session('userdata.id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return ['id' => $id, 'version' => max(1, $version), 'status' => $status];
    }

    /** @api */
    public function supplement(int $outputId, string $supplement): array
    {
        Guard::manager();
        $parent = DB::table('openceo_management_outputs')->where('id', $outputId)->first();
        if (! $parent) {
            throw new \InvalidArgumentException('Output not found.');
        }
        $content = rtrim($parent->content)."\n\n## Human Supplement\n\n".trim($supplement)."\n";
        return $this->save($parent->output_type, $content, $parent->period_key ?? '', 'supplement', $outputId, ['supplement_of' => $outputId]);
    }

    /** @api */
    public function finalize(int $outputId): array
    {
        Guard::manager();
        $parent = DB::table('openceo_management_outputs')->where('id', $outputId)->first();
        if (! $parent) {
            throw new \InvalidArgumentException('Output not found.');
        }
        return $this->save($parent->output_type, $parent->content, $parent->period_key ?? '', 'final', $outputId, ['finalized_from' => $outputId]);
    }

    /** @api */
    public function latest(string $outputType, string $periodKey = ''): array
    {
        Guard::manager();
        $query = DB::table('openceo_management_outputs')->where('output_type', $outputType);
        if ($periodKey !== '') {
            $query->where('period_key', $periodKey);
        }
        $row = $query->orderByDesc('version')->orderByDesc('id')->first();
        return $row ? (array) $row : [];
    }
}
