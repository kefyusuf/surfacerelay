# Research Sources

Research snapshot prepared 2026-09-05. Re-check time-sensitive browser/API details before implementing compatibility logic.

## WebMCP primary sources

- WebMCP Community Group repository: https://github.com/webmachinelearning/webmcp
- WebMCP draft: https://webmachinelearning.github.io/webmcp/
- Chrome WebMCP overview: https://developer.chrome.com/docs/ai/webmcp
- Chrome imperative API: https://developer.chrome.com/docs/ai/webmcp/imperative-api
- Chrome tool security guidance: https://developer.chrome.com/docs/ai/webmcp/secure-tools
- W3C Web Machine Learning Community Group: https://www.w3.org/groups/cg/webmachinelearning/
- Awesome WebMCP: https://github.com/webmachinelearning/awesome-webmcp

## Browser/runtime ecosystem

- MCP-B / WebMCP community runtime: https://github.com/WebMCP-org
- Google Chrome Labs WebMCP tools/demos: https://github.com/GoogleChromeLabs/webmcp-tools
- WebMCP Registry experiment: https://github.com/Skopaq-AI/webmcpregistry
- WebMCP React: https://github.com/mcpcat/webmcp-react
- WebMCP proxy/bridge examples were reviewed as adjacent architecture, not a target product category.

## Server/framework integrations reviewed

- Existing Livewire WebMCP package: https://github.com/HelgeSverre/livewire-webmcp
- Laravel WebMCP demo/example search landscape: https://github.com/search?q=webmcp+laravel&type=repositories
- Django WebMCP helpers: https://github.com/seunghan91/webmcp-django
- Rails WebMCP: https://github.com/jessewaites/webmcp-rails
- Ash/Phoenix LiveView WebMCP: https://github.com/sunprema/ash_web_mcp
- WordPress Abilities → WebMCP example: https://github.com/code-atlantic/webmcp-abilities

## MCP / Laravel ecosystem

- Laravel MCP: https://github.com/laravel/mcp
- PHP MCP Laravel SDK: https://github.com/php-mcp/laravel
- Model Context Protocol: https://modelcontextprotocol.io/

The existence of maintained MCP implementations is the reason SurfaceRelay does not implement MCP transports/JSON-RPC itself.

## Generic tool generation / conversion reviewed

- webmcp-gen: https://github.com/Nidhicodes/webmcp-gen

Generic DOM→tool and endpoint→tool conversion are treated as adjacent/secondary categories because they do not directly solve trusted active UI state.

## Research conclusions that are deliberately conservative

1. Do not claim WebMCP replaces MCP; the WebMCP proposal itself positions backend integrations as complementary.
2. Do not claim framework integration is unique; isolated integrations already exist.
3. Do claim the project focuses on a narrower seam: action semantics + runtime bindings + trusted UI/session context + surface projection.
4. Re-check WebMCP API names/annotations before release because the draft and browser implementation are evolving.
