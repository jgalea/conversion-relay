<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Support;

/** In-memory stand-in for $wpdb, backed by SQLite, enough for the event log table. */
final class FakeWpdb {

	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	private \PDO $pdo;

	public function __construct() {
		$this->pdo = new \PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'CREATE TABLE wp_options (option_name TEXT PRIMARY KEY, option_value TEXT)' );
		$this->pdo->exec(
			'CREATE TABLE wp_wpch_events (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				event_id TEXT NOT NULL, destination TEXT NOT NULL, type TEXT NOT NULL, source TEXT NOT NULL,
				entity_id TEXT NOT NULL DEFAULT "", status TEXT NOT NULL DEFAULT "pending",
				attempts INTEGER NOT NULL DEFAULT 0, payload TEXT, response TEXT,
				created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
				UNIQUE (event_id, destination)
			)'
		);
	}

	public function prepare( string $sql, ...$args ): string {
		$args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args;
		$i    = 0;
		return (string) preg_replace_callback(
			'/%[sdi]/',
			function ( array $m ) use ( &$i, $args ): string {
				$arg = $args[ $i++ ];
				if ( '%i' === $m[0] ) {
					return '"' . str_replace( '"', '', (string) $arg ) . '"';
				}
				return '%d' === $m[0] ? (string) (int) $arg : $this->pdo->quote( (string) $arg );
			},
			$sql
		);
	}

	public function query( string $sql ) {
		$upsert = false !== strpos( $sql, 'ON DUPLICATE KEY UPDATE' );
		$sql    = str_replace(
			'ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
			'ON CONFLICT(event_id, destination) DO UPDATE SET updated_at = excluded.updated_at',
			$sql
		);
		$before = $upsert ? $this->count() : 0;
		$rows   = $this->pdo->exec( $sql );
		if ( $upsert ) {
			return $this->count() > $before ? 1 : 2;
		}
		return $rows;
	}

	public function get_var( string $sql ) {
		$value = $this->pdo->query( $sql )->fetchColumn();
		return false === $value ? null : $value;
	}

	public function get_results( string $sql, $output = null ): array {
		return $this->pdo->query( $sql )->fetchAll( \PDO::FETCH_ASSOC );
	}

	public function esc_like( string $text ): string {
		return $text;
	}

	public function count_options(): int {
		return (int) $this->pdo->query( 'SELECT COUNT(*) FROM wp_options' )->fetchColumn();
	}

	public function insert_option( string $name ): void {
		$this->pdo->exec( 'INSERT INTO wp_options VALUES (' . $this->pdo->quote( $name ) . ', "x")' );
	}

	public function table_exists( string $table ): bool {
		return (bool) $this->pdo->query( "SELECT name FROM sqlite_master WHERE name = '{$table}'" )->fetchColumn();
	}

	public function count(): int {
		return (int) $this->pdo->query( 'SELECT COUNT(*) FROM wp_wpch_events' )->fetchColumn();
	}
}
