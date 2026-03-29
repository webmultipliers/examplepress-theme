=== ExamplePress ===

Contributors: vinnysgreen
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A code-first WordPress theme built on Blockstudio.

== Description ==

ExamplePress replaces the traditional WordPress template hierarchy with a single-entry-point router. All requests flow through one universal template which dispatches to modular Blockstudio components based on query context.

The theme is an infrastructure layer — it owns the engine (router, guards, feature registry, configuration pipeline). Companion plugins own the application (routing logic, template blocks, patterns, frontend assets).

Requires the Blockstudio plugin (v7.1+).

== Changelog ==

= 1.0.0 =
* Single-entry-point router architecture.
* Three-layer template guard system.
* Feature registry with 23 toggleable features.
* Declarative configuration via examplepress.json.
* Design token injection (colors, layout, typography).
* Read-only admin settings dashboard.
* WP-CLI scaffolding command.

== Copyright ==

ExamplePress is distributed under the terms of the GNU GPL v2 or later.
