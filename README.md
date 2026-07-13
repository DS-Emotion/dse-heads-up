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

## Disclaimer

This plugin is provided "as is", without warranty of any kind, express or
implied. The authors and DS.Emotion accept no responsibility or liability for
any issues, damage, data loss, or disruption that may arise from installing,
using, or relying on this plugin. You install and use it entirely at your own
risk.

---

