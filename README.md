# ExamplePress

A code-first WordPress theme built on [Blockstudio](https://blockstudio.dev). All requests flow through a single entry point. A PHP resolver maps the WordPress query context to a modular template block.

## Requirements

| Dependency | Version |
|---|---|
| WordPress | 6.9+ |
| PHP | 8.4+ |
| [Blockstudio](https://blockstudio.dev) | 7.1+ |
| Composer | 2.x |

## Installation

1. Install and activate the **Blockstudio** plugin.
2. Clone this repository into `wp-content/themes/examplepress-theme`.
3. Run `composer install --no-dev`.
4. Activate the theme.

## How It Works

Every request hits one block template:

```
templates/index.html  →  <!-- wp:examplepress-theme/router /-->
```

The router block calls `examplepress_get_current_route()`, resolves a template slug, and renders the matching Blockstudio block.

Override any mapping with the `examplepress_route_context` filter:

```php
add_filter( 'examplepress_route_context', function ( $template_slug, $slug ) {
    if ( is_singular( 'press_release' ) ) {
        return 'singular';
    }
    return $template_slug;
}, 10, 2 );
```

## Project Structure

```
examplepress-theme/
  blockstudio/
    init.php                  # Blockstudio code-snippet entry point
    router/                   # Dispatcher block
    templates/                # One directory per route
      index/                  # Default — start here
  templates/
    index.html                # The only WordPress template file
  functions.php               # Theme setup + route resolver
  style.css                   # Theme header
  theme.json                  # Block theme settings
```

## Development

### Adding a Route

1. Create `blockstudio/templates/my-route/block.json`:
   ```json
   {
     "$schema": "https://blockstudio.dev/schema/block",
     "name": "examplepress-theme/template-my-route",
     "title": "Template: My Route",
     "category": "theme",
     "blockstudio": true
   }
   ```
2. Create `blockstudio/templates/my-route/index.php` with your markup.
3. Add a condition in `functions.php` (or use the filter above) to map to `my-route`.

### Styles & Scripts

Drop files next to any `block.json` and Blockstudio enqueues them automatically:

| File | Loaded |
|---|---|
| `style.scss` | Frontend + editor |
| `style.editor.scss` | Editor only |
| `style.scoped.scss` | Scoped to the block instance |
| `script.js` | Frontend + editor (ES module) |
| `script.view.js` | Frontend only |
| `global-style.scss` | All pages, regardless of block usage |

## Releases

Push to `main` or tag `v*` to trigger the GitHub Actions release workflow. It builds Composer dependencies, zips the theme (respecting `.distignore`), and publishes a GitHub Release with an `updates.json` manifest for self-hosted update checking.

Bump the version in `style.css` before merging to `main` — the workflow enforces this on PRs.
