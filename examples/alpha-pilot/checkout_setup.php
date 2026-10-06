<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema = $app->make('db')->connection()->getSchemaBuilder();
if (!$schema->hasColumn('memberships', 'can_pay')) {
    $schema->table('memberships', fn ($table) => $table->boolean('can_pay')->default(false));
    $app->make('db')->table('memberships')->whereIn('user_id', [1, 2])->update(['can_pay' => true]);
}
if (!$schema->hasTable('pilot_checkouts')) {
    $schema->create('pilot_checkouts', function ($table): void {
        $table->string('id')->primary(); $table->unsignedInteger('actor_id'); $table->string('tenant_id');
        $table->string('session_hash'); $table->string('binding_id'); $table->unsignedInteger('record_id');
        $table->string('selection_hash'); $table->string('idempotency_key'); $table->string('challenge_id')->nullable();
        $table->text('receipt_ciphertext')->nullable(); $table->string('status'); $table->unsignedInteger('attempts')->default(0);
        $table->unsignedInteger('expires_at'); $table->unsignedInteger('amount_minor');
    });
}
if (!$schema->hasTable('pilot_payment_effects')) {
    $schema->create('pilot_payment_effects', function ($table): void {
        $table->id(); $table->string('checkout_id')->unique(); $table->unsignedInteger('actor_id');
        $table->string('tenant_id'); $table->unsignedInteger('order_id'); $table->unsignedInteger('amount_minor');
        $table->string('currency'); $table->string('session_hash');
    });
}
echo "Local simulated checkout schema ready.\n";
