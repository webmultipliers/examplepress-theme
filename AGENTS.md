# ExamplePress Theme — AI Agent Context

## Foundational Constraint

ExamplePress is an **infrastructure layer**. The theme owns the engine (router, guards, feature registry, configuration pipeline, Blockstudio bridge). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets). The theme provides zero "reasonable defaults" for front-end rendering — only a clean, documented handoff.

## Architecture Overview

### Request Flow

```
Browser → WordPress → templates/index.html
  → examplepress-theme/router block (blockstudio/router/index.php)
    → examplepress_get_current_route()          # resolve slug via filter
    → apply_filters('examplepress_route_data')  # enrich payload
    → do_action('examplepress_route_resolved')  # pre-dispatch hook
    → bs_block( namespace/template-{slug} )     # dispatch to Blockstudio block
```

### Configuration Resolution Order

Every feature toggle and option resolves in this priority (highest wins):

1. **PHP Filter** — `add_filter('examplepress_feature_{id}', ...)`
2. **examplepress.json** — `features.{id}` (bool or `{ enabled, options }`)
3. **Registration Default** — hardcoded in `inc/features/*.php`

The `design` section in `examplepress.json` is a shorthand that normalises into feature options:
- `design.colors` → `features.theme-colors.options.palette`
- `design.layout.wideSize` → `features.theme-layout.options.wide_size`
- `design.layout.contentSize` → `features.theme-layout.options.content_size`
- `design.typography.fontFamilies` → `features.theme-typography.options.font_families`
- `design.typography.fontSizes` → `features.theme-typography.options.font_sizes`
- `design.strict` → `features.design-strict.enabled`

### Settings Page URL Routing

The settings page supports deep-linking via query params: `?page=examplepress-settings&tab={id}&section={value}`. The `tab` param maps to `data-tab-id` on tab buttons. The `section` param is used within the Config tab to select a specific file subtab.

### Developer Mode

Define `EP_DEV_MODE` as `true` in `wp-config.php` to auto-disable all three guards during development. The settings page displays a red warning banner when active.

## Public API

### Filters

| Filter | Description |
|---|---|
| `examplepress_route_context` | Override the resolved route slug (default: `get-started`) |
| `examplepress_theme_namespace` | Override the Blockstudio namespace (default: `examplepress-theme`) |
| `examplepress_template_prefix` | Override the template block prefix (default: `template`) |
| `examplepress_template_block_name` | Override the fully-qualified block name |
| `examplepress_route_data` | Enrich the data payload passed to `bs_block()` |
| `examplepress_feature_{id}` | Override a feature's enabled state |
| `examplepress_feature_{id}_{key}` | Override a feature's option value |
| `examplepress_allowed_block_types` | Customise allowed blocks when `restrict-block-types` is active |
| `examplepress_features` | Modify the full feature registry |
| `examplepress_admin_tabs_hidden` | Hide settings page tabs by ID (array of strings) |
| `examplepress_feature_details` | Override or extend feature detail descriptions shown in the settings modal |

### Actions

| Action | Description |
|---|---|
| `examplepress_route_resolved` | Fires before router dispatch with `($slug, $block_name)` |

### WP-CLI

| Command | Description |
|---|---|
| `wp examplepress init` | Generate a starter `examplepress.json` pre-populated with all registration defaults |

## Feature Inventory (23 features)

### Theme Support
`title-tag`, `responsive-embeds`, `post-thumbnails`, `wp-block-styles`, `html5`

### Editor Controls
`disable-remote-block-patterns`, `disable-core-block-patterns`, `restrict-block-types` (opt-in), `openverse`

### Admin Customization
`post-lock-window`, `remove-dashboard-widgets`, `login-branding`

### Design Tokens
`theme-colors`, `theme-layout`, `theme-typography` — inject into `wp_theme_json_data_theme` at runtime

### Design Controls
`design-strict` — locks down `appearanceTools` when enabled via `design.strict: true`

### Site Options
`disable-redirect-guess-404`, `permalink-structure`, `managed-options`

### Guards
`guard-template-redirect`, `guard-template-rest`, `guard-template-resolution` — three-layer template lockdown preventing users from creating templates that bypass the router. Auto-disabled by `EP_DEV_MODE`.

## Directory Structure

```
inc/
├── admin/
│   └── settings-page.php   # Read-only admin dashboard
├── config.php              # examplepress_get_config(), JSON reader + design normalisation + EP_DEV_MODE
├── cli.php                 # WP-CLI commands (wp examplepress init)
├── feature-registry.php    # Registry API (register, enabled, option, boot)
├── features.php            # Loader for domain-specific feature files
├── features/
│   ├── theme-support.php
│   ├── editor-controls.php
│   ├── admin-customization.php
│   ├── design-tokens.php   # Colors, layout, typography, strict mode
│   ├── site-options.php
│   └── guards.php
├── router.php              # Routing helper functions
└── plugins.php             # Plugin dependency checker (admin notice)

blockstudio/
├── router/                 # Single-entry dispatch block
├── site-editor/            # JS/CSS lockdown layers (PHP guards moved to features)
├── templates/              # Template blocks (get-started fallback)
└── patterns/               # Block pattern directory

examplepress.json           # Declarative configuration (features, design, plugins)
schema/examplepress-theme.json  # JSON Schema for IDE autocompletion
```

## Rules for Contributors

- **Never add application logic to the theme.** Routing decisions, template blocks, and frontend assets belong in companion plugins.
- **Every new behaviour must be a registered feature** with a unique ID, default state, and filter support.
- **The theme must work with zero plugins installed** — the `get-started` fallback is the baseline.
- **Guards exist to protect the router pattern.** Do not remove them without understanding the template hijacking problem they solve.
- **`design.*` values provide option data, not feature toggles.** A `features.{id}: false` toggle always takes precedence over `design.*` shorthand values for that feature.
