# Ticketech: PCU EdTech Helpdesk

Ticketech is a web-based EdTech helpdesk support and ticketing system for
Philippine Christian University. It provides public help-center screens and a
staff-facing helpdesk interface, backed by ticketing-related database tables
and initial department/category seed data. The current routes render the
interface; ticket submission, authentication, and staff actions still need to
be connected to application workflows.

## Requirements

- PHP 8.3 or newer with the `pdo_mysql` extension enabled
- Composer
- Node.js and npm
- XAMPP with its MySQL service

## Setup

Follow these steps on each computer that will run the app:

1. Install the requirements listed above. In particular, the PHP used by
   Composer must be version 8.3 or newer and have `pdo_mysql` enabled.
2. Clone the repository and enter the project directory:

```sh
git clone <repository-url> ticketech
cd ticketech
```

Replace `<repository-url>` with the repository's clone URL.

3. Start MySQL from the XAMPP Control Panel. MySQL must be listening on port
   `3306`, unless you configure a different port in `.env`. Start Apache too if
   you plan to create the database through phpMyAdmin.
4. Create a MySQL database named `ticketech` in phpMyAdmin, or run this in its
   SQL tab:

```sql
CREATE DATABASE ticketech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. From the project directory, run:

```sh
composer run setup
```

This installs the PHP and JavaScript dependencies, copies `.env.example` to
`.env` if needed, generates an application key, runs database migrations, and
builds frontend assets. The example database settings match a typical local
XAMPP installation: host `127.0.0.1`, port `3306`, database `ticketech`, user
`root`, and a blank password. If your MySQL credentials or port differ, update
the corresponding `DB_*` values in `.env` before setup. Setup does not overwrite
an existing `.env`.

Each computer's clone connects to the MySQL server configured in its own `.env`.
Local databases are not automatically shared between computers. To share tickets
and other data, configure each installation to connect to the same reachable
MySQL server and database.

If XAMPP MySQL stops immediately, another MySQL service may already be using
port `3306`. Check the XAMPP MySQL log and Windows services. Stop the other
service only if it is safe, or configure XAMPP and `.env` to use the same free
port. Do not stop a database service another application depends on.

After changing `.env`, clear cached configuration before retrying setup:

```sh
php artisan config:clear
composer run setup
```

## Run Locally

Start the Laravel server, queue worker, and Vite together:

```sh
composer run dev
```

Then open <http://localhost:8000>. `npm run dev` by itself only starts Vite; it
does not serve the Laravel application. In PowerShell, if direct `npm` commands
are blocked by the script execution policy, use `npm.cmd` instead.

## Project structure

- `app/` - application code and models
- `database/` - ticketing schema, factories, and seeders
- `resources/` - help-center and staff Blade views, CSS, and JavaScript
- `routes/` - public and staff interface routes
- `tests/` - feature and unit tests