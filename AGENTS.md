## Serena

- Prefer Serena for symbol discovery, references, and targeted code navigation.
- Inspect only the relevant code; avoid reading entire large files unless necessary.
- Use grep mainly for literals, config, logs, templates, or non-symbol text.

## Graphify

This project has a knowledge graph at graphify-out/.

- Use Graphify for architecture, dependencies, execution paths, and cross-file relationships.
- Prefer `graphify query "<question>"`, `graphify path "<A>" "<B>"`, or `graphify explain "<concept>"` over broad source browsing.
- Use graphify-out/wiki/index.md for broad navigation when available.
- Read GRAPH_REPORT.md only when scoped Graphify queries are insufficient.
- The graph may intentionally lag behind current source; use Serena for current implementation details.
- NEVER run `graphify update .` automatically. Graphify updates are performed manually by the developer.
