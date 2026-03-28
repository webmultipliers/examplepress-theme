# ExamplePress

ExamplePress is a code-first WordPress theme built on Blockstudio. It replaces the traditional WordPress template hierarchy with a single-entry-point router. All requests flow through one universal template which dispatches to modular Blockstudio components based on the query context.

## Features

* **Single-Entry Routing:** Routes all requests through a single dispatcher instead of relying on the traditional WordPress template hierarchy.
* **Modular Components:** Loads template components from version-controlled files using Blockstudio.
* **Code-First Architecture:** Keeps developers in the code, with themes built out of PHP, JSON, and HTML.
* **Clean Frontend Output:** Removes unnecessary wrapper divs from routing and template blocks.
* **Disabled Remote Patterns:** Prevents remote block patterns from loading by default for better performance and control.

## Requirements

* **WordPress:** 6.9 or higher
* **PHP:** 8.4 or higher
* **Plugin:** Blockstudio (v7.1+) is strictly required.
* **Composer:** Required for vendor autoloading.

## Project Structure

* `functions.php`: Handles theme setup, composer autoloading, and registers routing functions.
* `templates/index.html`: The universal entry point containing only the `` block.
* `blockstudio/router/`: Contains the logic for the Theme Router block, which dynamically determines and renders the appropriate template block.
* `blockstudio/templates/`: Directory for modular template blocks (e.g., `get-started`), which act as the actual views for your routes.

## How Routing Works

ExamplePress bypasses the standard template hierarchy by using a single `templates/index.html` file. Inside, a custom router block takes over:

1. The `examplepress_get_current_route()` function determines the target slug (defaulting to `get-started`).
2. The router block (`examplepress-theme/router`) renders dynamically via `blockstudio/router/index.php`.
3. The router dynamically constructs the name of the template block to load (e.g., `examplepress-theme/template-get-started`) and renders it.

## Developer Hooks & Filters

ExamplePress provides several custom filters in `functions.php` to allow developers to easily extend or override the routing and templating behavior.

### Custom Theme Filters

* `examplepress_route_context`
  Filters the current route slug.
  * **Parameters:** `$slug` (string)
  * **Default:** `'get-started'`

* `examplepress_theme_namespace`
  Filters the namespace used for the theme's blocks.
  * **Parameters:** `$namespace` (string)
  * **Default:** `'examplepress-theme'`

* `examplepress_template_prefix`
  Filters the prefix applied to template blocks.
  * **Parameters:** `$prefix` (string)
  * **Default:** `'template'`

* `examplepress_template_block_name`
  Filters the fully assembled block name before it is passed to the Blockstudio renderer.
  * **Parameters:** `$full_block_name` (string), `$slug` (string), `$prefix` (string), `$theme_ns` (string)
  * **Default:** `sprintf( '%s/%s-%s', $theme_ns, $prefix, $slug )`

### Third-Party & Core Filters Modified

* `should_load_remote_block_patterns`
  Forced to return `false` to disable core remote block patterns.
* `blockstudio/patterns/paths`
  Appends `EP_THEME_PATH . '/blockstudio/patterns'` to the registered Blockstudio pattern directories.
* `blockstudio/blocks/components/inner_blocks/frontend/wrap`
  Used to intercept the frontend rendering of specific blocks. It specifically returns `false` to remove the default Blockstudio wrappers for the `examplepress-theme/router` block and any registered template blocks (matching the format `$theme_ns/$template_prefix-*`).

## Author

**Vinny S. Green**
* [vinnysgreen.com](https://vinnysgreen.com)
