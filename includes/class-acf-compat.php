<?php

class OILM_ACF_Compat {

	private $processor;

	public function __construct( $processor ) {
		$this->processor = $processor;
	}

	public function init() {
		add_action( 'plugins_loaded', array( $this, 'maybe_register_acf_filter' ) );

		// Safety net: strip plugin links from excluded areas in the final HTML output.
		// This catches any links that ACF processing added to field values
		// before they were placed inside <header>, <nav>, or <footer> elements.
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ) );
	}

	public function maybe_register_acf_filter() {
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		$settings = get_option( 'oilm_settings' );
		if ( empty( $settings['enable_plugin'] ) ) {
			return;
		}

		add_filter( 'acf/format_value', array( $this, 'process_acf_field' ), 99, 3 );
	}

	public function process_acf_field( $value, $post_id, $field ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

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

	public function start_output_buffer() {
		ob_start( array( $this, 'strip_excluded_links' ) );
	}

	public function strip_excluded_links( $html ) {
		if ( empty( $html ) || stripos( $html, 'op-internal-link' ) === false ) {
			return $html;
		}

		// Collect all CSS selectors for excluded areas
		$excluded = array(
			// Tag-based
			'header', 'nav', 'footer',
			// Class-based
			'.navbar', '.site-header', '.main-navigation', '.navigation',
			'.menu-container', '.sub-menu', '.children', '.menu-item-has-children',
			'.page_item_has_children', '.elementor-location-header',
			// ID-based
			'#header', '#nav',
		);

		$settings = get_option( 'oilm_settings' );
		if ( isset( $settings['exclude_elements'] ) && is_array( $settings['exclude_elements'] ) ) {
			$excluded = array_merge( $excluded, $settings['exclude_elements'] );
		}

		// Build patterns: for each excluded container, find <a class="op-internal-link">
		// and replace it with just its text content.
		$link_regex = '#<a\b[^>]*class="[^"]*\bop-internal-link\b[^"]*"[^>]*>(.*?)</a>#is';

		foreach ( $excluded as $sel ) {
			$sel = trim( $sel );
			if ( $sel === '' ) continue;

			if ( $sel[0] === '#' ) {
				$id = preg_quote( substr( $sel, 1 ), '#' );
				$html = preg_replace_callback(
					'#<(\w+)[^>]*\bid\s*=\s*["\']' . $id . '["\'][^>]*>.*?</\1>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$1', $m[0] );
					},
					$html
				);
			} elseif ( $sel[0] === '.' ) {
				$class = preg_quote( substr( $sel, 1 ), '#' );
				$html = preg_replace_callback(
					'#<(\w+)[^>]*\bclass\s*=\s*["\'][^"]*\b' . $class . '\b[^"]*["\'][^>]*>.*?</\1>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$1', $m[0] );
					},
					$html
				);
			} else {
				$tag = preg_quote( $sel, '#' );
				$html = preg_replace_callback(
					'#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$1', $m[0] );
					},
					$html
				);
			}
		}

		return $html;
	}
}
