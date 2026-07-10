# DreamHost Site Template (MVP Framework)

This is a minimalist PHP template framework developed originally for
db.**MarbleTrack3** and now used as a starter for DreamHost-based sites.
It includes a simple admin dashboard, a lightweight templating engine,
and a clean layout system with cookie-based authentication.

---

## 📂 Structure

- `prepend.php`: Bootstrap. Every page includes it. Registers the autoloader, builds `$config`, connects to the DB, and checks login state.
- `classes/`: PHP classes, autoloaded by namespace (`\Database\Base` → `classes/Database/Base.php`). **One class per file** — the autoloader cannot find a class whose filename does not match it.
- `classes/Config.php`: Your site's settings. **Not in the repo.** See *First install*.
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
   Everything above `wwwroot/` — including `classes/Config.php` and the bootstrap
   token — must stay unreachable from the web.

3. **Create the database** in the DreamHost panel, with a user granted on it.
   The application will create its own *tables*, but it will not create the database.

4. **Clone this repo locally**, then copy the whole tree to the server once:

   ```bash
   rsync -a --exclude .git ./ example:/home/dh_user/example.com/
   ```

   where `example` is an ssh `Host` you have defined in `~/.ssh/config`.

   ⚠️ The target directory may already contain DreamHost's own `.dh-diag → /dh/web/diag`
   symlink, which is owned by `root`. Do not try to remove it, and do not `git clone`
   over the top of it.

5. **Create `classes/Config.php`** from `classes/ConfigSample.php` and fill it in.
   Nothing runs without this file — `prepend.php` constructs `\Config` on line one of real work.

   | Property | Notes |
   |---|---|
   | `$site_title` | Shown in `<title>` and the front-page heading |
   | `$domain_name` | **Must equal the browser's `HTTP_HOST`**, or `DBExistaroo` refuses to run |
   | `$cookie_name` | Any name; distinguish it per site |
   | `$app_path` | Project root on the server, e.g. `/home/dh_user/example.com` |
   | `$dbHost` `$dbUser` `$dbPass` `$dbName` | Read directly by `Database\Base::getPDO()` |

   It holds a plaintext DB password, so it is gitignored. `chmod 600` it.

6. **Visit `/`.** With no `applied_DB_versions` table, `DBExistaroo` applies the `00` and
   `01` schemas, creating `applied_DB_versions`, `users`, and `cookies`. With `users`
   empty, every URL redirects to `/login/register.php`.

7. **Create the first admin.** Loading `/login/register.php` writes a random
   `bootstrap_token.txt` into `$app_path` — above the web root, `chmod 0600`. Read it over
   ssh and paste it into the form along with your username and password. Without the token
   the form is refused: a fresh vhost is found by scanners quickly, and the first visitor
   would otherwise be handed the admin account. The token file is deleted once the admin
   exists.

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
files. Prefixes `00` and `01` are applied automatically on a fresh install; everything after
is applied by an admin from `/admin/migrate_tables.php`. Applied versions are recorded in
`applied_DB_versions`. There is no automated rollback — undo by hand in phpMyAdmin.

---

## 📝 License

No license yet. Use it privately, tweak as needed. Attribution appreciated if it grows into something shared.

---

## ✨ Origin

Originally created during work on the **MarbleTrack3** stop-motion animation archive (June 2025). Designed for fun and minimal overhead.
