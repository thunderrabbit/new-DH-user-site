# DreamHost Site Template (MVP Framework)

This is a minimalist PHP template framework developed originally for
db.**MarbleTrack3** and now used as a starter for DreamHost-based sites.
It includes a simple admin dashboard, a lightweight templating engine,
and a clean layout system with optional authentication hooks.

---

## 📂 Structure

- `classes/Template.php`: Core rendering engine with support for string-capture (`grabTheGoods()`) and layout nesting.
- `wwwroot/`: Public-facing files. Place your admin pages here (`/admin/index.php`, etc).
- `templates/`: Your site’s UI. Includes layout wrappers and specific content templates.
- `css/styles.css`: Soft blue aesthetic with clean panels and nav bar.

---

## 🚀 Features

- Lightweight custom templating (no Twig, Blade, or Smarty)
- Admin dashboard scaffold
- Built-in layout nesting (`grabTheGoods()`)
- Styled with light blues and page panels
- Easily set up first (admin) user
- Uses cookies in DB for logins

---

## 🔧 Setup (with DreamHost Deployment)

1. **Set up a DreamHost new user account:**
   - Clone [thunderrabbit/new-DH-user-account](https://github.com/thunderrabbit/new-DH-user-account)

2. **Set your domain's Web Directory in DreamHost panel:**
   - e.g. `/home/dh_user/example.com/wwwroot`

3. **Clone this repo locally** into a working directory.

4. **Configure your deploy script:**
   - Edit `scp_files_to_dh.sh` to point to your DH username and target path.

5. **Clone this repo server-side** (optional but useful):
   - Clone to `/home/dh_user/example.com`
   - ⚠️ Be aware of DreamHost system links like `.dh-diag → /dh/web/diag` — **The symlink is owned by `root`**.

6. **Deploy with `scp_files_to_dh.sh`** or manually sync files.

7. Customize the templates:
   - `/templates/layout/admin_base.tpl.php`: Main layout
   - `/templates/admin/index.tpl.php`: Admin dashboard

8. Visit `/` to automagically create admin user in the freshly set up TABLEs `users` and `cookies`

---

## 📝 License

No license yet. Use it privately, tweak as needed. Attribution appreciated if it grows into something shared.

---

## ✨ Origin

Originally created during work on the **MarbleTrack3** stop-motion animation archive (June 2025). Designed for fun and minimal overhead.
