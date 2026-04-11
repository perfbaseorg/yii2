<p align="center">
  <a href="https://perfbase.com">
    <img src="https://cdn.perfbase.com/img/logo-full.svg" alt="Perfbase" width="300">
  </a>
</p>

<h3 align="center">Perfbase for Yii 2</h3>
<p align="center">
  Yii 2 integration for <a href="https://perfbase.com">Perfbase</a>.
</p>

<p align="center">
  <a href="https://packagist.org/packages/perfbase/yii2"><img src="https://img.shields.io/packagist/v/perfbase/yii2" alt="Packagist Version"></a>
  <a href="https://github.com/perfbaseorg/yii2/blob/main/LICENSE.txt"><img src="https://img.shields.io/packagist/l/perfbase/yii2" alt="License"></a>
  <a href="https://github.com/perfbaseorg/yii2/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/perfbaseorg/yii2/ci.yml?branch=main" alt="CI"></a>
  <img src="https://img.shields.io/badge/php-7.4%2B-blue" alt="PHP Version">
  <img src="https://img.shields.io/badge/yii-2.x-blue" alt="Yii Version">
</p>

This package is a thin adapter over [`perfbase/php-sdk`](https://packagist.org/packages/perfbase/php-sdk). Framework wiring lives here; transport and extension access stay in the shared SDK.

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
    'api_url' => 'https://ingress.perfbase.cloud',
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

## Development

Verify locally with:

```bash
composer install
composer run test
composer run phpstan
```

## Limitations

- Queue workers are out of scope for v1
- No profiler toolbar or Yii-specific UI
- Exit code capture for console commands depends on what the Yii 2 response object exposes

## Documentation

Full documentation is available at [perfbase.com/docs](https://perfbase.com/docs).

- **Docs**: [perfbase.com/docs](https://perfbase.com/docs)
- **Issues**: [github.com/perfbaseorg/yii2/issues](https://github.com/perfbaseorg/yii2/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## License

Apache-2.0. See [LICENSE.txt](LICENSE.txt).
