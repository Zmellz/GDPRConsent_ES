=== GDPR Consent Auditor ===
Contributors: seniorwpdev
Tags: gdpr, privacy, cookies, consent, eprivacy, lgpd, ccpa, cookie banner, cookie consent, analytics
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Technical audit tool for privacy, cookies and third-party scripts. Generates a privacy report and lets you build a configurable consent banner with granular per-category acceptance.

== Description ==

**GDPR Consent Auditor** is a technical assistive tool for website administrators. It scans the WordPress site, detects plugins, scripts, cookies, iframes and known third-party services, generates a technical privacy report, and ships a configurable consent banner with granular per-category acceptance.

This plugin does **not** guarantee legal compliance. Its purpose is to provide an honest, transparent, privacy-by-design tool that helps the administrator audit the site and offer visitors a real choice over the cookies and scripts that run in their browser.

Key features:

* Admin menu with Dashboard, Scan results, Consent banner, Consent logs, Legal templates and Settings.
* Scanner that detects installed plugins, active plugins, active theme, registered scripts and styles, server-side cookies, and 10 well-known third-party services (Google Analytics, GTM, Meta Pixel, YouTube, Vimeo, Google Maps, Hotjar, TikTok Pixel, LinkedIn Insight, reCAPTCHA).
* Heuristic classifier that maps every detected element to a GDPR-style category (Necessary / Preferences / Statistics / Marketing) with a recommended action and a suggested legal basis.
* Configurable consent banner with three layouts (bottom bar, centered modal, floating widget), per-category granular preferences, Accept-all / Reject-all / Configure buttons, and a cookie policy link.
* Script blocker that wraps external tracking scripts and iframes with `type="text/plain"` and `data-gdpr-ca-category`, then reactivates them client-side when consent is granted.
* Consent log table with pseudonymized IP (SHA-256 + site-unique salt), User-Agent, action, accepted categories, and consent version. Configurable retention period.
* Google Consent Mode v2 integration (ad_storage, analytics_storage, functionality_storage, ad_personalization, ad_user_data).
* Shortcode `[gdpr_ca_revoke]` to render a "Cookie preferences" link anywhere on the site so visitors can revoke or change their consent at any time.
* Editable legal templates for cookie policy, privacy notice, category descriptions and banner message — all marked as orientative and to be reviewed by a qualified legal professional.
* Translation-ready (text domain: `gdpr-consent-auditor`).
* Export the technical report in three formats: plain text (.txt), CSV (.csv) and JSON (.json) for integration with downstream tooling.
* Optional dynamic-script blocker: a tiny JS shim injected at the top of `<head>` intercepts `document.createElement('script')` and `Element.prototype.setAttribute` so that scripts added dynamically by GTM, Meta Pixel loaders or similar are also wrapped as `type="text/plain"` until consent is granted.
* Meta Pixel Consent Mode integration: `fbq('consent', 'revoke')` is the default state; when the visitor accepts the Marketing category, `fbevents.js` is loaded and `fbq('init', pixelId)` + `fbq('track', 'PageView')` are fired.
* PHPUnit test suite (7 test classes, 50+ assertions) covering the Activator, helpers, Scanner, Classifier, Consent Manager, Script Blocker, Report Generator and Legal Templates.

= Security =

The plugin follows the WordPress Coding Standards and applies the following security practices:

* Capability checks via `current_user_can('manage_options')` on every admin action.
* Nonces on all admin AJAX endpoints and on the public consent REST endpoint.
* Sanitization (`sanitize_text_field`, `sanitize_textarea_field`, `wp_kses_post`) on every input.
* Escaping (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`) on every output.
* Prepared SQL statements (`$wpdb->prepare()`) for all database queries.
* REST permission callback set explicitly on every route.
* Pseudonymization of identifiers by default (SHA-256 hashing of IP + User-Agent).

== Installation ==

1. Upload the entire `gdpr-consent-auditor` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. A new top-level menu **GDPR Auditor** appears in the admin sidebar.
4. Go to **GDPR Auditor → Settings** to configure the consent log storage, script blocking and Google Consent Mode v2.
5. Go to **GDPR Auditor → Consent banner** to configure the banner layout, texts and category labels.
6. Go to **GDPR Auditor → Legal templates** to review and edit the cookie policy, privacy notice and other texts.
7. Go to **GDPR Auditor → Dashboard** and click **Run scan now** to generate the first technical report.

== Frequently Asked Questions ==

= Does this plugin make my site GDPR-compliant? =

No. The plugin is a technical assistive tool. Compliance depends on many factors (your data flows, contracts with processors, the information you provide to users, etc.) that the plugin cannot evaluate. Have your legal texts and consent flow reviewed by a qualified professional.

= Why are some cookies not detected? =

The server-side scanner can only see cookies set during the PHP request. Cookies set client-side by third-party JS (e.g. `_ga`, `_fbp`) are detected by matching the registered script URLs against a known services list. The banner JS complements this by activating or blocking scripts based on the visitor's choice.

= How do I block a script the plugin did not detect? =

Go to **GDPR Auditor → Consent banner → Manual script blocking rules**, add a substring or pattern (e.g. `googletagmanager`), and pick a category. Any script whose handle or URL contains the pattern will be wrapped as `type="text/plain"` until the visitor accepts that category.

= How do visitors revoke consent? =

Add the shortcode `[gdpr_ca_revoke label="Cookie preferences"]` to any page, post, footer or widget. Clicking the link clears the consent cookie and reloads the page so the banner appears again.

== Screenshots ==

1. Dashboard with summary cards and the technical report.
2. Scan results table grouped by section.
3. Consent banner settings — layout, texts, categories.
4. Consent logs with pseudonymized identifiers.
5. Legal templates editor with disclaimer.
6. Front-end consent banner (bottom bar layout).
7. Front-end preferences panel.

== Changelog ==

= 1.0.0 =
* Initial release.
* Scanner, classifier, report generator.
* Configurable consent banner (bar, modal, widget).
* Granular per-category preferences.
* Script and iframe blocking.
* Consent log table with pseudonymization.
* Google Consent Mode v2 integration.
* Editable legal templates.
* Revocation shortcode.

= 1.1.0 =
* Export the technical report as CSV and JSON (in addition to plain text).
* Meta Pixel Consent Mode integration: `fbq('consent', 'revoke'/'grant')` with optional Pixel ID.
* Optional dynamic-script blocker shim that intercepts `document.createElement('script')` and `Element.prototype.setAttribute` to wrap scripts added dynamically by JavaScript.
* PHPUnit test suite (composer.json + phpunit.xml.dist + tests/phpunit/) covering 7 classes and 50+ assertions.

== Upgrade Notice ==

= 1.0.0 =
First public release.

= 1.1.0 =
Adds CSV/JSON export, Meta Pixel Consent Mode, dynamic-script blocker, and PHPUnit test suite.
