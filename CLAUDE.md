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

### Getting the site onto the server

- The first copy of a new site is made **by git, in the directory the panel created**:
  `git init`, `git remote add origin git@github.com:<owner>/<name>.git`, `git fetch origin`,
  `git checkout -f -B master origin/master`. `git clone` refuses that directory (the panel
  put favicons and a root-owned `.dh-diag` symlink in `wwwroot/`), and nothing may delete
  `.dh-diag`. Never `rsync` the tree. The installed site is a real repo with its full
  history; `.git/` sits above the web root, so it is not web-readable.
- The server pulls over ssh with a deploy key (`~/.ssh/id_ed25519` on the server, added to
  the GitHub repo with write access). `bootstrap_token.txt` and `Config.php` are gitignored
  and go up by `scp`. The `setup-this-new-site` skill drives all of this.
- `DEST_PATH` is the **project root** on the server (the directory holding `wwwroot/`,
  `classes/`, `prepend.php`), not the web root.

### Initial Setup

1. Copy `classes/Config/ConfigSample.php` to `classes/Config/Config.php` and fill it in. `$domain_name` must
   equal the browser's `HTTP_HOST` or `DBExistaroo::domainMatches()` aborts the request.
2. The database must already exist; the app creates only its own tables (checked by `DBExistaroo`)
3. First visit applies the `00` and `01` schemas, creating `applied_DB_versions`, `users`, `cookies`
4. The first admin is **not** created automatically. With `users` empty, every URL redirects to
   `/login/register.php`, which refuses to register anyone unless `bootstrap_token.txt` is present
   in `$app_path` (above the web root). The operator generates that file locally and copies it up with `scp`
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
- Login state managed by `Auth\IsLoggedIn`, in two halves that must stay apart:
  - `resumeFromCookie()` runs on every request from `prepend.php`. Its only writes are
    expiring a cookie the DB no longer knows and `CookieRepository::touch()`, which stamps
    `last_access` at most hourly. It never looks at credentials.
  - `attemptPasswordLogin()` is called by `/login/index.php` on POST and nowhere else. A
    `username`/`pass` pair in any other form is just data.
  - Both end in the private `establishSession()` funnel (regenerate session id, drop the
    CSRF token, issue the remember-me cookie, record the user). **Any new way in — emailed
    sign-in links, OAuth — must call `establishSession()` and nothing else.**
  - Login failures get one generic message; naming which half was wrong enumerates usernames.
  - `attemptPasswordLogin()` returns `Auth\LoginResult` (`Success`, `BadCredentials`,
    `Throttled`). `Auth\LoginThrottle` counts failures in `login_attempts`: 5 per username or
    20 per IP inside 15 minutes and the password is not even checked. A success clears that
    username's count. A missing `login_attempts` table (site deployed before the migration)
    means no throttle, not no login.
  - Remember-me cookies live in `cookies` via `Database\CookieRepository`. Each row has an
    `expires_at` the lookup honours (the browser's expiry is not trusted), and issuing a cookie
    purges expired rows. `IsLoggedIn::revokeOtherSessions()` signs the user out everywhere but
    the current browser; **every password-change path must call it** (`/profile/` does).
  - `/profile/devices/` lists the user's live cookies (`IsLoggedIn::devices()`, one
    `Auth\Device` each, named by `Auth\DeviceLabel`) and signs out one
    (`revokeDevice()`, scoped to the logged-in user) or all others. The current browser gets no
    button there: `/logout/` revokes **every** session, not just this one.
  - Device names come from `cookies.user_agent`, added by `02_devices/alter_cookies_user_agent.sql`
    (applied from the admin dashboard). Until a site applies it, `CookieRepository` falls back
    to the old columns: logins keep working and devices show as "Unknown device".

### CSRF

- One token per PHP session (`Security\CSRFProtectaroo`), minted on first use and never
  consumed, so the back button and a second tab keep working. The first attempt at CSRF
  (`d557b9f..50c54f6`) used single-use per-form tokens and was killed as overkill; don't
  bring that back.
- **Enforced centrally in `prepend.php`**: every POST/PUT/PATCH/DELETE is rejected with 403
  unless it carries the token, before any page code or DB work runs. A handler does not
  need to check anything, and cannot forget to.
- Every `View\Template` is built with the protector (`new \View\Template(config: $config,
  csrf: $csrfProtect)`) and hands it to its template as `$csrf`. Forms: write
  `<?= $csrf->field() ?>` inside the `<form>`. `fetch()` callers: send the `X-CSRF-Token`
  header with `$csrf->getToken()` (always `json_encode()` it into JS). See
  `templates/admin/migrate_tables.tpl.php`.
