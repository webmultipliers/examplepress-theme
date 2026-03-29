# Configuration

ExamplePress is configured through three JSON files and a PHP filter chain. This guide covers the declarative configuration layer.

## examplepress.json

The primary configuration file. Lives in the theme root and is read by `examplepress_get_config()` on every request (cached for the request lifetime).

### Schema

A JSON Schema is available at `schema/examplepress-theme.json`. Point your editor at it for autocompletion:

```json
{
  "$schema": "https://www.examplepress.com/schema/examplepress-theme"
}
```

### Sections

#### features

Toggle or configure registered theme features. Keys are feature IDs.

```json
{
  "features": {
    "openverse": false,
    "restrict-block-types": {
      "enabled": true,
      "options": {
        "types": ["core/heading", "core/paragraph", "core/image"]
      }
    }
  }
}
```

- Boolean values toggle the feature on/off
- Object values can set `enabled` and override `options`
- Omitted features use their registration defaults

#### design

Shorthand for design token features. Normalised into feature options at load time.

```json
{
  "design": {
    "colors": [
      { "name": "Primary", "slug": "primary", "color": "#a3122e" },
      { "name": "Secondary", "slug": "secondary", "color": "#205375" }
    ],
    "layout": {
      "wideSize": "1200px",
      "contentSize": "800px"
    }
  }
}
```

Colors are injected into WordPress's theme.json at runtime via the `wp_theme_json_data_theme` filter. Each colour generates a CSS custom property: `--wp--preset--color--{slug}`.

Layout dimensions set the global `wideSize` and `contentSize` for block alignment.

#### docs

Documentation links shown in the settings page Docs tab. Client forks can replace these to point at their own documentation.

```json
{
  "docs": [
    {
      "title": "Companion Plugin Guide",
      "description": "Build your first companion plugin.",
      "url": "https://github.com/webmultipliers/examplepress-theme/blob/development/docs/companion-plugin.md",
      "category": "Start Here"
    }
  ]
}
```

| Field | Required | Description |
|---|---|---|
| `title` | yes | Card heading in the Docs tab |
| `description` | no | Summary text below the title |
| `url` | yes | Link to the documentation page |
| `category` | no | Eyebrow label for grouping (e.g. "Architecture", "Configuration") |

The default entries point at the GitHub repo's `docs/` directory. When forking for a client, replace these with URLs to your own documentation, internal wikis, or Notion pages.

#### plugins

A curated plugin directory. Each entry declares a dependency with metadata for the settings page and admin notices.

```json
{
  "plugins": [
    {
      "slug": "blockstudio",
      "name": "Blockstudio",
      "tier": "required",
      "pricing": "paid",
      "cloud_dependent": false,
      "source": { "type": "direct", "url": "https://blockstudio.dev/" }
    },
    {
      "slug": "admin-columns-pro",
      "name": "Admin Columns Pro",
      "tier": "recommended",
      "pricing": "paid",
      "cloud_dependent": false,
      "source": { "type": "direct", "url": "https://www.admincolumns.com/" },
      "fallback_slug": "codepress-admin-columns"
    },
    {
      "slug": "redirection",
      "name": "Redirection",
      "tier": "recommended",
      "pricing": "free",
      "cloud_dependent": false,
      "source": { "type": "wporg" }
    }
  ]
}
```

| Field | Required | Description |
|---|---|---|
| `slug` | yes | Plugin folder name used for detection |
| `name` | yes | Human-readable name |
| `tier` | yes | `required`, `recommended`, or `optional` |
| `pricing` | yes | `free` or `paid` |
| `cloud_dependent` | no | `true` if the plugin connects to a SaaS service |
| `source.type` | yes | `wporg`, `direct` (vendor site), or `private` (self-hosted) |
| `source.url` | no* | Download or purchase URL (*required for `direct` and `private`) |
| `fallback_slug` | no | Slug of a free WordPress.org alternative |

**Fallback logic:** If a paid plugin is missing but its `fallback_slug` is installed and active, the dependency is marked as satisfied (free version). The settings page shows the fallback status, and admin notices include links to the free alternative.

**Backwards compatibility:** The legacy `{ "required": [], "recommended": [] }` format is still accepted and normalised at load time.

## theme.json

Minimal structural file. ExamplePress keeps this lean — only the schema version and `appearanceTools` toggle. Colours, layout, and typography are injected at runtime by the feature registry.

