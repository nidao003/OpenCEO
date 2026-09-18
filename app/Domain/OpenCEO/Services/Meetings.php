<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\DB;

class Meetings
{
    /** @api */
    public function create(string $title, string $meetingType = 'weekly', string $periodKey = '', array $agenda = []): int
    {
        Guard::manager();
        return (int) DB::table('openceo_meetings')->insertGetId([
            'meeting_type' => $meetingType,
            'title' => $title,
            'period_key' => $periodKey ?: null,
            'agenda_json' => json_encode($agenda, JSON_UNESCAPED_UNICODE),
            'status' => 'planned',
            'created_by' => session('userdata.id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function addDecision(int $meetingId, string $title, string $content = '', int $projectId = 0, int $ownerUserId = 0): int
    {
        Guard::manager();
        return (int) DB::table('openceo_decisions')->insertGetId([
            'meeting_id' => $meetingId ?: null,
            'project_id' => $projectId ?: null,
            'title' => $title,
            'content' => $content,
            'status' => 'proposed',
            'owner_user_id' => $ownerUserId ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function addAction(int $meetingId, string $title, int $ownerUserId = 0, string $dueDate = '', int $projectId = 0): int
    {
        Guard::manager();
        return (int) DB::table('openceo_actions')->insertGetId([
            'meeting_id' => $meetingId ?: null,
            'project_id' => $projectId ?: null,
            'title' => $title,
            'owner_user_id' => $ownerUserId ?: null,
            'due_date' => $dueDate ?: null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function addRisk(int $meetingId, string $title, string $content = '', string $severity = 'medium', int $projectId = 0, int $ownerUserId = 0): int
    {
        Guard::manager();
        return (int) DB::table('openceo_risks')->insertGetId([
            'meeting_id' => $meetingId ?: null,
            'project_id' => $projectId ?: null,
            'title' => $title,
            'content' => $content,
            'severity' => $severity,
            'status' => 'open',
            'owner_user_id' => $ownerUserId ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @api */
    public function updateDecisionStatus(int $decisionId, string $status): bool
    {
        Guard::manager();
        if (! in_array($status, ['proposed', 'approved', 'rejected', 'modified'], true)) {
            throw new \InvalidArgumentException('Invalid decision status.');
        }
        return DB::table('openceo_decisions')->where('id', $decisionId)->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    /** @api */
    public function updateActionStatus(int $actionId, string $status): bool
    {
        Guard::manager();
        if (! in_array($status, ['open', 'in_progress', 'blocked', 'done', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Invalid action status.');
        }
        return DB::table('openceo_actions')->where('id', $actionId)->update(['status' => $status, 'updated_at' => now()]) > 0;
    }
}
