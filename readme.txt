=== DSE Heads-Up ===
Contributors: dsemotion
Tags: users, status, activity, dashboard, team
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A shared team status board inside the WordPress admin. Every user is listed and can set their own task status and open a tray describing the page and message they are working on, giving the team visibility of current activity at a glance.

== Description ==

DSE Heads-Up lists every registered user as a status card. Each person sets their own status:

* Inactive (grey) — the default for everyone.
* In Progress (amber) — opens their tray so the whole team can see what they are on.

When a user selects In Progress, their card expands into an open "tray" with a dark header showing the page they are Working on and a free-text Message. A green "Currently logged in" indicator shows who is online right now, and each card shows the user's Last Log In time.

Everyone can read each other's open trays; only the user themselves can change their own status, page and message. The board appears at the top of the WordPress Dashboard home screen and is also available from the "Heads-Up" admin menu item. A built-in User Guide (editable via /user-guide.txt) explains it to the team.

Updates are delivered from a public GitHub repository via the bundled Plugin Update Checker.

== Installation ==

1. Upload the `dse-heads-up` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the "Plugins" menu.
3. Open the Dashboard (or the "Heads-Up" menu item) and set your status.

== Changelog ==

= 1.2.2 =
* Renamed the "Super Admin" role to "Admin Plus" to avoid confusion with the WordPress multisite Super Admin. The role slug is unchanged, so existing role assignments carry over automatically (the stored label is migrated on the next page load).

= 1.2.1 =
* The Content Freeze announcement popup can no longer be dismissed by non-Super-Admins: its confirm button is removed and the popup stays on every admin screen until a Super Admin lifts the freeze. Regular announcements are unaffected.

= 1.2.0 =
* New: "Super Admin" user role (assign in Users -> edit user -> Role). Super Admins get a CONTENT FREEZE toggle on their own Heads-Up card.
* Content Freeze locks the whole CMS for everyone without the Super Admin role: content, media, comments, themes, plugins, settings and users cannot be changed until the freeze is lifted. Everyone can still log in and use the Heads-Up board.
* While frozen, a red banner appears on every admin page and the freeze is auto-announced as a popup that every user must confirm.
* Any Super Admin can lift a freeze; developers can adjust the blocked capabilities with the `uao_frozen_caps` filter.

= 1.1.1 =
* Fix: AJAX saves and announcement confirmations failed when the site was opened via a different hostname than the configured Site URL (e.g. localhost:port, LAN IP, or a machine without the .local hosts entry). All plugin AJAX now posts to a relative same-origin URL.
* Failed saves now show the HTTP status (e.g. "Error saving (HTTP 403)") instead of a generic message, and the announcement popup shows an error next to the confirm button.

= 1.1.0 =
* New: "Announce to everyone" tick box on your own card. When ticked, your Working on / Message is shown as an overlay popup to every other user on any admin screen.
* Each user must click "I've seen this" to confirm and dismiss the popup; confirmations are stored per user.
* Editing your message while the announce box is ticked re-announces it, so everyone sees the updated version again.
* Untick the box to withdraw the announcement for everyone.

= 1.0.1 =
* Renamed the "InActive" status label to "Inactive".
* Your own card is now pinned to the top of the board so you can set your status without scrolling.
* Clicking "Done" now saves and reloads the board so the header counts and ordering stay accurate.

= 1.0.0 =
* First release as DSE Heads-Up, packaged for GitHub-based updates.
