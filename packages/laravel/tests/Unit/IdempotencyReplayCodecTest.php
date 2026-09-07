<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Idempotency\CorruptIdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\UnreplayableIdempotencyOutput;

final class IdempotencyReplayCodecTest extends TestCase
{
    public function test_codec_types_are_available(): void
    {
        self::assertTrue(
            class_exists(IdempotencyReplayCodec::class),
            'IdempotencyReplayCodec must exist before replay behavior can pass.',
        );
        self::assertTrue(class_exists(UnreplayableIdempotencyOutput::class));
        self::assertTrue(class_exists(CorruptIdempotencyRecord::class));
    }

    public function test_round_trip_is_deterministic_and_preserves_supported_wire_values(): void
    {
        $codec = $this->codec();
        $value = [
            'z' => null,
            'message' => 'İstanbul',
            'items' => [true, 7, 1.0, 'x'],
            'nested' => ['b' => 2, 'a' => 1],
        ];

        $payload = $codec->encode($value);

        self::assertSame(
            '{"items":[true,7,1.0,"x"],"message":"İstanbul","nested":{"a":1,"b":2},"z":null}',
            $payload,
        );
        self::assertSame(
            [
                'items' => [true, 7, 1.0, 'x'],
                'message' => 'İstanbul',
                'nested' => ['a' => 1, 'b' => 2],
                'z' => null,
            ],
            $codec->decode($payload),
        );
        self::assertStringNotContainsString('serialize', $payload);
    }

    public function test_all_supported_scalar_roots_round_trip(): void
    {
        $codec = $this->codec();

        foreach ([null, true, false, 0, 42, 1.0, 'text', []] as $value) {
            self::assertSame($value, $codec->decode($codec->encode($value)));
        }
    }

    public function test_unsupported_executor_outputs_fail_closed_without_value_leakage(): void
    {
        $codec = $this->codec();
        $secret = 'secret-output-value';

        foreach ([
            new ReplayCodecSecretObject($secret),
            static fn (): string => $secret,
            INF,
            NAN,
            ['ok' => 1, 4 => $secret],
        ] as $value) {
            try {
                $codec->encode($value);
                self::fail('Unsupported output must not become a replay payload.');
            } catch (UnreplayableIdempotencyOutput $e) {
                self::assertStringNotContainsString($secret, $e->getMessage());
            }
        }

        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        try {
            $codec->encode($resource);
            self::fail('Resources must not become replay payloads.');
        } catch (UnreplayableIdempotencyOutput $e) {
            self::assertStringNotContainsString('Resource', $e->getMessage());
        } finally {
            fclose($resource);
        }
    }

    public function test_corrupt_persisted_payload_fails_closed_without_payload_leakage(): void
    {
        $codec = $this->codec();
        $payload = '{not-json-secret}';

        try {
            $codec->decode($payload);
            self::fail('Corrupt persisted replay payload must fail closed.');
        } catch (CorruptIdempotencyRecord $e) {
            self::assertStringNotContainsString($payload, $e->getMessage());
        }
    }

    private function codec(): IdempotencyReplayCodec
    {
        self::assertTrue(
            class_exists(IdempotencyReplayCodec::class),
            'IdempotencyReplayCodec must exist before replay behavior can pass.',
        );

        return new IdempotencyReplayCodec();
    }
}

final class ReplayCodecSecretObject
{
    public function __construct(public string $secret) {}
}
