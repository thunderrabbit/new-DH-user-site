# DreamHost Site Template (MVP Framework)

This is a minimalist PHP template framework developed originally for
db.**MarbleTrack3** and now used as a starter for DreamHost-based sites.
It includes a simple admin dashboard, a lightweight templating engine,
and a clean layout system with cookie-based authentication.

---

## 📂 Structure

- `prepend.php`: Bootstrap. Every page includes it. Registers the autoloader, builds `$config`, connects to the DB, and checks login state.
- `classes/`: PHP classes, autoloaded by namespace (`\Database\Base` → `classes/Database/Base.php`). **One class per file** — the autoloader cannot find a class whose filename does not match it.
- `classes/Config/Config.php`: Your site's settings. **Not in the repo.** See *First install*.
- `classes/Template.php`: Rendering engine with string-capture (`grabTheGoods()`) and layout nesting.
- `templates/`: Your site's UI. Layout wrappers plus content templates (`.tpl.php`).
- `wwwroot/`: Public web root. Put your pages here (`/admin/index.php`, etc).
- `wwwroot/css/styles.css`: Soft blue aesthetic with clean panels and nav bar.
- `db_schemas/`: Numbered schema directories. `00_bedrock/` and `01_gumdrop_cloud/` are applied automatically on first run.

---

## 🚀 Features

- Lightweight custom templating (no Twig, Blade, or Smarty)
- Admin dashboard scaffold
- Built-in layout nesting (`grabTheGoods()`)
- Styled with light blues and page panels
- Token-gated creation of the first (admin) user
- Login cookies stored hashed in the DB

---

## 🔧 First install

