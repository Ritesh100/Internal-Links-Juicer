<?php

class OILM_ACF_Compat {

	private $processor;

	public function __construct( $processor ) {
		$this->processor = $processor;
	}

	public function init() {
		// Defer registration to plugins_loaded to ensure ACF is loaded first
		add_action( 'plugins_loaded', array( $this, 'maybe_register_acf_filter' ) );
	}

	public function maybe_register_acf_filter() {
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		$settings = get_option('oilm_settings');
		$is_enabled = isset($settings['enable_plugin']) ? $settings['enable_plugin'] : 1;

		if ( ! $is_enabled ) {
			return;
		}

		add_filter( 'acf/format_value', array( $this, 'process_acf_field' ), 99, 3 );
	}

	public function process_acf_field( $value, $post_id, $field ) {
		// Avoid running in the backend to preserve raw data for editors
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		// Skip processing for user-excluded ACF fields (used in nav menus, etc.)
		$settings = get_option( 'oilm_settings' );
		if ( ! empty( $settings['exclude_acf_fields'] ) && is_array( $settings['exclude_acf_fields'] ) ) {
			$field_name = isset( $field['name'] ) ? $field['name'] : '';
			$field_key  = isset( $field['key'] ) ? $field['key'] : '';
			if ( in_array( $field_name, $settings['exclude_acf_fields'], true ) || in_array( $field_key, $settings['exclude_acf_fields'], true ) ) {
				return $value;
			}
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
