=== ExamplePress ===

Contributors: vinnysgreen
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.4
Stable tag: 1.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A code-first WordPress theme built on Blockstudio.

== Description ==

ExamplePress replaces the traditional WordPress template hierarchy with a single-entry-point router. All requests flow through one universal template which dispatches to modular Blockstudio components based on query context.

The theme is an infrastructure layer — it owns the engine (router, guards, feature registry, configuration pipeline). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

Requires the Blockstudio plugin (v7.1+).

== Changelog ==

= 1.0.3 =
* Modular Vite-based admin asset pipeline replacing monolithic JS/CSS.
* Removed legacy dependency config formats and dead code.
* Inline connection data consolidated into ExamplePressData.

= 1.0.2 =
* Feature registry expanded to 37 toggleable features (spacing, borders, shadows, global styles, Blockstudio controls).
* Multi-origin route registry — companion plugins register route origins with configurable priority.
* Route origin health checks and namespace resolution visible in Health tab.
* Build tab with one-click app scaffolding from GitHub template repos.
* GitHub App integration for token-free repo creation and push.
* Troy Server integration — register, connect, and manage companion plugins on Troy instances.
* Connections tab for GitHub and Troy credential management with live connection tests.
* Notifications hub aggregating dependency, health, and routing warnings per-user.
* Demo companion plugin install/uninstall from the admin dashboard.
* JSON Schema updated with updater, routing, and troy sections for full IDE autocompletion.
* REST endpoint argument schemas with type enforcement and sanitization.

= 1.0.1 =
* GitHub Releases auto-updater with stable/prerelease channel support.
* Design token system expanded with spacing, borders, shadows, and global styles.
* Blockstudio settings bridge — configure Blockstudio via examplepress.json.
* Dependency management dashboard with tier, pricing, and fallback detection.
* Library tab placeholder for upcoming v1.1 content.

= 1.0.0 =
* Single-entry-point router architecture.
* Three-layer template guard system.
* Feature registry with 22 toggleable features.
* Declarative configuration via examplepress.json.
* Design token injection (colors, layout, typography).
* Read-only admin settings dashboard.
* WP-CLI scaffolding command.

== Copyright ==

ExamplePress is distributed under the terms of the GNU GPL v2 or later.