1. **Create a DreamHost user and domain**, if you have not already:
   see [thunderrabbit/new-DH-user-account](https://github.com/thunderrabbit/new-DH-user-account).
   Subdomains and users are created in the DreamHost panel, not from the command line.

2. **Point the domain's Web Directory at `wwwroot`** in the DreamHost panel:
   e.g. `/home/dh_user/example.com/wwwroot`.
   Everything above `wwwroot/` — including `classes/Config/Config.php` and the bootstrap
   token — must stay unreachable from the web.

3. **Create the database** in the DreamHost panel, with a user granted on it.
   The application will create its own *tables*, but it will not create the database.

4. **Clone this repo locally** and turn on its commit checks with
   `git config core.hooksPath .githooks` (see *Checks on every commit*). Then generate the
   setup token that will let you — and only you — create the first admin user:

   ```bash
   openssl rand -hex 16 > bootstrap_token.txt
   chmod 600 bootstrap_token.txt
   cat bootstrap_token.txt          # keep this on screen; you will paste it in step 7
   ```

   The file is gitignored. It sits in the project root, which is **above** the web root, so
   it is never web-readable. The site does not create this file and cannot be registered
   without it.

   Now give the server its copy, **by git**. The panel has already made
   `/home/dh_user/example.com/wwwroot/` (with two empty favicons and a root-owned
   `.dh-diag` symlink in it), so `git clone` refuses the directory. Instead, on the
   server:

   ```bash
   cd ~/example.com
   git init
   git remote add origin git@github.com:owner/example.com.git
   git fetch origin
   git checkout -f -B master origin/master
   ```

   The server pulls over ssh with a **deploy key**: generate one there
   (`ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519`), add its `.pub` to the repo on
   GitHub as a deploy key with write access, and trust GitHub's host keys in
   `~/.ssh/known_hosts`. Nothing is deleted, so `.dh-diag` is never touched; it and
   the favicons are in `.gitignore`.

   The server gets the full history, so the installed site is a real repo: you can
   `git log` it, `git diff` a hand-edit made in a panic at 2am, and see at a glance
   whether the running code matches a commit. `.git/` lives in the project root,
   which is **above** the web root, exactly like `classes/` and the token. Verify
   after the first checkout that `https://example.com/.git/config` returns 404.

   The token is gitignored, so copy it up by hand:

   ```bash
   scp bootstrap_token.txt example:example.com/
   ```

   where `example` is an ssh `Host` you have defined in `~/.ssh/config`.

5. **Create `classes/Config/Config.php`** from `classes/Config/ConfigSample.php` and fill it in.
   Nothing runs without this file — `prepend.php` constructs `\Config` on line one of real work.

   | Property | Notes |
   |---|---|
   | `$site_title` | Shown in `<title>` and the front-page heading |
   | `$allow_registration` | May strangers sign up? Ships `true`. See *Who may register* |
   | `$domain_name` | **Must equal the browser's `HTTP_HOST`**, or `DBExistaroo` refuses to run |
   | `$cookie_name` | Any name; distinguish it per site. The browser sees `__Host-<name>` |
   | `$app_path` | Project root on the server, e.g. `/home/dh_user/example.com` |
   | `$dbHost` `$dbUser` `$dbPass` `$dbName` | Read directly by `Database\Base::getPDO()` |

   It holds a plaintext DB password, so it is gitignored: write it locally, `scp` it to
   `example:example.com/classes/Config/`, and `chmod 600` it there.

6. **Visit `/`.** With no `applied_DB_versions` table, `DBExistaroo` applies the `00` and
   `01` schemas, creating `applied_DB_versions`, `users`, `cookies` (with server-side expiry), and
   `login_attempts`. With `users`
   empty, every URL redirects to `/login/register.php`.

7. **Create the first admin.** On `/login/register.php`, paste the token from step 4 along
   with the username and password you want. The gate exists because a fresh vhost is found
   by scanners quickly, and without it the first visitor would be handed the admin account.
   The page tells an anonymous visitor nothing about where the token lives.

   The server deletes its copy once the admin exists, and after that the value is never
   consulted again — so a later `scp` that restores the file is inert.

8. **Delete your local copy** of `bootstrap_token.txt`. It has done its job.

   If you ever lose the token before creating the admin, generate a new one and `scp` it up.
   Nothing on the server needs to be cleaned up first.

### Who may register

The token gates the **first** account only, and that account is the admin. It is created even when
registration is closed — otherwise a closed site could never be set up.

Everyone after that is governed by `$allow_registration` in `classes/Config/Config.php`:

| Value | `/login/register.php` |
|---|---|
| `true` | Anyone may create a `role='user'` account, with no token and no login. Ships this way |
| `false` | 403, on both GET and POST |

The moment you create the first admin, the page tells you which way it is set, and the admin
dashboard repeats it on every visit. Change the value and `scp` `Config.php` up again — no other step.

A `Config.php` written before this property existed also behaves as `true`, so upgrading an existing
site never silently locks its users out.

---

## 🚀 Deploying a branch

`deploy_to_dh.sh` pushes the current branch to `origin`, then pulls it on the server over
ssh (after a `git restore .` there). The server pulls over https, so a public repo needs
no GitHub key on the DreamHost user.

```bash
# edit DEST and DEST_PATH inside it, commit that, then:
./deploy_to_dh.sh
```

In your own site, commit the filled-in script. Only the `new-DH-user-site` template keeps
`DEST="example"` committed (it hides its own filled-in copy with
`git update-index --skip-worktree deploy_to_dh.sh`), so clones don't inherit its host.
It only updates an installed site; the first copy is the git checkout in step 4. A site
whose server has a deploy key sets `PULL_URL="origin"` so the pull goes over ssh.

---

## 🧪 Checks on every commit

`.githooks/pre-commit` runs phpcs on each staged PHP file and PHPStan over the whole staged
tree, both in Docker (`standards-codeception-runner:php8.5`, no network). Any finding blocks
the commit.

Git never turns on a repo's hooks by itself, so **run this once in every clone you commit
from**:

```bash
git config core.hooksPath .githooks
```

`git config core.hooksPath` should then print `.githooks`. The hook needs Docker running and
`vendor/` populated (the composer command is in `CLAUDE.md`). Merges don't run it, and
`git commit --no-verify` skips it in an emergency.

---

## 🧩 Adding a page

A page is a PHP file in `wwwroot/` that includes `prepend.php`, checks login, fills a
content template, and wraps it in a layout:

```php
<?php

declare(strict_types=1);

preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Config\Config $config
 * @var \Auth\IsLoggedIn $is_logged_in
 */
```

That regex leans on DreamHost's `/home/username/domain.com/` layout to find the project
root from any depth. Use it in every page; do not use relative includes.

`declare(strict_types=1);` goes first in every PHP file except templates, so a wrong scalar
type is an error rather than a silent conversion. The `@var` block names the `prepend.php`
globals the page uses (`$config`, `$is_logged_in`, `$mla_request`, `$mla_database`,
`$dbExistaroo`) so PHPStan knows their types. A global the page uses but doesn't list is
reported as possibly undefined, and the commit check blocks it.

Then see `wwwroot/admin/index.php` with `templates/admin/index.tpl.php` and
`templates/layout/base.tpl.php` for the page → content → layout pattern. Every page
uses that one layout, and it draws the one menu in `templates/partials/menu.tpl.php`.
Hand the layout `username` ('' when logged out) and `is_admin` so the menu can choose
its links.

---

## 🗄️ Database migrations

Add a numbered directory under `db_schemas/` (e.g. `02_widgets/`) holding `create_*.sql`
files. Prefixes `00` and `01` are applied automatically, on a fresh install and on the next
request of an existing one; everything after is applied by an admin from
`/admin/migrate_tables.php`. Applied versions are recorded in
`applied_DB_versions`. There is no automated rollback — undo by hand in phpMyAdmin.

---

## 📝 License

No license yet. Use it privately, tweak as needed. Attribution appreciated if it grows into something shared.

---

## ✨ Origin

Originally created during work on the **MarbleTrack3** stop-motion animation archive (June 2025). Designed for fun and minimal overhead.
