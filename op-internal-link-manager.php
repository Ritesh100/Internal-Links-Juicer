<?php
/**
 * Plugin Name:       OP Internal Link Manager
 * Plugin URI:        https://outpace.com
 * Description:       Automatically insert internal links into post/page content based on keyword-to-URL rules defined in the admin.
 * Version:           1.0.8
 * Requires Plugins:
 * Requires at least: 5.0
 * Requires PHP:      8.0
 * Author:            Ritesh OutpaceSeo
 * Author URI:        https://github.com/Ritesh100/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       op-internal-link-manager
 * Domain Path:       /languages
 * Update URI:        https://github.com/Ritesh100/Internal-Links-Juicer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'OILM_VERSION', '1.0.8' );
define( 'OILM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OILM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OILM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OILM_GITHUB_OWNER', 'Ritesh100' );
define( 'OILM_GITHUB_REPO', 'Internal-Links-Juicer' );
define( 'OILM_GITHUB_BRANCH', 'main' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-activator.php
 */
function activate_outpace_internal_link_manager() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
	OILM_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-deactivator.php
 */

function deactivate_outpace_internal_link_manager() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-deactivator.php';
	OILM_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_outpace_internal_link_manager' );
register_deactivation_hook( __FILE__, 'deactivate_outpace_internal_link_manager' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_outpace_internal_link_manager() {
	$plugin = new OILM_Plugin();
	$plugin->run();
}
run_outpace_internal_link_manager();
