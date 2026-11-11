<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Download Monitor file download events.
final class DownloadMonitor extends AbstractSource {

	public function id(): string {
		return 'downloadmonitor';
	}

	public function label(): string {
		return 'Download Monitor';
	}

	public function is_available(): bool {
		return class_exists( 'WP_DLM' ) || function_exists( 'download_monitor' );
	}

	public function supported_events(): array {
		return array( EventType::FILE_DOWNLOAD );
	}

	protected function hooks(): void {
		add_action( 'dlm_downloading', array( $this, 'on_download' ), 10, 3 );
	}

	/**
	 * @param mixed $download  Download object when the plugin is loaded.
	 * @param mixed $version   Version object (unused).
	 * @param mixed $file_path File path (unused).
	 */
	public function on_download( $download = null, $version = null, $file_path = null ): void {
		$entity_id = ( is_object( $download ) && method_exists( $download, 'get_id' ) )
			? (string) $download->get_id()
			: '';

		$title = ( is_object( $download ) && method_exists( $download, 'get_title' ) )
			? $download->get_title()
			: '';

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = wp_hash( $entity_id . '|' . $ip );

		$this->emit_public(
			array(
				'type'      => EventType::FILE_DOWNLOAD,
				'entity_id' => $key,
				'meta'      => array(
					'download_id' => $entity_id,
					'title'       => $title,
				),
			),
			$key
		);
	}
}
