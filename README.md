# DSE Heads-Up

A shared team **status board** inside the WordPress admin. Every user is listed
and can set their own status (**Inactive / In Progress**) and open a "tray"
describing the page and message they are working on — so the team has visibility
of current activity at a glance, without having to ask.

It appears at the top of the **Dashboard** home screen and as a **Heads-Up** item
in the admin menu. A live "Currently logged in" indicator shows who is online now.

This plugin ships with GitHub-based update checking (YahnisElsts Plugin Update
Checker, v5.7), so installed sites see an **"update available"** notice whenever a
newer GitHub Release is published — just like a wordpress.org plugin. The repo is
**public**, so no access tokens are required.

---

## What's inside

```
dse-heads-up/
├── dse-heads-up.php          ← main plugin file (header + GitHub repo URL here)
├── includes/
│   ├── render-page.php       ← the status-board UI
│   └── dashboard-home.php    ← embeds the board on the Dashboard home
├── assets/
│   ├── admin.css             ← styles (loaded inline)
│   └── admin.js              ← behaviour (loaded inline)
├── user-guide.txt            ← the in-app User Guide text (edit freely)
├── plugin-update-checker/    ← the update-checker library (do not edit)
├── readme.txt                ← WordPress-style readme
└── README.md                 ← this file
```

---

## Configuration

Everything site-specific lives in **`dse-heads-up.php`**:

- **Header block** — `Plugin Name`, `Description`, `Version`, etc.
- **GitHub repo URL** — the `buildUpdateChecker()` call. Point it at the public
  repo for this plugin.
- **Slug** — the 3rd argument to `buildUpdateChecker()` (`dse-heads-up`) must match
  the plugin folder name.

Two behaviours can be tuned with WordPress filters (no core edits):

- `uao_presence_seconds` — how long after activity a user still shows as
  "Currently logged in" (default 5 minutes).
- `uao_guide_file` — path to the User Guide text file (default `/user-guide.txt`).

---

## Installing on a WordPress site

- **Manual zip:** On GitHub use **Code → Download ZIP**, or download the ZIP from a
  **Release**. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the
  ZIP, and activate.
- Once installed, future updates are notify-only: WordPress shows them in
  **Dashboard → Updates** and on the Plugins screen when a newer Release exists.

---

## Releasing an update

WordPress only shows "update available" when the version number goes **up** *and* a
matching GitHub **Release** exists. Both must happen together.

1. **Bump the version** in `dse-heads-up.php` (the `Version:` header), e.g.
   `1.0.0` → `1.0.1`. Use [semantic versioning](https://semver.org).
2. **Commit and push.**
3. **Publish a GitHub Release** with a matching tag (`v1.0.1` or `1.0.1`).

To check immediately on a site: **Dashboard → Updates → Check again**.

### Common gotchas

- Version bumped but no Release published → no update appears.
- Release published but version header not bumped → no update appears.
- Tag lower than or equal to the installed version → no update appears.
- Keep the **slug** (folder name + 3rd argument) identical or the updater won't
  recognise the installed plugin.

---

## Never commit secrets

The repo is public. Do **not** commit API keys, passwords, tokens, or
client-specific credentials. Read any secret from a WordPress setting entered on
each site — never hardcode it.

---

## Updating the library

`plugin-update-checker/` is [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)
v5.7. To upgrade, download the newest release from that repo and replace the folder.
