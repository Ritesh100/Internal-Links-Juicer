<?php

class OILM_ACF_Compat {

	private $processor;

	public function __construct( $processor ) {
		$this->processor = $processor;
	}

	public function init() {
		// Only apply if ACF is active
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		$settings = get_option('oilm_settings');
		$is_enabled = isset($settings['enable_plugin']) ? $settings['enable_plugin'] : 1;

		if ( ! $is_enabled ) {
			return;
		}

		// Process all ACF field types (accordion, text, textarea, wysiwyg, email, url, etc.)
		add_filter( 'acf/format_value', array( $this, 'process_acf_field' ), 99, 3 );
	}

	public function process_acf_field( $value, $post_id, $field ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}
		
		// Avoid running in the backend to preserve raw data for editors
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		return $this->processor->process_content( $value );
	}
}
