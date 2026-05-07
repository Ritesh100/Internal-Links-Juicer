<?php

class OILM_Settings {

	public function register_settings() {
		register_setting( 'oilm_settings_group', 'oilm_settings' );

		// General Tab
		add_settings_section( 'oilm_general_settings', __( 'General Settings', 'op-internal-link-manager' ), array( $this, 'general_settings_cb' ), 'oilm_settings_general' );
		add_settings_field( 'enable_plugin', __( 'Enable Plugin', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'enable_plugin' ) );
		add_settings_field( 'global_max_links', __( 'Global Max Links per Page', 'op-internal-link-manager' ), array( $this, 'render_number' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'global_max_links', 'desc' => '0 for unlimited' ) );
		add_settings_field( 'global_max_url_links', __( 'Global Max Links per Target URL per Page', 'op-internal-link-manager' ), array( $this, 'render_number' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'global_max_url_links', 'desc' => '0 for unlimited' ) );
		add_settings_field( 'default_new_tab', __( 'Open links in new tab default', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'default_new_tab' ) );
		add_settings_field( 'default_nofollow', __( 'Add nofollow default', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_general', 'oilm_general_settings', array( 'id' => 'default_nofollow' ) );

		// Targeting Tab
		add_settings_section( 'oilm_targeting_settings', __( 'Content Targeting', 'op-internal-link-manager' ), array( $this, 'targeting_settings_cb' ), 'oilm_settings_targeting' );
		add_settings_field( 'enabled_post_types', __( 'Enabled Post Types', 'op-internal-link-manager' ), array( $this, 'render_post_types' ), 'oilm_settings_targeting', 'oilm_targeting_settings' );
		add_settings_field( 'process_excerpts', __( 'Process Excerpts', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'process_excerpts', 'desc' => 'Add links inside post excerpts.' ) );
		add_settings_field( 'process_comments', __( 'Process Comments', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'process_comments', 'desc' => 'Add links inside user comments.' ) );
		add_settings_field( 'enable_elementor', __( 'Enable Elementor Compatibility', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_targeting', 'oilm_targeting_settings', array( 'id' => 'enable_elementor' ) );

		// Exclusions Tab
		add_settings_section( 'oilm_exclusions_settings', __( 'Exclusions', 'op-internal-link-manager' ), array( $this, 'exclusions_settings_cb' ), 'oilm_settings_exclusions' );
		add_settings_field( 'exclude_post_ids', __( 'Exclude Post/Page IDs', 'op-internal-link-manager' ), array( $this, 'render_text' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_post_ids', 'desc' => 'Comma-separated list of Post IDs to never process.' ) );
		add_settings_field( 'exclude_headings', __( 'Exclude Headings', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_headings', 'desc' => 'Do not add links inside H1-H6 tags.' ) );
		add_settings_field( 'exclude_existing_links', __( 'Exclude Existing Links', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings', array( 'id' => 'exclude_existing_links', 'desc' => 'Do not add links inside existing anchor tags.' ) );
		add_settings_field( 'exclude_elements', __( 'Exclude Specific HTML Elements', 'op-internal-link-manager' ), array( $this, 'render_exclude_elements' ), 'oilm_settings_exclusions', 'oilm_exclusions_settings' );

		// Advanced Tab
		add_settings_section( 'oilm_advanced_settings', __( 'Advanced Configuration', 'op-internal-link-manager' ), array( $this, 'advanced_settings_cb' ), 'oilm_settings_advanced' );
		add_settings_field( 'enable_pluralization', __( 'Enable Pluralization', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'enable_pluralization', 'desc' => 'Automatically match basic plural forms (e.g., adding "s" or "es") of keywords.' ) );
		add_settings_field( 'first_occurrence_only', __( 'First Occurrence Only', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'first_occurrence_only', 'desc' => 'Only link the very first matched keyword on the entire page, then stop processing.' ) );
		add_settings_field( 'debug_mode', __( 'Debug Mode', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'debug_mode' ) );
		add_settings_field( 'remove_data_on_uninstall', __( 'Remove Data on Uninstall', 'op-internal-link-manager' ), array( $this, 'render_checkbox' ), 'oilm_settings_advanced', 'oilm_advanced_settings', array( 'id' => 'remove_data_on_uninstall', 'desc' => 'Delete all rules and settings if the plugin is uninstalled.' ) );
	}

	public function general_settings_cb() { echo '<p>' . __( 'Configure global defaults and limits.', 'op-internal-link-manager' ) . '</p>'; }
	public function targeting_settings_cb() { echo '<p>' . __( 'Configure where links should be inserted.', 'op-internal-link-manager' ) . '</p>'; }
	public function exclusions_settings_cb() { echo '<p>' . __( 'Configure where links should NOT be inserted.', 'op-internal-link-manager' ) . '</p>'; }
	public function advanced_settings_cb() { echo '<p>' . __( 'Advanced matching and system configurations.', 'op-internal-link-manager' ) . '</p>'; }

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

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$tabs = array(
			'general'    => __( 'General', 'internal-link-manager' ),
			'targeting'  => __( 'Targeting', 'internal-link-manager' ),
			'exclusions' => __( 'Exclusions', 'internal-link-manager' ),
			'advanced'   => __( 'Advanced', 'internal-link-manager' ),
		);

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap oilm-modern-wrap oilm-settings-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<nav class="nav-tab-wrapper oilm-settings-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'internal-link-manager' ); ?>">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=internal-link-manager-settings&tab=' . $tab ) ); ?>" class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>" <?php echo $active_tab === $tab ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form action="options.php" method="post" class="oilm-settings-card">
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
