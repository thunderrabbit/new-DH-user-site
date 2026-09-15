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

   Now copy the whole tree to the server — **including `.git`**:

   ```bash
   rsync -a ./ example:/home/dh_user/example.com/
   ```

   where `example` is an ssh `Host` you have defined in `~/.ssh/config`. The token goes up
   with everything else — `rsync` does not read `.gitignore`.

   **Do not `--exclude .git`.** The server gets the full history, so the installed site is a
   real repo: you can `git log` it, `git diff` a hand-edit made in a panic at 2am, and see
   at a glance whether the running code matches a commit. A copy without history is a dead
   tree — the one thing you cannot reconstruct later by re-syncing.

   This costs nothing in exposure: `.git/` lives in the project root, which is **above** the
   web root, exactly like `classes/` and the token. Verify after the first sync that
   `https://example.com/.git/config` returns 404.

   ⚠️ The target directory may already contain DreamHost's own `.dh-diag → /dh/web/diag`
   symlink, which is owned by `root`. Do not try to remove it, and do not `git clone`
   over the top of it.

5. **Create `classes/Config/Config.php`** from `classes/Config/ConfigSample.php` and fill it in.
   Nothing runs without this file — `prepend.php` constructs `\Config` on line one of real work.

   | Property | Notes |
   |---|---|
   | `$site_title` | Shown in `<title>` and the front-page heading |
   | `$allow_registration` | May strangers sign up? Ships `true`. See *Who may register* |
   | `$domain_name` | **Must equal the browser's `HTTP_HOST`**, or `DBExistaroo` refuses to run |
   | `$cookie_name` | Any name; distinguish it per site |
   | `$app_path` | Project root on the server, e.g. `/home/dh_user/example.com` |
   | `$dbHost` `$dbUser` `$dbPass` `$dbName` | Read directly by `Database\Base::getPDO()` |

   It holds a plaintext DB password, so it is gitignored. `chmod 600` it.

6. **Visit `/`.** With no `applied_DB_versions` table, `DBExistaroo` applies the `00` and
   `01` schemas, creating `applied_DB_versions`, `users`, `cookies` (with server-side expiry), and
   `login_attempts`. With `users`
   empty, every URL redirects to `/login/register.php`.

7. **Create the first admin.** On `/login/register.php`, paste the token from step 4 along
   with the username and password you want. The gate exists because a fresh vhost is found
   by scanners quickly, and without it the first visitor would be handed the admin account.
   The page tells an anonymous visitor nothing about where the token lives.

   The server deletes its copy once the admin exists, and after that the value is never
   consulted again — so a later `rsync` that restores the file is inert.

8. **Delete your local copy** of `bootstrap_token.txt`. It has done its job.

   If you ever lose the token before creating the admin, generate a new one and re-sync it.
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
dashboard repeats it on every visit. Change the value and re-sync `Config.php` — no other step.

A `Config.php` written before this property existed also behaves as `true`, so upgrading an existing
site never silently locks its users out.

---

## ✏️ Editing an installed site

`sync_files_to_dh_sample.sh` watches your working copy and copies **each file you save** up
to the server. It is not a deploy script: it does not sync the tree, delete anything, or
know about git. The bulk copy in step 4 is a one-time job.

```bash
cp sync_files_to_dh_sample.sh sync_files_to_bc.sh   # 'bc' = an ssh Host in ~/.ssh/config
# edit DEST and DEST_PATH inside it
./sync_files_to_bc.sh                               # leave running in a spare terminal
```

Name the copy after the ssh `Host` it points at, so a glance at the filename tells you which
server you are about to write to. Your copies are gitignored; the sample is not. Keep the
username, hostname, and key path in `~/.ssh/config`, never in the script.

`DEST_PATH` is the **project root** on the server (the directory containing `wwwroot/`,
`classes/`, `prepend.php`), not the web root.

Do not deploy by pushing to a git remote. Commits are for history, not for transport.

---

## 🧪 Checks on every commit

`.githooks/pre-commit` runs phpcs on each staged PHP file and PHPStan over the whole staged
tree, both in Docker (`standards-codeception-runner:php8.3`, no network). Any finding blocks
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
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';
```

That regex leans on DreamHost's `/home/username/domain.com/` layout to find the project
root from any depth. Use it in every page; do not use relative includes.

Then see `wwwroot/admin/index.php` with `templates/admin/index.tpl.php` and
`templates/layout/admin_base.tpl.php` for the page → content → layout pattern.

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
