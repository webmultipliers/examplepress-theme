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

#### plugins

Declare plugin dependencies. The theme checks installed/active status and surfaces an admin notice for missing required plugins.

```json
{
  "plugins": {
    "required": ["blockstudio"],
    "recommended": ["redirection", "wp-crontrol"]
  }
}
```

Values are plugin directory slugs (matching the plugin's folder name in `wp-content/plugins/`).

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

Do not add `color.palette` or `layout` settings here — they will conflict with the runtime injection from `examplepress.json`.

## blockstudio.json

Controls Blockstudio's development tools and asset pipeline.

```json
{
  "$schema": "https://blockstudio.dev/schema/blockstudio",
  "assets": {
    "enqueue": true,
    "minify": true,
    "scss": true
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
| `assets.minify` | Minify CSS and JS output |
| `assets.scss` | Enable SCSS compilation |
| `dev.grab` | Enable the block grab tool in the editor |
| `dev.canvas` | Enable canvas mode with admin bar visibility |

## How Config is Loaded

1. `functions.php` requires `inc/config.php`
2. `examplepress_get_config()` reads and parses `examplepress.json`
3. `examplepress_normalise_design_config()` maps `design.*` into `features.*`
4. The normalised config is cached in a static variable for the request
5. `examplepress_feature_enabled()` and `examplepress_feature_option()` consult the cached config during resolution

## Forking Without Code

To configure ExamplePress for a client project without writing PHP:

1. Copy the theme into the project
2. Edit `examplepress.json` to set colours, layout, features, and plugin requirements
3. Build a companion plugin for routing and templates
4. Deploy

The companion plugin only needs to hook `examplepress_route_context` and `examplepress_theme_namespace`. All other configuration lives in the JSON file.
