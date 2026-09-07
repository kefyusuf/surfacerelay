<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surfacerelay_idempotency_records', function (Blueprint $table): void {
            $table->char('key_hash', 64)->primary();
            $table->char('intent_fingerprint', 64);
            $table->string('state', 32);
            $table->text('output_payload')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surfacerelay_idempotency_records');
    }
};
