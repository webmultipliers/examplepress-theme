# Feature Registry API

The feature registry is a centralised system for registering, querying, and booting toggleable theme behaviours. Every feature has a unique ID, a default state, optional configuration, and supports three levels of override.

## Registration

Features are registered during file load (before `after_setup_theme`). The registry stores them and boots them when `examplepress_boot_features()` is called.

```php
examplepress_register_feature( 'my-feature', [
    'label'   => 'My Feature',
    'default' => true,
    'options' => [ 'duration' => 30 ],
    'setup'   => function ( $id ) {
        if ( ! examplepress_feature_enabled( $id ) ) {
            return;
        }
        add_filter( 'some_hook', function () use ( $id ) {
            $duration = examplepress_feature_option( $id, 'duration', 30 );
            return $duration;
        } );
    },
] );
```

### Registration Arguments

| Key | Type | Description |
|---|---|---|
| `label` | string | Human-readable name shown in the settings page |
| `default` | bool | Whether the feature is enabled by default |
| `options` | array | Key/value pairs for feature configuration |
| `setup` | callable | Complex boot function. Receives `($id, $feature)`. Responsible for checking enabled state internally. |
| `hook` | string | Target hook name (simple features only, mutually exclusive with `setup`) |
| `callback` | callable | Hook callback (simple features only) |
| `type` | string | `'filter'` (default) or `'action'` (simple features only) |
| `priority` | int | Hook priority. Default `10` (simple features only) |

### Simple vs Complex Features

**Simple features** use `hook` + `callback`. The registry wires them automatically when the feature is enabled:

```php
examplepress_register_feature( 'disable-remote-block-patterns', [
    'label'    => 'Disable Remote Block Patterns',
    'default'  => true,
    'hook'     => 'should_load_remote_block_patterns',
    'callback' => '__return_false',
] );
```

**Complex features** use `setup`. The callable must check `examplepress_feature_enabled()` internally:

```php
examplepress_register_feature( 'custom-lock', [
    'label'   => 'Custom Post Lock',
    'default' => true,
    'options' => [ 'duration' => 30 ],
    'setup'   => function ( $id ) {
        if ( ! examplepress_feature_enabled( $id ) ) {
            return;
        }
        // Wire hooks here
    },
] );
```

## Resolution Order

Both `examplepress_feature_enabled()` and `examplepress_feature_option()` resolve values using this priority:

| Priority | Source | Mechanism |
|---|---|---|
| 1 (highest) | PHP Filter | `add_filter('examplepress_feature_{id}', ...)` |
| 2 | JSON Config | `examplepress.json` → `features.{id}` |
| 3 (lowest) | Registration Default | Hardcoded in `examplepress_register_feature()` |

### JSON Config Formats

Features in `examplepress.json` can be a simple boolean or an object:

```json
{
  "features": {
    "openverse": false,
    "post-lock-window": {
      "enabled": true,
      "options": {
        "duration": 60
      }
    }
  }
}
```

For boolean values, the value directly overrides the enabled state.
For objects, `enabled` overrides the state and `options` overrides individual option keys.

## API Functions

### examplepress_feature_enabled( $id )

Check whether a feature is enabled after resolving all override layers.

```php
if ( examplepress_feature_enabled( 'login-branding' ) ) {
    // Feature is active
}
```

### examplepress_feature_option( $id, $key, $fallback )

Retrieve a feature option value after resolving all override layers.

```php
$duration = examplepress_feature_option( 'post-lock-window', 'duration', 30 );
```

### examplepress_get_features()

Return the full registry array. Filterable via `examplepress_features`.

```php
$all = examplepress_get_features();
foreach ( $all as $id => $feature ) {
    echo $feature['label'] . ': ' . ( examplepress_feature_enabled( $id ) ? 'on' : 'off' );
}
```

### examplepress_boot_features()

Called on `after_setup_theme`. Iterates all registered features and either calls their `setup` function or wires their `hook`/`callback`.

## Design Token Integration

The `design` section in `examplepress.json` is a shorthand that normalises into feature options during config loading:

| JSON Path | Maps To |
|---|---|
| `design.colors` | `features.theme-colors.options.palette` |
| `design.layout.wideSize` | `features.theme-layout.options.wide_size` |
| `design.layout.contentSize` | `features.theme-layout.options.content_size` |

This means the following are equivalent:

```json
{
  "design": {
    "colors": [{ "name": "Primary", "slug": "primary", "color": "#a3122e" }]
  }
}
```

```json
{
  "features": {
    "theme-colors": {
      "options": {
        "palette": [{ "name": "Primary", "slug": "primary", "color": "#a3122e" }]
      }
    }
  }
}
```

The `design` shorthand is recommended for readability. Both can coexist — `design` values are normalised first, then explicit `features` entries take precedence.
