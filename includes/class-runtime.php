<?php
/**
 * Runtime snippet output and execution.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Runtime {
	private Repository $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( 'wp_head', array( $this, 'run_wp_head' ), 10 );
		add_action( 'wp_body_open', array( $this, 'run_wp_body_open' ), 10 );
		add_action( 'wp_footer', array( $this, 'run_wp_footer' ), 10 );
		add_action( 'admin_head', array( $this, 'run_admin_head' ), 10 );
		add_action( 'admin_footer', array( $this, 'run_admin_footer' ), 10 );
		add_action( 'plugins_loaded', array( $this, 'run_php_plugins_loaded' ), 20 );
		add_action( 'init', array( $this, 'run_php_init' ), 20 );
		add_shortcode( 'sm_snippet', array( $this, 'shortcode' ) );
	}

	public function run_wp_head(): void {
		$this->run_placement( 'wp_head' );
	}

	public function run_wp_body_open(): void {
		$this->run_placement( 'wp_body_open' );
	}

	public function run_wp_footer(): void {
		$this->run_placement( 'wp_footer' );
	}

	public function run_admin_head(): void {
		$this->run_placement( 'admin_head' );
	}

	public function run_admin_footer(): void {
		$this->run_placement( 'admin_footer' );
	}

	public function run_php_plugins_loaded(): void {
		$this->run_placement( 'php_plugins_loaded' );
	}

	public function run_php_init(): void {
		$this->run_placement( 'php_init' );
	}

	public function shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sm_snippet' );
		$snippet = $this->repository->get( absint( $atts['id'] ) );

		if ( ! $snippet || empty( $snippet['active'] ) || 'shortcode' !== $snippet['placement'] ) {
			return '';
		}

		if ( ! Targeting::matches( $snippet ) || ! $this->can_run() ) {
			return '';
		}

		return $this->render_to_string( $snippet );
	}

	private function run_placement( string $placement ): void {
		if ( ! $this->can_run() ) {
			return;
		}

		foreach ( $this->repository->get_active_for_placement( $placement ) as $snippet ) {
			if ( Targeting::matches( $snippet ) ) {
				$this->render( $snippet );
			}
		}
	}

	private function can_run(): bool {
		if ( defined( 'SM_SNIPPETS_SAFE_MODE' ) && SM_SNIPPETS_SAFE_MODE ) {
			return false;
		}

		if ( isset( $_GET['sm-snippets-safe-mode'] ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		return '1' !== get_option( 'sm_snippets_safe_mode', '0' );
	}

	private function render_to_string( array $snippet ): string {
		ob_start();
		$this->render( $snippet );
		return (string) ob_get_clean();
	}

	private function render( array $snippet ): void {
		if ( 'php' === $snippet['type'] ) {
			$this->execute_php( (string) $snippet['code'], (int) $snippet['id'] );
			return;
		}

		echo "\n<!-- SM Snippets: " . esc_html( $snippet['name'] ) . " -->\n";

		if ( 'css' === $snippet['type'] ) {
			echo "<style id=\"sm-snippet-" . esc_attr( (string) $snippet['id'] ) . "\">\n";
			echo wp_strip_all_tags( $snippet['code'] );
			echo "\n</style>\n";
			return;
		}

		if ( 'js' === $snippet['type'] ) {
			echo "<script id=\"sm-snippet-" . esc_attr( (string) $snippet['id'] ) . "\">\n";
			echo $snippet['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</script>\n";
			return;
		}

		echo $snippet['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n";
	}

	private function execute_php( string $code, int $snippet_id ): void {
		$code = preg_replace( '/^\s*<\?php\s*/', '', $code );

		try {
			eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		} catch ( Throwable $exception ) {
			if ( current_user_can( 'manage_options' ) ) {
				printf(
					"\n<!-- SM Snippets PHP error in snippet %d: %s -->\n",
					$snippet_id,
					esc_html( $exception->getMessage() )
				);
			}
		}
	}
}
