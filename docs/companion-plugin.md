# Companion Plugin Guide

ExamplePress is an infrastructure theme. It provides the router, guards, and feature registry. Your companion plugin provides the application — routing logic, template blocks, patterns, and frontend assets.

## Scaffolding

Create a plugin with this minimal structure:

```
wp-content/plugins/my-site-core/
├── app/
│   └── templates/
│       └── front/
│           ├── block.json
│           └── index.php
├── my-site-core.php
└── composer.json (optional)
```

## Plugin Bootstrap

```php
<?php
/**
 * Plugin Name: My Site Core
 * Description: Companion plugin for ExamplePress.
 * Requires Plugins: blockstudio
 */

// 1. Point the theme router at this plugin's blocks.
add_filter( 'examplepress_theme_namespace', fn() => 'my-site-core' );

// 2. Define your routing cascade.
add_filter( 'examplepress_route_context', function ( $slug ) {
    if ( is_front_page() || is_home() ) return 'front';
    if ( is_singular( 'post' ) )        return 'single';
    if ( is_singular( 'page' ) )        return 'page';
    if ( is_archive() )                 return 'archive';
    if ( is_search() )                  return 'search';
    if ( is_404() )                     return '404';
    return $slug;
} );

// 3. Initialize Blockstudio for this plugin.
add_action( 'init', fn() => Blockstudio\Build::init( [
    'dir' => plugin_dir_path( __FILE__ ) . 'app',
] ) );
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

| In the theme | In the companion plugin |
|---|---|
| Router dispatch | Routing logic (`examplepress_route_context`) |
| Feature registry API | Feature overrides via filters |
| Guard system | Guard toggling for specific workflows |
| Design token injection | Color/layout values in `examplepress.json` |
| Admin settings page | Application-specific admin pages |
| — | Template blocks |
| — | Block patterns |
| — | Frontend assets and styles |
| — | Custom post types and taxonomies |
| — | Application-specific hooks |
