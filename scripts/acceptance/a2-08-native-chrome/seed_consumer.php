<?php

declare(strict_types=1);

/**
 * A2-08 live-consumer seeder: creates the acceptance member, membership and
 * synthetic orders for the local native-Chrome proof. Local acceptance
 * tooling only; synthetic records, never production data.
 *
 * Run from the consumer app directory: php <this-file>.php
 */

$appRoot = getcwd();
if (! is_file($appRoot . '/vendor/autoload.php') || ! is_file($appRoot . '/bootstrap/app.php')) {
    fwrite(STDERR, "Run this script from the consumer app directory.\n");
    exit(2);
}

require $appRoot . '/vendor/autoload.php';
$app = require $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$email = 'a2.member@example.test';

$user = App\Models\User::query()->firstOrCreate(
    ['email' => $email],
    ['name' => 'A2 Member', 'password' => Hash::make('a2-acceptance-password')],
);

DB::table('a2_memberships')->updateOrInsert(
    ['user_id' => $user->getKey(), 'tenant_id' => 'tenant-a'],
    ['can_inspect' => true],
);

DB::table('a2_orders')->updateOrInsert(['id' => 101], ['tenant_id' => 'tenant-a']);
DB::table('a2_orders')->updateOrInsert(['id' => 201], ['tenant_id' => 'tenant-b']);

echo json_encode([
    'seeded' => true,
    'email' => $email,
    'userId' => $user->getKey(),
    'orders' => [101, 201],
    'memberships' => [['tenant' => 'tenant-a', 'can_inspect' => true]],
], JSON_THROW_ON_ERROR), PHP_EOL;
