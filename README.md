# ExamplePress Theme

A minimal WordPress theme built on [Blockstudio](https://blockstudio.dev). ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks.

The theme is **strictly a presentation layer**. It contains a router block, a fallback template, and Blockstudio configuration — nothing else. All platform infrastructure — the routing engine, feature registry, configuration, admin dashboard, security, and app management — is owned by the [ExamplePress Platform Kernel](https://github.com/webmultipliers/examplepress-mu) (`examplepress-mu`), a required Must-Use plugin.

## How It Works

```text
Browser → WordPress → templates/index.html
  → examplepress-theme/router (blockstudio/router/index.php)
    → examplepress_resolve_route()                # MU Kernel
    → apply_filters('examplepress_route_data')    # enrich payload
    → do_action('examplepress_route_resolved')    # pre-dispatch hook
    → bs_block( namespace/template-{slug} )       # render Blockstudio block
```

`templates/index.html` contains a single block: `<!-- wp:examplepress-theme/router /-->`. The router block calls into the MU Kernel to resolve the current request to a route origin and slug, then dispatches to the matching Blockstudio template block via `bs_block()`. If no companion plugin claims the route, the bundled `get-started` fallback template is rendered.

## Requirements

| Dependency              | Version                    |
|-------------------------|----------------------------|
| WordPress               | >= 6.9                     |
| PHP                     | >= 8.4                     |
| ExamplePress MU Kernel  | Required (must-use plugin) |
| Blockstudio             | >= 7.1                     |

## Installation

1. Install the [ExamplePress Platform Kernel](https://github.com/webmultipliers/examplepress-mu) as a must-use plugin
2. Clone or download into `wp-content/themes/examplepress-theme/`
3. Run `composer install`
4. Activate the theme
5. Install and activate Blockstudio

## What's in the Theme

| File | Purpose |
|------|---------|
| `functions.php` | Constants (`EP_THEME_VERSION`, `EP_THEME_PATH`, `EP_THEME_URI`), Composer autoloader, Blockstudio inner-block wrap filter |
| `templates/index.html` | Single router block entry point |
| `blockstudio/router/` | Router dispatch block — resolves route via MU Kernel, renders matched template block |
| `blockstudio/templates/get-started/` | Default fallback template shown when no companion plugin claims the route |
| `blockstudio/init.php` | Blockstudio theme entry point |
| `blockstudio.json` | Blockstudio asset and dev tool configuration |
| `theme.json` | WordPress block editor settings (appearance tools, color, typography, spacing) |
| `examplepress.json` | Theme updater configuration (GitHub repo, release channel, WP/PHP requirements) |
| `style.css` | WordPress theme header metadata |
| `composer.json` | PHP autoloader (Blockstudio dependency) |

## What's NOT in the Theme

Everything else lives in the MU Kernel (`examplepress-mu`):

- Routing engine (`examplepress_register_route_origin`, `examplepress_resolve_route`)
- Feature registry and all 31 registered features
- Configuration pipeline (`examplepress.json` parsing, design token normalization)
- Admin dashboard (all pages, assets, Vite build)
- Template guards (redirect, REST, resolution)
- App lifecycle (discovery, registry, scaffolding, GitHub integration)
- REST API endpoints
- Dependency resolution and notifications
- WP-CLI commands
- Platform security (capability lockdown, `DISALLOW_FILE_EDIT`)

---

**Author:**
Vinny S. Green — [vinnysgreen.com](https://vinnysgreen.com)
