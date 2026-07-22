<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

final class DeliveryResult {

	private bool $ok;
	private string $message;
	private int $code;

	private function __construct( bool $ok, string $message, int $code ) {
		$this->ok      = $ok;
		$this->message = $message;
		$this->code    = $code;
	}

	public static function success( string $message = '', int $code = 200 ): self {
		return new self( true, $message, $code );
	}

	public static function failure( string $message, int $code = 0 ): self {
		return new self( false, $message, $code );
	}

	public function ok(): bool {
		return $this->ok;
	}

	public function message(): string {
		return $this->message;
	}

	public function code(): int {
		return $this->code;
	}
}
