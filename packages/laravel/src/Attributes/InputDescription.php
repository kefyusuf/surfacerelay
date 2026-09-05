<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Attributes;

use Attribute;

/**
 * Explicit, verbatim input-schema description for a single parameter.
 * Protocol-neutral schema-compilation metadata: it is never derived from
 * docblocks, comments, parameter names, or framework validation messages,
 * and no description is emitted when the attribute is absent.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class InputDescription
{
    public function __construct(
        public readonly string $description,
    ) {}
}
