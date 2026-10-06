<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\{Auth, Cache, Crypt, DB};

/** Disposable local 3D simulation; never a bank/payment or OTP implementation. */
final class CheckoutRuntime
{
    private static function selectionHash(): string
    {
        return hash('sha256', json_encode(session('selection', []), JSON_THROW_ON_ERROR));
    }

    private static function binding(?string $candidate = null): array
    {
        $binding = session('binding');
        abort_unless(is_array($binding) && ($candidate === null || $candidate === $binding['id'])
            && $binding['driver'] === 'acceptance.http' && $binding['session'] === session()->getId()
            && $binding['expires'] > time(), 409);
        return $binding;
    }

    private static function owned(string $id, bool $checkContext = true): object
    {
        abort_unless(Auth::check(), 403);
        $flow = DB::table('pilot_checkouts')->where('id', $id)->first();
        abort_unless($flow !== null, 404);
        abort_unless($flow->actor_id === Auth::id() && $flow->session_hash === hash('sha256', session()->getId()), 403);
        if ($checkContext) {
            $binding = self::binding();
            abort_unless($flow->tenant_id === AcceptanceRuntime::tenant() && $flow->binding_id === $binding['id']
                && $flow->record_id === session('record') && $flow->selection_hash === self::selectionHash(), 403);
            abort_unless(DB::table('memberships')->where('user_id', Auth::id())->where('tenant_id', $flow->tenant_id)
                ->where('can_pay', true)->exists(), 403);
        }
        if ($flow->expires_at <= time() && !in_array($flow->status, ['completed', 'failed', 'expired'], true)) {
            DB::table('pilot_checkouts')->where('id', $id)->whereIn('status', ['pending', 'verified'])
                ->where('expires_at', '<=', time())->update(['status' => 'expired', 'receipt_ciphertext' => null]);
        }
        // Status/page observers may race a terminal transition under the flow
        // lock. Reload actual persisted state even when our snapshot was fresh.
        $current = DB::table('pilot_checkouts')->where('id', $id)->first();
        abort_unless($current !== null, 404);
        return $current;
    }

    public static function page(string $id): void
    {
        self::owned($id);
    }

    public static function status(): array
    {
        abort_unless(Auth::check(), 403);
        $id = session('checkout_id');
        if (!is_string($id)) { return ['status' => 'none', 'nextPageUrl' => null]; }
        $flow = self::owned($id);
        return ['id' => $flow->id, 'status' => $flow->status, 'orderId' => $flow->record_id,
            'amountMinor' => $flow->amount_minor, 'currency' => 'TRY', 'simulation' => true,
            'attemptsRemaining' => max(0, 3 - $flow->attempts), 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $flow->expires_at),
            'nextPageUrl' => $flow->status === 'pending' ? '/3d/' . $flow->id : null];
    }

    public static function invoke(array $payload): array
    {
        abort_unless(array_diff(array_keys($payload), ['bindingId', 'input']) === []
            && isset($payload['bindingId']) && is_string($payload['bindingId'])
            && isset($payload['input']) && is_array($payload['input'])
            && $payload['input'] === ['method' => 'test-card'], 422);
        $binding = self::binding($payload['bindingId']);
        $id = session('checkout_id');
        if (!is_string($id)) {
            $id = bin2hex(random_bytes(16));
            $server = ['bindingId' => $binding['id'], 'input' => $payload['input'], 'idempotencyKey' => 'checkout-' . $id];
            $result = AcceptanceRuntime::invokePayment($server, $id);
            if ($result['status'] !== 'confirmation_required') { return $result; }
            DB::table('pilot_checkouts')->insert(['id' => $id, 'actor_id' => Auth::id(),
                'tenant_id' => AcceptanceRuntime::tenant(), 'session_hash' => hash('sha256', session()->getId()),
                'binding_id' => $binding['id'], 'record_id' => session('record'), 'selection_hash' => self::selectionHash(),
                'idempotency_key' => $server['idempotencyKey'], 'challenge_id' => $result['confirmation']['challengeId'],
                'status' => 'pending', 'expires_at' => time() + 180, 'amount_minor' => 1999]);
            session()->put('checkout_id', $id);
            return $result;
        }
        return Cache::store('file')->lock('pilot-checkout-' . $id, 15)->block(5, function () use ($id, $payload, $binding) {
            $flow = self::owned($id);
            abort_unless(in_array($flow->status, ['pending', 'verified', 'completed'], true), 409);
            $server = ['bindingId' => $binding['id'], 'input' => $payload['input'], 'idempotencyKey' => $flow->idempotency_key];
            if ($flow->status === 'pending') {
                $result = AcceptanceRuntime::invokePayment($server, $id);
                if ($result['status'] === 'confirmation_required') {
                    DB::table('pilot_checkouts')->where('id', $id)
                        ->update(['challenge_id' => $result['confirmation']['challengeId']]);
                }
                return $result;
            }
            if ($flow->status === 'verified') { $server['receipt'] = Crypt::decryptString($flow->receipt_ciphertext); }
            $result = AcceptanceRuntime::invokePayment($server, $id);
            if ($result['status'] === 'succeeded') {
                DB::table('pilot_checkouts')->where('id', $id)->update(['status' => 'completed', 'receipt_ciphertext' => null]);
            } elseif ($flow->status === 'verified' && $result['status'] === 'confirmation_required') {
                DB::table('pilot_checkouts')->where('id', $id)->update(['status' => 'expired', 'receipt_ciphertext' => null]);
            }
            return $result;
        });
    }

    public static function verify(string $id, mixed $code): array
    {
        abort_unless(is_string($code) && preg_match('/^[0-9]{6}$/D', $code) === 1, 422);
        return Cache::store('file')->lock('pilot-checkout-' . $id, 15)->block(5, function () use ($id, $code) {
            $flow = self::owned($id);
            abort_unless($flow->status === 'pending', 409);
            if (!hash_equals('123456', $code)) {
                $attempts = $flow->attempts + 1;
                DB::table('pilot_checkouts')->where('id', $id)->update(['attempts' => $attempts,
                    'status' => $attempts >= 3 ? 'failed' : 'pending']);
                return ['status' => $attempts >= 3 ? 'failed' : 'pending', 'attemptsRemaining' => max(0, 3 - $attempts),
                    'httpStatus' => $attempts >= 3 ? 423 : 422];
            }
            $receipt = AcceptanceRuntime::confirmation()->approveChallenge($flow->challenge_id);
            if ($receipt === null) {
                DB::table('pilot_checkouts')->where('id', $id)->update(['status' => 'expired']);
                abort(409);
            }
            $ttl = max(5, min(120, (int) (getenv('PILOT_CONFIRMATION_TTL') ?: 60)));
            DB::table('pilot_checkouts')->where('id', $id)->update(['status' => 'verified',
                'receipt_ciphertext' => Crypt::encryptString($receipt), 'expires_at' => min($flow->expires_at, time() + $ttl)]);
            return ['status' => 'verified', 'simulation' => true, 'httpStatus' => 200];
        });
    }

    public static function reset(): void
    {
        abort_unless(Auth::check(), 403);
        $id = session('checkout_id');
        if (is_string($id)) {
            Cache::store('file')->lock('pilot-checkout-' . $id, 15)->block(5, function () use ($id) {
                $flow = self::owned($id, false);
                if ($flow->status !== 'completed') {
                    DB::table('pilot_checkouts')->where('id', $id)->update(['status' => 'failed', 'receipt_ciphertext' => null]);
                }
            });
        }
        session()->forget('checkout_id');
    }
}
