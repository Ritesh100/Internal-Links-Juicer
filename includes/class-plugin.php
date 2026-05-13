<?php

class OILM_Plugin {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'OILM_VERSION' ) ) {
			$this->version = OILM_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'op-internal-link-manager';

		$this->load_dependencies();
		OILM_Activator::maybe_upgrade();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once OILM_PLUGIN_DIR . 'includes/class-activator.php';
		require_once OILM_PLUGIN_DIR . 'includes/class-admin.php';
		require_once OILM_PLUGIN_DIR . 'includes/class-settings.php';
		
		require_once OILM_PLUGIN_DIR . 'includes/class-github-updater.php';

		if ( is_admin() ) {
			require_once OILM_PLUGIN_DIR . 'includes/class-link-rules.php';
			require_once OILM_PLUGIN_DIR . 'includes/class-reports.php';
		}

		require_once OILM_PLUGIN_DIR . 'includes/class-content-processor.php';
		require_once OILM_PLUGIN_DIR . 'includes/class-elementor-compat.php';
		require_once OILM_PLUGIN_DIR . 'includes/class-woocommerce-compat.php';
		require_once OILM_PLUGIN_DIR . 'includes/class-acf-compat.php';
	}

	private function define_admin_hooks() {
		$plugin_admin = new OILM_Admin( $this->get_plugin_name(), $this->get_version() );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup', array( $plugin_admin, 'register_dashboard_widget' ) );
		add_filter( 'plugin_action_links_' . OILM_PLUGIN_BASENAME, array( $plugin_admin, 'plugin_action_links' ) );

		$plugin_settings = new OILM_Settings();
		add_action( 'admin_init', array( $plugin_settings, 'register_settings' ) );

		$github_updater = new OILM_GitHub_Updater( OILM_PLUGIN_DIR . 'op-internal-link-manager.php', $this->get_version(), OILM_GITHUB_OWNER, OILM_GITHUB_REPO, OILM_GITHUB_BRANCH );
		$github_updater->init();

		if ( is_admin() ) {
			$plugin_rules = new OILM_Link_Rules();
			add_action( 'admin_post_oilm_save_rule', array( $plugin_rules, 'save_rule' ) );
			add_action( 'admin_post_oilm_delete_rule', array( $plugin_rules, 'delete_rule' ) );
			add_action( 'wp_ajax_oilm_search_links', array( $plugin_rules, 'search_links' ) );
		}
	}

	private function define_public_hooks() {
		$settings = get_option('oilm_settings');
		$is_enabled = isset($settings['enable_plugin']) ? $settings['enable_plugin'] : 1;

		if ( ! $is_enabled ) {
			return;
		}

		$processor = new OILM_Content_Processor();
		
		// Priority 99 to ensure it runs late after most shortcodes and formatting
		add_filter( 'the_content', array( $processor, 'process_content' ), 99 );

		if ( isset( $settings['process_excerpts'] ) && $settings['process_excerpts'] ) {
			add_filter( 'get_the_excerpt', array( $processor, 'process_content' ), 99 );
		}

		if ( isset( $settings['process_comments'] ) && $settings['process_comments'] ) {
			add_filter( 'comment_text', array( $processor, 'process_content' ), 99 );
		}

		$elementor_compat = new OILM_Elementor_Compat( $processor );
		$elementor_compat->init();

		$wc_compat = new OILM_WooCommerce_Compat( $processor );
		$wc_compat->init();

		$acf_compat = new OILM_ACF_Compat( $processor );
		$acf_compat->init();

		// Process navigation menus
		add_filter( 'wp_nav_menu_items', array( $processor, 'process_content' ), 99 );
		add_filter( 'render_block_core/navigation', array( $processor, 'process_content' ), 99 );

		// Process widget content (footer, sidebar, etc.)
		add_filter( 'widget_text', array( $processor, 'process_content' ), 99 );
		add_filter( 'widget_block_content', array( $processor, 'process_content' ), 99 );

		// Process rendered blocks in header/footer areas
		add_filter( 'render_block_core/site-title', array( $processor, 'process_content' ), 99 );
		add_filter( 'render_block_core/site-tagline', array( $processor, 'process_content' ), 99 );
	}

	public function run() {
		// Execution starts via hooks
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}
}
