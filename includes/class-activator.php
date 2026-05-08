<?php

class OILM_Activator {

	public static function activate() {
		self::create_tables();
		self::set_default_options();
	}

	public static function maybe_upgrade() {
		$db_version = get_option( 'oilm_db_version' );

		if ( $db_version !== OILM_VERSION ) {
			self::create_tables();
			self::normalize_location_counts();
			self::set_default_options();
		}
	}

	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$rules_table_name = $wpdb->prefix . 'oilm_rules';
		$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';

		$sql = "CREATE TABLE $rules_table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			keywords text NOT NULL,
			url varchar(255) NOT NULL,
			title_attr varchar(255) DEFAULT '' NOT NULL,
			is_exact_match tinyint(1) DEFAULT 0 NOT NULL,
			open_new_tab tinyint(1) DEFAULT 0 NOT NULL,
			is_nofollow tinyint(1) DEFAULT 0 NOT NULL,
			is_sponsored tinyint(1) DEFAULT 0 NOT NULL,
			max_links_per_page int(11) DEFAULT 0 NOT NULL,
			max_uses_per_keyword int(11) DEFAULT 0 NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			priority int(11) DEFAULT 10 NOT NULL,
			insert_count bigint(20) DEFAULT 0 NOT NULL,
			last_inserted_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;

		CREATE TABLE $locations_table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) NOT NULL,
			post_id bigint(20) NOT NULL,
			source_type varchar(30) DEFAULT 'content' NOT NULL,
			insert_count bigint(20) DEFAULT 0 NOT NULL,
			last_keyword varchar(255) DEFAULT '' NOT NULL,
			first_inserted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			last_inserted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule_post_source (rule_id, post_id, source_type),
			KEY rule_id (rule_id),
			KEY post_id (post_id),
			KEY last_inserted_at (last_inserted_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( 'oilm_db_version', OILM_VERSION );
	}

	private static function normalize_location_counts() {
		global $wpdb;

		$rules_table_name = $wpdb->prefix . 'oilm_rules';
		$locations_table_name = $wpdb->prefix . 'oilm_insertion_locations';

		$suppress = $wpdb->suppress_errors();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $locations_table_name ) );
		$wpdb->suppress_errors( $suppress );

		if ( $table_exists !== $locations_table_name ) {
			return;
		}

		$wpdb->query( "UPDATE $locations_table_name SET insert_count = 1 WHERE insert_count > 1" );
		$wpdb->query(
			"UPDATE $rules_table_name rules
			INNER JOIN (
				SELECT rule_id, SUM(insert_count) AS total_insertions, MAX(last_inserted_at) AS last_inserted_at
				FROM $locations_table_name
				GROUP BY rule_id
			) locations ON locations.rule_id = rules.id
			SET rules.insert_count = locations.total_insertions,
				rules.last_inserted_at = locations.last_inserted_at"
		);
	}

	private static function set_default_options() {
		$default_settings = array(
			'global_max_links' => 0,
			'global_max_url_links' => 0,
			'link_css_class' => 'op-internal-link',
			'enable_plugin' => 1,
			'enabled_post_types' => array('post', 'page'),
			'enable_elementor' => 1,
			'exclude_headings' => 1,
			'exclude_existing_links' => 1,
			'default_new_tab' => 0,
			'default_nofollow' => 0,
			'global_override_rule_attributes' => 0,
			'debug_mode' => 0,
			'remove_data_on_uninstall' => 0,
			// New Advanced Options
			'process_excerpts' => 0,
			'process_comments' => 0,
			'exclude_post_ids' => '',
			'exclude_elements' => array(),
			'enable_pluralization' => 0,
			'first_occurrence_only' => 0,
		);

		// Only add if not exists to not overwrite existing user settings during update
		if ( ! get_option( 'oilm_settings' ) ) {
			add_option( 'oilm_settings', $default_settings );
		} else {
			// Merge new defaults into existing settings for upgrades
			$existing = get_option( 'oilm_settings' );
			$updated = wp_parse_args( $existing, $default_settings );
			update_option( 'oilm_settings', $updated );
		}
	}

}
