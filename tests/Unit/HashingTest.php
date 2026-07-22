<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Support\Hashing;

final class HashingTest extends TestCase {

	public function test_email_is_normalized_then_hashed(): void {
		$this->assertSame( hash( 'sha256', 'jane@example.com' ), Hashing::email( '  Jane@Example.com ' ) );
	}

	public function test_phone_strips_formatting(): void {
		$this->assertSame( hash( 'sha256', '34600112233' ), Hashing::phone( '+34 600 112 233' ) );
	}

	public function test_already_hashed_value_passes_through(): void {
		$hash = hash( 'sha256', 'jane@example.com' );
		$out  = Hashing::user_data( [ 'email' => $hash ] );
		$this->assertSame( $hash, $out['email'] );
	}

	public function test_empty_fields_are_dropped(): void {
		$out = Hashing::user_data( [ 'email' => '', 'phone' => '+1 (555) 000' ] );
		$this->assertArrayNotHasKey( 'email', $out );
		$this->assertArrayHasKey( 'phone', $out );
	}
}
