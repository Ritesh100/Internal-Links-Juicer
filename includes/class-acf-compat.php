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

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
		}

		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Build XPath queries for all excluded areas (tags, classes, IDs)
		$exclusion_queries = array();

		// 1. Structural tag exclusions
		foreach ( array( 'header', 'nav', 'footer' ) as $tag ) {
			$exclusion_queries[] = "//{$tag}//a[contains(@class, 'op-internal-link')]";
		}

		// 2. Common class/ID exclusions matching parse_and_replace in content processor
		$extra_exclusions = array(
			'.navbar', '.site-header', '.main-navigation', '.navigation',
			'.menu-container', '.sub-menu', '.children', '.menu-item-has-children',
			'.page_item_has_children', '#header', '#nav', '.elementor-location-header'
		);

		// 3. User-configured exclusions from settings
		$settings = get_option( 'oilm_settings' );
		if ( isset( $settings['exclude_elements'] ) && is_array( $settings['exclude_elements'] ) ) {
			$extra_exclusions = array_merge( $extra_exclusions, $settings['exclude_elements'] );
		}

		foreach ( $extra_exclusions as $excl ) {
			$excl = trim( $excl );
			if ( '' === $excl ) {
				continue;
			}
			if ( $excl[0] === '.' ) {
				$class = substr( $excl, 1 );
				$exclusion_queries[] = "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]//a[contains(@class, 'op-internal-link')]";
			} elseif ( $excl[0] === '#' ) {
				$id = substr( $excl, 1 );
				$exclusion_queries[] = "//*[@id='$id']//a[contains(@class, 'op-internal-link')]";
			} else {
				$exclusion_queries[] = "//{$excl}//a[contains(@class, 'op-internal-link')]";
			}
		}

		// Strip matching links in all excluded areas
		foreach ( $exclusion_queries as $query ) {
			$links = $xpath->query( $query );
			foreach ( $links as $link ) {
				$text = $link->textContent;
				$link->parentNode->replaceChild( $dom->createTextNode( $text ), $link );
			}
		}

		return $dom->saveHTML();
	}
}
