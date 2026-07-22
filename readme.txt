=== DSE Heads-Up ===
Contributors: dsemotion
Tags: users, status, activity, dashboard, team
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
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

= 1.3.0 =
* New: "Announce to everyone" tick box on your own card. Your Working on / Message becomes an overlay popup shown to every other user on any admin screen; each user clicks "I've seen this" to confirm. Editing your message while ticked re-announces it.
* New: "Admin Plus" user role (assign in Users -> edit user -> Role) with a CONTENT FREEZE toggle on their own Heads-Up card.
* Content Freeze locks the whole CMS for everyone without the Admin Plus role: content, media, comments, themes, plugins, settings and users cannot be changed until the freeze is lifted. A red banner shows on every admin page, and the freeze announcement popup cannot be dismissed by non-Admin-Plus users until the freeze is lifted.
* Any Admin Plus user can lift a freeze; developers can adjust the blocked capabilities with the `uao_frozen_caps` filter.
* Fix: AJAX now posts to a relative same-origin URL, so saves work when the site is opened via a different hostname (localhost:port, LAN IP, missing hosts entry). Failed saves show the HTTP status code.

= 1.0.1 =
* Renamed the "InActive" status label to "Inactive".
* Your own card is now pinned to the top of the board so you can set your status without scrolling.
* Clicking "Done" now saves and reloads the board so the header counts and ordering stay accurate.

= 1.0.0 =
* First release as DSE Heads-Up, packaged for GitHub-based updates.
