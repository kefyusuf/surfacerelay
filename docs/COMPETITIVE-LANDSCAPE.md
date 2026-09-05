# Competitive Landscape

## Category map

| Category | Existing activity | SurfaceRelay response |
|---|---|---|
| WebMCP JS wrappers/hooks | crowded/emerging | do not compete on generic registration ergonomics |
| WebMCP polyfills | established experiments | reuse/feature-detect, do not own polyfill |
| MCP ↔ WebMCP bridges | multiple projects | not core scope |
| DOM/site → tool generation | active experiments | not core scope |
| OpenAPI → tools | commodity direction | optional importer later |
| Laravel MCP servers | mature/official ecosystem exists | integrate rather than reimplement |
| Laravel/Livewire WebMCP | direct early packages exist | differentiate with action/binding/trust architecture |
| Phoenix/LiveView | isolated framework-specific work emerging | evidence for common contract need |
| Filament agent operation | MCP CRUD packages exist | focus on active page/selection/filter context via WebMCP |
| Adapter conformance | fragmented | build SurfaceRelay-specific lifecycle/trust conformance after two bindings |

## Differentiation pillars

1. Action Definition ≠ Runtime Binding.
2. Trusted page/session context is first-class and non-caller-authoritative.
3. One application action can back human UI, WebMCP, and later MCP projection.
4. Consequential action confirmation and idempotency are runtime boundaries, not prompt conventions.
5. Conformance tests lifecycle/trust semantics, not generic WebMCP browser compliance.
6. Laravel/Filament provides a real production-shaped reference vertical.
