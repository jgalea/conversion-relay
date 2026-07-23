<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Destinations\Fathom;
use WPConversionHub\Destinations\Ga4;
use WPConversionHub\Destinations\GoogleAds;
use WPConversionHub\Destinations\MetaAds;
use WPConversionHub\Destinations\Pinterest;
use WPConversionHub\Destinations\Plausible;
use WPConversionHub\Destinations\PostHog;
use WPConversionHub\Destinations\TikTok;
use WPConversionHub\Destinations\Umami;
use WPConversionHub\Destinations\Vireo;
use WPConversionHub\Destinations\Webhook;

/**
 * Locks the free/Pro boundary: analytics destinations are free, ad platforms are Pro.
 */
final class DestinationTierTest extends TestCase {

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public function tierProvider(): array {
		return array(
			'ga4'       => array( Ga4::class, DestinationInterface::TIER_FREE ),
			'plausible' => array( Plausible::class, DestinationInterface::TIER_FREE ),
			'fathom'    => array( Fathom::class, DestinationInterface::TIER_FREE ),
			'umami'     => array( Umami::class, DestinationInterface::TIER_FREE ),
			'posthog'   => array( PostHog::class, DestinationInterface::TIER_FREE ),
			'vireo'     => array( Vireo::class, DestinationInterface::TIER_FREE ),
			'webhook'   => array( Webhook::class, DestinationInterface::TIER_FREE ),
			'meta'      => array( MetaAds::class, DestinationInterface::TIER_PRO ),
			'googleads' => array( GoogleAds::class, DestinationInterface::TIER_PRO ),
			'tiktok'    => array( TikTok::class, DestinationInterface::TIER_PRO ),
			'pinterest' => array( Pinterest::class, DestinationInterface::TIER_PRO ),
		);
	}

	/**
	 * @dataProvider tierProvider
	 */
	public function test_destination_tier( string $class, string $expected_tier ): void {
		$destination = new $class();
		$this->assertSame( $expected_tier, $destination->tier() );

		if ( DestinationInterface::TIER_PRO === $expected_tier ) {
			$this->assertNotSame( '', $destination->pro_note(), 'Pro destinations must carry an upsell note.' );
			$this->assertSame( DestinationInterface::CONSENT_ADS, $destination->consent_category() );
		}
	}
}
