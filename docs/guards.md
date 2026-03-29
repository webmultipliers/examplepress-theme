# Guard System

Guards are a three-layer defence system that prevents users from creating WordPress templates that would bypass the router. Without guards, a user could create a `single.html` template in the site editor, and WordPress would silently use it instead of the router for single post requests.

## Why Guards Exist

ExamplePress routes all requests through `templates/index.html` containing a single router block. If a user creates additional templates via the site editor:

1. WordPress stores them in the database as `wp_template` custom post types
2. The template hierarchy resolves these database templates before the theme's `index.html`
3. The router is bypassed, breaking all routing logic from companion plugins
4. The site appears to work but routing filters no longer fire

Guards prevent this by blocking template creation at multiple levels.

## The Three Layers

### 1. guard-template-redirect

**Feature ID:** `guard-template-redirect`

Intercepts admin navigation to the site editor's template screens and redirects to the Styles panel. Also rewrites the "Edit Site" admin bar link to point at Styles instead of the template editor.

**Hooks used:**
- `current_screen` — catches direct URL navigation to template editor
- `admin_bar_menu` — rewrites the Edit Site link

### 2. guard-template-rest

**Feature ID:** `guard-template-rest`

Blocks template creation and index template deletion at the REST API level. This is the most critical guard — it prevents template creation regardless of whether the request comes from the site editor UI, WP-CLI, or a direct API call.

**Hooks used:**
- `rest_pre_dispatch` — intercepts POST (creation) and DELETE (index deletion) requests to `/wp/v2/templates`

**Error responses:**
- `ep_template_creation_disabled` (403) — returned for all POST requests
- `ep_template_delete_disabled` (403) — returned when attempting to delete a template containing "index"

### 3. guard-template-resolution

**Feature ID:** `guard-template-resolution`

Filters out user-created (database) templates at resolution time. Even if a template record exists in the database (created before guards were active, or via WP-CLI), it will never resolve for a request.

**Hooks used:**
- `get_block_templates` — removes templates where `source === 'custom'`

## JS/CSS Layers

In addition to the PHP guards (which are registered as features), the theme includes JS and CSS lockdown layers in `blockstudio/site-editor/`:

- **block-editor-scripts.js** — MutationObserver that hides Templates, Template Parts, Patterns, and Pages from the site editor sidebar navigation
- **block-editor-styles.scss** — CSS fallback that hides template navigation items and the "Add new template" button

These are Blockstudio block assets, not part of the feature registry.

## Disabling Guards

Each guard is a registered feature and can be toggled individually.

### Via examplepress.json

```json
{
  "features": {
    "guard-template-redirect": false,
    "guard-template-rest": false,
    "guard-template-resolution": false
  }
}
```

### Via PHP Filter

```php
// Disable all guards during development
add_filter( 'examplepress_feature_guard-template-redirect', '__return_false' );
add_filter( 'examplepress_feature_guard-template-rest', '__return_false' );
add_filter( 'examplepress_feature_guard-template-resolution', '__return_false' );
```

### When to Disable

- **During migration** — if you need to temporarily access the template editor to inspect or export existing templates
- **During development** — if you want to prototype templates in the site editor before converting them to Blockstudio blocks
- **For specific user roles** — wrap the filter in a capability check to give administrators template access while restricting editors

## Health Monitoring

The admin settings page (Health tab) reports the status of each guard. If any guard is disabled, it shows a warning to alert administrators.
