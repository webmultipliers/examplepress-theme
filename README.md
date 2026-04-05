# ExamplePress Theme

A minimal WordPress theme built on Blockstudio. ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks.

The theme is **strictly a presentation and routing layer**. All platform infrastructure — features, configuration, admin UI, security, app management — is owned by the **ExamplePress Platform Kernel** (`examplepress-mu`). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

## How It Works

```text
Browser → WordPress → templates/index.html
  → examplepress-theme/router (blockstudio/router/index.php)
    → examplepress_resolve_route()                # resolve via route origin registry
    → apply_filters('examplepress_route_data')    # enrich payload
    → do_action('examplepress_route_resolved')    # pre-dispatch hook
    → bs_block( namespace/template-{slug} )       # render template block
```

Every request enters through `templates/index.html`, which contains only the router block. The router resolves a route slug, constructs the target block name, and dispatches to the matching Blockstudio template block. If no companion plugin intercepts the route, the `get-started` fallback is rendered.

## Requirements

| Dependency              | Version                    |
|-------------------------|----------------------------|
| WordPress               | >= 6.9                     |
| PHP                     | >= 8.4                     |
| ExamplePress MU Kernel  | Required (must-use plugin) |
| Blockstudio             | >= 7.1                     |

## Routing API

Companion plugins register route origins:

```php
examplepress_register_route_origin( 'my-plugin', [
    'front'  => fn() => is_front_page() || is_home(),
    'single' => fn() => is_singular(),
], 10 );
```

### Filters & Actions

| Hook                                 | Type   | Description                                          |
|--------------------------------------|--------|------------------------------------------------------|
| `examplepress_resolved_origin`       | filter | Override the resolved route origin                   |
| `examplepress_route_data`            | filter | Enrich the data payload passed to `bs_block()`       |
| `examplepress_route_resolved`        | action | Fires before dispatch with `($slug, $block_name)`   |
| `examplepress_template_prefix`       | filter | Override the template block prefix (default: `template`) |
| `examplepress_template_block_name`   | filter | Override the fully-qualified block name              |

## File Structure

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

---

**Author:**
Vinny S. Green — [vinnysgreen.com](https://vinnysgreen.com)
