<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final class FilamentCurrentSelectionResolver
{
    public const int DEFAULT_MAX_SELECTION_RECORDS = 500;

    private const int CHUNK_SIZE = 100;

    private const string IDENTITY_DOMAIN = "surfacerelay.filament.current_selection.v1\n";

    private readonly RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(
        private readonly Page $page,
        private readonly int $maxSelectionRecords = self::DEFAULT_MAX_SELECTION_RECORDS,
        ?RuntimeScopeCanonicalizer $canonicalizer = null,
    ) {
        if ($this->maxSelectionRecords < 1) {
            throw InvalidFilamentCurrentSelection::invalidConfiguration();
        }

        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function resolve(): ?ResolvedTrustedValue
    {
        if (! $this->page instanceof HasTable) {
            return null;
        }

        try {
            $selected = $this->page->getSelectedTableRecords(
                true,
                min(self::CHUNK_SIZE, $this->maxSelectionRecords + 1),
            );
        } catch (\Throwable) {
            throw InvalidFilamentCurrentSelection::resolutionFailed();
        }

        /** @var array<string, Model> $recordsByIdentity */
        $recordsByIdentity = [];
        $observed = 0;

        try {
            foreach ($selected as $record) {
                $observed++;

                if ($observed > $this->maxSelectionRecords) {
                    throw InvalidFilamentCurrentSelection::limitExceeded();
                }

                if (! $record instanceof Model) {
                    throw InvalidFilamentCurrentSelection::unsupportedValue();
                }

                try {
                    $encodedIdentity = FilamentRecordIdentity::fromModel($record)->encode(
                        $this->canonicalizer,
                        'filament.current_selection.record_identity',
                    );
                } catch (InvalidFilamentRecordIdentity) {
                    throw InvalidFilamentCurrentSelection::invalidRecordIdentity();
                }

                if (array_key_exists($encodedIdentity, $recordsByIdentity)) {
                    throw InvalidFilamentCurrentSelection::duplicateIdentity();
                }

                $recordsByIdentity[$encodedIdentity] = $record;
            }
        } catch (InvalidFilamentCurrentSelection $e) {
            throw $e;
        } catch (\Throwable) {
            throw InvalidFilamentCurrentSelection::resolutionFailed();
        }

        if ($recordsByIdentity === []) {
            return null;
        }

        try {
            $table = $this->page->getTable();

            if (
                $table->getRelationship() instanceof BelongsToMany
                && $table->allowsDuplicates()
            ) {
                throw InvalidFilamentCurrentSelection::ambiguousDuplicateRows();
            }
        } catch (InvalidFilamentCurrentSelection $e) {
            throw $e;
        } catch (\Throwable) {
            throw InvalidFilamentCurrentSelection::resolutionFailed();
        }

        ksort($recordsByIdentity, SORT_STRING);

        try {
            $encodedSet = $this->canonicalizer->encode(
                array_keys($recordsByIdentity),
                'filament.current_selection.identity_set',
            );
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentCurrentSelection::invalidRecordIdentity();
        }

        return new ResolvedTrustedValue(
            value: array_values($recordsByIdentity),
            provenance: new ContextProvenance('filament.current_selection'),
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $encodedSet),
        );
    }
}
