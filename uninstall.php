<?php
/**
 * Uninstall cleanup.
 *
 * @package SM_Snippets
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sm_snippet_revisions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sm_snippets" );

delete_option( 'sm_snippets_safe_mode' );
