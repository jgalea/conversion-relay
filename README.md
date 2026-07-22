<div align="center">

# WP Conversion Hub

[![License](https://img.shields.io/badge/LICENSE-GPL--2.0-5C9E31?style=for-the-badge)](LICENSE)
[![WordPress](https://img.shields.io/badge/WORDPRESS-6.2%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white)](https://wordpress.org)
[![Built by](https://img.shields.io/badge/BUILT%20BY-JGALEA-8A2BE2?style=for-the-badge)](https://github.com/jgalea)

**No-code bridge from WordPress plugin events to analytics and ad platforms. Open, extensible, no tag manager.**

</div>

## What it does

WP Conversion Hub turns actions in the plugins you already run — a WooCommerce
purchase, a Gravity Forms submission, an EDD sale — into one normalized event, then
routes that event to every analytics and advertising platform you enable. No Google
Tag Manager, no snippets pasted into `functions.php`.

The design separates three concerns:

- **Sources** hook a plugin and emit a `NormalizedEvent`.
- **The Hub** applies consent and deduplication, then delivers.
- **Destinations** send to a platform over a server transport (Measurement
  Protocol / Conversions API), a client transport (pixel / gtag), or both.

Server delivery runs through a durable async queue (Action Scheduler when
available) with retries and a dead-letter state, so a slow network call never
blocks checkout. Client pixels are persisted across redirects and fired exactly
once on the next page view, so a gateway return or a Woo Blocks checkout doesn't
lose the conversion.

## Sources

WooCommerce · Easy Digital Downloads · Gravity Forms · Contact Form 7 · Elementor
Forms · WordPress core (search, login, registration).

## Destinations

Google Analytics 4 (client + Measurement Protocol) · Plausible · Fathom · Umami ·
PostHog · Meta Ads (Pixel + Conversions API) · Google Ads.

## Privacy

Advertising destinations default to denied until consent is granted. Do Not Track
is honored, and a `wpch_consent` filter lets a consent-management platform decide
per event and category. Enhanced conversions (hashing first-party customer data
for better attribution) are opt-in and off by default.

## Extending

Register your own source or destination — no core changes needed:

```php
add_action( 'wpch_register_destination', function ( $registry ) {
    $registry->add_destination( new My_Destination() );
} );
```

Implement `WPConversionHub\Destinations\DestinationInterface` (or extend
`AbstractDestination`) and `WPConversionHub\Sources\SourceInterface`
(or `AbstractSource`).

## Development

```bash
composer install
composer test    # PHPUnit
composer stan    # PHPStan
composer lint    # PHPCS (WordPress standards)
```

## WP-CLI

```bash
wp conversion-hub status         # delivery counts by status
wp conversion-hub destinations   # registered destinations, enabled + configured
wp conversion-hub test-event     # dispatch a synthetic event
wp conversion-hub retry          # re-queue failed deliveries
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