```json
{
  "$schema": "https://schemas.wp.org/wp/6.9/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true,
    "useRootPaddingAwareAlignments": false
  }
}
```

Do not add `color.palette`, `layout`, or `typography` settings here — they will conflict with the runtime injection from `examplepress.json`.

## blockstudio.json

Controls Blockstudio's development tools and asset pipeline.

```json
{
  "$schema": "https://app.blockstudio.dev/schema/blockstudio",
  "assets": {
    "enqueue": true,
    "minify": { "css": true, "js": true },
    "process": { "scss": true }
  },
  "dev": {
    "grab": { "enabled": true },
    "canvas": { "enabled": true, "adminBar": true }
  }
}
```

| Key | Description |
|---|---|
| `assets.enqueue` | Enable automatic asset enqueuing for blocks |
| `assets.minify.css/js` | Minify CSS and JS output |
| `assets.process.scss` | Enable SCSS compilation |
| `dev.grab` | Enable the block grab tool in the editor |
| `dev.canvas` | Enable canvas mode with admin bar visibility |

## Strict Design Mode

Set `design.strict: true` in `examplepress.json` to lock down all appearance tools in the block editor:

```json
{
  "design": {
    "strict": true
  }
}
```

When strict mode is active, the theme forces `appearanceTools: false` in theme.json at runtime. This disables all custom color pickers, font size inputs, spacing controls, and border settings. Users can only use the presets defined in the design token configuration. This is the level of editor lockdown that enterprise clients typically require.

Strict mode can also be enabled via the feature system:
```php
add_filter( 'examplepress_feature_design-strict', '__return_true' );
```

## Typography Configuration

Define font families and size presets in the `design.typography` section:

```json
{
  "design": {
    "typography": {
      "fontFamilies": [
        { "name": "System", "slug": "system", "fontFamily": "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" },
        { "name": "Monospace", "slug": "mono", "fontFamily": "'JetBrains Mono', ui-monospace, monospace" }
      ],
      "fontSizes": [
        { "slug": "sm", "size": "0.875rem", "name": "Small" },
        { "slug": "md", "size": "1rem", "name": "Medium" },
        { "slug": "lg", "size": "1.25rem", "name": "Large" }
      ]
    }
  }
}
```

These are injected into WordPress's theme.json at runtime via the `theme-typography` feature, generating CSS custom properties (`--wp--preset--font-family--{slug}`, `--wp--preset--font-size--{slug}`).

When no typography is configured, the settings page displays system defaults.

## Developer Mode

Define `EP_DEV_MODE` in `wp-config.php` to auto-disable all three template guards during companion plugin development:

```php
define( 'EP_DEV_MODE', true );
```

When active:
- All guard features (`guard-template-redirect`, `guard-template-rest`, `guard-template-resolution`) are bypassed via PHP filters
- The settings page displays a red warning banner
- Guard health checks will show as "Disabled"

Remove the constant before deploying to production.

## Design vs Features Precedence

The `design.*` shorthand normalises into `features.*` options during config loading. If both are set, `features.*` entries take precedence because they are applied after normalisation:

- `design.colors` provides option data for `theme-colors`
- A `features.theme-colors: false` toggle disables the feature entirely, even if `design.colors` has values

This means you can use `design.*` for token values while still using `features.*` to disable a category.

## How Config is Loaded

1. `functions.php` requires `inc/config.php`
2. `examplepress_get_config()` reads and parses `examplepress.json`
3. `examplepress_normalise_design_config()` maps `design.*` into `features.*`
4. `EP_DEV_MODE` guard bypass filters are registered if the constant is defined
5. The normalised config is cached in a static variable for the request
6. `examplepress_feature_enabled()` and `examplepress_feature_option()` consult the cached config during resolution

## WP-CLI

Generate a starter configuration file pre-populated with all registration defaults:

```bash
wp examplepress init          # creates examplepress.json
wp examplepress init --force  # overwrites existing file
```

## Forking Without Code

To configure ExamplePress for a client project without writing PHP:

1. Copy the theme into the project
2. Run `wp examplepress init` or edit `examplepress.json` manually
3. Set colours, layout, typography, features, and plugin requirements in the JSON
4. Build a companion plugin for routing and templates
5. Deploy

The companion plugin only needs to hook `examplepress_route_context` and `examplepress_theme_namespace`. All other configuration lives in the JSON file.
