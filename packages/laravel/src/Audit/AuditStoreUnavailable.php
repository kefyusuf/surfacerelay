<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

final class AuditStoreUnavailable extends \RuntimeException
{
    public static function operationFailed(): self
    {
        return new self('Audit store operation failed.');
    }

    public static function encodingFailed(): self
    {
        return new self('Audit event encoding failed.');
    }
}
