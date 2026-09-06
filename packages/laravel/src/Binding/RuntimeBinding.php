<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Binding;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Protocol-neutral PHP representation of spec/0.1 Runtime Binding.
 *
 * This descriptor carries an exact already-validated ActionDefinition
 * identity and driver-owned target data. It does not resolve, authorize,
 * discover, execute, revoke, refresh, or silently retarget bindings.
 */
final readonly class RuntimeBinding implements \JsonSerializable
{
    private const string DRIVER_PATTERN = '/^[a-z][a-z0-9_.:-]{0,79}$/';
    private const string EXTENSION_KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';
    private const string RFC3339_PATTERN = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>\d{2})T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d+)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D';

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public string $bindingId,
        public ActionDefinition $definition,
        public string $driver,
        public BindingLifecycle $lifecycle,
        public array $target,
        public ?string $expiresAt = null,
        public array $extensions = [],
    ) {
        $bindingIdLength = mb_strlen($this->bindingId, 'UTF-8');
        if ($bindingIdLength < 1 || $bindingIdLength > 240) {
            throw InvalidRuntimeBinding::bindingId($this->bindingId);
        }

        if (preg_match(self::DRIVER_PATTERN, $this->driver) !== 1) {
            throw InvalidRuntimeBinding::driver($this->driver);
        }

        if ($this->target === [] || !$this->hasOnlyStringKeys($this->target)) {
            throw InvalidRuntimeBinding::target();
        }

        if ($this->expiresAt !== null && !self::isValidRfc3339($this->expiresAt)) {
            throw InvalidRuntimeBinding::expiresAt($this->expiresAt);
        }

        foreach (array_keys($this->extensions) as $key) {
            if (!is_string($key) || preg_match(self::EXTENSION_KEY_PATTERN, $key) !== 1) {
                throw InvalidRuntimeBinding::extensionKey($key);
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [
            'bindingId' => $this->bindingId,
            'action' => [
                'id' => $this->definition->id,
                'version' => $this->definition->version,
            ],
            'driver' => $this->driver,
            'lifecycle' => $this->lifecycle->value,
            'target' => $this->target,
            'expiresAt' => $this->expiresAt,
        ];

        if ($this->extensions !== []) {
            $result['extensions'] = $this->extensions;
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param array<mixed> $value */
    private function hasOnlyStringKeys(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    private static function isValidRfc3339(string $value): bool
    {
        if (preg_match(self::RFC3339_PATTERN, $value, $matches) !== 1) {
            return false;
        }

        $year = (int) $matches['year'];
        if ($year === 0) {
            return false;
        }

        return checkdate(
            (int) $matches['month'],
            (int) $matches['day'],
            $year,
        );
    }
}
