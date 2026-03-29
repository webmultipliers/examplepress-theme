# ExamplePress

A code-first WordPress theme built on [Blockstudio](https://blockstudio.dev). ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks. The theme is an **infrastructure layer** — it owns the engine (router, guards, feature registry, configuration pipeline). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

## How It Works

```
Browser -> WordPress -> templates/index.html
  -> examplepress-theme/router (blockstudio/router/index.php)
    -> examplepress_get_current_route()           # resolve slug via filter
    -> apply_filters('examplepress_route_data')    # enrich payload
    -> do_action('examplepress_route_resolved')    # pre-dispatch hook
    -> bs_block( namespace/template-{slug} )       # render template block
```

Every request enters through `templates/index.html`, which contains only the router block. The router resolves a route slug, constructs the target block name, and dispatches to the matching Blockstudio template block. If no companion plugin intercepts the route, the `get-started` fallback is rendered.

## Requirements

| Dependency | Version |
|---|---|
| WordPress | >= 6.9 |
| PHP | >= 8.4 |
| Blockstudio | >= 7.1 |
| Composer | Required for autoloading |

## Installation

1. Clone or download into `wp-content/themes/examplepress-theme/`
2. Run `composer install`
3. Activate the theme
4. Install and activate Blockstudio

The theme works standalone with the built-in `get-started` template. To build your site, create a companion plugin that hooks the router.

## Configuration

ExamplePress uses a declarative JSON configuration file alongside the standard WordPress filter chain.

### Resolution Order

When resolving a feature toggle or option, the registry checks in this priority (highest wins):

1. **PHP Filter** — `add_filter('examplepress_feature_{id}', ...)`
2. **examplepress.json** — `features.{id}` (boolean or `{ enabled, options }`)
3. **Registration Default** — hardcoded in `inc/features/*.php`

### examplepress.json

```json
{
  "$schema": "https://www.examplepress.com/schema/examplepress-theme",
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
    "colors": [
      { "name": "Primary", "slug": "primary", "color": "#a3122e" }
    ],
    "layout": {
      "wideSize": "1200px",
      "contentSize": "800px"
    }
  },
  "plugins": {
    "required": ["blockstudio"],
    "recommended": ["redirection"]
  }
}
```

A JSON Schema is available at `schema/examplepress-theme.json` for IDE autocompletion.

## Directory Structure

```
examplepress-theme/
├── assets/
│   ├── css/                    # Admin and login stylesheets
│   └── js/                     # Settings page JS
├── blockstudio/
│   ├── router/                 # Single-entry dispatch block
│   ├── site-editor/            # JS/CSS template lockdown layers
│   ├── templates/              # Template blocks (get-started fallback)
│   └── patterns/               # Block patterns
├── docs/                       # Project documentation
├── inc/
│   ├── admin/
│   │   └── settings-page.php   # Read-only admin dashboard
│   ├── features/
│   │   ├── theme-support.php   # Title tag, embeds, thumbnails, HTML5
│   │   ├── editor-controls.php # Block patterns, type restrictions, Openverse
│   │   ├── admin-customization.php # Dashboard widgets, login branding
│   │   ├── design-tokens.php   # Colors and layout via wp_theme_json_data_theme
│   │   ├── site-options.php    # Permalinks, managed options, 404 guessing
│   │   └── guards.php         # Three-layer template lockdown
│   ├── config.php              # examplepress_get_config(), JSON reader
│   ├── feature-registry.php    # Registry API (register, enabled, option, boot)
│   ├── features.php            # Loader for domain-specific feature files
│   ├── router.php              # Routing helper functions
│   └── plugins.php             # Plugin dependency checker
├── schema/
│   └── examplepress-theme.json # JSON Schema for examplepress.json
├── templates/
│   └── index.html              # Single WordPress template (router only)
├── blockstudio.json            # Blockstudio asset pipeline config
├── examplepress.json           # Declarative theme configuration
├── functions.php               # Pure bootstrap
├── style.css                   # Theme metadata
└── theme.json                  # Minimal structural settings
```

## Registered Features

### Theme Support
`title-tag` `responsive-embeds` `post-thumbnails` `wp-block-styles` `html5`

### Editor Controls
`disable-remote-block-patterns` `disable-core-block-patterns` `restrict-block-types` (opt-in) `openverse`

### Admin
`post-lock-window` `remove-dashboard-widgets` `login-branding`

### Design Tokens
`theme-colors` `theme-layout` — injected into theme.json at runtime via `wp_theme_json_data_theme`

### Site Options
`disable-redirect-guess-404` `permalink-structure` `managed-options`

### Guards
`guard-template-redirect` `guard-template-rest` `guard-template-resolution` — three-layer lockdown preventing template creation that would bypass the router

All features are toggleable via `examplepress.json` or PHP filters:
```php
// Disable a feature
add_filter( 'examplepress_feature_openverse', '__return_false' );

// Override an option
add_filter( 'examplepress_feature_post-lock-window_duration', fn() => 120 );
```

## Filters & Actions

### Routing

| Hook | Type | Description |
|---|---|---|
| `examplepress_route_context` | filter | Override the resolved route slug (default: `get-started`) |
| `examplepress_route_data` | filter | Enrich the data payload passed to `bs_block()` |
| `examplepress_route_resolved` | action | Fires before dispatch with `($slug, $block_name)` |
| `examplepress_theme_namespace` | filter | Override the Blockstudio namespace (default: `examplepress-theme`) |
| `examplepress_template_prefix` | filter | Override the template block prefix (default: `template`) |
| `examplepress_template_block_name` | filter | Override the fully-qualified block name |

### Features

| Hook | Type | Description |
|---|---|---|
| `examplepress_feature_{id}` | filter | Toggle any feature on/off |
| `examplepress_feature_{id}_{key}` | filter | Override a feature option value |
| `examplepress_features` | filter | Modify the full feature registry |
| `examplepress_allowed_block_types` | filter | Customise allowed blocks when `restrict-block-types` is active |

## Building a Companion Plugin

ExamplePress is designed to be extended, not modified. Create a companion plugin to own your site's routing and templates:

```php
<?php
/**
 * Plugin Name: My Site Core
 */

// 1. Point the router at your plugin's blocks
add_filter( 'examplepress_theme_namespace', fn() => 'my-site-core' );

// 2. Define routing logic
add_filter( 'examplepress_route_context', function ( $slug ) {
    if ( is_front_page() ) return 'front';
    if ( is_singular( 'post' ) ) return 'single';
    if ( is_404() ) return '404';
    return $slug;
} );

// 3. Initialize Blockstudio for your plugin
add_action( 'init', fn() => Blockstudio\Build::init( [
    'dir' => plugin_dir_path( __FILE__ ) . 'app',
] ) );
```

Then create template blocks in your plugin's `app/templates/` directory with matching slugs (`template-front`, `template-single`, `template-404`).

## Admin Dashboard

A read-only settings page is available at **ExamplePress** in the admin menu. It surfaces:

- **Features** — All registered features with their resolved state and source (default/JSON/PHP filter)
- **Design** — Color palette swatches, layout dimensions, typography preview
- **Blocks** — All discovered Blockstudio blocks
- **Plugins** — Dependency status for required/recommended plugins
- **Config** — Raw JSON viewer for configuration files with syntax highlighting
- **Health** — Environment checks, theme integrity, router status, guard state
- **Docs** — Quick links and full hook reference

## Author

**Vinny S. Green** — [vinnysgreen.com](https://vinnysgreen.com)
