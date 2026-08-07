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

## Configuration and security

Production settings are supplied through environment variables; copy
`.env.example` as a reference, but do not commit a real `.env` file. The web
server's document root must be `public/` so the database, uploads, logs, source,
and configuration files cannot be requested directly.

Before allowing public registrations:

- use HTTPS and set `APP_URL` to the exact HTTPS origin;
- store the database on persistent, access-controlled storage;
- retain uploaded artwork outside `public/` or move it to private object storage;
- configure encrypted backups and test restoring them;
- rotate or forward `storage/logs/database.jsonl`;
- keep PHP and Composer dependencies patched;
- enable multi-factor authentication for GitHub and hosting accounts.

Authentication uses regenerated session IDs, idle and absolute session expiry,
CSRF tokens, generic login failures, password-hash upgrades, and database-backed
login/registration throttling. Responses include a restrictive browser security
policy and private pages are marked `no-store`. Image uploads are checked by
size, detected MIME type, decoded dimensions, and pixel count before storage.

The unauthenticated deployment health check is available at
`/?route=health`. It returns only `{"status":"ok"}` and no user or system
details.

## Database monitoring

SQLite runs in write-ahead logging (WAL) mode with a five-second busy timeout.
Dashboard filtering and pagination are executed in SQL and supported by indexes
for each user's title, collection, and status fields.

Prepared queries are written as JSON Lines to
`storage/logs/database.jsonl`. Parameter values are deliberately excluded so
passwords, search terms, and user data are not copied into the log. Watch calls
while using the application with:

```bash
tail -f storage/logs/database.jsonl
```

To show only calls above the configured slow-query threshold:

```bash
jq 'select(.slow == true)' storage/logs/database.jsonl
```

The log directory is excluded from Git. Production hosting should rotate this
file or forward the JSON entries to its monitoring service.

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
