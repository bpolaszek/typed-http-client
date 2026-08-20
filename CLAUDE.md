# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`bentools/typed-http-client` — a small library (PHP >= 8.2) decorating Symfony's HTTP Client so that responses hydrate into typed objects via `getData()`. Only hard dependency: `symfony/contracts`. `symfony/http-client` is dev-only (used as default transport and in tests).

## Commands

```bash
composer ci:check                 # everything CI runs: composer validate + phpstan + cs-fixer + tests w/ 100% coverage
composer tests:run                # pest
composer types:check              # phpstan, level max on src/
composer style:check              # php-cs-fixer dry-run (@PhpCsFixer ruleset, risky allowed)
composer style:fix                # php-cs-fixer fix
vendor/bin/pest --filter 'name'   # run a single test (matches Pest `it(...)` descriptions)
```

CI (`.github/workflows/ci.yaml`) enforces **100% test coverage** (`pest --coverage --min=100`). Any new code path must be covered or `ci:check` fails.

## Architecture

Everything hinges on one generic parameter `T` — the type produced from a response — threaded through the whole API with PHPStan `@template` annotations:

- `TypedHttpClient<T>` (implements `HttpClientInterface`) decorates an inner client. `request()` wraps the inner response into a `DataAwareResponse<T>`; `stream()` uses a `WeakMap` to yield chunks keyed by the *outer* (typed) response instead of the inner one, and refuses foreign responses (`LogicException`).
- `DataFactoryInterface<T>` is the single extension point: `__invoke(ResponseInterface, bool $throw, RequestContext): T`. Adapters: `ClosureDataFactory` (wraps user closures), `ArrayDataFactory` (default fallback, returns `toArray()`).
- `DataAwareResponse<T>` decorates the inner response (all `ResponseInterface` methods proxied) and adds `getData(bool $throw = true)` — lazy and memoized (factory runs at most once).
- `RequestContext` (method, url, options) is captured at request time and handed to factories, so one factory can discriminate by endpoint.

The word **"inner"** consistently designates the decorated Symfony object (`innerClient`, `innerResponse`); keep that convention.

## Conventions

- Tests are written with **Pest** (`describe`/`it`), one file per class, shared helpers in `tests/Pest.php` (`todoPayload()`, `innerResponse()`). Fixtures (`Todo`, `TodoFactory`) live in `tests/Fixtures/`.
- New classes: `final` (and `readonly` where possible), `declare(strict_types=1)`, full generics annotations — phpstan runs at level max.
- `sandbox.php` is a manual playground hitting jsonplaceholder.typicode.com; it is not part of the test suite.
- The `$throw` flag follows Symfony's HttpClient convention and must be forwarded to the inner response / factory, never swallowed.
