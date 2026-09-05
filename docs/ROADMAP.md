# Roadmap

## M0 — Contract foundation

Outcome: provisional v0.1 Action Definition, Runtime Binding, Invocation, and Result semantics are internally coherent and validated by fixtures.

Exit criteria: T-001 through T-005 done.

## M1 — Laravel action kernel

Outcome: protocol-neutral PHP action registry and trusted execution pipeline exist with unit tests.

Exit criteria: T-101 through T-110 done.

## M2 — Livewire binding

Outcome: mounted Livewire components explicitly expose bindings to shared application actions; human and agent path share execution logic.

Exit criteria: T-201 through T-204 done.

## M3 — Browser runtime / WebMCP

Outcome: browser runtime registers current bindings as WebMCP tools through one isolated adapter and handles lifecycle/cancellation.

Exit criteria: T-301 through T-305 done.

## M4 — Production trust controls

Outcome: confirmation receipts, idempotency, output policies, and audit are real runtime mechanisms with negative tests.

## M5 — Filament vertical

Outcome: a multi-tenant order demo proves current record/selection/filter state can be agent-operated without becoming caller-authoritative.

## M6 — HTMX portability proof

Outcome: the same contract and browser runtime support an explicit HTMX binding outside the Livewire execution model.

## M7 — Conformance and ecosystem bridges

Outcome: executable adapter conformance suite plus optional MCP/OpenAPI bridges built on maintained ecosystem implementations.

## Release gates

### 0.1.0-alpha

- M0–M3 complete.
- Prep List end-to-end.
- No production-safety claim.

### 0.2.0-alpha

- M4 complete.
- Confirmation/idempotency/audit documented and tested.

### 0.3.0-beta

- M5 complete.
- Filament real-world vertical.

### 0.4.0-beta

- M6 complete.
- Cross-binding evidence exists.

### 1.0 consideration

Only after contract semantics have survived multiple bindings, security review, migration testing, and real application use.
