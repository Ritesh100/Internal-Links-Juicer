<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    Internal_Link_Manager
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check setting to determine if data should be deleted
$options = get_option( 'oilm_settings' );
$remove_data_on_uninstall = isset( $options['remove_data_on_uninstall'] ) && $options['remove_data_on_uninstall'] == '1';

if ( $remove_data_on_uninstall ) {
	global $wpdb;

	$rules_table_name = $wpdb->prefix . 'oilm_rules';
	$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';

	// Delete tables
	$wpdb->query( "DROP TABLE IF EXISTS {$rules_table_name}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$locations_table_name}" );

	// Delete options
	delete_option( 'oilm_db_version' );
	delete_option( 'oilm_settings' );
	delete_transient( 'oilm_active_rules' );
}
