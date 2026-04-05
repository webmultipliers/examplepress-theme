# ExamplePress Theme — AI Agent Context

## Foundational Constraint

ExamplePress uses a **Kernel + Theme** architecture. The theme is **strictly a presentation and routing layer** — it dispatches requests to Blockstudio template blocks via a single-entry-point router. The **ExamplePress Platform Kernel** (`examplepress-mu`) owns everything else: features, configuration, admin UI, security, app management, scaffolding, and REST APIs. Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

**The theme contains no admin pages, no feature registry, no configuration pipeline, no build tooling, and no JavaScript.**

## Architecture

### Request Flow

```
Browser → WordPress → templates/index.html
  → examplepress-theme/router block (blockstudio/router/index.php)
    → examplepress_resolve_route()              # resolve via route origin registry
    → apply_filters('examplepress_route_data')  # enrich payload
    → do_action('examplepress_route_resolved')  # pre-dispatch hook
    → bs_block( namespace/template-{slug} )     # dispatch to Blockstudio block
```

### Route Origins

Companion plugins register route origins via `examplepress_register_route_origin()`. The router evaluates all origins by priority and dispatches to the first match. When no origin matches, `get-started` is rendered under the theme namespace.

## Public API

### Filters

| Filter | Description |
|---|---|
| `examplepress_resolved_origin` | Override the resolved route origin (namespace + slug) |
| `examplepress_template_prefix` | Override the template block prefix (default: `template`) |
| `examplepress_template_block_name` | Override the fully-qualified block name |
| `examplepress_route_data` | Enrich the data payload passed to `bs_block()` |

### Actions

| Action | Description |
|---|---|
| `examplepress_route_resolved` | Fires before router dispatch with `($slug, $block_name)` |

### Functions

| Function | Description |
|---|---|
| `examplepress_register_route_origin( $ns, $routes, $priority )` | Register a route origin |
| `examplepress_resolve_route()` | Resolve current request to `{ namespace, slug }` |
| `examplepress_get_template_prefix()` | Get template block prefix |
| `examplepress_get_template_block_name( $slug, $prefix, $ns )` | Build fully-qualified block name |
| `examplepress_has_route_origins()` | Check if any origins registered |
| `examplepress_get_route_origin_namespaces()` | Get all registered namespaces |
| `examplepress_get_route_origin_map()` | Full introspection of registered origins |
| `examplepress_route_slug_owner( $slug )` | Check which namespace owns a slug |
| `examplepress_detect_route_conflicts()` | Find slugs claimed by multiple origins |

## Directory Structure

```
functions.php               # Bootstrap: constants, routing includes, Blockstudio filters
templates/index.html        # Single-entry router block dispatch
theme.json                  # WordPress block editor settings
style.css                   # Theme metadata
blockstudio.json            # Blockstudio configuration
examplepress.json           # Updater configuration

inc/
├── route-registry.php      # Multi-origin route registration and resolution
└── router.php              # Route resolution and block name construction

blockstudio/
├── init.php                # Blockstudio entry point
├── router/                 # Router dispatch block
└── templates/
    └── get-started/        # Default fallback template block
```

## Rules for Contributors

- **The theme is routing and presentation only.** No admin pages, no features, no config parsing, no REST endpoints, no build tooling.
- **Never add application logic to the theme.** Routing decisions, template blocks, and frontend assets belong in companion plugins.
- **Platform infrastructure belongs in the MU Kernel.** Features, configuration, admin UI, app lifecycle, GitHub integration, scaffolding, REST APIs, dependencies, notifications, CLI, plugin management, security, and guards are all MU responsibilities.
- **The theme must work with zero plugins installed** — the `get-started` fallback is the baseline.
