<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Definition;

use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;

/**
 * Protocol-neutral runtime representation of a spec/0.1 Action Definition.
 *
 * This value object carries no adapter, driver, or surface knowledge: no
 * Livewire, Filament, WebMCP, MCP, HTTP, or browser concepts appear here.
 * RuntimeBinding is a separate contract; nothing in this class identifies a
 * runtime target.
 *
 * The ID grammar is ASCII (`^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$`), so its
 * maxLength is enforced with byte length (strlen), where bytes equal
 * characters. Title and description are UTF-8 and measured with
 * mb_strlen in characters, matching the JSON Schema string length.
 *
 * Validation only: this class does not compile or validate JSON Schema
 * semantics (T-103), does not normalize or trim supplied strings, and does
 * not derive context requirements from risk/effect. Declared contract
 * metadata is represented as given; enforcement happens later in the
 * invocation pipeline.
 */
final readonly class ActionDefinition
{
    private const string ID_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    private const int ID_MAX_LENGTH = 160;
    private const int TITLE_MAX_LENGTH = 120;
    private const int DESCRIPTION_MAX_LENGTH = 2000;

    /** Extension keys are namespaced: `namespace/key` (frozen spec grammar). */
    private const string EXTENSION_KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';

    /** @var list<ContextRequirement> Canonical enum-declaration order; caller order is irrelevant. */
    public array $contextRequirements;

    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed>|null $outputSchema
     * @param list<ContextRequirement> $contextRequirements
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public string $id,
        public int $version,
        public string $title,
        public string $description,
        public array $inputSchema,
        public ActionScope $scope,
        public ActionEffect $effect,
        public ActionRisk $risk,
        public IdempotencyPolicy $idempotency,
        public OutputTrust $outputTrust,
        array $contextRequirements,
        public ?array $outputSchema = null,
        public array $extensions = [],
    ) {
        if (preg_match(self::ID_PATTERN, $this->id) !== 1) {
            throw InvalidActionDefinition::id($this->id);
        }
        if (strlen($this->id) > self::ID_MAX_LENGTH) {
            throw InvalidActionDefinition::idTooLong($this->id);
        }
        if ($this->version < 1) {
            throw InvalidActionDefinition::version($this->version);
        }
        $titleLength = mb_strlen($this->title, 'UTF-8');
        if ($titleLength < 1 || $titleLength > self::TITLE_MAX_LENGTH) {
            throw InvalidActionDefinition::title($this->title);
        }
        $descriptionLength = mb_strlen($this->description, 'UTF-8');
        if ($descriptionLength < 1 || $descriptionLength > self::DESCRIPTION_MAX_LENGTH) {
            throw InvalidActionDefinition::description($this->description);
        }
        $this->contextRequirements = $this->canonicalContextRequirements($contextRequirements);
        $this->validateExtensions($this->extensions);
    }

    /** @param list<ContextRequirement> $requirements */
    private function canonicalContextRequirements(array $requirements): array
    {
        $positions = array_map(
            static fn (ContextRequirement $requirement): string => $requirement->value,
            ContextRequirement::cases(),
        );
        $positions = array_flip($positions);
        $canonical = [];
        foreach ($requirements as $requirement) {
            if (!$requirement instanceof ContextRequirement) {
                throw new InvalidActionDefinition(
                    'contextRequirements must only contain ContextRequirement enum values.'
                );
            }
            if (isset($canonical[$requirement->value])) {
                throw InvalidActionDefinition::duplicateContextRequirement($requirement);
            }
            $canonical[$requirement->value] = $requirement;
        }
        usort(
            $canonical,
            static fn (ContextRequirement $a, ContextRequirement $b): int
                => $positions[$a->value] <=> $positions[$b->value],
        );
        return array_values($canonical);
    }

    /** @param array<string, mixed> $extensions */
    private function validateExtensions(array $extensions): void
    {
        foreach (array_keys($extensions) as $key) {
            if (!is_string($key) || preg_match(self::EXTENSION_KEY_PATTERN, $key) !== 1) {
                throw InvalidActionDefinition::extensionKey($key);
            }
        }
    }
}
