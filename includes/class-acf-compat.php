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
		// Avoid running in the backend to preserve raw data for editors
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			if ( '' === trim( $value ) ) {
				return $value;
			}
			return $this->processor->process_content( $value );
		}

		if ( is_array( $value ) ) {
			return $this->process_array_value( $value, $post_id, $field );
		}

		return $value;
	}

	private function process_array_value( $value, $post_id, $field ) {
		foreach ( $value as $key => $val ) {
			if ( is_string( $val ) ) {
				if ( '' !== trim( $val ) ) {
					$value[ $key ] = $this->processor->process_content( $val );
				}
			} elseif ( is_array( $val ) ) {
				$value[ $key ] = $this->process_array_value( $val, $post_id, $field );
			}
		}
		return $value;
	}
}
