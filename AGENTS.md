# ExamplePress Theme — AI Agent Context

## Foundational Constraint

ExamplePress uses a **Kernel + Theme** architecture. The theme is a **presentation and routing layer** — it owns the engine (router, feature registry, configuration pipeline, Blockstudio bridge). The **ExamplePress Platform Kernel** (`examplepress-mu`) is the undisputed platform governor — it owns security, template guards, permalink enforcement, and capability lockdown. Companion plugins own the application (routing logic, template blocks, patterns, frontend assets). The theme provides zero "reasonable defaults" for front-end rendering — only a clean, documented handoff.

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
- `design.typography.{fluid,lineHeight,textColumns,writingMode,dropCap,defaultFontSizes}` → `features.theme-typography.options.*`
- `design.spacing.*` → `features.theme-spacing.options.*`
- `design.borders.*` → `features.theme-borders.options.*`
- `design.shadows.*` → `features.theme-shadows.options.*`
- `design.globalStyles.*` → `features.theme-global-styles.options.*` (also sets `enabled: true`)
- `design.strict` → `features.design-strict.enabled`

The `blockstudio` section normalises the same way:
- `blockstudio.assets` → `features.blockstudio-assets`
- `blockstudio.assetReset` → `features.blockstudio-asset-reset`
- `blockstudio.minify` → `features.blockstudio-minify`
- `blockstudio.scss` → `features.blockstudio-scss`
- `blockstudio.tailwind` → `features.blockstudio-tailwind`
- `blockstudio.editor` → `features.blockstudio-editor`
- `blockstudio.blockEditor` → `features.blockstudio-block-editor`
- `blockstudio.aiContext` → `features.blockstudio-ai-context`
- `blockstudio.blockTags` → `features.blockstudio-block-tags`
- `blockstudio.dev` → `features.blockstudio-dev`
- `blockstudio.users` → `features.blockstudio-users`

Blockstudio features write back via `add_filter('blockstudio/settings/{path}', ...)`. When disabled, Blockstudio falls back to its own `blockstudio.json` or defaults.

### Settings Page URL Routing

The settings page supports deep-linking via query params: `?page=examplepress-settings&tab={id}&section={value}`. The `tab` param maps to `data-tab-id` on tab buttons. The `section` param is used within the Config tab to select a specific file subtab.

### Developer Mode

Template guards are enforced by the MU Kernel (`examplepress-mu`). Define `EP_DEV_MODE` in `wp-config.php` to bypass guards during development (requires MU Kernel support). The settings page displays a red warning banner when active.

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
| `examplepress_settings_fonts_url` | Override or disable the Google Fonts URL loaded by the settings page (return empty string for GDPR compliance) |

### Actions

| Action | Description |
|---|---|
| `examplepress_route_resolved` | Fires before router dispatch with `($slug, $block_name)` |

### WP-CLI

| Command | Description |
|---|---|
| `wp examplepress init` | Generate a starter `examplepress.json` pre-populated with all registration defaults |

## Feature Inventory (31 features)

Every feature has a `group` key used by the settings page to auto-sort into tabs.

### Theme Support (group: `theme`)
`title-tag`, `responsive-embeds`, `post-thumbnails`, `wp-block-styles`, `html5`

### Editor Controls (group: `editor`)
`disable-remote-block-patterns`, `disable-core-block-patterns`, `restrict-block-types` (opt-in), `openverse`, `post-lock-window`

### Admin Customization (group: `admin`)
`remove-dashboard-widgets`, `login-branding`

### Design Tokens (group: `design`)
`theme-colors`, `theme-layout`, `theme-typography`, `theme-spacing`, `theme-borders`, `theme-shadows`, `theme-global-styles` — inject into `wp_theme_json_data_theme` at priority 39

### Design Controls (group: `design`)
`design-strict` — locks down `appearanceTools` when enabled via `design.strict: true` (priority 99)

### Platform Kernel (examplepress-mu)
The following are enforced by the MU Kernel and are **not** registered or toggleable via the theme:
- Template guards (redirect, REST, resolution) — three-layer template lockdown preventing users from creating templates that bypass the router
- Permalink structure enforcement (`/%postname%/`)
- Capability lockdown and `DISALLOW_FILE_EDIT`

