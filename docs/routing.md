# Router & Routing

ExamplePress replaces the WordPress template hierarchy with a single-entry-point router. Every request renders through `templates/index.html`, which contains only the router block.

## How It Works

Companion plugins register **route origins** — each declares a namespace and a set of route conditions. The router evaluates all registered origins by priority (lower number = first) and dispatches to the first match.

```php
examplepress_register_route_origin( 'my-site-core', [
    'front'  => fn() => is_front_page() || is_home(),
    'single' => fn() => is_singular(),
    '404'    => fn() => is_404(),
], 10 );
```

A second plugin can handle different routes without conflict:

```php
examplepress_register_route_origin( 'my-blog-addon', [
    'archive' => fn() => is_archive(),
    'search'  => fn() => is_search(),
], 20 ); // Higher number = evaluated after priority 10
```

Priority is declared in `examplepress.json`:

```json
{
    "routing": {
        "priority": 10
    }
}
```

## Request Flow

```
1. WordPress loads templates/index.html
2. Router block executes blockstudio/router/index.php
3. examplepress_resolve_route() walks the route origin registry
4. First matching origin returns { namespace, slug }
5. If no origin matches → defaults to examplepress-theme/template-get-started
6. examplepress_get_template_prefix() resolves the prefix
7. Block name assembled: {namespace}/{prefix}-{slug}
8. apply_filters('examplepress_route_data', [], $slug, $block_name)
9. do_action('examplepress_route_resolved', $slug, $block_name)
10. bs_block() dispatches to the resolved template block
```

## Default Behaviour

With no companion plugin installed, the router resolves to `examplepress-theme/template-get-started` — the built-in fallback that displays the architecture documentation.

## Routing Filters

### examplepress_resolved_origin

Filter the registry-resolved route origin before dispatch.

```php
add_filter( 'examplepress_resolved_origin', function ( $origin ) {
    // $origin = [ 'namespace' => '...', 'slug' => '...' ]
    return $origin;
} );
```

**Parameters:** `$origin` (array with `namespace` and `slug` keys)

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

## Route Origin API

### Registration

```php
examplepress_register_route_origin( string $namespace, array $routes, int $priority = 10 ): void
```

### Introspection

```php
// Check if any origins are registered
examplepress_has_route_origins(): bool

// Get all registered namespaces
examplepress_get_route_origin_namespaces(): string[]

// Full map of origins with priorities and route lists
examplepress_get_route_origin_map(): array[]

// Check which namespace owns a specific slug
examplepress_route_slug_owner( string $slug ): ?string

// Detect slugs claimed by multiple origins
examplepress_detect_route_conflicts(): array
```

### Conflict Detection

When two plugins claim the same route slug, the one with lower priority wins. Use `examplepress_detect_route_conflicts()` to identify overlaps:

```php
$conflicts = examplepress_detect_route_conflicts();
// [ 'front' => [ { namespace: 'plugin-a', priority: 10 }, { namespace: 'plugin-b', priority: 20 } ] ]
```

## Theme Immutability

ExamplePress is designed as an immutable theme. Companion plugins extend it via filters and route origins — they never modify theme files. Use `examplepress_check_theme_immutability()` in CI or health checks to verify no unexpected files have been added to the theme directory.

## Missing Templates

If the resolved block does not exist, the router renders a diagnostic message showing the expected block name. This helps developers identify which template block needs to be created.

## Why Not the Template Hierarchy?

The traditional hierarchy (`single.php`, `archive.php`, etc.) creates implicit routing via filename conventions. This works for simple sites but becomes unpredictable when:

- Multiple plugins modify template resolution
- Custom post types need non-standard layouts
- The same layout serves multiple query contexts
- Templates need to live in version-controlled plugin code, not the theme

The router pattern makes routing **explicit, filterable, and multi-origin safe**. Every routing decision is a registry entry that can be inspected, overridden, and tested.
