"""Generate a disposable registry Filament consumer; never modify the source app."""
import json
from pathlib import Path
import shutil
import argparse
import sys
import hashlib

ROOT = Path(__file__).resolve().parents[3]
DEST = ROOT / '.tmp/t907-filament-demo'
BASE = ROOT / 'examples/alpha-livewire-pilot'
baseline_gateway = (BASE / 'app/HoldGateway.php').read_text()
if hashlib.sha256(baseline_gateway.encode()).hexdigest() != '144e72e3858fd4c5747202ea9a282867e5529b6e5d294a868b71ffe47d68c561':
    sys.exit('Pinned T-906 gateway changed; review transformations before regenerating.')
parser = argparse.ArgumentParser()
parser.add_argument('--refresh-owned', action='store_true')
args = parser.parse_args()
for ancestor in [ROOT / '.tmp', DEST]:
    if ancestor.is_symlink() or (ancestor.exists() and ancestor.resolve() != ancestor.absolute()):
        sys.exit('Refusing redirected temporary fixture path.')
marker = DEST / '.t907-owned'
if DEST.exists() and any(DEST.iterdir()):
    for entry in DEST.rglob('*'):
        if entry.is_symlink() or entry.resolve() != entry.absolute():
            sys.exit('Refusing redirected entry in owned fixture directory.')
    if not args.refresh_owned or not marker.is_file() or marker.read_text() != 'surfacerelay-t907-filament\n':
        sys.exit('Refusing existing nonempty fixture directory.')
DEST.mkdir(parents=True, exist_ok=True)
marker.write_text('surfacerelay-t907-filament\n')
for name in ['config', 'bootstrap', 'public', 'routes']:
    shutil.copytree(BASE / name, DEST / name, dirs_exist_ok=True)
for name in ['setup.php']:
    shutil.copy2(BASE / name, DEST / name)
(DEST / 'app').mkdir(exist_ok=True)
shutil.copy2(BASE / 'app/User.php', DEST / 'app/User.php')
composer = json.loads((BASE / 'composer.json').read_text())
composer['name'] = 'surfacerelay/temporary-filament-acceptance'
composer['require']['filament/filament'] = '^5.0'
(DEST / 'composer.json').write_text(json.dumps(composer, indent=2) + '\n')
(DEST / 'package.json').write_text(json.dumps({'private': True, 'type': 'module',
    'dependencies': {'@surfacerelay/browser-runtime': '0.1.0-alpha.1'},
    'devDependencies': {'esbuild': '^0.25.0'}, 'scripts': {'build': 'esbuild client.mjs --bundle --format=esm --outfile=public/assets/client.js'}}, indent=2) + '\n')
client = (ROOT / 'examples/filament-orders-live/client.mjs').read_text()
start, end = client.index('import '), client.index('// Filament')
client = "import { DriverRegistry, LivewireBrowserDriver, GlobalLivewireBrowserRuntime, resolveDocumentModelContext, WebMcpRegistrationLifecycle } from '@surfacerelay/browser-runtime';\n\n" + client[end:]
(DEST / 'client.mjs').write_text(client)
for template in Path(__file__).parent.glob('*.template'):
    relative = template.name.removesuffix('.template').replace('__', '/')
    target = DEST / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(template.read_text())
