<?php
/**
 * Plugin bootstrap.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;

	private DB $db;
	private Repository $repository;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		$db = new DB();
		$db->create_tables();
		delete_site_transient( 'update_plugins' );

		if ( false === get_option( 'sm_snippets_safe_mode' ) ) {
			add_option( 'sm_snippets_safe_mode', '0', '', false );
		}
	}

	private function __construct() {
		$this->db = new DB();
		$this->repository = new Repository( $this->db );
	}

	public function load(): void {
		load_plugin_textdomain( 'sm-snippets', false, dirname( plugin_basename( SM_SNIPPETS_FILE ) ) . '/languages' );

		add_filter( 'site_transient_update_plugins', array( $this, 'remove_wordpress_org_update' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'remove_wordpress_org_update' ) );

		( new Runtime( $this->repository ) )->register_hooks();

		if ( is_admin() ) {
			( new Admin( $this->repository, $this->db ) )->register_hooks();
		}
	}

	public function remove_wordpress_org_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin = plugin_basename( SM_SNIPPETS_FILE );

		if ( isset( $transient->response[ $plugin ] ) ) {
			unset( $transient->response[ $plugin ] );
		}

		return $transient;
	}
}
