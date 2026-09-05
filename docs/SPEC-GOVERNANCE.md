# Provisional Contract Governance

## Status

`spec/0.1` is an implementation experiment, not a standard.

## Change rule

A base-contract change must include:

1. motivation/use case;
2. compatibility impact;
3. schema change;
4. valid/invalid fixture updates;
5. conformance impact;
6. implementation impact on Livewire and HTMX once both exist;
7. decision-register or ADR update.

## Extension-first rule

Before adding a field to the base Action Definition, ask whether a namespaced extension can prove the use case first.

## Promotion rule

Do not publish a 1.0 contract/spec claim until at least two materially different binding systems implement shared scenarios and no framework-specific assumption is required in the base model.
