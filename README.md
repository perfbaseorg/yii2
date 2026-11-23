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

- PHP `>=7.4 <8.7`
- Yii2 `^2.0.45` (tested with 2.0.45 and 2.0.55)
- `perfbase/php-sdk` `^1.9`
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
    'profile_http_status_codes' => [...range(200, 299), ...range(500, 599)],
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
        'console' => ['queue/listen', 'queue/run', 'serve', 'serve/*'],
    ],
]
```

Notes:

- Values may be strings, as read from environment variables: `'true'` / `'false'` /
  `'1'` / `'0'` for booleans, digit strings for `timeout` and `flags`, and empty
  strings for "not set" (`proxy`, `api_url`, `app_version`)
- if `exclude` is set without a `console` key, the default console excludes still apply

- `sample_rate` must be numeric between `0.0` and `1.0`
- `profile_http_status_codes` defaults to `[...range(200, 299), ...range(500, 599)]`. Add codes such as `404` if you want to keep them, or set it to `[]` to disable HTTP trace submission entirely.
- `environment` is derived from `YII_ENV`, otherwise `production`
- `app_version` is application-defined

## Feature Flags

`flags` is passed to the Perfbase extension. The default,
`FeatureFlags::DefaultFlags`, enables everything except per-function CPU time and
memory allocation tracking. `0` (`FeatureFlags::AllFlags`) enables every feature
except the opt-in flags `UsePreciseClock` and `TrackCpuTime`.

Available flags (combine with `|`): `UsePreciseClock`, `TrackCpuTime`,
`TrackMemoryAllocation`, `TrackExceptions`, `TrackFileCompilation`, `TrackPdo`,
`TrackMongodb`, `TrackElasticsearch`, `TrackCaches`, `TrackHttp`, `TrackMail`,
`TrackFileOperations`, `TrackProc`, `TrackProcessList`, `TrackErrors`,
`TrackMagicMethods`, `TrackOpcache`, `TrackStreams` (PHP 8.6+).

```php
'flags' => \Perfbase\SDK\FeatureFlags::DefaultFlags | \Perfbase\SDK\FeatureFlags::TrackCpuTime,
```

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
- `user_id` when the application instantiated the `user` component during the request and an identity is logged in
- `hostname`
- `environment`
- `app_version`
- `php_version`

Behavior details:

- action naming prefers stable route/controller identifiers and never contains the query string
- `http_url` is the full request URL including the query string (see below)
- the profiler never instantiates the `user` component or auto-renews the identity itself, so it does not open sessions or run cookie-login queries
- HTTP traces are only submitted when the response status code is in `profile_http_status_codes`
- profiling is skipped when filters fail or the extension is unavailable

## URL and Privacy Behavior

`http_url` keeps the query string, because it often identifies the operation
(`/index.php?r=site/page`), but the values of credential-looking parameters are
replaced with `REDACTED` by the PHP SDK's `UrlSanitizer`: names such as `token`,
`access_token`, `password`, `api_key`, `signature`, `nonce`, `PHPSESSID` and
`email`. User info and fragments are dropped.

```text
https://example.com/ajax.php?method=example&token=secret
```

becomes:

```text
https://example.com/ajax.php?method=example&token=REDACTED
```

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

The console span name is `cli`; the command route (`controller/action`) is in
the `action` attribute. Earlier releases named this span `artisan`.

Long-running commands are excluded by default, because each would keep a single
trace open for the life of the process:

- yii2-queue workers `queue/listen` and `queue/run`. Queue commands are matched as
  `queue/<action>` whatever ID the queue component is registered under
  (`mail-queue/listen` matches too).
- the built-in development server, `serve`.

Add any other daemons of your own to `exclude.console`. Removing an entry from
`exclude.console` opts that command back in.

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
    'console' => ['queue/listen', 'queue/run', 'serve', 'serve/*', 'help'],
],
```

## Error Handling

The adapter fails open by design:

- invalid SDK configuration becomes a no-op
- missing extension becomes a no-op
- submit failures, including exceptions thrown by the SDK while submitting, do not break the Yii application

Behavior:

- `debug=false`: swallow and optionally log errors
- `debug=true`: rethrow adapter/runtime errors

## Runtime Architecture

Primary entry points:

- [`PerfbaseComponent.php`](src/PerfbaseComponent.php)
- [`PerfbaseBootstrap.php`](src/PerfbaseBootstrap.php)
- lifecycle classes in [`src/Lifecycle`](src/Lifecycle)
- support helpers in [`src/Support`](src/Support)

This package does not implement its own buffering, retry, or persistence layer.

## Development

Verify locally with:

```bash
composer install
composer run test
composer run phpstan
```

Yii 2.0.x depends on `bower-asset/*` packages that are only published on
asset-packagist. Without that repository Composer falls back to the unreleased
`2.2.x-dev` branch, so add it before installing:

```bash
composer config repositories.asset-packagist composer https://asset-packagist.org
```

## Limitations

- Queue jobs are not profiled individually, and queue worker commands are excluded by default
- No profiler toolbar or Yii-specific UI
- Exit code capture for console commands depends on what the Yii 2 response object exposes

## Documentation

Full documentation is available at [perfbase.com/docs](https://perfbase.com/docs).

- **Docs**: [perfbase.com/docs](https://perfbase.com/docs)
- **Issues**: [github.com/perfbaseorg/yii2/issues](https://github.com/perfbaseorg/yii2/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## License

Apache-2.0. See [LICENSE.txt](LICENSE.txt).
