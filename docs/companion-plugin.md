# Companion Plugin Guide

ExamplePress is an immutable infrastructure theme. It provides the router, guards, and feature registry. Your companion plugin provides the application — routing logic, template blocks, patterns, and frontend assets.

The theme is never modified. All customization happens through companion plugins via filters, route origins, and the feature registry.

## Scaffolding

Create a plugin with this minimal structure:

```
wp-content/plugins/my-site-core/
├── app/
│   └── templates/
│       └── front/
│           ├── block.json
│           └── index.php
├── examplepress.json
├── my-site-core.php
└── composer.json (optional)
```

## App Manifest (examplepress.json)

Every companion plugin includes an `examplepress.json` that declares identity, routing priority, and Troy connection data:

```json
{
    "$schema": "https://www.examplepress.com/schema/app",
    "name": "My Site Core",
    "slug": "my-site-core",
    "description": "Main companion plugin for my site.",
    "version": "1.0.0",
    "routing": {
        "priority": 10
    },
    "troy": {
        "server_url": "",
        "repo": "",
        "repo_id": ""
    }
}
```

### Routing Priority

The `routing.priority` value controls evaluation order when multiple companion plugins are active. Lower numbers are evaluated first:

| Priority | Use Case |
|---|---|
| 1-5 | Core application routes (front page, main navigation) |
| 10 | Default — standard companion plugin |
| 20-50 | Add-on plugins that extend the core companion |
| 90-99 | Catch-all / fallback route handlers |

## Plugin Bootstrap

```php
<?php
/**
 * Plugin Name: My Site Core
 * Description: Companion plugin for ExamplePress.
 * Theme: examplepress-theme
 * Requires Plugins: blockstudio
 */

// 1. Register route origins with conditions.
if ( function_exists( 'examplepress_register_route_origin' ) ) {
    $config   = json_decode( file_get_contents( __DIR__ . '/examplepress.json' ), true ) ?: [];
    $priority = (int) ( $config['routing']['priority'] ?? 10 );

    examplepress_register_route_origin( 'my-site-core', [
        'front'  => fn() => is_front_page() || is_home(),
        'single' => fn() => is_singular( 'post' ),
        'page'   => fn() => is_singular( 'page' ),
        'archive' => fn() => is_archive(),
        '404'    => fn() => is_404(),
    ], $priority );

    unset( $config, $priority );
}

// 2. Initialize Blockstudio for this plugin.
add_action( 'init', fn() => Blockstudio\Build::init( [
    'dir' => plugin_dir_path( __FILE__ ) . 'app',
] ) );
```

### Multi-Plugin Example

A blog add-on that handles archive and search while the core plugin handles everything else:

```php
// In my-blog-addon.php
if ( function_exists( 'examplepress_register_route_origin' ) ) {
    examplepress_register_route_origin( 'my-blog-addon', [
        'archive' => fn() => is_post_type_archive( 'post' ),
        'search'  => fn() => is_search(),
        'author'  => fn() => is_author(),
    ], 20 ); // Evaluated after core plugin (priority 10)
}
```

The core plugin at priority 10 is evaluated first. If it doesn't match (e.g., the request is a search page and the core plugin doesn't claim `search`), the blog add-on at priority 20 gets a chance.

### Checking for Conflicts

Before registering a route, you can check if another plugin already claims it:

```php
$owner = examplepress_route_slug_owner( 'archive' );
if ( $owner && $owner !== 'my-blog-addon' ) {
    // Another plugin already owns 'archive'
}
```

## Template Blocks

Each route slug maps to a template block. The router constructs the block name as `{namespace}/template-{slug}`.

For a route slug of `front` with namespace `my-site-core`, the router looks for `my-site-core/template-front`.

### block.json

```json
{
  "$schema": "https://blockstudio.dev/schema/block",
  "name": "my-site-core/template-front",
  "title": "Template: Front Page",
  "category": "theme",
  "blockstudio": true
}
```

### index.php

```php
<?php
/**
 * Front Page Template
 */
?>
<main useBlockProps>
    <h1>Welcome</h1>
    <p>This is rendered by the companion plugin.</p>
</main>
```

### Fallback Behaviour

If your plugin claims a route but the template block doesn't exist yet, the router falls back to `examplepress-theme/template-{slug}` before showing an error. This lets you build templates incrementally without breaking the site.

## Enriching Route Data

Use the `examplepress_route_data` filter to pass contextual data to your template blocks:

```php
add_filter( 'examplepress_route_data', function ( $data, $slug, $block_name ) {
    if ( $slug === 'single' ) {
        $data['post']        = get_queried_object();
        $data['breadcrumbs'] = my_get_breadcrumbs();
    }
    return $data;
}, 10, 3 );
```

The data is available in your template block via the `$a` variable (Blockstudio's data passthrough):

```php
<!-- index.php of your template-single block -->
<?php $post = $a['post'] ?? null; ?>
<article useBlockProps>
    <h1><?php echo esc_html( $post->post_title ?? '' ); ?></h1>
</article>
```

## Pre-Dispatch Hook

Use `examplepress_route_resolved` to run setup logic before the template renders:

```php
add_action( 'examplepress_route_resolved', function ( $slug, $block_name ) {
    // Enqueue route-specific assets
    if ( $slug === 'front' ) {
        wp_enqueue_style( 'my-hero-css', ... );
    }
}, 10, 2 );
```

## Overriding Features

Your companion plugin can override any theme feature using PHP filters (highest priority):

```php
// Enable block type restrictions
add_filter( 'examplepress_feature_restrict-block-types', '__return_true' );

// Customise allowed blocks
add_filter( 'examplepress_feature_restrict-block-types_types', function () {
    return [
        'core/heading',
        'core/paragraph',
        'core/image',
        'core/group',
        'core/columns',
        'my-site-core/hero',
    ];
} );

// Disable a guard during development
add_filter( 'examplepress_feature_guard-template-redirect', '__return_false' );
```

## What Belongs Where

| In the theme (immutable) | In the companion plugin |
|---|---|
| Router dispatch + fallback chain | Route origin registration |
| Route origin registry API | Route conditions and priority |
| Feature registry API | Feature overrides via filters |
| Guard system | Guard toggling for specific workflows |
| Design token injection | Color/layout values in `examplepress.json` |
| Admin settings page | Application-specific admin pages |
| Template lockdown | — |
| — | Template blocks |
| — | Block patterns |
| — | Frontend assets and styles |
| — | Custom post types and taxonomies |
| — | Application-specific hooks |
