# ExamplePress Theme

A minimal WordPress theme built on [Blockstudio](https://blockstudio.dev). ExamplePress replaces the traditional template hierarchy with a single-entry-point router that dispatches to modular Blockstudio blocks.

The theme is **strictly a presentation layer**. It contains a router block, a fallback template, and Blockstudio configuration — nothing else. All platform infrastructure — the routing engine, feature registry, configuration, admin dashboard, security, and app management — is owned by the [ExamplePress Platform Kernel](https://github.com/webmultipliers/examplepress-mu) (`examplepress-mu`), a required Must-Use plugin.