<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Hub\Dispatcher;

/** Contract every event source (WooCommerce, Gravity Forms, core, ...) implements. */
interface SourceInterface {

	public function id(): string;

	public function label(): string;

	/**
	 * Whether the underlying plugin is present. Sources must not hook when false.
	 */
	public function is_available(): bool;

	/**
	 * Attach the plugin's hooks. Called only when the source is enabled and available.
	 */
	public function register( Dispatcher $hub ): void;

	/**
	 * @return string[] EventType constants this source can emit.
	 */
	public function supported_events(): array;

	/**
	 * CSS selectors whose form submissions the client bridge should track. Empty
	 * for server-side sources; non-empty for client-tracked forms (page builders,
	 * AJAX form plugins) that have no reliable server hook.
	 *
	 * @return string[]
	 */
	public function client_selectors(): array;
}
