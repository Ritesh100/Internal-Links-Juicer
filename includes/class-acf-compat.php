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
		if ( empty( $html ) ) {
			return $html;
		}

		$link_regex = $this->get_generated_link_regex();
		if ( ! preg_match( $link_regex, $html ) ) {
			return $html;
		}

		// Collect all CSS selectors for excluded areas
		$excluded = array(
			// Tag-based
			'header', 'nav', 'footer',
			// Class-based
			'.navbar', '.site-header', '.main-navigation', '.navigation',
			'.menu', '.menu-item', '.menu-container', '.nav-menu',
			'.wp-block-navigation', '.wp-block-navigation-item',
			'.sub-menu', '.children', '.menu-item-has-children',
			'.page_item_has_children', '.elementor-location-header',
			// ID-based
			'#header', '#nav',
			// Attribute-based
			'[role="navigation"]',
		);

		$settings = get_option( 'oilm_settings' );
		if ( ! empty( $settings['exclude_headings'] ) ) {
			$excluded = array_merge( $excluded, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) );
		}

		if ( isset( $settings['exclude_elements'] ) && is_array( $settings['exclude_elements'] ) ) {
			$excluded = array_merge( $excluded, $settings['exclude_elements'] );
		}

		foreach ( $excluded as $sel ) {
			$sel = trim( $sel );
			if ( $sel === '' ) continue;

			if ( $sel[0] === '#' ) {
				$id = preg_quote( substr( $sel, 1 ), '#' );
				$html = preg_replace_callback(
					'#<(\w+)[^>]*\bid\s*=\s*["\']' . $id . '["\'][^>]*>.*?</\1>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$2', $m[0] );
					},
					$html
				);
			} elseif ( $sel[0] === '.' ) {
				$class = preg_quote( substr( $sel, 1 ), '#' );
				$html = preg_replace_callback(
					'#<(\w+)[^>]*\bclass\s*=\s*(["\'])[^"\']*\b' . $class . '\b[^"\']*\2[^>]*>.*?</\1>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$2', $m[0] );
					},
					$html
				);
			} elseif ( preg_match( '/^\[role=["\']?([^"\']+)["\']?\]$/', $sel, $role_match ) ) {
				$role = preg_quote( $role_match[1], '#' );
				$html = preg_replace_callback(
					'#<(\w+)[^>]*\brole\s*=\s*["\']' . $role . '["\'][^>]*>.*?</\1>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$2', $m[0] );
					},
					$html
				);
			} else {
				$tag = preg_quote( $sel, '#' );
				$html = preg_replace_callback(
					'#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is',
					function( $m ) use ( $link_regex ) {
						return preg_replace( $link_regex, '$2', $m[0] );
					},
					$html
				);
			}
		}

		return $html;
	}

	private function get_generated_link_regex() {
		$settings = get_option( 'oilm_settings' );
		$class_value = isset( $settings['link_css_class'] ) ? $settings['link_css_class'] : 'op-internal-link';
		$classes = preg_split( '/\s+/', trim( (string) $class_value ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		if ( empty( $classes ) ) {
			$classes = array( 'op-internal-link' );
		}

		$class_pattern = implode( '|', array_map( 'preg_quote', $classes ) );

		return '#<a\b[^>]*\bclass\s*=\s*(["\'])[^"\']*\b(?:' . $class_pattern . ')\b[^"\']*\1[^>]*>(.*?)</a>#is';
	}
}
