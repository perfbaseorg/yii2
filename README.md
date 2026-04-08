# Perfbase for Yii2

`perfbase/yii2` is the Yii 2 adapter for Perfbase.

It is intentionally thin: framework wiring lives here, transport and extension access stay in `perfbase/php-sdk`.

## Scope

v1 supports:

- HTTP request profiling
- Console command profiling

v1 does not support:

- Queue profiling
- Scheduler-specific profiling
- Custom buffering or delivery layers
- Yii-specific profiler panels

## Requirements

- PHP `>=7.4 <8.5`
- Yii2 `^2.0.45`
- `perfbase/php-sdk` `^1.0`
- The Perfbase PHP extension installed for the target PHP runtime

## Installation

```bash
composer require perfbase/yii2
```

Register the component and bootstrap it in your application config:

```php
return [
    'bootstrap' => ['perfbase'],
    'components' => [
        'perfbase' => [
            'class' => \Perfbase\Yii2\PerfbaseComponent::class,
            'enabled' => true,
            'api_key' => getenv('PERFBASE_API_KEY') ?: '',
            'sample_rate' => 0.1,
            'app_version' => '1.0.0',
        ],
    ],
];
```

The same component can be registered in web and console app configs.

## Configuration

```php
[
    'enabled' => false,
    'debug' => false,
    'log_errors' => true,
    'api_key' => '',
    'api_url' => 'https://receiver.perfbase.com',
    'sample_rate' => 0.1,
    'timeout' => 10,
    'proxy' => null,
    'flags' => \Perfbase\SDK\FeatureFlags::DefaultFlags,
    'app_version' => '',
    'include' => [
        'http' => ['*'],
        'console' => ['*'],
    ],
    'exclude' => [
        'http' => [],
        'console' => [],
    ],
]
```

Notes:

- `sample_rate` must be numeric between `0.0` and `1.0`
- `environment` is derived from `YII_ENV`, otherwise `production`
- `app_version` is application-defined

## HTTP Profiling

HTTP profiling is attached through Yii2 application events:

- `EVENT_BEFORE_ACTION` creates and starts the HTTP lifecycle
- `EVENT_AFTER_REQUEST` finalizes and submits
- Yii2 error-handler state is consulted during finalization to attach exception context

Attributes include:

- `source=http`
- `action`
- `http_method`
- `http_url`
- `http_status_code`
- `user_ip`
- `user_agent`
- `user_id` when Yii user state is available and authenticated
- `hostname`
- `environment`
- `app_version`
- `php_version`

Behavior details:

- action naming prefers stable route/controller identifiers
- `http_url` excludes query strings
- profiling is skipped when filters fail or the extension is unavailable

## Console Profiling

Console profiling is attached through the same application-level event strategy:

- `EVENT_BEFORE_ACTION` creates and starts the console lifecycle
- `EVENT_AFTER_REQUEST` finalizes and submits
- the Yii2 error handler is consulted to attach exception context when present

Console attributes include:

- `source=console`
- `action`
- `exit_code` when Yii2 exposes one through the response object

Exit code handling is intentionally best-effort and non-invasive.

## Filters

Supported contexts:

- `http`
- `console`

Supported filter styles:

- `*`
- `.*`
- glob patterns such as `site/*`
- regex patterns such as `/^GET \/api\//`

Example:

```php
'include' => [
    'http' => ['site/*', '/^GET \\/api\\//'],
    'console' => ['cache/*', 'migrate'],
],
'exclude' => [
    'http' => ['debug/*'],
    'console' => ['help'],
],
```

## Error Handling

The adapter fails open by design:

- invalid SDK configuration becomes a no-op
- missing extension becomes a no-op
- submit failures do not break the Yii application

Behavior:

- `debug=false`: swallow and optionally log errors
- `debug=true`: rethrow adapter/runtime errors

## Runtime Architecture

Primary entry points:

- [`PerfbaseComponent.php`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii2/src/PerfbaseComponent.php)
- [`PerfbaseBootstrap.php`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii2/src/PerfbaseBootstrap.php)
- lifecycle classes in [`src/Lifecycle`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii2/src/Lifecycle)
- support helpers in [`src/Support`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii2/src/Support)

This package does not implement its own buffering, retry, or persistence layer.

## Local Development

Local development uses the sibling SDK checkout:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../lib-php-sdk"
    }
  ]
}
```

Verify locally with:

```bash
composer install
composer run test
composer run phpstan
```

## Limitations

- queue workers are out of scope for v1
- no profiler toolbar or Yii-specific UI
- exit code capture for console commands depends on what the Yii2 response object exposes
