<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

/**
 * Single source of truth for whether Pro features are unlocked. No paywall is
 * wired yet; a licensing integration (Freemius / EDD Software Licensing) will
 * filter `wpch_is_pro`. The `WPCH_PRO` constant force-unlocks for development.
 */
final class License {

	public static function is_pro(): bool {
		if ( defined( 'WPCH_PRO' ) && WPCH_PRO ) {
			return true;
		}
		return (bool) apply_filters( 'wpch_is_pro', false );
	}
}
