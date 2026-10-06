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
    $table->unsignedInteger('user_id'); $table->string('tenant_id'); $table->boolean('can_refund');
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
    $db->table('memberships')->insert(['user_id' => $index + 1, 'tenant_id' => 'tenant-a', 'can_refund' => $name !== 'denied']);
}
$db->table('memberships')->insert(['user_id' => 1, 'tenant_id' => 'tenant-b', 'can_refund' => true]);
$db->table('orders')->insert([['id' => 101, 'tenant_id' => 'tenant-a'], ['id' => 102, 'tenant_id' => 'tenant-a'], ['id' => 201, 'tenant_id' => 'tenant-b']]);
echo "Installed application fixture initialized.\n";
