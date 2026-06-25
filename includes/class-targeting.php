<?php
/**
 * Request targeting rules.
 *
 * @package SM_Snippets
 */

namespace SM_Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Targeting {
	public static function matches( array $snippet ): bool {
		$rules = wp_parse_args( $snippet['targeting'] ?? array(), Repository::default_targeting() );

		if ( ! empty( $rules['admin_test_only'] ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! self::matches_auth( (string) $rules['auth'] ) ) {
			return false;
		}

		if ( ! self::matches_environment( (string) $rules['environment'] ) ) {
			return false;
		}

		if ( ! self::matches_mode( (string) $rules['mode'] ) ) {
			return false;
		}

		if ( ! self::matches_post_ids( (string) $rules['post_ids'] ) ) {
			return false;
		}

		if ( ! self::matches_post_types( (array) $rules['post_types'] ) ) {
			return false;
		}

		if ( ! self::matches_paths( (string) $rules['include_paths'], (string) $rules['exclude_paths'] ) ) {
			return false;
		}

		return true;
	}

	private static function matches_auth( string $auth ): bool {
		if ( 'logged-in' === $auth ) {
			return is_user_logged_in();
		}

		if ( 'logged-out' === $auth ) {
			return ! is_user_logged_in();
		}

		return true;
	}

	private static function matches_environment( string $environment ): bool {
		if ( 'any' === $environment || '' === $environment ) {
			return true;
		}

		if ( function_exists( 'wp_get_environment_type' ) ) {
			return $environment === wp_get_environment_type();
		}

		return true;
	}

	private static function matches_mode( string $mode ): bool {
		if ( 'home' === $mode ) {
			return is_front_page() || is_home();
		}

		if ( 'singular' === $mode ) {
			return is_singular();
		}

		return true;
	}

	private static function matches_post_ids( string $post_ids ): bool {
		$ids = self::csv_to_ints( $post_ids );

		if ( empty( $ids ) ) {
			return true;
		}

		return is_singular() && in_array( get_queried_object_id(), $ids, true );
	}

	private static function matches_post_types( array $post_types ): bool {
		$post_types = array_filter( array_map( 'sanitize_key', $post_types ) );

		if ( empty( $post_types ) ) {
			return true;
		}

		return is_singular( $post_types );
	}

	private static function matches_paths( string $include_paths, string $exclude_paths ): bool {
		$path = self::current_path();

		if ( self::path_list_matches( $exclude_paths, $path ) ) {
			return false;
		}

		if ( '' === trim( $include_paths ) ) {
			return true;
		}

		return self::path_list_matches( $include_paths, $path );
	}

	private static function current_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		return trailingslashit( '/' . ltrim( (string) $path, '/' ) );
	}

	private static function path_list_matches( string $list, string $path ): bool {
		$patterns = array_filter( array_map( 'trim', preg_split( '/\R/', $list ) ?: array() ) );

		foreach ( $patterns as $pattern ) {
			$normalized = trailingslashit( '/' . ltrim( $pattern, '/' ) );

			if ( $normalized === $path ) {
				return true;
			}

			if ( '*/' === substr( $normalized, -2 ) ) {
				$prefix = substr( $normalized, 0, -2 );
				if ( 0 === strpos( $path, $prefix ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function csv_to_ints( string $value ): array {
		return array_values(
			array_filter(
				array_map(
					'absint',
					array_map( 'trim', explode( ',', $value ) )
				)
			)
		);
	}
}