gateway = baseline_gateway.replace('HoldGateway', 'FilamentGateway')
gateway = gateway.replace("getenv('PILOT_CONFIRMATION_TTL') ?: 60", "getenv('PILOT_CONFIRMATION_TTL') ?: 5")
gateway = gateway.replace('public static function definition(): ActionDefinition', 'public static function definition(bool $record = false): ActionDefinition')
gateway = gateway.replace("'pilot.livewire.orders.hold'", "$record ? 'pilot.filament.orders.hold' : 'pilot.filament.orders.refund'")
gateway = gateway.replace('ContextRequirement::CurrentRecord,\n                ContextRequirement::BrowserSession', '$record ? ContextRequirement::CurrentRecord : ContextRequirement::CurrentSelection')
gateway = gateway.replace('public static function invoke(OrderDesk $component, string $reason): array', 'public static function invoke(ListOrders|EditOrder $component, string $reason, ?string $receipt): array')
gateway = gateway.replace("if ($row->receipt_ciphertext !== null) { $payload['receipt'] = \\Illuminate\\Support\\Facades\\Crypt::decryptString($row->receipt_ciphertext); }", "$payload['receipt'] = $receipt;")
gateway = gateway.replace('$definition = self::definition();', '$definition = self::definition($component instanceof EditOrder);')
start = gateway.index('        $entries = ')
end = gateway.index("        Gate::define", start)
gateway = gateway[:start] + '        $composer = new TrustedContextComposer(new LaravelAuthenticatedActorResolver(app(\'auth\'), \'web\'), $tenantResolver);\n' + gateway[end:]
gateway = gateway.replace("$id = $context->require(ContextRequirement::CurrentRecord)->value;\n            return DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->exists();", "$records = $context->has(ContextRequirement::CurrentRecord) ? [$context->require(ContextRequirement::CurrentRecord)->value] : $context->require(ContextRequirement::CurrentSelection)->value;\n            foreach ($records as $record) { if ($record->tenant_id !== $tenant || !DB::table('orders')->where('tenant_id', $tenant)->where('id', $record->id)->exists()) return false; }\n            return $records !== [];")
gateway = gateway.replace("$ids = [$context->require(ContextRequirement::CurrentRecord)->value];", "$records = $context->has(ContextRequirement::CurrentRecord) ? [$context->require(ContextRequirement::CurrentRecord)->value] : $context->require(ContextRequirement::CurrentSelection)->value;\n                $ids = array_map(static fn ($record) => $record->id, $records);")
gateway = gateway.replace("return ['orderId' => $ids[0], 'held' => true, 'secret' => 'HOLD_RAW_OUTPUT_SECRET'];", "return ['orderIds' => $ids, 'affectedCount' => count($ids), 'secret' => 'FILAMENT_RAW_OUTPUT_SECRET'];")
gateway = gateway.replace("['orderId' => $rawOutput['orderId'], 'held' => $rawOutput['held']]", "['orderIds' => $rawOutput['orderIds'], 'affectedCount' => $rawOutput['affectedCount']]")
gateway = gateway.replace("$outcome = $bus->dispatch(new ActionCall($definition->id, 1, $input, $context, $descriptor->bindingId, $payload['receipt'] ?? null));", "$outcome = (new \\SurfaceRelay\\Laravel\\Filament\\Invocation\\FilamentActionGateway($bus, $composer, confirmationBridge: new \\SurfaceRelay\\Laravel\\Filament\\Confirmation\\FilamentConfirmationBridge()))->dispatch($component, $definition->id, 1, $input, 'filament', bin2hex(random_bytes(16)), bindingId: $descriptor->bindingId, confirmationReceipt: $receipt, idempotencyKey: $row->idempotency_key);")
gateway = gateway.replace('public static function active(OrderDesk $component): object', 'public static function active(ListOrders|EditOrder $component): object')
gateway = gateway.replace("&& $row->tenant_id === self::tenant() && $row->record_id === $component->recordId\n            && $row->record_id === session('record', 101)", "&& $row->tenant_id === self::tenant()")
gateway = gateway.replace("&& $row->tenant_id === self::tenant(), 409);", "&& $row->tenant_id === self::tenant()\n            && (!($component instanceof EditOrder) || $row->record_id === $component->getRecord()->getKey())\n            && json_decode($row->descriptor, true)['action']['id'] === self::definition($component instanceof EditOrder)->id, 409);")
(DEST / 'app/FilamentGateway.php').write_text(gateway)
routes = (BASE / 'routes/web.php').read_text().replace("Route::get('/', fn () => view('pilot'));", "Route::get('/', fn () => redirect('/admin/orders'));")
routes = routes.replace('App\\HoldGateway', 'App\\FilamentGateway')
(DEST / 'routes/web.php').write_text(routes)
(DEST / 'bootstrap/providers.php').write_text("<?php\nreturn [SurfaceRelay\\Laravel\\SurfaceRelayServiceProvider::class, App\\PanelProvider::class];\n")
(DEST / 'public/router.php').write_text("<?php\n$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);\nif(is_string($path)&&!str_contains($path,'..')&&is_file(__DIR__.$path))return false;\nrequire __DIR__.'/index.php';\n")
for lock in ['composer.lock', 'package-lock.json']:
    if (Path(__file__).parent / lock).is_file(): shutil.copy2(Path(__file__).parent / lock, DEST / lock)
print(DEST)
