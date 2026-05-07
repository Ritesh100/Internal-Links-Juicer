<?php

class OILM_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function enqueue_styles( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'op-internal-link-manager' ) === false ) {
			return;
		}
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_style( $this->plugin_name, OILM_PLUGIN_URL . 'assets/admin.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'op-internal-link-manager' ) === false ) {
			return;
		}
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
		wp_enqueue_script( $this->plugin_name, OILM_PLUGIN_URL . 'assets/admin.js', array( 'jquery', 'select2' ), $this->version, true );
	}

	public function add_plugin_admin_menu() {
		// Main Menu Page
		add_menu_page(
			__( 'Internal Links', 'op-internal-link-manager' ),
			__( 'Internal Links', 'op-internal-link-manager' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_rules_page' ),
			'dashicons-admin-links',
			30
		);

		// Submenus
		add_submenu_page(
			$this->plugin_name,
			__( 'Link Rules', 'op-internal-link-manager' ),
			__( 'Link Rules', 'op-internal-link-manager' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_rules_page' )
		);

		add_submenu_page(
			$this->plugin_name,
			__( 'Settings', 'op-internal-link-manager' ),
			__( 'Settings', 'op-internal-link-manager' ),
			'manage_options',
			$this->plugin_name . '-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			$this->plugin_name,
			__( 'Reports', 'op-internal-link-manager' ),
			__( 'Reports', 'op-internal-link-manager' ),
			'manage_options',
			$this->plugin_name . '-reports',
			array( $this, 'display_reports_page' )
		);
	}

	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'oilm_dashboard_widget',
			'Internal Links',
			array( $this, 'display_dashboard_widget' )
		);
	}

	public function display_dashboard_widget() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'oilm_rules';
		
		// Fallback check if table doesn't exist yet
		if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
			echo '<p>Please activate the plugin to see stats.</p>';
			return;
		}

		$active_rules = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE is_active = 1" );
		$total_insertions = $wpdb->get_var( "SELECT SUM(insert_count) FROM $table_name" );
		$total_insertions = $total_insertions ? $total_insertions : 0;

		echo '<ul>';
		echo '<li><strong>Active Rules:</strong> ' . absint( $active_rules ) . '</li>';
		echo '<li><strong>Total Links Inserted:</strong> ' . absint( $total_insertions ) . '</li>';
		echo '</ul>';
		echo '<p><a href="' . admin_url('admin.php?page=op-internal-link-manager') . '" class="button button-primary">Manage Rules</a></p>';
	}

	public function display_rules_page() {
		require_once OILM_PLUGIN_DIR . 'includes/class-link-rules.php';
		$rules_page = new OILM_Link_Rules();
		$rules_page->render_page();
	}

	public function display_settings_page() {
		require_once OILM_PLUGIN_DIR . 'includes/class-settings.php';
		$settings_page = new OILM_Settings();
		$settings_page->render_page();
	}

	public function display_reports_page() {
		require_once OILM_PLUGIN_DIR . 'includes/class-reports.php';
		$reports_page = new OILM_Reports();
		$reports_page->render_page();
	}
}
