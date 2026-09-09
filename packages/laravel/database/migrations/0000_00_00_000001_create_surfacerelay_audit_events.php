<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surfacerelay_audit_events', function (Blueprint $table): void {
            $table->char('event_id', 32)->primary();
            $table->dateTime('recorded_at', precision: 6)->index();
            $table->longText('correlation_id');
            $table->char('correlation_hash', 64)->index();
            $table->longText('surface');
            $table->string('action_id', 160);
            $table->unsignedInteger('action_version');
            $table->string('action_scope', 32);
            $table->string('action_effect', 32);
            $table->string('action_risk', 32);
            $table->string('idempotency_policy', 32);
            $table->string('output_sensitivity', 32);
            $table->string('output_content_trust', 64);
            $table->string('outcome_kind', 16);
            $table->string('halted_at', 32)->nullable();
            $table->longText('halt_code')->nullable();
            $table->char('halt_code_hash', 64)->nullable();
            $table->boolean('human_confirmation_present');
            $table->json('trusted_context_manifest');

            $table->index(
                ['action_id', 'action_version', 'recorded_at'],
                'sr_audit_action_recorded_idx',
            );
            $table->index(
                ['outcome_kind', 'halt_code_hash', 'recorded_at'],
                'sr_audit_outcome_halt_recorded_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surfacerelay_audit_events');
    }
};
