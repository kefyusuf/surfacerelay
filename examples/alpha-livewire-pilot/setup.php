<?php

declare(strict_types=1);

$root = $argv[1] ?? __DIR__;
foreach (['bootstrap/cache', 'database', 'storage/framework/cache/data',
    'storage/framework/cache/locks', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
    if (!is_dir($root . '/' . $directory)) { mkdir($root . '/' . $directory, 0777, true); }
}
touch($root . '/database/acceptance.sqlite');
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if ($kernel->call('migrate', ['--force' => true]) !== 0) { throw new RuntimeException('Fixture migration failed.'); }
$schema = $app->make('db')->connection()->getSchemaBuilder();
$schema->create('users', function ($table): void {
    $table->id(); $table->string('email')->unique(); $table->string('password'); $table->rememberToken();
});
$schema->create('memberships', function ($table): void {
    $table->unsignedInteger('user_id'); $table->string('tenant_id'); $table->boolean('can_hold');
    $table->unique(['user_id', 'tenant_id']);
});
$schema->create('orders', function ($table): void { $table->unsignedInteger('id')->primary(); $table->string('tenant_id'); });
$schema->create('effects', function ($table): void {
    $table->id(); $table->unsignedInteger('actor_id'); $table->string('tenant_id');
    $table->text('order_ids'); $table->string('reason'); $table->unsignedInteger('worker_pid');
});
$db = $app->make('db')->connection();
foreach (['owner', 'peer', 'denied'] as $index => $name) {
    $db->table('users')->insert(['id' => $index + 1, 'email' => $name . '@example.test',
        'password' => $app->make('hash')->make('acceptance-password')]);
    $db->table('memberships')->insert(['user_id' => $index + 1, 'tenant_id' => 'tenant-a', 'can_hold' => $name !== 'denied']);
}
$db->table('memberships')->insert(['user_id' => 1, 'tenant_id' => 'tenant-b', 'can_hold' => true]);
$db->table('orders')->insert([['id' => 101, 'tenant_id' => 'tenant-a'], ['id' => 102, 'tenant_id' => 'tenant-a'], ['id' => 201, 'tenant_id' => 'tenant-b']]);
$schema->create('pilot_livewire_bindings', function ($table): void {
    $table->string('binding_id')->primary(); $table->string('component_id'); $table->string('session_hash');
    $table->unsignedInteger('actor_id')->nullable(); $table->string('tenant_id')->nullable();
    $table->unsignedInteger('record_id'); $table->boolean('active'); $table->text('descriptor');
    $table->unsignedInteger('expires_at'); $table->string('idempotency_key');
    $table->string('challenge_id')->nullable(); $table->string('pending_reason')->nullable();
    $table->text('receipt_ciphertext')->nullable();
});
echo "Installed application fixture initialized.\n";