- No `global` and no `$GLOBALS` (mg's MLAPHP step): a function takes what it needs as a
  parameter, a class as a constructor argument, a template as a `set()` variable.
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
- Uses `\PrintRob\Debug::printRob()` for debugging output
- Error display enabled in `prepend.php` for development
- Templates use `.tpl.php` extension and PHP template syntax

### Including prepend.php

All PHP files (except templates and prepend.php itself) must include prepend.php. Use this consistent pattern regardless of directory depth:

```php
<?php

declare(strict_types=1);

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Config\Config $config
 * @var \Auth\IsLoggedIn $is_logged_in
 */
```

List only the globals the page uses in the `@var` block, so PHPStan knows their types.

This leverages DreamHost's consistent `/home/username/domain.com/` path structure to dynamically find the project root.

## Database Schema Management

- Automatic application of schemas with prefixes "00" and "01". Since `e8b3b26` that is true
  on existing installs too: a file added to `00`/`01` later is applied on the next request,
  so code and its schema can ship together without an admin having to log in first. The
  admin dashboard is for `02` onward.
- Manual migration application via admin interface (`/admin/migrate_tables.php`)
- **Initial schemas must be named `create_*.sql`.** The auto-applied prefixes (`00`, `01`)
  bring the database into existence, so every file in them is a `CREATE TABLE`;
  `applyInitialSchemas()` globs for exactly that.
- **Later migrations are named freely** — they ALTER as often as they create.
  `Database\SchemaPath::resolve()` checks the path *shape* only
  (`^[0-9]{2}_[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+\.sql$`). It is a traversal guard on a string
  arriving in a JSON POST body, not a naming convention: keep the anchors and the dot-free
  character class, and don't read it as a rule about verbs.
- Files within a directory run alphabetically, so FK targets must sort before the tables that
  reference them. Order with a numeric prefix: `01_alter_users.sql`, `02_create_user_emails.sql`.
- Each schema directory represents a version (e.g., `00_bedrock/`, `01_gumdrop_cloud/`)
- Each **file** is tracked separately in `applied_DB_versions` as `<dir>/<filename>`. Renaming
  an already-applied file re-runs it, because that path string is the key.

## Cache-busting assets

DreamHost caches `.css`/`.js` for 30 days, so asset URLs carry a semver path segment
instead of `?v=N`: `/css/<?= \Version\Site::SEMVER ?>/styles.css`. `wwwroot/css/.htaccess`
strips a leading version segment (a digit, then any digits and dots:
`1/`, `0.1/`, `1.3.4/`) from any path that isn't a real file, so every version is served
the current file. The URL only has to change.

- `Version\Site` owns the site's `/css/` (and `/js/`, once there is one: copy the same
  `.htaccess` into it). A class constant, not a define, because `register.php` runs without
  `prepend.php`.
- Bump `SEMVER` in a bubble's BEGIN commit, and again for any later change to those assets.
- Never add `?v=` again.

## Common Development Tasks

### Local Development
- No build step - pure PHP development (tests and style gates below)
- PHP errors displayed on screen via `prepend.php` configuration
- Debug mode: Add `?debug=1` to any URL for additional debugging output
- Use `\PrintRob\Debug::printRob($variable)` for debugging (similar to `var_dump` but formatted)

### Deploying a branch
- `deploy_to_dh.sh` pushes the current branch to `origin`, then over ssh runs
  `git restore .` and `git pull` in the server's checkout. With `PULL_URL` empty the server
  pulls over https, so a public repo needs no GitHub key there; `PULL_URL="origin"` pulls
  over ssh with the server's deploy key.
- A clone fills in `DEST` and `DEST_PATH` and **commits** them. The script refuses to run
  while `DEST` is still `"example"`.
- **Only this template repo** (`new-DH-user-site`, checked out as base.robnugen.com) keeps
  `example` committed. Its filled-in copy is hidden with
  `git update-index --skip-worktree deploy_to_dh.sh`; `--no-skip-worktree` it, restore the
  placeholders, commit, then refill and re-hide. Never commit a real host here.
- It updates an installed site only. The first install is the git checkout above. A site
  whose server has a deploy key sets `PULL_URL="origin"`.

### Database Operations
- Visit `/admin/migrate_tables.php` to manually apply pending migrations
- Database schemas automatically applied for prefixes "00" and "01"; later prefixes need an admin

## Standards gates (unit tests + phpcs + PHPStan) — run BEFORE you commit

The template carries three commit-tier gates from `~/work/rob/standards-mcp`
(hermetic: read-only mount, no network, no credentials). Clones inherit them.

    ~/work/rob/standards-mcp/run-unit.sh  <this repo>   # Codeception Unit suite
    ~/work/rob/standards-mcp/run-phpcs.sh <this repo>   # PSR-12 style gate
    ~/work/rob/standards-mcp/run-phpstan.sh <this repo> # PHPStan, level in phpstan.neon

All three must pass (or the change is not commit-ready). A fourth check,
mutation testing, reports but does not gate: see "Mutation testing" below.
Setup and fixing:

