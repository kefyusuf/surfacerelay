<?php

declare(strict_types=1);

/**
 * Quickstart demo seeder: creates the login user and synthetic orders for the
 * try-it flow. Local demo data only — synthetic records, never production.
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

use App\Support\FixedTenantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$email = 'quickstart@example.test';

$user = App\Models\User::query()->firstOrCreate(
    ['email' => $email],
    ['name' => 'Quickstart Demo', 'password' => Hash::make('quickstart-password')],
);

DB::table('quickstart_orders')->updateOrInsert(
    ['id' => 101],
    ['tenant_id' => FixedTenantResolver::TENANT, 'status' => 'paid'],
);
DB::table('quickstart_orders')->updateOrInsert(
    ['id' => 102],
    ['tenant_id' => FixedTenantResolver::TENANT, 'status' => 'shipped'],
);

echo json_encode([
    'seeded' => true,
    'email' => $email,
    'password' => 'quickstart-password',
    'orders' => [101 => 'paid', 102 => 'shipped'],
    'tenant' => FixedTenantResolver::TENANT,
], JSON_THROW_ON_ERROR), PHP_EOL;
