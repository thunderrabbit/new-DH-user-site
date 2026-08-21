# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a minimalist PHP web application framework designed for DreamHost deployment. It's a custom template-based site with admin dashboard functionality, database migration system, and user authentication using cookies stored in the database.

## Key Architecture

### Core Components

- **Template System**: `classes/View/Template.php` - Custom templating engine with layout nesting via `grabTheGoods()` method
- **Database Layer**: `classes/Database/` - PDO-based database abstraction with migration system
- **Authentication**: `classes/Auth/` - Cookie-based login system with IP tracking
- **Configuration**: Must create `classes/Config/Config.php` from `classes/Config/ConfigSample.php` with actual database credentials
- **Bootstrap**: `prepend.php` - Application initialization, autoloader, and database checks
- **One class per file.** `Mlaphp\Autoloader` maps `\Database\EDuplicateKey` to
  `classes/Database/EDuplicateKey.php`. A second class in a file is unreachable until
  something else happens to load that file, and the failure looks like `You call that a file?`

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
  manual `rsync -a ./ <HOST>:<DEST_PATH>/` — **with `.git`, never `--exclude .git`**. The
  installed site is meant to be a real repo with its full history; `.git/` sits above the
  web root, so it is not web-readable.
- Copy the sample to `sync_files_to_<HOST>.sh`, where `<HOST>` is an ssh `Host` from
  `~/.ssh/config`. Those copies are gitignored, so the username, host, and key path stay
  out of the repo.
- It watches `close_write` **and** `moved_to`, because the Write tool (and emacs, and vim)
  saves by writing a temp file and renaming it into place. Watching `close_write` alone
  copies the temp file and never the real one.
- Transfer is `rsync --relative --secluded-args`, not `scp`: `--relative` creates missing
  remote directories, and `--secluded-args` keeps filenames away from the remote shell.

### Initial Setup

1. Copy `classes/Config/ConfigSample.php` to `classes/Config/Config.php` and fill it in. `$domain_name` must
   equal the browser's `HTTP_HOST` or `DBExistaroo::domainMatches()` aborts the request.
2. The database must already exist; the app creates only its own tables (checked by `DBExistaroo`)
3. First visit applies the `00` and `01` schemas, creating `applied_DB_versions`, `users`, `cookies`
4. The first admin is **not** created automatically. With `users` empty, every URL redirects to
   `/login/register.php`, which refuses to register anyone unless `bootstrap_token.txt` is present
   in `$app_path` (above the web root). The operator generates that file locally and rsyncs it up
   before deploying; the site never creates it. Deleted once the admin exists, and never consulted
   again afterwards. The public page must not disclose the token's path or the ssh username.

### Authentication Flow

- Session-based with database-stored cookies; the DB stores a sha256 hash, not the cookie value
- With no users, every URL redirects to `/login/register.php` (see the bootstrap token above)
- **Open registration is intentional, and now switchable.** The token gates only the first account
  (the admin), which is created even when registration is closed. Everyone after is governed by
  `$config->allow_registration`: `true` is the shipped default; `false` makes `/login/register.php`
  return 403 on GET and POST. Read it as `?? true` so a `Config.php` predating the property keeps its
  old behaviour instead of locking a live site's users out.
- IP address tracking via `Auth\IPBin` class
- Login state managed by `Auth\IsLoggedIn` class

### CSRF

- One token per PHP session (`Security\CSRFProtectaroo`), minted on first use and never
  consumed, so the back button and a second tab keep working. The first attempt at CSRF
  (`d557b9f..50c54f6`) used single-use per-form tokens and was killed as overkill; don't
  bring that back.
- **Enforced centrally in `prepend.php`**: every POST/PUT/PATCH/DELETE is rejected with 403
  unless it carries the token, before any page code or DB work runs. A handler does not
  need to check anything, and cannot forget to.
- Forms: write `<?= csrf_field() ?>` inside the `<form>`. `fetch()` callers: send the
  `X-CSRF-Token` header with `csrf_token()` (always `json_encode()` it into JS). See
  `templates/admin/migrate_tables.tpl.php`.
- `/login/register.php` skips `prepend.php`, so it starts the session and checks the token
  itself, and only on the open-registration path. The first-admin path is gated by the
  bootstrap token instead.
- The auth cookie is `SameSite=Lax` (see `Auth\CookieOptions`): `Strict` logged out anyone
  arriving by link from another site. Lax is the backstop; the token is the defence.

## Important Files

- `prepend.php` - Main application bootstrap (included by all pages)
- `wwwroot/index.php` - Site entry point with hardcoded DreamHost path
- `classes/Template.php` - Core templating functionality
- `classes/Database/DBExistaroo.php` - Database existence/migration manager
- `classes/Database/Base.php` - PDO connection and utility methods

## Development Notes

- Composer is dev-only (codeception, phpcs); the app itself is pure PHP with a custom
  autoloader and no runtime dependencies. `vendor/` is gitignored — populate it with
  the containerized composer command below. Nothing in `vendor/` deploys.
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
- No build step - pure PHP development (tests and style gates below)
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
- Database schemas automatically applied for prefixes "00" and "01"; later prefixes need an admin

## Standards gates (unit tests + phpcs) — run BEFORE you commit

The template carries the two commit-tier gates from `~/work/rob/standards-mcp`
(hermetic: read-only mount, no network, no credentials). Clones inherit them.

    ~/work/rob/standards-mcp/run-unit.sh  <this repo>   # Codeception Unit suite
    ~/work/rob/standards-mcp/run-phpcs.sh <this repo>   # PSR-12 style gate

Both must pass (or the change is not commit-ready). Setup and fixing:

- Populate `vendor/` once per clone (rootless docker, so container root writes
  as your user):

      docker run --rm -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app \
        composer:2 composer install --no-interaction

  `config.platform.php` pins resolution to php 8.3 (the runner's php) — don't
  remove it, or composer will resolve packages too new to parse.

- Style failures: auto-fix first, then re-check:

      docker run --rm --network none -v "$PWD":/app -w /app \
        standards-codeception-runner:php8.3 php -d memory_limit=1G vendor/bin/phpcbf

- The ruleset is `phpcs.xml` — PSR-12 with two excludes documented inline
  (pending manual cleanup). `templates/*.tpl.php` are out of scope.
- Unit tests live in `Tests/Unit/`; the suite bootstraps its own autoloader and
  must stay DB-free and session-free (see `Tests/Unit/_bootstrap.php`). A test
  that needs a live DB or endpoint does not belong in the Unit suite.

## Error Handling and Debugging

- `prepend.php` calls `DBExistaroo::checkaroo()`, which returns an array of errors
- The sentinel error `YallGotAnyMoreOfThemUsers` is what triggers the registration redirect
- All PHP errors are displayed to screen during development (`ini_set` calls at the top of `prepend.php`)
- Template system supports debug context via `?debug=1` parameter
