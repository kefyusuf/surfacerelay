# SurfaceRelay

> **Experimental project. Not an official W3C, WebMCP, MCP, Laravel, or browser-vendor project.**

SurfaceRelay explores a contract and adapter architecture for exposing **stateful, server-driven web application actions** to AI agents without duplicating application logic or forcing teams to create parallel agent-only REST APIs.

The project is intentionally narrower than a generic WebMCP SDK. Endpoint-first applications already have strong paths through OpenAPI, GraphQL, function calling, and MCP. SurfaceRelay focuses on actions whose meaning depends on an active application surface: mounted components, authenticated browser sessions, current records, selected rows, active filters, unsaved form state, modal/wizard steps, and tenant-scoped UI state.

## Core model

SurfaceRelay separates four concerns:

1. **Action Definition** — what an application can do, expressed with protocol-neutral semantics.
2. **Runtime Binding** — how that action is executable in the current runtime (Livewire, HTMX, LiveView, HTTP, custom driver, etc.).
3. **Trusted Runtime** — validation, authorization, tenancy, confirmation, idempotency, execution, output controls, and audit.
4. **Surface Projection** — how the action is projected to WebMCP, MCP, a human UI, or another agent-facing surface.

```text
                     Action Definition
                            │
             ┌──────────────┴──────────────┐
             │                             │
      Runtime Binding              Surface Projection
             │                             │
       ┌─────┼─────┐                 ┌─────┼─────┐
       │     │     │                 │     │     │
   Livewire HTMX HTTP             WebMCP MCP  Human UI
       │     │     │                 │     │     │
       └─────┴─────┴──────┬──────────┴─────┴─────┘
                           │
                 Trusted Action Runtime
                           │
          validate → authorize → confirm → execute
                    → redact → audit
```

## Why this exists

WebMCP is page-centric and human-in-the-loop. That is a strong fit for UI-bound actions, but it is still an evolving browser API. SurfaceRelay isolates that moving browser boundary and keeps application semantics independent from WebMCP, MCP, Livewire, Filament, or any single framework.

A Filament table with three selected orders illustrates the gap. An OpenAPI document can describe `GET /orders/{id}` and `POST /refunds`; it does not naturally represent **the records the authenticated user has selected on the currently open tenant-scoped table**. SurfaceRelay treats that current UI state as trusted runtime context rather than caller-supplied action input.

## Initial scope

The first reference runtime is Laravel. Livewire is the first runtime binding and Filament is the first production-oriented vertical. HTMX is the portability proof because the same browser-side binding pattern can sit in front of PHP, Python, Ruby, Go, Java, or .NET backends.

### In scope

- Protocol-neutral action definitions.
- Runtime bindings for stateful/server-driven UI frameworks.
- Trusted actor, tenant, record, selection, and browser-session context.
- Separate discovery and invocation authorization.
- Effect, risk, idempotency, output-sensitivity, and output content-trust semantics.
- Confirmation receipts for consequential actions.
- WebMCP projection behind an isolated browser adapter.
- Laravel reference runtime.
- Livewire and Filament adapters.
- HTMX portability proof.
- Adapter conformance fixtures and negative tests.
- Later: projection through an existing Laravel MCP implementation.

### Explicitly out of scope for the core

- Implementing WebMCP itself or maintaining a WebMCP polyfill.
- Implementing MCP transports or JSON-RPC from scratch.
- Browser automation, DOM scraping, or autonomous browsing.
- LLM planning/orchestration or agent memory.
- Authentication systems.
- Replacing OpenAPI.
- Automatic exposure of every controller, component, model, or method.
- Inventing a site-wide discovery standard outside WebMCP.

## Repository status

This repository is a **research-backed implementation starter**. The schemas under `spec/0.1/` are provisional. Do not market them as a public standard until two materially different bindings implement them and the conformance scenarios have proven useful.

## Development

Start with:

1. [`AGENTS.md`](AGENTS.md)
2. [`STATUS.md`](STATUS.md)
3. [`TASKS.md`](TASKS.md)
4. [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
5. [`docs/THREAT-MODEL.md`](docs/THREAT-MODEL.md)

Run:

```bash
python scripts/validate.py
```

For Laravel kernel changes:

```bash
cd packages/laravel
composer test
composer validate --strict
```

## Validation

The initial repository should always keep this command green:

```bash
python scripts/validate.py
```

As implementation grows, package-specific tests are added to the same verification contract.

## External review checkpoints

After every completed task, update:

- `STATUS.md`
- `REVIEW_REQUEST.md`

These two files are deliberately compact. They make periodic external review fast: a reviewer can inspect the current task, changed files, verification evidence, unresolved risks, and the exact commit/branch needing review.

## License

Apache-2.0. See [`LICENSE`](LICENSE).
