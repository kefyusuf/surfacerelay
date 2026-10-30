# When to use SurfaceRelay — and when a plain MCP tool is enough

> **Experimental project; unofficial.** This guide states implemented behavior
> only: the proven surface today is **read-only, page-scoped actions** with
> hosted acceptance receipts ([getting started](getting-started.md)); the write/
> consequential path is designed but not acceptance-complete (A2-04…A2-07).

The honest starting point: **if your action is stateless and its input fully
describes the operation, you do not need SurfaceRelay.** Write a plain MCP
tool or let OpenAPI describe the endpoint. Adding this contract layer to a
stateless CRUD call buys you nothing and costs you four concepts.

SurfaceRelay pays for itself where the *meaning of the action depends on the
application surface the human is looking at right now* — and where running
the wrong thing is expensive enough that enforcement must be a server
boundary rather than a prompt convention.

## The decision table

| Situation | Use |
| --- | --- |
| Stateless action over a clean endpoint; input fully describes it (e.g. "get order 42" in a single-tenant API) | **Plain MCP tool** — skip SurfaceRelay |
| The correct input depends on live UI state: the rows the admin **selected**, the **active filters**, the current **wizard step**, the currently **mounted component** | **SurfaceRelay** — that state becomes trusted runtime context the caller cannot forge |
| A consequential action (refund, delete, external side effect) where "please confirm" must be enforced by the **runtime** (opaque receipts, idempotency, replay protection), not by the prompt | **SurfaceRelay** — after the write-path acceptance lands; today's proven surface is read-only |
| The same action should reach the human UI **and** WebMCP agents **and** later MCP clients without duplicating authorization | **SurfaceRelay** — one definition, many projections |
| You want an agent to browser-automate, scrape, or click through a UI | **Not SurfaceRelay** — explicitly out of scope |
| You need authentication, an agent planner, or a general MCP server | **Not SurfaceRelay** — explicitly out of scope |

## Signals you are in the niche

- The action's correct input depends on what the human has on screen right
  now, and asking the agent to "just pass the IDs" would make caller input
  the authority.
- Mistakes are expensive enough that confirmation must be a receipt check
  server-side, not a sentence in the tool description.
- More than one agent surface will need the same semantics.
- You would otherwise build a parallel agent-only endpoint and duplicate your
  authorization logic inside it.

## The canonical contrast

A Filament orders table on a tenant-scoped panel:

- *Plain tool:* `get_order(order_id)` — correct as long as the order ID is
  all the meaning there is, and your server re-checks access. Nothing here
  needs SurfaceRelay.
- *SurfaceRelay:* "inspect the orders the admin **selected on the currently
  filtered table**" — the selection and filters are trusted context issued by
  the page, never caller parameters; and the eventual "refund these" is a
  consequential action with a runtime-issued receipt. That sentence is not
  expressible as an honest plain-tool schema without trusting the caller.

## What one action costs

The minimal read-only path (all CI-verified — see
[getting started](getting-started.md)):

- one `ActionDefinition` (~35 lines),
- one explicitly exposed page method plus the server-issued browser
  descriptor (~35 lines),
- one ~30-line self-guarding registration module and an import map.

In exchange: caller input stays untrusted, discovery never grants authority,
unsupported surfaces fail closed, and every dispatch is audited server-side.

## See also

- Positioning summary: [POSITIONING.md](../POSITIONING.md)
- The quickstart: [getting-started.md](getting-started.md)
- Threat model: [THREAT-MODEL.md](../THREAT-MODEL.md)
