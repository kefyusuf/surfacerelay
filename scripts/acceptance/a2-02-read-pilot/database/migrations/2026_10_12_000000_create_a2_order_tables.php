<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('a2_orders', static function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->index('tenant_id');
        });
        Schema::create('a2_memberships', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('tenant_id');
            $table->boolean('can_inspect')->default(false);
            $table->unique(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('a2_memberships');
        Schema::dropIfExists('a2_orders');
    }
};
