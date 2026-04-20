=== Exit Intent Popup ===
Contributors: custom
Tags: popup, exit intent, newsletter, signup, conversion
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A lightweight exit-intent popup with configurable desktop and mobile triggers. Paste any signup form embed code into the content area.

== Description ==

Minimal, no-dependency popup plugin. You bring the content — typically an email signup form embed code from Mailchimp, ConvertKit, MailerLite, or your own HTML — and the plugin handles triggers, frequency capping, accessibility, and animation.

Features:

* Desktop exit-intent (mouse leaves top of viewport)
* Mobile scroll-up detection
* Optional mobile back-button interception
* Optional inactivity timer
* Optional timed fallback
* Once-per-session / per-day / per-week frequency capping
* Focus trap, ESC to close, overlay click to dismiss
* `prefers-reduced-motion` aware
* No external services or tracking

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload Plugin, or drop the unzipped folder into `/wp-content/plugins/`.
2. Activate through the Plugins menu.
3. Go to Settings → Exit Intent Popup and configure.
4. Paste your form embed code into the Content field.
5. Save, then append `?eip_preview=1` to any front-end URL to test.

== Frequently Asked Questions ==

= My form embed code isn't working =

Paste the raw HTML into the Content field. Script and iframe tags are preserved. Avoid wrapping them in any visual editor — this plugin uses a plain HTML textarea specifically to prevent that.

= How do I test without waiting for the frequency cap? =

Add `?eip_preview=1` to any front-end URL. This forces the popup to appear and skips the cookie check.

= Will this work with my theme? =

Yes. Styles are scoped to `.eip-*` classes. The modal inherits sensible defaults (white background, dark text) and you can tweak width, radius, overlay darkness, and accent colour from the settings page.

== Changelog ==

= 1.0.0 =
* Initial release.
