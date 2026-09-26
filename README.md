# Ticketech Help Desk

Ticketech is the starting point for a ticketing help desk system. The current
project is a Laravel 13 scaffold; help desk features and the product interface
have not been implemented yet.

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js and npm

## Setup

```sh
composer run setup
npm run dev
```

The setup script installs PHP and JavaScript dependencies, creates the local
environment file and application key, and runs database migrations. The example
environment uses SQLite.

## Main folders

- `app/` - application code
- `database/` - migrations, factories, and seeders
- `resources/` - Blade views, CSS, and JavaScript
- `routes/` - HTTP route definitions
- `tests/` - feature and unit tests