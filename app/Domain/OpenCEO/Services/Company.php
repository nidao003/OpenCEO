<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class Company
{
    /** @api */
    public function getProfile(): array
    {
        Guard::manager();
        $row = DB::table('openceo_company_profile')->orderBy('id')->first();
        if (! $row) {
            return [];
        }
        $data = (array) $row;
        $data['strategic_focus'] = $this->decode($data['strategic_focus'] ?? null, []);
        $data['context_json'] = $this->decode($data['context_json'] ?? null, []);
        return $data;
    }

    /** @api */
    public function saveProfile(
        string $name,
        string $background = '',
        string $businessModel = '',
        string $mission = '',
        string $vision = '',
        array $strategicFocus = [],
        array $context = [],
    ): array {
        Guard::manager();
        $existing = DB::table('openceo_company_profile')->orderBy('id')->first();
        $payload = [
            'name' => trim($name),
            'background' => $background,
            'business_model' => $businessModel,
            'mission' => $mission,
            'vision' => $vision,
            'strategic_focus' => json_encode($strategicFocus, JSON_UNESCAPED_UNICODE),
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('openceo_company_profile')->where('id', $existing->id)->update($payload);
            $id = (int) $existing->id;
        } else {
            $payload['created_at'] = now();
            $id = (int) DB::table('openceo_company_profile')->insertGetId($payload);
        }
        return ['id' => $id] + $this->getProfile();
    }

    /** @api */
    public function addMemory(
        string $content,
        string $memoryType = 'context',
        string $title = '',
        string $sourceType = 'manual',
        string $sourceId = '',
        array $metadata = [],
    ): int {
        Guard::manager();
        return (int) DB::table('openceo_company_memory')->insertGetId([
            'memory_type' => $memoryType,
            'title' => $title,
            'content' => $content,
            'source_type' => $sourceType,
            'source_id' => $sourceId ?: null,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function listMemory(string $memoryType = '', bool $activeOnly = true, int $limit = 100): array
    {
        Guard::manager();
        $query = DB::table('openceo_company_memory')->orderByDesc('id');
        if ($memoryType !== '') {
            $query->where('memory_type', $memoryType);
        }
        if ($activeOnly) {
            $query->where('active', true);
        }
        return collect($query->limit(max(1, min($limit, 500)))->get())
            ->map(function ($row) {
                $data = (array) $row;
                $data['metadata_json'] = $this->decode($data['metadata_json'] ?? null, []);
                return $data;
            })->all();
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
