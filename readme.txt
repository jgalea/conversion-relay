=== Conversion Relay ===
Contributors: jeangalea
Tags: conversion tracking, analytics, google analytics, meta pixel, woocommerce
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress conversions to the analytics and ad platforms you report on. No tag manager, no code.

== Description ==

Conversion Relay connects the plugins you already run to the analytics and
advertising platforms you report on, without a tag manager and without pasting
snippets into your theme.

Every tracked action becomes one normalized event that is routed to each platform
you enable. Server-side delivery is durable and retried in the background, so a
slow request never blocks checkout. Client-side pixels are fired reliably even
when a conversion finishes on a later page, such as a payment-gateway return.

**Sources** (auto-detected when the plugin is active):

* WooCommerce (purchase, add to cart, begin checkout, refund)
* Easy Digital Downloads (purchase, begin checkout)
* Gravity Forms, WPForms, Fluent Forms, Ninja Forms, Contact Form 7, Elementor Forms (submissions)
* GiveWP (donations)
* WordPress core (site search, login, registration)

**Analytics destinations (free):**

* Google Analytics 4 (client + Measurement Protocol)
* Plausible Analytics
* Fathom Analytics
* Umami
* PostHog
* Matomo
* Vireo Analytics
* Any endpoint, via a generic Webhook

**Advertising destinations (Pro):**

* Meta (Facebook) Ads — Pixel + Conversions API
* Google Ads — conversion tracking with enhanced conversions
* TikTok Ads — Events API
* Pinterest Ads — Conversions API

New sources and destinations register through simple filters, so the list grows
without touching the core.

== Privacy ==

Advertising destinations default to denied until consent is granted. The plugin
honors Do Not Track and exposes a `wpch_consent` filter so a consent-management
platform can decide per event and category. Enhanced conversions (hashing and
sending first-party customer data) are opt-in and off by default.

== Free vs Pro ==

The free plugin is complete on its own: every source and every analytics
destination, with durable server-side delivery, consent gating, and a live
delivery log.

Pro adds server-side delivery to the ad platforms (Meta, Google Ads, TikTok,
Pinterest), enhanced conversions, deeper ecommerce data, and reporting. See the
plugin website for details.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins
   screen in WordPress.
2. Activate it.
3. Go to Settings → Conversion Relay, connect the destinations you use, and toggle
   which sources to track.

== Frequently Asked Questions ==

= Does this need a tag manager? =

No. Events are wired directly from your plugins to each platform.

= Will server-side tracking slow down my checkout? =

No. Server-side delivery runs in the background with retries, so the request that
completes a sale is never held up waiting on an analytics or ad platform.

= Where do delivery errors show up? =

Settings → Conversion Relay → Status lists recent delivery attempts, including
failures and retries. `wp conversion-hub status` shows the same from WP-CLI.

= Can I add a platform that isn't listed? =

Yes. Register your own source or destination with `wpch_register_source` /
`wpch_register_destination` — no core changes needed.

== Screenshots ==

1. Destinations — connect analytics and ad platforms with no code.
2. Sources — toggle which plugins to track and choose server, client, or both.
3. Status — a live log of every delivery attempt, with retries and failures.
4. Consent — analytics and advertising consent defaults, with Do Not Track.

== Changelog ==

= 0.1.0 =
* Initial release: event hub with durable async delivery, ten sources, and twelve
  destinations across analytics and advertising.

== Upgrade Notice ==

= 0.1.0 =
First release.
