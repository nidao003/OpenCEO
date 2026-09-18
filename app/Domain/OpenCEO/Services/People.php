<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class People
{
    /** @api */
    public function upsertIdentity(
        string $provider,
        string $externalUserId,
        string $displayName = '',
        array $metadata = [],
    ): array {
        Guard::manager();
        $existing = DB::table('openceo_person_identities')
            ->where('provider', $provider)
            ->where('external_user_id', $externalUserId)
            ->first();

        $payload = [
            'display_name' => $displayName,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('openceo_person_identities')->where('id', $existing->id)->update($payload);
            return ['id' => (int) $existing->id, 'user_id' => $existing->user_id, 'status' => $existing->status];
        }

        $payload += [
            'provider' => $provider,
            'external_user_id' => $externalUserId,
            'status' => 'pending',
            'created_at' => now(),
        ];
        $id = (int) DB::table('openceo_person_identities')->insertGetId($payload);
        return ['id' => $id, 'user_id' => null, 'status' => 'pending'];
    }

    /** @api */
    public function listPending(string $provider = ''): array
    {
        Guard::manager();
        $query = DB::table('openceo_person_identities')->where('status', 'pending')->orderByDesc('id');
        if ($provider !== '') {
            $query->where('provider', $provider);
        }
        return collect($query->limit(200)->get())->map(fn ($row) => (array) $row)->all();
    }

    /** @api */
    public function listUsers(int $limit = 500): array
    {
        Guard::manager();
        return collect(DB::table('zp_user')
            ->select(['id', 'username', 'firstname', 'lastname', 'department', 'jobTitle', 'status'])
            ->whereRaw('LOWER(status) = ?', ['a'])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(max(1, min($limit, 1000)))
            ->get())
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @api */
    public function bindIdentity(int $identityId, int $userId): array
    {
        Guard::manager();
        if (! DB::table('zp_user')->where('id', $userId)->exists()) {
            throw new \InvalidArgumentException('User not found.');
        }
        $updated = DB::table('openceo_person_identities')->where('id', $identityId)->update([
            'user_id' => $userId,
            'status' => 'confirmed',
            'updated_at' => now(),
        ]);
        if (! $updated) {
            throw new \InvalidArgumentException('Identity not found.');
        }
        app(Reports::class)->attachIdentity($identityId, $userId);
        return ['identity_id' => $identityId, 'user_id' => $userId, 'status' => 'confirmed'];
    }
}
