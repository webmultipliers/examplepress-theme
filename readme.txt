=== ExamplePress ===

Contributors: vinnysgreen
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.4
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A minimal WordPress theme built on Blockstudio.

== Description ==

ExamplePress replaces the traditional WordPress template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks.

The theme is strictly a presentation layer. All platform infrastructure — routing engine, features, configuration, admin UI, security, app management — is owned by the ExamplePress Platform Kernel (examplepress-mu). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

Requires the ExamplePress MU Kernel and the Blockstudio plugin (v7.1+).

== Changelog ==

= 1.2.1 =
* Architecture renovation: theme stripped to presentation layer only.
* All platform infrastructure moved to ExamplePress MU Kernel (examplepress-mu).
* Removed feature registry, configuration pipeline, admin dashboard, and build tooling from theme.
* Routing engine, guards, app management, scaffolding, and REST APIs now owned by MU Kernel.

= 1.0.3 =
* Modular Vite-based admin asset pipeline replacing monolithic JS/CSS.
* Removed legacy dependency config formats and dead code.

= 1.0.2 =
* Multi-origin route registry for companion plugins.
* Build tab with one-click app scaffolding from GitHub template repos.
* GitHub App and Troy Server integration.
* Notifications hub and dependency management dashboard.

= 1.0.1 =
* GitHub Releases auto-updater with channel support.
* Design token system and Blockstudio settings bridge.

= 1.0.0 =
* Single-entry-point router architecture.
* Template guard system.
* Feature registry and declarative configuration.
* Admin settings dashboard.

== Copyright ==

ExamplePress is distributed under the terms of the GNU GPL v2 or later.
