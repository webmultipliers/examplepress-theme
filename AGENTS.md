# ExamplePress Theme — AI Agent Context

## Foundational Constraint

ExamplePress uses a **Kernel + Theme** architecture. The theme is **strictly a presentation layer** — it contains Blockstudio blocks that render output. The **ExamplePress Platform Kernel** (`examplepress-mu`) owns everything else: routing, features, configuration, admin UI, security, app management, scaffolding, and REST APIs. Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

**The theme contains no PHP logic beyond constants, autoloading, and Blockstudio filters. No routing engine, no admin pages, no feature registry, no configuration pipeline, no build tooling, no JavaScript.**

## Architecture

### Request Flow

```
Browser → WordPress → templates/index.html
  → examplepress-theme/router block (blockstudio/router/index.php)
    → examplepress_resolve_route()              # provided by MU Kernel
    → apply_filters('examplepress_route_data')  # enrich payload
    → do_action('examplepress_route_resolved')  # pre-dispatch hook
    → bs_block( namespace/template-{slug} )     # dispatch to Blockstudio block
```

The router block calls functions provided by the MU Kernel. The theme does not define these functions — it only renders their output.

## Directory Structure

```
functions.php               # Constants, autoloader, Blockstudio filters
templates/index.html        # Single-entry router block dispatch
theme.json                  # WordPress block editor settings
style.css                   # Theme metadata
blockstudio.json            # Blockstudio configuration
examplepress.json           # Updater configuration

blockstudio/
├── init.php                # Blockstudio entry point
├── router/                 # Router dispatch block (calls MU Kernel functions)
└── templates/
    └── get-started/        # Default fallback template block
```

## Rules for Contributors

- **The theme is presentation only.** No routing logic, no admin pages, no features, no config parsing, no REST endpoints, no build tooling.
- **Never add PHP logic to the theme.** If it's not rendering a Blockstudio block, it belongs in the MU Kernel.
- **The theme depends on the MU Kernel.** The router block calls `examplepress_resolve_route()` and related functions provided by `examplepress-mu`. Without the kernel, the router has nothing to dispatch.
- **Companion plugins own the application.** Routing decisions, template blocks, patterns, and frontend assets belong in companion plugins, not the theme.
