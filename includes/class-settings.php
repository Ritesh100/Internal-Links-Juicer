<?php

class OILM_Settings {

	public function register_settings() {
		register_setting( 'oilm_settings_group', 'oilm_settings' );

		// General Tab
		add_settings_section( 'oilm_general_settings', __( 'General Settings', 'internal-link-manager' ), array( $this, 'general_settings_cb' ), 'oilm_settings_general' );
		add_settings_field( 'enable_plugin', __( 'Enable Plugin', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'enable_plugin' ) );
		add_settings_field( 'global_max_links', __( 'Global Max Links per Page', 'internal-link-manager' ), array( $this, 'render_number' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'global_max_links', 'desc' => '0 for unlimited' ) );
		add_settings_field( 'global_max_url_links', __( 'Global Max Links per Target URL per Page', 'internal-link-manager' ), array( $this, 'render_number' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'global_max_url_links', 'desc' => '0 for unlimited' ) );
		add_settings_field( 'default_new_tab', __( 'Open links in new tab default', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'default_new_tab' ) );
		add_settings_field( 'default_nofollow', __( 'Add nofollow default', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'default_nofollow' ) );

		// Targeting Tab
		add_settings_section( 'oilm_targeting_settings', __( 'Content Targeting', 'internal-link-manager' ), array( $this, 'targeting_settings_cb' ), 'oilm_settings_targeting' );
		add_settings_field( 'enabled_post_types', __( 'Enabled Post Types', 'internal-link-manager' ), array( $this, 'render_post_types' ), 'oilm_settings_targeting', 'oilm_targeting_settings' );
		add_settings_field( 'process_excerpts', __( 'Process Excerpts', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'process_excerpts', 'desc' => 'Add links inside post excerpts.' ) );
		add_settings_field( 'process_comments', __( 'Process Comments', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'process_comments', 'desc' => 'Add links inside user comments.' ) );
		add_settings_field( 'enable_elementor', __( 'Enable Elementor Compatibility', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'enable_elementor' ) );

		// Exclusions Tab
		add_settings_section( 'oilm_exclusions_settings', __( 'Exclusions', 'internal-link-manager' ), array( $this, 'exclusions_settings_cb' ), 'oilm_settings_exclusions' );
		add_settings_field( 'exclude_post_ids', __( 'Exclude Post/Page IDs', 'internal-link-manager' ), array( $this, 'render_text' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_post_ids', 'desc' => 'Comma-separated list of Post IDs to never process.' ) );
		add_settings_field( 'exclude_headings', __( 'Exclude Headings', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_headings', 'desc' => 'Do not add links inside H1-H6 tags.' ) );
		add_settings_field( 'exclude_existing_links', __( 'Exclude Existing Links', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_existing_links', 'desc' => 'Do not add links inside existing anchor tags.' ) );
		add_settings_field( 'exclude_elements', __( 'Exclude Specific HTML Elements', 'internal-link-manager' ), array( $this, 'render_exclude_elements' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings' );

		// Advanced Tab
		add_settings_section( 'oilm_advanced_settings', __( 'Advanced Configuration', 'internal-link-manager' ), array( $this, 'advanced_settings_cb' ), 'oilm_settings_advanced' );
		add_settings_field( 'enable_pluralization', __( 'Enable Pluralization', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'enable_pluralization', 'desc' => 'Automatically match basic plural forms (e.g., adding "s" or "es") of keywords.' ) );
		add_settings_field( 'first_occurrence_only', __( 'First Occurrence Only', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'first_occurrence_only', 'desc' => 'Only link the very first matched keyword on the entire page, then stop processing.' ) );
		add_settings_field( 'debug_mode', __( 'Debug Mode', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'debug_mode' ) );
		add_settings_field( 'remove_data_on_uninstall', __( 'Remove Data on Uninstall', 'internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'remove_data_on_uninstall', 'desc' => 'Delete all rules and settings if the plugin is uninstalled.' ) );
	}

	public function general_settings_cb() { echo '<p>' . __( 'Configure global defaults and limits.', 'internal-link-manager' ) . '</p>'; }
	public function targeting_settings_cb() { echo '<p>' . __( 'Configure where links should be inserted.', 'internal-link-manager' ) . '</p>'; }
	public function exclusions_settings_cb() { echo '<p>' . __( 'Configure where links should NOT be inserted.', 'internal-link-manager' ) . '</p>'; }
	public function advanced_settings_cb() { echo '<p>' . __( 'Advanced matching and system configurations.', 'internal-link-manager' ) . '</p>'; }

	public function render_checkbox( $args ) {
		$options = get_option( 'oilm_settings' );
		$id = $args['id'];
		$checked = isset( $options[$id] ) ? $options[$id] : 0;
		echo '<input type="checkbox" name="oilm_settings[' . esc_attr($id) . ']" value="1" ' . checked( 1, $checked, false ) . ' />';
		if ( isset( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	public function render_text( $args ) {
		$options = get_option( 'oilm_settings' );
		$id = $args['id'];
		$val = isset( $options[$id] ) ? $options[$id] : '';
		echo '<input type="text" name="oilm_settings[' . esc_attr($id) . ']" value="' . esc_attr($val) . '" class="regular-text" />';
		if ( isset( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	public function render_number( $args ) {
		$options = get_option( 'oilm_settings' );
		$id = $args['id'];
		$val = isset( $options[$id] ) ? absint($options[$id]) : 0;
		echo '<input type="number" name="oilm_settings[' . esc_attr($id) . ']" value="' . esc_attr($val) . '" min="0" />';
		if ( isset( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	public function render_post_types() {
		$options = get_option( 'oilm_settings' );
		$enabled = isset( $options['enabled_post_types'] ) && is_array( $options['enabled_post_types'] ) ? $options['enabled_post_types'] : array('post', 'page');
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		
		echo '<select name="oilm_settings[enabled_post_types][]" multiple="multiple" class="oilm-select2 regular-text">';
		foreach ( $post_types as $pt ) {
			if ( $pt->name === 'attachment' ) continue;
			$selected = in_array( $pt->name, $enabled ) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr( $pt->name ) . '" ' . $selected . '>' . esc_html( $pt->label ) . '</option>';
		}
		echo '</select>';
	}

	public function render_exclude_elements() {
		$options = get_option( 'oilm_settings' );
		$excluded = isset( $options['exclude_elements'] ) && is_array( $options['exclude_elements'] ) ? $options['exclude_elements'] : array();
		
		$elements = array( 'blockquote', 'strong', 'em', 'i', 'b', 'ul', 'ol', 'li', 'table', 'span' );
		
		echo '<select name="oilm_settings[exclude_elements][]" multiple="multiple" class="oilm-select2 regular-text">';
		foreach ( $elements as $el ) {
			$selected = in_array( $el, $excluded ) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr( $el ) . '" ' . $selected . '>&lt;' . esc_html( $el ) . '&gt;</option>';
		}
		echo '</select>';
		echo '<p class="description">Select HTML elements where links should NEVER be inserted.</p>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=internal-link-manager-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
				<a href="?page=internal-link-manager-settings&tab=targeting" class="nav-tab <?php echo $active_tab == 'targeting' ? 'nav-tab-active' : ''; ?>">Targeting</a>
				<a href="?page=internal-link-manager-settings&tab=exclusions" class="nav-tab <?php echo $active_tab == 'exclusions' ? 'nav-tab-active' : ''; ?>">Exclusions</a>
				<a href="?page=internal-link-manager-settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
			</h2>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'oilm_settings_group' );
				do_settings_sections( 'oilm_settings_' . $active_tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
