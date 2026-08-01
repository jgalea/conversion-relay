=== Conversion Relay ===
Contributors: jeangalea
Tags: conversion tracking, analytics, google analytics, meta pixel, woocommerce
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
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

Nothing is sent anywhere until you enable a destination and enter its credentials.

**Sources** (auto-detected when the plugin is active):

* Ecommerce: WooCommerce, Easy Digital Downloads, WP Simple Pay, Download Monitor
* Forms: Gravity Forms, WPForms, Fluent Forms, Ninja Forms, Contact Form 7, Formidable, Forminator, Elementor Forms, JetFormBuilder, Kadence, SureForms, Everest Forms, HappyForms, WeForms, WS Form, Kali Forms
* Memberships and courses: MemberPress, Paid Memberships Pro, Restrict Content Pro, Ultimate Member, Simple Membership, LearnDash, LifterLMS, Tutor LMS
* Email and CRM: FluentCRM, Groundhogg, MailPoet, Mailster, Mailchimp for WordPress, The Newsletter Plugin, FunnelKit Automations
* Donations and affiliates: GiveWP, Charitable, AffiliateWP, SliceWP, Solid Affiliate, FluentAffiliate
* Builders and booking: Elementor, Divi, Bricks, Breakdance, Oxygen, Beaver Builder, LatePoint, FluentBooking
* WordPress core: site search, login, registration

**Destinations:**

* Analytics: Google Analytics 4 (client and Measurement Protocol), Plausible, Fathom, Umami, PostHog, Matomo, Simple Analytics, Pirsch, Swetrix, Rybbit, Beam, TinyAnalytics, GoSquared, Usermaven, UserBird, Wide Angle, Koko Analytics, Vireo Analytics
* Behaviour: Microsoft Clarity, Hotjar
* Advertising: Meta (Pixel and Conversions API), Google Ads, Microsoft Ads, TikTok Ads, Pinterest Ads, Reddit Ads, LinkedIn Ads, X (Twitter) Ads
* Google Tag Manager, and any endpoint via a generic Webhook

New sources and destinations register through simple filters, so the list grows
without touching the core.

== Privacy ==

Advertising destinations default to denied until consent is granted. The plugin
honors Do Not Track and exposes a `wpch_consent` filter so a consent-management
platform can decide per event and category. Enhanced conversions (hashing and
sending first-party customer data) are opt-in and off by default.

== External services ==

This plugin sends conversion data to third-party analytics and advertising
platforms. A platform is contacted only after you enable that destination and
enter its credentials on Settings → Conversion Relay. No destination is enabled
by default, and disabling one stops all traffic to it.

For every enabled destination, each tracked conversion sends: the event name
(for example purchase, form submission, registration), the order or entity ID
used for deduplication, order value and currency, cart or line items where the
platform supports them, the page URL and referrer, a timestamp, the visitor's IP
address and user agent, and any first-party click or analytics identifiers
present in cookies or the URL (`_ga`, `_fbp`, `_fbc`, `gclid`, `wbraid`,
`gbraid`).

If you switch on enhanced conversions (off by default), customer email, phone and
name are SHA-256 hashed before sending. Plain-text customer details are never
transmitted.

Google (Google Analytics 4, Google Ads, Google Tag Manager)
Events are sent to the GA4 Measurement Protocol at google-analytics.com from your
server, and the `gtag.js` or `gtm.js` script is loaded from googletagmanager.com
in the visitor's browser to fire client-side conversions. Terms of service:
https://policies.google.com/terms Privacy policy: https://policies.google.com/privacy

Meta (Facebook and Instagram Ads)
Conversions are sent to the Meta Conversions API at graph.facebook.com from your
server, and the Meta Pixel is loaded from connect.facebook.net in the visitor's
browser. Terms of service: https://www.facebook.com/legal/terms Privacy policy:
https://www.facebook.com/privacy/policy/

Microsoft (Microsoft Advertising and Clarity)
Conversions are sent through the UET tag loaded from bat.bing.com, and Clarity
events through the tag loaded from clarity.ms, both in the visitor's browser.
Microsoft Advertising agreement:
https://about.ads.microsoft.com/en-us/resources/policies/microsoft-advertising-agreement
Clarity terms: https://clarity.microsoft.com/terms Privacy statement:
https://www.microsoft.com/en-us/privacy/privacystatement

TikTok Ads
Conversions are sent to the TikTok Events API at business-api.tiktok.com from
your server. Business products terms:
https://ads.tiktok.com/i18n/official/policy/business-products-terms Privacy
policy: https://www.tiktok.com/legal/page/global/privacy-policy/en

Pinterest Ads
Conversions are sent to the Pinterest Conversions API at api.pinterest.com from
your server. Terms of service: https://business.pinterest.com/business-terms-of-service/
Privacy policy: https://policy.pinterest.com/en/privacy-policy

Reddit Ads
Conversions are sent to the Reddit Conversions API at ads-api.reddit.com from
your server. Advertising services agreement:
https://business.reddithelp.com/s/article/Reddit-Advertising-Services-Agreement
Privacy policy: https://www.reddit.com/policies/privacy-policy

LinkedIn Ads
Conversions are sent to the LinkedIn Conversions API at api.linkedin.com from
your server. Ads agreement: https://www.linkedin.com/legal/sas-terms Privacy
policy: https://www.linkedin.com/legal/privacy-policy

X (Twitter) Ads
Conversions are fired through the X website tag loaded from
static.ads-twitter.com in the visitor's browser. Ads terms:
https://legal.x.com/ads-terms.html Privacy policy: https://x.com/en/privacy

Hotjar
Events are sent through the Hotjar script loaded from static.hotjar.com in the
visitor's browser. Terms of service:
https://www.hotjar.com/legal/policies/terms-of-service/ Privacy policy:
https://www.hotjar.com/legal/policies/privacy/

Plausible Analytics
Events are sent from your server to plausible.io, or to the self-hosted address
you enter. Terms: https://plausible.io/terms Privacy policy:
https://plausible.io/privacy

PostHog
Events are sent from your server to the PostHog host you enter, us.i.posthog.com
by default. Terms: https://posthog.com/terms Privacy policy:
https://posthog.com/privacy

Umami
Events are sent from your server to the Umami host you enter, cloud.umami.is by
default. Terms: https://umami.is/terms Privacy policy: https://umami.is/privacy

Matomo
Events are sent from your server to the Matomo address you enter, which is your
own instance or Matomo Cloud. Cloud terms:
https://matomo.org/matomo-cloud-terms-of-service/ Privacy policy:
https://matomo.org/privacy-policy/

Webhook
If you enable the Webhook destination, the full normalized event is POSTed as
JSON to the URL you enter. You control that endpoint and the terms that apply to
it.

Fathom, Simple Analytics, Pirsch, Swetrix, Rybbit, Beam, TinyAnalytics,
GoSquared, Usermaven, UserBird, Wide Angle and Koko Analytics are fired through
the tracking script those services already install on your site. Conversion Relay
calls the script that is present in the page and opens no connection of its own.
Vireo Analytics is written to locally, with no external request.

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

= 0.2.0 =
* Every destination is now available to every user, with no license checks.
* Documented all external services in the readme.

= 0.1.0 =
* Initial release: event hub with durable async delivery, ten sources, and twelve
  destinations across analytics and advertising.

== Upgrade Notice ==

= 0.2.0 =
All destinations unlocked and external services documented.