### Blockstudio (group: `blockstudio`)
`blockstudio-assets`, `blockstudio-asset-reset`, `blockstudio-minify`, `blockstudio-scss`, `blockstudio-tailwind`, `blockstudio-editor`, `blockstudio-block-editor`, `blockstudio-ai-context`, `blockstudio-block-tags`, `blockstudio-dev`, `blockstudio-users` — bridge Blockstudio runtime settings via `blockstudio/settings/{path}` filters. All default to disabled; enable via `blockstudio.*` shorthand or `features.blockstudio-*` in `examplepress.json`.

## Directory Structure

```
inc/
├── admin/
│   ├── admin-assets.php    # Vite-aware asset enqueuing (dev + prod)
│   ├── admin-registry.php  # Declarative submenu registration (attaches to MU parent)
│   ├── settings-data.php   # Data helpers for admin page payloads
│   └── pages/              # Per-page render functions and data gatherers
├── config.php              # examplepress_get_config(), JSON reader + design/blockstudio normalisation
├── feature-registry.php    # Registry API (register, enabled, option, guarded_setup, boot, get_by_group)
├── features.php            # Loader for domain-specific feature files
├── features/
│   ├── theme-support.php
│   ├── editor-controls.php
│   ├── admin-customization.php
│   ├── design-tokens.php   # Colors, layout, typography, spacing, borders, shadows, global styles, strict mode
│   └── blockstudio.php     # 11 Blockstudio integration features
├── route-registry.php      # Multi-origin route registry (register_route_origin, resolve)
└── router.php              # Routing helper functions

blockstudio/
├── router/                 # Single-entry dispatch block
├── site-editor/            # JS/CSS lockdown layers (PHP guards enforced by MU Kernel)
├── templates/              # Template blocks (get-started fallback)
└── patterns/               # Block pattern directory

examplepress.json           # Declarative configuration (features, design, updater, dependencies)
schema/examplepress-theme.json  # JSON Schema for IDE autocompletion (covers theme + companion plugin configs)
languages/
└── examplepress-theme.pot  # Translation template
```

### Platform Infrastructure (in MU Kernel)

The following capabilities are provided by the MU Kernel (`examplepress-mu`) and consumed by the theme's admin pages via `function_exists()` guards:

- **App discovery & registry** — `examplepress_get_apps()`, `examplepress_registry_*()`, `ep_app` CPT
- **Dependency resolution** — `examplepress_get_dependencies()`
- **Notification aggregation** — `examplepress_gather_notifications()`, archive/restore REST endpoint
- **GitHub integration** — repo creation, scaffold push, App JWT signing, token management
- **Scaffolder** — template repo scaffolding, placeholder replacement, Codespaces URLs
- **REST API** — `/examplepress/v1/` endpoints for apps, connections, demo, updater, filesystem
- **Plugin manager** — updater and demo plugin install/update/uninstall
- **WP-CLI** — `wp examplepress init`

## Rules for Contributors

- **Never add application logic to the theme.** Routing decisions, template blocks, and frontend assets belong in companion plugins.
- **Every new behaviour must be a registered feature** with a unique ID, `group`, default state, and filter support.
- **Use `examplepress_guarded_setup()`** in all complex feature `setup` callables instead of manually checking `examplepress_feature_enabled()`. The only exception is features that intentionally run regardless of enabled state (e.g. `openverse` which writes the setting either way).
- **Every feature must have a `group` key** — one of: `design`, `editor`, `admin`, `theme`, `blockstudio`.
- **The theme must work with zero plugins installed** — the `get-started` fallback is the baseline. Admin pages degrade gracefully via `function_exists()` guards when MU kernel functions are unavailable.
- **Platform infrastructure belongs in the MU Kernel.** App lifecycle, GitHub integration, scaffolding, REST APIs, dependencies, notifications, CLI, and plugin management are all MU responsibilities. The theme only renders the admin UI.
- **Guards are enforced by the MU Kernel.** The theme blindly assumes the MU plugin is active and managing the security perimeter.
- **Shorthand sections (`design.*`, `blockstudio.*`) provide option data, not feature toggles.** A `features.{id}: false` toggle always takes precedence over shorthand values for that feature.
- **Blockstudio features follow the filter bridge pattern:** register with `examplepress_register_feature()`, then use `add_filter('blockstudio/settings/{path}', ...)` inside the guarded setup to write values back. Never generate or modify `blockstudio.json` from PHP.
