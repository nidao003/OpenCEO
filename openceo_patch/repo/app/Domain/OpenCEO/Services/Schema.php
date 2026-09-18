<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

class Schema
{
    public function install(): array
    {
        $created = [];
        $definitions = $this->definitions();

        foreach ($definitions as $table => $definition) {
            if (DbSchema::hasTable($table)) {
                continue;
            }
            DbSchema::create($table, $definition);
            $created[] = $table;
        }

        return $created;
    }

    public function status(): array
    {
        return collect(array_keys($this->definitions()))
            ->mapWithKeys(fn (string $table) => [$table => DbSchema::hasTable($table)])
            ->all();
    }

    /** @return array<string, callable(Blueprint): void> */
    private function definitions(): array
    {
        return [
            'openceo_company_profile' => function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255)->default('');
                $table->longText('background')->nullable();
                $table->longText('business_model')->nullable();
                $table->longText('mission')->nullable();
                $table->longText('vision')->nullable();
                $table->json('strategic_focus')->nullable();
                $table->json('context_json')->nullable();
                $table->timestamps();
            },
            'openceo_company_memory' => function (Blueprint $table): void {
                $table->id();
                $table->string('memory_type', 80)->default('context');
                $table->string('title', 255)->nullable();
                $table->longText('content');
                $table->string('source_type', 80)->nullable();
                $table->string('source_id', 191)->nullable();
                $table->json('metadata_json')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['memory_type', 'active']);
            },
            'openceo_person_identities' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('provider', 50);
                $table->string('external_user_id', 191);
                $table->string('display_name', 255)->nullable();
                $table->string('status', 30)->default('pending');
                $table->json('metadata_json')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'external_user_id'], 'openceo_identity_provider_external_unique');
                $table->index(['user_id', 'status']);
            },
            'openceo_project_aliases' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('alias', 255);
                $table->string('normalized_alias', 255);
                $table->string('source', 80)->default('manual');
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamps();
                $table->unique('normalized_alias', 'openceo_project_alias_normalized_unique');
                $table->index('project_id');
            },
            'openceo_project_candidates' => function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('normalized_name', 255);
                $table->string('status', 30)->default('pending');
                $table->decimal('confidence', 5, 4)->default(0);
                $table->json('evidence_json')->nullable();
                $table->unsignedBigInteger('suggested_parent_project_id')->nullable();
                $table->unsignedBigInteger('resolved_project_id')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['normalized_name', 'status']);
            },
            'openceo_reports' => function (Blueprint $table): void {
                $table->id();
                $table->string('report_type', 30);
                $table->string('report_batch_key', 80)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('external_identity_id')->nullable();
                $table->string('source_filename', 512)->nullable();
                $table->string('source_hash', 64)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->longText('raw_text')->nullable();
                $table->json('structured_json')->nullable();
                $table->json('validation_json')->nullable();
                $table->string('status', 30)->default('ingested');
                $table->timestamps();
                $table->index(['report_type', 'report_batch_key']);
                $table->index(['user_id', 'period_end']);
                $table->index('source_hash');
            },
            'openceo_report_items' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('report_id');
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('candidate_id')->nullable();
                $table->string('item_type', 50);
                $table->string('title', 512)->nullable();
                $table->longText('content')->nullable();
                $table->decimal('progress', 6, 2)->nullable();
                $table->string('status', 50)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();
                $table->index(['report_id', 'item_type']);
                $table->index(['project_id', 'item_type']);
            },
            'openceo_project_events' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('candidate_id')->nullable();
                $table->unsignedBigInteger('report_id')->nullable();
                $table->string('event_type', 80);
                $table->json('payload_json')->nullable();
                $table->longText('source_text')->nullable();
                $table->decimal('confidence', 5, 4)->default(0);
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();
                $table->index(['project_id', 'observed_at']);
                $table->index(['candidate_id', 'observed_at']);
            },
            'openceo_project_observations' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('report_id')->nullable();
                $table->decimal('progress_estimate', 6, 2)->nullable();
                $table->string('health', 30)->nullable();
                $table->string('schedule_state', 30)->nullable();
                $table->string('trend', 30)->nullable();
                $table->json('blockers_json')->nullable();
                $table->json('risks_json')->nullable();
                $table->longText('summary')->nullable();
                $table->decimal('confidence', 5, 4)->default(0);
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();
                $table->index(['project_id', 'observed_at']);
            },
            'openceo_observation_corrections' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('observation_id');
                $table->string('field_name', 80);
                $table->longText('original_value')->nullable();
                $table->longText('corrected_value')->nullable();
                $table->longText('reason')->nullable();
                $table->unsignedBigInteger('corrected_by')->nullable();
                $table->timestamps();
                $table->index(['observation_id', 'field_name']);
            },
            'openceo_company_snapshots' => function (Blueprint $table): void {
                $table->id();
                $table->string('period_type', 30);
                $table->string('period_key', 80);
                $table->json('state_json');
                $table->timestamp('generated_at');
                $table->timestamps();
                $table->unique(['period_type', 'period_key'], 'openceo_snapshot_period_unique');
            },
            'openceo_meetings' => function (Blueprint $table): void {
                $table->id();
                $table->string('meeting_type', 30)->default('weekly');
                $table->string('title', 255);
                $table->string('period_key', 80)->nullable();
                $table->json('agenda_json')->nullable();
                $table->string('status', 30)->default('planned');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            },
            'openceo_decisions' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->string('title', 512);
                $table->longText('content')->nullable();
                $table->string('status', 30)->default('proposed');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->timestamps();
                $table->index(['status', 'project_id']);
            },
            'openceo_actions' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->string('title', 512);
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 30)->default('open');
                $table->timestamps();
                $table->index(['status', 'due_date']);
            },
            'openceo_risks' => function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->string('title', 512);
                $table->longText('content')->nullable();
                $table->string('severity', 30)->default('medium');
                $table->string('status', 30)->default('open');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->timestamps();
                $table->index(['status', 'severity']);
            },
            'openceo_management_outputs' => function (Blueprint $table): void {
                $table->id();
                $table->string('output_type', 50);
                $table->string('period_key', 80)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->string('status', 30)->default('draft');
                $table->longText('content');
                $table->json('metadata_json')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['output_type', 'period_key', 'status'], 'openceo_output_lookup');
            },
        ];
    }
}