- Populate `vendor/` once per clone (rootless docker, so container root writes
  as your user):

      docker run --rm -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app \
        composer:2 composer install --no-interaction

  New sites run PHP 8.5 or later: `composer.json` requires it, PHPStan analyses
  as 8.5 (`phpVersion` in `phpstan.neon`), and the hook and standards-mcp
  (`php_target` in its `repos.yaml`) use the php8.5 runner. Change all of them
  together. `config.platform.php` pins resolution to php 8.5 (the runner's php) —
  don't remove it, or composer will resolve packages too new to parse.

- Style failures: auto-fix first, then re-check:

      docker run --rm --network none -v "$PWD":/app -w /app \
        standards-codeception-runner:php8.5 php -d memory_limit=1G vendor/bin/phpcbf

- The ruleset is `phpcs.xml`: PSR-12 over `classes/`, `wwwroot/`, `prepend.php`
  and `Tests/`, with two narrow exclusions explained inline (side effects in
  `prepend.php`; Codeception's `_before`/`_after` names under `Tests/`). They are
  deliberate, not a backlog. `templates/*.tpl.php` are out of scope.
- **Every commit is checked.** `.githooks/pre-commit` runs phpcs on the staged
  content of each staged PHP file in `phpcs.xml`'s `<file>` scope (the whole
  scope when `phpcs.xml` itself is staged), and PHPStan over the whole staged
  tree whenever PHP, `phpstan.neon` or `composer.lock` is staged. Any error or
  warning blocks the commit. Enable it once per clone with
  `git config core.hooksPath .githooks`. Merges (`git close-bubble`) don't run
  it; `git commit --no-verify` is the emergency bypass, not a way to skip a fix.
  The hook doesn't run the unit suite; run that yourself.
- Lines over 120 characters are warnings phpcbf can't fix, and the hook blocks
  on warnings too: put parameters and array items one per line, and split long
  strings with concatenation.
- PHPStan runs at level 10, its maximum, with no baseline. Fix a finding rather
  than baselining it or lowering the level. Entry scripts name the globals they
  take from `prepend.php` in a `@var` block under the include. A finding PHPStan
  can't see past gets `// @phpstan-ignore <identifier> (reason)` on that one line,
  never a blanket ignore: the `catch` around `new \Config\Config()` is live
  because the autoloader throws, and `$config->allow_registration ?? true` is
  there for a Config.php older than the property. The one ignore in
  `phpstan.neon` is scoped to `wwwroot/` and the `$matches[1]` of the include
  idiom below.
- Every PHP file in scope starts with `declare(strict_types=1);`. Templates are
  out of scope: mostly HTML, and a declare must be a file's first statement.
  Superglobals, decoded JSON and PDO rows are `mixed`, so check `is_string()` or
  `is_numeric()` before passing them on. A field posted as `name[]=x` arrives as
  an array.
- Unit tests live in `Tests/Unit/`; the suite bootstraps its own autoloader and
  must stay DB-free and session-free (see `Tests/Unit/_bootstrap.php`). A test
  that needs a live DB or endpoint does not belong in the Unit suite. An
  in-memory SQLite (`LoginThrottleTest`) is fine: no server, no credentials, gone
  with the process. Classes that want that coverage keep their SQL portable.

### Mutation testing (Infection) — on trial, print-only

    ~/work/rob/standards-mcp/run-infection.sh <this repo>   # whole repo, a few seconds

Infection makes small changes ("mutants") to `classes/` and re-runs the Unit
tests that cover each changed line. A mutant the tests still pass on is a
**survivor**: a line a test runs but would not notice being wrong. Design and
trial result: `docs/superpowers/specs/2026-11-12-infection-mutation-testing-design.md`.

- **The hook prints, it does not block.** When a commit stages a file under
  `classes/`, `.githooks/pre-commit` mutates just those files and lists the
  survivors (about one extra second). Nothing Infection does or fails to do
  changes the commit's exit status. Blocking is Rob's decision after the trial.
- **A survivor gets one of four answers**, in this order of preference:
  1. a unit test that asserts the behaviour the mutant broke;
  2. simpler code, when the survivor shows a default, cast or branch does
     nothing (own commit, all gates still passing);
  3. `// @infection-ignore-all` on the line above the statement, with the reason
     in the same comment block, when no sensible test exists (a random pick, a
     default PHPStan level 10 requires);
  4. the mutator switched off in `infection.json5`, only when it is noise
     everywhere. The two visibility mutators are off for that reason.
  Never write a test whose only purpose is to kill a mutant.
- **No score targets.** No `--min-msi`. The output that matters is the list.
- **Only class methods and closures are mutated.** Plain functions and
  top-level script code (everything in `wwwroot/`) get no mutants, and code no
  Unit test runs is reported only with `--with-uncovered`.
- Extra arguments pass through: `--filter=classes/Foo.php` for one file,
  `--static-analysis-tool=phpstan` to let PHPStan judge each mutant as well
  (several times slower, so not in the hook).
- Infection needs pcov, which is in the runner image but switched off because
  it slows the other gates; the wrapper and the hook turn it on with
  `-d pcov.enabled=1`. `infection.json5` names `Tests/Unit/_bootstrap.php` so
  Infection can load classes through the project's autoloader.
- `composer.json` allows the `infection/extension-installer` plugin; it is what
  registers the Codeception adapter.

## Error Handling and Debugging

- `prepend.php` calls `DBExistaroo::checkaroo()`, which returns an array of errors
- The sentinel error `YallGotAnyMoreOfThemUsers` is what triggers the registration redirect
- All PHP errors are displayed to screen during development (`ini_set` calls at the top of `prepend.php`)
- Template system supports debug context via `?debug=1` parameter
