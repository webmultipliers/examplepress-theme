# ExamplePress

> **Beta** — ExamplePress is in public beta. Core architecture is stable. APIs and configuration may evolve based on feedback. Report issues on [GitHub](https://github.com/webmultipliers/examplepress-theme/issues).

A code-first WordPress theme built on Blockstudio. ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks. The theme is an **infrastructure layer** — it owns the engine (router, guards, feature registry, configuration pipeline, and scaffolding). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

## How It Works

```text
Browser → WordPress → templates/index.html
  → examplepress-theme/router (blockstudio/router/index.php)
    → examplepress_get_current_route()            # resolve slug via filter
    → apply_filters('examplepress_route_data')    # enrich payload
    → do_action('examplepress_route_resolved')    # pre-dispatch hook
    → bs_block( namespace/template-{slug} )       # render template block
```

Every request enters through `templates/index.html`, which contains only the router block. The router resolves a route slug, constructs the target block name, and dispatches to the matching Blockstudio template block. If no companion plugin intercepts the route, the `get-started` fallback is rendered.

## Requirements

| Dependency   | Version         |
|--------------|----------------|
| WordPress    | >= 6.9         |
| PHP          | >= 8.4         |
| Blockstudio  | >= 7.1         |
| Composer     | Required for autoloading |

## Installation & Onboarding

1. Clone or download into `wp-content/themes/examplepress-theme/`
2. Run `composer install`
3. Activate the theme
4. Install and activate Blockstudio
5. **Install the Demo:**  
   Navigate to the ExamplePress settings page in your WordPress admin. Under the "Build" tab, click to install and activate the built-in Demo Companion Plugin. This will take over the router and demonstrate how to build custom template blocks.

## App Scaffolding (Build)

ExamplePress includes a built-in interface (via the "Build" tab in the admin dashboard) to instantly scaffold your own companion plugins. It connects to a Troy Server (Cloud or Custom) using a GitHub Personal Access Token to generate a repository pre-configured with the ExamplePress foundation, CI/CD pipelines, and optional automated staging deployments via SFTP.

## Configuration

ExamplePress uses a declarative JSON configuration file alongside the standard WordPress filter chain.

### Resolution Order

When resolving a feature toggle or option, the registry checks in this priority (highest wins):

1. **PHP Filter** — `add_filter('examplepress_feature_{id}', ...)`
2. **examplepress.json** — `features.{id}` (boolean or `{ enabled, options }`)
3. **Registration Default** — hardcoded in `inc/features/*.php`

### `examplepress.json`

```json
{
  "$schema": "https://www.examplepress.com/schema/examplepress-theme",
  "admin_tabs": {
    "hidden": []
  },
  "features": {
    "openverse": false,
    "restrict-block-types": {
      "enabled": true,
      "options": {
        "types": ["core/heading", "core/paragraph", "core/image"]
      }
    }
  },
  "design": {
    "strict": true,
    "colors": [
      { "name": "Primary", "slug": "primary", "color": "#a3122e" }
    ],
    "layout": {
      "wideSize": "1200px",
      "contentSize": "800px"
    },
    "typography": {
      "fontFamilies": [
        { "name": "System", "slug": "system", "fontFamily": "-apple-system, sans-serif" }
      ],
      "fontSizes": [
        { "slug": "md", "size": "1rem", "name": "Medium" }
      ]
    }
  },
  "dependencies": [
    {
      "slug": "blockstudio",
      "name": "Blockstudio",
      "tier": "required",
      "pricing": "free",
      "source": { "type": "direct", "url": "https://github.com/flavor/flavor" }
    }
  ]
}
```

A JSON Schema is available at `schema/examplepress-theme.json` for IDE autocompletion.

## Registered Features (37 Total)

### Theme Support

- `title-tag`
- `responsive-embeds`
- `post-thumbnails`
- `wp-block-styles`
- `html5`

### Editor Controls

- `disable-remote-block-patterns`
- `disable-core-block-patterns`
- `restrict-block-types` (opt-in)
- `openverse`

### Admin Customization

- `post-lock-window`
- `remove-dashboard-widgets`
- `login-branding`

### Design Tokens

- `theme-colors`
- `theme-layout`
- `theme-typography`
- `design-strict` — injected into `theme.json` at runtime via `wp_theme_json_data_theme`. Setting `design-strict` locks down `appearanceTools` globally.

### Site Options

- `disable-redirect-guess-404`
- `permalink-structure`
- `managed-options`

### Spacing, Borders & Shadows

- `theme-spacing`
- `theme-borders`
- `theme-shadows`
- `theme-global-styles`

### Guards

- `guard-template-redirect`
- `guard-template-rest`
- `guard-template-resolution`  
  A three-layer lockdown preventing template creation that would bypass the router.

### Blockstudio Controls

- `blockstudio-assets`
- `blockstudio-asset-reset`
- `blockstudio-minify`
- `blockstudio-scss`
- `blockstudio-tailwind`
- `blockstudio-editor`
- `blockstudio-block-editor`
- `blockstudio-ai-context`
- `blockstudio-block-tags`
- `blockstudio-dev`
- `blockstudio-users`

All features are toggleable via `examplepress.json` or PHP filters:

```php
// Disable a feature
add_filter( 'examplepress_feature_openverse', '__return_false' );

// Override an option
add_filter( 'examplepress_feature_post-lock-window_duration', fn() => 120 );
```

## Filters & Actions

### Routing

| Hook                         | Type    | Description                                               |
|------------------------------|---------|-----------------------------------------------------------|
| examplepress_route_context   | filter  | Override the resolved route slug (default: get-started)   |
| examplepress_route_data      | filter  | Enrich the data payload passed to bs_block()              |
| examplepress_route_resolved  | action  | Fires before dispatch with ($slug, $block_name)           |
| examplepress_theme_namespace | filter  | Override the Blockstudio namespace (default: examplepress-theme) |
| examplepress_template_prefix | filter  | Override the template block prefix (default: template)     |
| examplepress_template_block_name | filter | Override the fully-qualified block name                  |

### Core & Admin

| Hook                              | Type    | Description                                              |
|------------------------------------|---------|----------------------------------------------------------|
| examplepress_feature_{id}          | filter  | Toggle any feature on/off                                |
| examplepress_feature_{id}_{key}    | filter  | Override a feature option value                          |
| examplepress_features              | filter  | Modify the full feature registry                         |
| examplepress_allowed_block_types   | filter  | Customise allowed blocks when restrict-block-types is active |
| examplepress_admin_tabs_hidden     | filter  | Hide specific tabs in the settings dashboard             |

## Admin Dashboard

A read-only settings page is available at **ExamplePress** in the admin menu. It surfaces:

- **Features** — All registered features with their resolved state and source
- **Design** — Color palette swatches, layout dimensions, typography preview
- **Connections** — GitHub App installation, PAT configuration, Troy Server authorization with live connection tests
- **Build & Demo** — Interface for scaffolding companion apps via GitHub template repos and managing the local Demo plugin
- **Blocks** — All discovered Blockstudio blocks
- **Dependencies** — Status for required/recommended plugins with tier and pricing info
- **Notifications** — Aggregated system warnings (missing dependencies, health failures, routing issues) with per-user archiving
- **Library** — Component library (coming in v1.1)
- **Config** — Raw JSON viewer for configuration files
- **Health** — Environment checks, theme integrity, router status, guard state, and connection health
- **Docs** — Documentation links configurable via examplepress.json
- **Support** — Blockstudio, GitHub repository, and contact links

> Define `EP_DEV_MODE` as `true` in `wp-config.php` to automatically disable all template guards during development.

---

**Author:**  
Vinny S. Green — [vinnysgreen.com](https://vinnysgreen.com)
