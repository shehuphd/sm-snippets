<?php
/**
 * Database table management.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DB {
	public string $snippets_table;
	public string $revisions_table;

	public function __construct() {
		global $wpdb;

		$this->snippets_table = $wpdb->prefix . 'sm_snippets';
		$this->revisions_table = $wpdb->prefix . 'sm_snippet_revisions';
	}

	public function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$snippets_sql = "CREATE TABLE {$this->snippets_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			description TEXT NOT NULL,
			code LONGTEXT NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'html',
			placement VARCHAR(40) NOT NULL DEFAULT 'wp_head',
			priority SMALLINT NOT NULL DEFAULT 10,
			active TINYINT(1) NOT NULL DEFAULT 0,
			targeting LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY active_placement (active, placement),
			KEY type (type)
		) $charset_collate;";

		$revisions_sql = "CREATE TABLE {$this->revisions_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			snippet_id BIGINT(20) UNSIGNED NOT NULL,
			snapshot LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY snippet_id (snippet_id)
		) $charset_collate;";

		dbDelta( $snippets_sql );
		dbDelta( $revisions_sql );
	}
}
