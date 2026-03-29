# Router & Routing

ExamplePress replaces the WordPress template hierarchy with a single-entry-point router. Every request renders through `templates/index.html`, which contains only the router block.

## Request Flow

```
1. WordPress loads templates/index.html
2. Router block executes blockstudio/router/index.php
3. examplepress_get_current_route() resolves the slug     (filterable via examplepress_route_context)
4. examplepress_get_theme_namespace() resolves the namespace     (filterable via examplepress_theme_namespace)
5. examplepress_get_template_prefix() resolves the prefix     (filterable via examplepress_template_prefix)
6. Block name assembled: {namespace}/{prefix}-{slug}     (filterable via examplepress_template_block_name)
7. apply_filters('examplepress_route_data', [], $slug, $block_name)
8. do_action('examplepress_route_resolved', $slug, $block_name)
9. bs_block() dispatches to the resolved template block
```

## Default Behaviour

With no companion plugin installed, the router resolves to `examplepress-theme/template-get-started` — the built-in fallback that displays the architecture documentation.

## Routing Filters

### examplepress_route_context

The primary routing hook. Companion plugins use this to implement their routing cascade.

```php
add_filter( 'examplepress_route_context', function ( $slug ) {
    if ( is_front_page() ) return 'front';
    if ( is_singular() )   return 'single';
    return $slug; // fall through to default
} );
```

**Parameters:** `$slug` (string) — the current route slug
**Default:** `'get-started'`

### examplepress_theme_namespace

Controls which Blockstudio namespace the router searches for template blocks. Override this to point the router at your companion plugin's blocks.

```php
add_filter( 'examplepress_theme_namespace', fn() => 'my-plugin' );
```

**Parameters:** `$namespace` (string)
**Default:** `'examplepress-theme'`

### examplepress_template_prefix

Controls the prefix prepended to the route slug when constructing the block name.

**Parameters:** `$prefix` (string)
**Default:** `'template'`

### examplepress_template_block_name

Final override for the fully-qualified block name. Use this for non-standard naming conventions.

```php
add_filter( 'examplepress_template_block_name', function ( $name, $slug, $prefix, $ns ) {
    if ( $slug === 'home' ) {
        return 'my-plugin/custom-homepage';
    }
    return $name;
}, 10, 4 );
```

**Parameters:** `$name` (string), `$slug` (string), `$prefix` (string), `$namespace` (string)

### examplepress_route_data

Enrich the data payload passed to the template block. The data is available in the template via Blockstudio's `$a` variable.

```php
add_filter( 'examplepress_route_data', function ( $data, $slug, $block_name ) {
    $data['queried_object'] = get_queried_object();
    return $data;
}, 10, 3 );
```

**Parameters:** `$data` (array), `$slug` (string), `$block_name` (string)

### examplepress_route_resolved (action)

Fires immediately before the router dispatches to the template block. Use for side effects (asset enqueuing, global state).

```php
add_action( 'examplepress_route_resolved', function ( $slug, $block_name ) {
    wp_enqueue_style( "template-{$slug}", ... );
}, 10, 2 );
```

**Parameters:** `$slug` (string), `$block_name` (string)

## Missing Templates

If the resolved block does not exist, the router renders a fallback message showing the expected block name. This helps developers identify which template block needs to be created.

## Why Not the Template Hierarchy?

The traditional hierarchy (`single.php`, `archive.php`, etc.) creates implicit routing via filename conventions. This works for simple sites but becomes unpredictable when:

- Multiple plugins modify template resolution
- Custom post types need non-standard layouts
- The same layout serves multiple query contexts
- Templates need to live in version-controlled plugin code, not the theme

The router pattern makes routing **explicit and filterable**. Every routing decision is a PHP filter that can be inspected, overridden, and tested.
