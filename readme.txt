=== WP Conversion Hub ===
Contributors: jeangalea
Tags: analytics, conversion tracking, google analytics, meta pixel, ecommerce
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

No-code bridge from WordPress plugin events to analytics and ad platforms. Open, extensible, no tag manager.

== Description ==

WP Conversion Hub connects the plugins you already run (WooCommerce, Easy Digital
Downloads, Gravity Forms, and more) to the analytics and advertising platforms you
report on, without a tag manager and without custom code.

Every tracked action becomes one normalized event that is routed to each platform
you enable. Server-side delivery is durable and retried; client-side pixels are
fired reliably even across checkout redirects.

Sources in this release:

* WooCommerce (purchase, add to cart, begin checkout, refund)
* Easy Digital Downloads (purchase, begin checkout)
* Gravity Forms (form submission)
* Contact Form 7 (form submission)
* Elementor Forms (form submission)
* WordPress core (search, login, registration)

Destinations in this release:

* Google Analytics 4 (client + Measurement Protocol)
* Plausible Analytics
* Fathom Analytics
* Umami
* PostHog
* Meta (Facebook) Ads — Pixel + Conversions API
* Google Ads
* Vireo Analytics (same-site, server-side)

New sources and destinations register through simple filters, so the list grows
without touching the core.

== Privacy ==

Advertising destinations default to denied until consent is granted. The plugin
honors Do Not Track and exposes a `wpch_consent` filter for consent-management
platforms. Enhanced conversions (hashing and sending first-party customer data)
are opt-in and off by default.

== Frequently Asked Questions ==

= Does this need a tag manager? =

No. Events are wired directly from your plugins to each platform.

= Where do delivery errors show up? =

Settings → Conversion Hub → Status lists recent delivery attempts, including
failures and retries. `wp conversion-hub status` shows the same from the CLI.

== Changelog ==

= 0.1.0 =
* Initial release: event hub, durable async delivery, six sources, seven destinations.
