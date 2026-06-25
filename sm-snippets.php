<?php
/**
 * Plugin Name: SM Snippets
 * Description: A local, boring-in-the-best-way snippet manager for WordPress.
 * Version: 0.1.6
 * Update URI: false
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Author: SM
 * License: GPL-2.0-or-later
 * Text Domain: sm-snippets
 *
 * @package SM_Snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SM_SNIPPETS_VERSION', '0.1.6' );
define( 'SM_SNIPPETS_FILE', __FILE__ );
define( 'SM_SNIPPETS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SM_SNIPPETS_URL', plugin_dir_url( __FILE__ ) );

require_once SM_SNIPPETS_PATH . 'includes/class-db.php';
require_once SM_SNIPPETS_PATH . 'includes/class-repository.php';
require_once SM_SNIPPETS_PATH . 'includes/class-targeting.php';
require_once SM_SNIPPETS_PATH . 'includes/class-runtime.php';
require_once SM_SNIPPETS_PATH . 'includes/class-admin.php';
require_once SM_SNIPPETS_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'SM_Snippets\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		SM_Snippets\Plugin::instance()->load();
	}
);
