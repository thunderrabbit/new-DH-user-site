# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a minimalist PHP web application framework designed for DreamHost deployment. It's a custom template-based site with admin dashboard functionality, database migration system, and user authentication using cookies stored in the database.

## Key Architecture

### Core Components

- **Template System**: `classes/Template.php` - Custom templating engine with layout nesting via `grabTheGoods()` method
- **Database Layer**: `classes/Database/` - PDO-based database abstraction with migration system
- **Authentication**: `classes/Auth/` - Cookie-based login system with IP tracking
- **Configuration**: Must create `classes/Config.php` from `classes/ConfigSample.php` with actual database credentials
- **Bootstrap**: `prepend.php` - Application initialization, autoloader, and database checks

### Database Migration System

- Migrations stored in `db_schemas/` with numbered prefixes (00_, 01_, 02_, etc.)
- `DBExistaroo` class handles automatic schema application and tracking
- Applied migrations tracked in `applied_DB_versions` table
- Manual rollbacks via PHPMyAdmin (no automated rollback system)

### Project Structure

- `wwwroot/` - Public web directory (DreamHost web root points here)
- `templates/` - Template files (.tpl.php) organized by feature
- `classes/` - PHP classes with PSR-4-style autoloading via `Mlaphp\Autoloader`
- `db_schemas/` - Database migration files

## Development Workflow

### Editing against a live server

- `sync_files_to_dh_sample.sh` watches this working copy and copies each **saved file**
  to the server, one at a time. It is **not** a deploy script: it does not sync the tree,
  delete anything, or know about git. The first bulk copy of a new site is a separate,
  manual `rsync -a --exclude .git`.
- Copy the sample to `sync_files_to_<HOST>.sh`, where `<HOST>` is an ssh `Host` from
  `~/.ssh/config`. Those copies are gitignored, so the username, host, and key path stay
  out of the repo.
- It watches `close_write` **and** `moved_to`, because the Write tool (and emacs, and vim)
  saves by writing a temp file and renaming it into place. Watching `close_write` alone
  copies the temp file and never the real one.
- Transfer is `rsync --relative --secluded-args`, not `scp`: `--relative` creates missing
  remote directories, and `--secluded-args` keeps filenames away from the remote shell.

### Initial Setup

1. Copy `classes/ConfigSample.php` to `classes/Config.php` and configure database credentials
2. First visit to site triggers automatic schema creation and admin user setup
3. Database must exist before application runs (checked by `DBExistaroo`)

### Authentication Flow

- Session-based with database-stored cookies
- First-time setup redirects to admin user creation unless visiting `/login/register.php`
- IP address tracking via `Auth\IPBin` class
- Login state managed by `Auth\IsLoggedIn` class

## Important Files

- `prepend.php` - Main application bootstrap (included by all pages)
- `wwwroot/index.php` - Site entry point with hardcoded DreamHost path
- `classes/Template.php` - Core templating functionality
- `classes/Database/DBExistaroo.php` - Database existence/migration manager
- `classes/Database/Base.php` - PDO connection and utility methods

## Development Notes

- No package manager (composer/npm) - pure PHP with custom autoloader
- Debug mode available via `?debug=1` URL parameter
- Uses `print_rob()` function for debugging output
- Error display enabled in `prepend.php` for development
- Templates use `.tpl.php` extension and PHP template syntax

### Including prepend.php

All PHP files (except templates and prepend.php itself) must include prepend.php. Use this consistent pattern regardless of directory depth:

```php
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';
```

This leverages DreamHost's consistent `/home/username/domain.com/` path structure to dynamically find the project root.

## Database Schema Management

- Automatic application of schemas with prefixes "00" and "01"
- Manual migration application via admin interface (`/admin/migrate_tables.php`)
- Schema files must follow `create_*.sql` naming convention
- Each schema directory represents a version (e.g., `00_bedrock/`, `01_gumdrop_cloud/`)

## Common Development Tasks

### Local Development
- No build, test, or lint commands - pure PHP development
- PHP errors displayed on screen via `prepend.php` configuration
- Debug mode: Add `?debug=1` to any URL for additional debugging output
- Use `print_rob($variable)` function for debugging (similar to `var_dump` but formatted)

### Getting a saved file onto the server
- Copy `sync_files_to_dh_sample.sh` to `sync_files_to_<HOST>.sh`, set `DEST` and
  `DEST_PATH` in it, and leave it running in a terminal while you work. Your copy is
  gitignored; the sample is not.
- `DEST_PATH` is the **project root** on the server (the directory holding `wwwroot/`,
  `classes/`, `prepend.php`), not the web root.
- Do not deploy by pushing to a git remote. Commits are for history, not transport.

### Database Operations
- Visit `/admin/migrate_tables.php` to manually apply pending migrations
- Database schemas automatically applied for prefixes "00" and "01"
- First-time setup creates admin user automatically (or redirects to `/login/register.php`)

## Error Handling and Debugging

- Application bootstrap in `prepend.php:46` performs database existence checks
- Missing users table triggers admin registration flow (`prepend.php:48-59`)
- All PHP errors displayed to screen during development (`prepend.php:5-8`)
- Template system supports debug context via `?debug=1` parameter
