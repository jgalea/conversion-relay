<?php

declare( strict_types=1 );

namespace WPConversionHub\Event;

/** The closed set of conversion event types every source and destination agrees on. */
final class EventType {

	public const PURCHASE        = 'purchase';
	public const FORM_SUBMISSION = 'form_submission';
	public const CLICK           = 'click';
	public const SUBSCRIBE       = 'subscribe';
	public const REGISTER        = 'register';
	public const FILE_DOWNLOAD   = 'file_download';
	public const ADD_TO_CART     = 'add_to_cart';
	public const BEGIN_CHECKOUT  = 'begin_checkout';
	public const DONATION        = 'donation';
	public const BOOKING         = 'booking';
	public const ENROLL          = 'enroll';
	public const SEARCH          = 'search';
	public const PAGE_VIEW       = 'page_view';
	public const VIEW_ITEM       = 'view_item';
	public const LOGIN           = 'login';
	public const SCROLL          = 'scroll';
	public const TIME_ON_PAGE    = 'time_on_page';
	public const PLAY            = 'play';
	public const REFUND          = 'refund';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::PURCHASE,
			self::FORM_SUBMISSION,
			self::CLICK,
			self::SUBSCRIBE,
			self::REGISTER,
			self::FILE_DOWNLOAD,
			self::ADD_TO_CART,
			self::BEGIN_CHECKOUT,
			self::DONATION,
			self::BOOKING,
			self::ENROLL,
			self::SEARCH,
			self::PAGE_VIEW,
			self::VIEW_ITEM,
			self::LOGIN,
			self::SCROLL,
			self::TIME_ON_PAGE,
			self::PLAY,
			self::REFUND,
		);
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}

	/**
	 * Event types that originate in the browser and have no reliable server hook.
	 *
	 * @return string[]
	 */
	public static function client_origin(): array {
		return array( self::SCROLL, self::TIME_ON_PAGE, self::PLAY, self::CLICK );
	}
}
