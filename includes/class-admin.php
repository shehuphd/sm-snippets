<?php
/**
 * WordPress admin screens.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private Repository $repository;
	private DB $db;

	public function __construct( Repository $repository, DB $db ) {
		$this->repository = $repository;
		$this->db = $db;
	}

	public static function placements(): array {
		return array(
			'wp_head'            => __( 'Site head', 'sm-snippets' ),
			'wp_body_open'       => __( 'Body open', 'sm-snippets' ),
			'wp_footer'          => __( 'Site footer', 'sm-snippets' ),
			'admin_head'         => __( 'Admin head', 'sm-snippets' ),
			'admin_footer'       => __( 'Admin footer', 'sm-snippets' ),
			'php_plugins_loaded' => __( 'PHP: plugins_loaded', 'sm-snippets' ),
			'php_init'           => __( 'PHP: init', 'sm-snippets' ),
			'shortcode'          => __( 'Shortcode/manual', 'sm-snippets' ),
		);
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'safe_mode_notice' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'SM Snippets', 'sm-snippets' ),
			__( 'SM Snippets', 'sm-snippets' ),
			'manage_options',
			'sm-snippets',
			array( $this, 'render_page' ),
			'dashicons-editor-code',
			58
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_sm-snippets' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'sm-snippets-admin', SM_SNIPPETS_URL . 'assets/admin.css', array(), SM_SNIPPETS_VERSION );
		wp_enqueue_script( 'sm-snippets-admin', SM_SNIPPETS_URL . 'assets/admin.js', array(), SM_SNIPPETS_VERSION, true );
	}

	public function safe_mode_notice(): void {
		if ( '1' !== get_option( 'sm_snippets_safe_mode', '0' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'SM Snippets safe mode is active. No snippets are currently running.', 'sm-snippets' ),
			esc_url( wp_nonce_url( admin_url( 'admin.php?page=sm-snippets&action=disable_safe_mode' ), 'sm_snippets_disable_safe_mode' ) ),
			esc_html__( 'Disable safe mode', 'sm-snippets' )
		);
	}

	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) || 'sm-snippets' !== $_GET['page'] ) {
			return;
		}

		$action = sanitize_key( $_GET['action'] ?? '' );

		if ( 'save' === $action && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_save();
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'sm_snippets_delete' );
			$this->repository->delete( absint( $_GET['id'] ?? 0 ) );
			$this->redirect( 'deleted=1' );
		}

		if ( 'toggle' === $action ) {
			check_admin_referer( 'sm_snippets_toggle' );
			$this->repository->toggle( absint( $_GET['id'] ?? 0 ), ! empty( $_GET['active'] ) );
			$this->redirect( 'updated=1' );
		}

		if ( 'enable_safe_mode' === $action ) {
			check_admin_referer( 'sm_snippets_enable_safe_mode' );
			update_option( 'sm_snippets_safe_mode', '1' );
			$this->redirect( 'safe_mode=1' );
		}

		if ( 'disable_safe_mode' === $action ) {
			check_admin_referer( 'sm_snippets_disable_safe_mode' );
			update_option( 'sm_snippets_safe_mode', '0' );
			$this->redirect( 'safe_mode=0' );
		}

		if ( 'export' === $action ) {
			check_admin_referer( 'sm_snippets_export' );
			$this->handle_export();
		}

		if ( 'import' === $action && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'sm_snippets_import' );
			$this->handle_import();
		}
	}

	public function render_page(): void {
		$view = sanitize_key( $_GET['view'] ?? 'list' );

		echo '<div class="wrap sm-snippets-wrap">';
		echo '<div class="sm-page-title">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'SM Snippets', 'sm-snippets' ) . '</h1>';
		echo '<span class="sm-byline">' . esc_html__( 'By', 'sm-snippets' ) . ' <a href="' . esc_url( 'https://mohammedshehu.com' ) . '" target="_blank" rel="noopener noreferrer">Mo</a></span>';
		echo '</div>';
		echo '<div class="sm-title-actions">';
		echo '<a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=sm-snippets&view=edit' ) ) . '">' . esc_html__( 'Add New', 'sm-snippets' ) . '</a>';
		echo '</div>';
		$this->render_notices();

		if ( 'edit' === $view ) {
			$this->render_edit();
		} elseif ( 'import-export' === $view ) {
			$this->render_import_export();
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	private function render_notices(): void {
		$messages = array(
			'saved'    => __( 'Snippet saved.', 'sm-snippets' ),
			'deleted'  => __( 'Snippet deleted.', 'sm-snippets' ),
			'updated'  => __( 'Snippet updated.', 'sm-snippets' ),
			'imported' => __( 'Snippets imported.', 'sm-snippets' ),
		);

		foreach ( $messages as $key => $message ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
	}

	private function render_list(): void {
		$snippets = $this->repository->get_all();
		$safe_mode_url = '1' === get_option( 'sm_snippets_safe_mode', '0' )
			? wp_nonce_url( admin_url( 'admin.php?page=sm-snippets&action=disable_safe_mode' ), 'sm_snippets_disable_safe_mode' )
			: wp_nonce_url( admin_url( 'admin.php?page=sm-snippets&action=enable_safe_mode' ), 'sm_snippets_enable_safe_mode' );

		echo '<p class="sm-snippets-actions">';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=sm-snippets&view=import-export' ) ) . '">' . esc_html__( 'Import / Export', 'sm-snippets' ) . '</a> ';
		echo '<a class="button button-secondary" href="' . esc_url( $safe_mode_url ) . '">' . esc_html( '1' === get_option( 'sm_snippets_safe_mode', '0' ) ? __( 'Disable Safe Mode', 'sm-snippets' ) : __( 'Pause All Snippets', 'sm-snippets' ) ) . '</a>';
		echo '</p>';

		echo '<table class="widefat fixed striped sm-snippets-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'sm-snippets' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'sm-snippets' ) . '</th>';
		echo '<th>' . esc_html__( 'Placement', 'sm-snippets' ) . '</th>';
		echo '<th>' . esc_html__( 'Priority', 'sm-snippets' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'sm-snippets' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'sm-snippets' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $snippets ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No snippets yet.', 'sm-snippets' ) . '</td></tr>';
		}

		foreach ( $snippets as $snippet ) {
			$edit_url = admin_url( 'admin.php?page=sm-snippets&view=edit&id=' . (int) $snippet['id'] );
			$toggle_url = wp_nonce_url(
				admin_url( 'admin.php?page=sm-snippets&action=toggle&id=' . (int) $snippet['id'] . '&active=' . ( $snippet['active'] ? '0' : '1' ) ),
				'sm_snippets_toggle'
			);
			$delete_url = wp_nonce_url(
				admin_url( 'admin.php?page=sm-snippets&action=delete&id=' . (int) $snippet['id'] ),
				'sm_snippets_delete'
			);

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $snippet['name'] ) . '</a></strong>';
			echo '<div class="row-actions">';
			echo '<span><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'sm-snippets' ) . '</a> | </span>';
			echo '<span><a href="' . esc_url( $toggle_url ) . '">' . esc_html( $snippet['active'] ? __( 'Deactivate', 'sm-snippets' ) : __( 'Activate', 'sm-snippets' ) ) . '</a> | </span>';
			echo '<span class="trash"><a href="' . esc_url( $delete_url ) . '" data-sm-confirm="' . esc_attr__( 'Delete this snippet?', 'sm-snippets' ) . '">' . esc_html__( 'Delete', 'sm-snippets' ) . '</a></span>';
			echo '</div></td>';
			echo '<td>' . esc_html( strtoupper( $snippet['type'] ) ) . '</td>';
			echo '<td>' . esc_html( self::placements()[ $snippet['placement'] ] ?? $snippet['placement'] ) . '</td>';
			echo '<td>' . esc_html( (string) $snippet['priority'] ) . '</td>';
			echo '<td><span class="sm-status ' . ( $snippet['active'] ? 'is-active' : 'is-paused' ) . '">' . esc_html( $snippet['active'] ? __( 'Active', 'sm-snippets' ) : __( 'Paused', 'sm-snippets' ) ) . '</span></td>';
			echo '<td>' . esc_html( $snippet['updated_at'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function render_edit(): void {
		$id = absint( $_GET['id'] ?? 0 );
		$snippet = $id ? $this->repository->get( $id ) : null;

		if ( ! $snippet ) {
			$snippet = array(
				'id'          => 0,
				'name'        => '',
				'description' => '',
				'code'        => '',
				'type'        => 'html',
				'placement'   => 'wp_head',
				'priority'    => 10,
				'active'      => false,
				'updated_at'  => '',
				'targeting'   => Repository::default_targeting(),
			);
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=sm-snippets&action=save' ) ) . '" class="sm-snippet-form">';
		wp_nonce_field( 'sm_snippets_save' );
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $snippet['id'] ) . '">';

		echo '<div class="sm-editor-heading">';
		echo '<a class="sm-back-link" href="' . esc_url( admin_url( 'admin.php?page=sm-snippets' ) ) . '">&lt; ' . esc_html__( 'Back to all snippets', 'sm-snippets' ) . '</a>';
		echo '<h2>' . esc_html( $snippet['id'] ? __( 'Edit Snippet', 'sm-snippets' ) : __( 'Add New Snippet', 'sm-snippets' ) ) . '</h2>';
		echo '</div>';

		echo '<div class="sm-editor-grid">';
		echo '<main class="sm-editor-main">';
		echo '<section class="sm-box sm-primary-box">';
		$this->field_text( 'name', __( 'Name', 'sm-snippets' ), $snippet['name'], true );
		$this->field_textarea( 'code', __( 'Code', 'sm-snippets' ), $snippet['code'], 18, 'code sm-code-editor' );
		$this->field_textarea( 'description', __( 'Notes', 'sm-snippets' ), $snippet['description'], 4 );
		echo '</section>';
		echo '</main><aside class="sm-editor-side">';
		$this->render_publish_box( $snippet );
		$this->render_settings_box( $snippet );
		$this->render_targeting_box( $snippet['targeting'] );
		$this->render_revisions_box( (int) $snippet['id'] );
		echo '</aside></div>';
		echo '</form>';
	}

	private function render_publish_box( array $snippet ): void {
		echo '<section class="sm-box sm-publish-box">';
		echo '<label class="sm-switch"><input type="checkbox" name="active" value="1" ' . checked( $snippet['active'], true, false ) . '><span>' . esc_html__( 'Active', 'sm-snippets' ) . '</span></label>';
		echo '<button type="submit" class="button button-primary button-large sm-save-button">' . esc_html__( 'Save Snippet', 'sm-snippets' ) . '</button>';
		echo '<a class="button button-large sm-back-button" href="' . esc_url( admin_url( 'admin.php?page=sm-snippets' ) ) . '">' . esc_html__( 'Back', 'sm-snippets' ) . '</a>';
		echo '</section>';
	}

	private function render_settings_box( array $snippet ): void {
		echo '<section class="sm-box sm-settings-box"><h2>' . esc_html__( 'Run Settings', 'sm-snippets' ) . '</h2>';
		$this->field_select( 'type', __( 'Type', 'sm-snippets' ), $snippet['type'], array(
			'html' => 'HTML',
			'css'  => 'CSS',
			'js'   => 'JavaScript',
			'php'  => 'PHP',
		) );
		$this->field_select( 'placement', __( 'Placement', 'sm-snippets' ), $snippet['placement'], self::placements() );
		$this->field_text( 'priority', __( 'Priority', 'sm-snippets' ), (string) $snippet['priority'], false, 'number' );

		echo '<dl class="sm-meta-list">';
		echo '<div><dt>' . esc_html__( 'Status', 'sm-snippets' ) . '</dt><dd><span class="sm-status ' . ( $snippet['active'] ? 'is-active' : 'is-paused' ) . '">' . esc_html( $snippet['active'] ? __( 'Active', 'sm-snippets' ) : __( 'Paused', 'sm-snippets' ) ) . '</span></dd></div>';
		echo '<div><dt>' . esc_html__( 'Updated', 'sm-snippets' ) . '</dt><dd>' . esc_html( $snippet['updated_at'] ?: __( 'Not saved yet', 'sm-snippets' ) ) . '</dd></div>';

		if ( ! empty( $snippet['id'] ) ) {
			echo '<div><dt>' . esc_html__( 'Shortcode', 'sm-snippets' ) . '</dt><dd><code>[sm_snippet id="' . esc_html( (string) $snippet['id'] ) . '"]</code></dd></div>';
		}

		echo '</dl>';
		echo '</section>';
	}

	private function render_targeting_box( array $rules ): void {
		$rules = wp_parse_args( $rules, Repository::default_targeting() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<section class="sm-box sm-targeting-box"><h2>' . esc_html__( 'Targeting', 'sm-snippets' ) . '</h2>';
		$this->field_select( 'targeting[mode]', __( 'Pages', 'sm-snippets' ), $rules['mode'], array(
			'all'      => __( 'Everywhere', 'sm-snippets' ),
			'home'     => __( 'Homepage only', 'sm-snippets' ),
			'singular' => __( 'Singular content only', 'sm-snippets' ),
		) );
		$this->field_select( 'targeting[auth]', __( 'Visitor', 'sm-snippets' ), $rules['auth'], array(
			'any'        => __( 'Anyone', 'sm-snippets' ),
			'logged-in'  => __( 'Logged in', 'sm-snippets' ),
			'logged-out' => __( 'Logged out', 'sm-snippets' ),
		) );
		$this->field_select( 'targeting[environment]', __( 'Environment', 'sm-snippets' ), $rules['environment'], array(
			'any'         => __( 'Any', 'sm-snippets' ),
			'production'  => __( 'Production', 'sm-snippets' ),
			'staging'     => __( 'Staging', 'sm-snippets' ),
			'development' => __( 'Development', 'sm-snippets' ),
			'local'       => __( 'Local', 'sm-snippets' ),
		) );
		$this->field_text( 'targeting[post_ids]', __( 'Specific post/page IDs', 'sm-snippets' ), $rules['post_ids'] );
		$this->field_textarea( 'targeting[include_paths]', __( 'Include URL paths', 'sm-snippets' ), $rules['include_paths'], 3 );
		$this->field_textarea( 'targeting[exclude_paths]', __( 'Exclude URL paths', 'sm-snippets' ), $rules['exclude_paths'], 3 );

		echo '<fieldset class="sm-post-types"><legend>' . esc_html__( 'Post types', 'sm-snippets' ) . '</legend>';
		echo '<div class="sm-checkbox-grid">';
		foreach ( $post_types as $post_type ) {
			echo '<label class="sm-checkbox"><input type="checkbox" name="targeting[post_types][]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, (array) $rules['post_types'], true ), true, false ) . '> ' . esc_html( $post_type->label ) . '</label>';
		}
		echo '</div></fieldset>';

		echo '<label class="sm-checkbox sm-admin-test"><input type="checkbox" name="targeting[admin_test_only]" value="1" ' . checked( ! empty( $rules['admin_test_only'] ), true, false ) . '> ' . esc_html__( 'Only run for admins while testing', 'sm-snippets' ) . '</label>';
		echo '</section>';
	}

	private function render_revisions_box( int $snippet_id ): void {
		if ( ! $snippet_id ) {
			return;
		}

		$revisions = $this->repository->get_revisions( $snippet_id );
		echo '<section class="sm-box"><h2>' . esc_html__( 'Recent Revisions', 'sm-snippets' ) . '</h2>';

		if ( empty( $revisions ) ) {
			echo '<p>' . esc_html__( 'No revisions yet.', 'sm-snippets' ) . '</p>';
		} else {
			echo '<ul class="sm-revisions">';
			foreach ( $revisions as $revision ) {
				echo '<li>' . esc_html( $revision['created_at'] ) . '</li>';
			}
			echo '</ul>';
		}

		echo '</section>';
	}

	private function render_import_export(): void {
		echo '<div class="sm-layout"><main>';
		echo '<section class="sm-box"><h2>' . esc_html__( 'Export', 'sm-snippets' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download all snippets as JSON.', 'sm-snippets' ) . '</p>';
		echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=sm-snippets&action=export' ), 'sm_snippets_export' ) ) . '">' . esc_html__( 'Export JSON', 'sm-snippets' ) . '</a>';
		echo '</section></main><aside>';
		echo '<section class="sm-box"><h2>' . esc_html__( 'Import', 'sm-snippets' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin.php?page=sm-snippets&action=import' ) ) . '">';
		wp_nonce_field( 'sm_snippets_import' );
		echo '<input type="file" name="import_file" accept="application/json"> ';
		echo '<button class="button">' . esc_html__( 'Import JSON', 'sm-snippets' ) . '</button>';
		echo '</form></section></aside></div>';
	}

	private function handle_save(): void {
		check_admin_referer( 'sm_snippets_save' );

		$data = wp_unslash( $_POST );
		$id = $this->repository->save( $data );
		wp_safe_redirect( admin_url( 'admin.php?page=sm-snippets&view=edit&id=' . $id . '&saved=1' ) );
		exit;
	}

	private function handle_export(): void {
		$filename = 'sm-snippets-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode( $this->repository->export_all(), JSON_PRETTY_PRINT );
		exit;
	}

	private function handle_import(): void {
		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			$this->redirect();
		}

		$contents = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ) );
		$data = json_decode( (string) $contents, true );
		$count = $this->repository->import_many( is_array( $data['snippets'] ?? null ) ? $data['snippets'] : array() );
		$this->redirect( 'imported=' . $count );
	}

	private function redirect( string $query = '' ): void {
		$url = admin_url( 'admin.php?page=sm-snippets' );
		if ( $query ) {
			$url .= '&' . $query;
		}
		wp_safe_redirect( $url );
		exit;
	}

	private function field_text( string $name, string $label, string $value, bool $required = false, string $type = 'text' ): void {
		echo '<label class="sm-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label>';
	}

	private function field_textarea( string $name, string $label, string $value, int $rows, string $class = '' ): void {
		echo '<label class="sm-field"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '" class="' . esc_attr( $class ) . '">' . esc_textarea( $value ) . '</textarea></label>';
	}

	private function field_select( string $name, string $label, string $value, array $options ): void {
		echo '<label class="sm-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option_value => $option_label ) {
			echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}
}
