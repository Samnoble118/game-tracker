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
