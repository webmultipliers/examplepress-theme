# ExamplePress Theme

A minimal WordPress theme built on Blockstudio. ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks.

The theme is **strictly a presentation layer**. All platform infrastructure — routing engine, features, configuration, admin UI, security, app management — is owned by the **ExamplePress Platform Kernel** (`examplepress-mu`). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

## How It Works

```text
Browser → WordPress → templates/index.html
  → examplepress-theme/router (blockstudio/router/index.php)
    → examplepress_resolve_route()                # provided by MU Kernel
    → apply_filters('examplepress_route_data')    # enrich payload
    → do_action('examplepress_route_resolved')    # pre-dispatch hook
    → bs_block( namespace/template-{slug} )       # render template block
```

Every request enters through `templates/index.html`, which contains only the router block. The router calls into the MU Kernel to resolve a route, then dispatches to the matching Blockstudio template block. If no companion plugin intercepts the route, the `get-started` fallback is rendered.

## Requirements

| Dependency              | Version                    |
|-------------------------|----------------------------|
| WordPress               | >= 6.9                     |
| PHP                     | >= 8.4                     |
| ExamplePress MU Kernel  | Required (must-use plugin) |
| Blockstudio             | >= 7.1                     |

## File Structure

```
functions.php               # Constants, autoloader, Blockstudio filters
templates/index.html        # Single-entry router block dispatch
theme.json                  # WordPress block editor settings
style.css                   # Theme metadata
blockstudio.json            # Blockstudio configuration
examplepress.json           # Updater configuration

blockstudio/
├── init.php                # Blockstudio entry point
├── router/                 # Router dispatch block
└── templates/
    └── get-started/        # Default fallback template block
```

---

**Author:**
Vinny S. Green — [vinnysgreen.com](https://vinnysgreen.com)
