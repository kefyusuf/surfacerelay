<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Definition;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

/**
 * Thrown when a value object violating the frozen spec/0.1 Action Definition
 * contract is constructed. This is a contract/construction concern and is
 * deliberately separate from the invocation result error taxonomy (T-110).
 */
final class InvalidActionDefinition extends \InvalidArgumentException
{
    public static function id(string $id): self
    {
        return new self(sprintf(
            'Action Definition ID "%s" violates the canonical grammar '
            . '^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$.',
            $id,
        ));
    }

    public static function idTooLong(string $id): self
    {
        return new self(sprintf(
            'Action Definition ID exceeds the maximum length of 160 bytes (%d given).',
            strlen($id),
        ));
    }

    public static function version(int $version): self
    {
        return new self(sprintf('Action Definition version must be >= 1 (%d given).', $version));
    }

    public static function title(string $title): self
    {
        return new self(sprintf(
            'Action Definition title must be between 1 and 120 characters (%d given).',
            mb_strlen($title, 'UTF-8'),
        ));
    }

    public static function description(string $description): self
    {
        return new self(sprintf(
            'Action Definition description must be between 1 and 2000 characters (%d given).',
            mb_strlen($description, 'UTF-8'),
        ));
    }

    public static function duplicateContextRequirement(ContextRequirement $requirement): self
    {
        return new self(sprintf(
            'contextRequirements contains duplicate value "%s".',
            $requirement->value,
        ));
    }

    public static function extensionKey(mixed $key): self
    {
        return new self(sprintf(
            'Extension key must match namespace/key grammar (got: %s).',
            var_export($key, true),
        ));
    }
}
