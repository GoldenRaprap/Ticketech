# Ticketech: PCU EdTech Helpdesk

Ticketech is a web-based EdTech helpdesk support and ticketing system for
Philippine Christian University. It provides public help-center screens and a
staff-facing helpdesk interface, backed by ticketing-related database tables
and initial department/category seed data. The current routes render the
interface; ticket submission, authentication, and staff actions still need to
be connected to application workflows.

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

## Project structure

- `app/` - application code and models
- `database/` - ticketing schema, factories, and seeders
- `resources/` - help-center and staff Blade views, CSS, and JavaScript
- `routes/` - public and staff interface routes
- `tests/` - feature and unit tests