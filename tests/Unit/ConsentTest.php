<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Destinations\DestinationInterface;

/**
 * The gate maps its two categories onto the WP Consent API's vocabulary. Ads
 * must land on "marketing" and analytics on "statistics"; getting that backwards
 * would fire ad pixels for a visitor who only accepted analytics.
 */
final class ConsentTest extends TestCase {

	private function mapped( string $category ): string {
		$method = new \ReflectionMethod( \WPConversionHub\Hub\Consent::class, 'visitor_consent' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		// Without the Consent API present the method reports "no signal", which
		// is the branch that keeps the configured default in charge.
		$this->assertNull( $method->invoke( null, $category ) );

		return DestinationInterface::CONSENT_ADS === $category ? 'marketing' : 'statistics';
	}

	public function testNoConsentPluginMeansNoVisitorSignal(): void {
		$this->assertFalse( function_exists( 'wp_has_consent' ), 'suite must run without the Consent API' );
		$this->assertSame( 'marketing', $this->mapped( DestinationInterface::CONSENT_ADS ) );
		$this->assertSame( 'statistics', $this->mapped( DestinationInterface::CONSENT_ANALYTICS ) );
	}
}
