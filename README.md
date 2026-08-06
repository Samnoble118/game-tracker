# Game Tracker

A namespaced, object-oriented PHP application for tracking your game collection,
platforms, play status, and current progress.

## Requirements

- PHP 8.2+
- Composer
- PDO SQLite extension

## Getting started

```bash
composer install
php -S localhost:8000 -t public
```

Open <http://localhost:8000> in your browser.

## Project structure

- `src/Domain` contains the core game model, status enum, and repository contract.
- `src/Application` contains use-case services.
- `src/Infrastructure` contains SQLite persistence.
- `src/Core` contains lazy shared infrastructure.
- `public` is the web entry point.
- `tests` contains automated tests.

`Database` is deliberately the only singleton. Its PDO connection is created
only on first use. Domain objects and application services use normal dependency
injection so they remain easy to test and replace.

## Versioning and releases

Game Tracker uses [Semantic Versioning](https://semver.org/). Released versions
are identified by Git tags such as `v1.0.0` and `v1.0.1`, then published as
GitHub Releases. Changes planned for the next release are collected in
[`CHANGELOG.md`](CHANGELOG.md) under `Unreleased`.

The version is intentionally not hardcoded in `composer.json`: Composer derives
package versions from Git tags. Create a release only from an reviewed commit on
`main`.

```bash
git tag -a v1.0.0 -m "Game Tracker 1.0.0"
git push origin v1.0.0
```
