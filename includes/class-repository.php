<?php
/**
 * Snippet persistence.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {
	private DB $db;

	public function __construct( DB $db ) {
		$this->db = $db;
	}

	public static function default_targeting(): array {
		return array(
			'mode'             => 'all',
			'include_paths'    => '',
			'exclude_paths'    => '',
			'post_ids'         => '',
			'post_types'       => array(),
			'auth'             => 'any',
			'environment'      => 'any',
			'admin_test_only'  => false,
		);
	}

	public function get_all(): array {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM {$this->db->snippets_table} ORDER BY active DESC, priority ASC, name ASC", ARRAY_A );
		return array_map( array( $this, 'normalize_row' ), $rows ?: array() );
	}

	public function get_active_for_placement( string $placement ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->db->snippets_table} WHERE active = 1 AND placement = %s ORDER BY priority ASC, id ASC",
				$placement
			),
			ARRAY_A
		);

		return array_map( array( $this, 'normalize_row' ), $rows ?: array() );
	}

	public function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->db->snippets_table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->normalize_row( $row ) : null;
	}

	public function save( array $data ): int {
		global $wpdb;

		$id = absint( $data['id'] ?? 0 );
		$existing = $id ? $this->get( $id ) : null;
		$targeting = wp_parse_args( $data['targeting'] ?? array(), self::default_targeting() );

		$row = array(
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'code'        => (string) ( $data['code'] ?? '' ),
			'type'        => $this->sanitize_type( $data['type'] ?? 'html' ),
			'placement'   => $this->sanitize_placement( $data['placement'] ?? 'wp_head' ),
			'priority'    => (int) ( $data['priority'] ?? 10 ),
			'active'      => empty( $data['active'] ) ? 0 : 1,
			'targeting'   => wp_json_encode( $targeting ),
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$this->store_revision( $existing );
			$wpdb->update( $this->db->snippets_table, $row, array( 'id' => $id ) );
			return $id;
		}

		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $this->db->snippets_table, $row );
		return (int) $wpdb->insert_id;
	}

	public function delete( int $id ): void {
		global $wpdb;

		$wpdb->delete( $this->db->snippets_table, array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( $this->db->revisions_table, array( 'snippet_id' => $id ), array( '%d' ) );
	}

	public function toggle( int $id, bool $active ): void {
		global $wpdb;

		$snippet = $this->get( $id );
		if ( $snippet ) {
			$this->store_revision( $snippet );
		}

		$wpdb->update(
			$this->db->snippets_table,
			array(
				'active'     => $active ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public function get_revisions( int $snippet_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->db->revisions_table} WHERE snippet_id = %d ORDER BY created_at DESC, id DESC LIMIT 20",
				$snippet_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public function export_all(): array {
		return array(
			'plugin'   => 'sm-snippets',
			'version'  => SM_SNIPPETS_VERSION,
			'exported' => current_time( 'mysql' ),
			'snippets' => $this->get_all(),
		);
	}

	public function import_many( array $snippets ): int {
		$count = 0;

		foreach ( $snippets as $snippet ) {
			if ( ! is_array( $snippet ) ) {
				continue;
			}

			unset( $snippet['id'], $snippet['created_at'], $snippet['updated_at'] );
			$snippet['active'] = 0;
			$this->save( $snippet );
			$count++;
		}

		return $count;
	}

	private function store_revision( array $snippet ): void {
		global $wpdb;

		$wpdb->insert(
			$this->db->revisions_table,
			array(
				'snippet_id' => (int) $snippet['id'],
				'snapshot'   => wp_json_encode( $snippet ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	private function normalize_row( array $row ): array {
		$targeting = json_decode( (string) ( $row['targeting'] ?? '' ), true );

		$row['id'] = (int) $row['id'];
		$row['priority'] = (int) $row['priority'];
		$row['active'] = ! empty( $row['active'] );
		$row['targeting'] = wp_parse_args( is_array( $targeting ) ? $targeting : array(), self::default_targeting() );

		return $row;
	}

	private function sanitize_type( string $type ): string {
		return in_array( $type, array( 'html', 'css', 'js', 'php' ), true ) ? $type : 'html';
	}

	private function sanitize_placement( string $placement ): string {
		$placements = array_keys( Admin::placements() );
		return in_array( $placement, $placements, true ) ? $placement : 'wp_head';
	}
}
