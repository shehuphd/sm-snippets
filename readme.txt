=== SM Snippets ===
Contributors: sm
Tags: snippets, header, footer, scripts, wordpress
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local, boring, reliable snippet control for WordPress.

== Description ==

SM Snippets is a small local snippet manager for WordPress. It lets a trusted admin paste HTML, CSS, JavaScript, or PHP and choose where it runs.

No cloud connection. No snippet marketplace. No remote sync. No Pro gating.

== Current features ==

* HTML, CSS, JavaScript, and PHP snippets.
* Site head, body open, site footer, admin head, admin footer, PHP plugins_loaded, PHP init, and shortcode/manual placements.
* Priority ordering.
* Page, path, post type, auth, environment, and admin-test-only targeting.
* Safe mode to pause all snippets.
* JSON import/export.
* Lightweight revision history.

== Safe mode ==

Safe mode can be enabled in the admin UI, via the `SM_SNIPPETS_SAFE_MODE` constant, or per request by appending `?sm-snippets-safe-mode=1` as an administrator.
