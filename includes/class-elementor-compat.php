<?php

class OILM_Elementor_Compat {

	private $processor;

	public function __construct( $processor ) {
		$this->processor = $processor;
	}

	public function init() {
		$settings = get_option('oilm_settings');
		$is_enabled = isset($settings['enable_elementor']) ? $settings['enable_elementor'] : 1;

		if ( ! $is_enabled ) {
			return;
		}

		// Filter Elementor frontend content rendering
		add_filter( 'elementor/frontend/the_content', array( $this->processor, 'process_content' ), 99 );
		
		// Filter specific text-based widgets in Elementor
		add_filter( 'elementor/widget/render_content', array( $this, 'process_widget_content' ), 99, 2 );
	}

	public function process_widget_content( $content, $widget ) {
		// Only process frontend, not editor
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return $content;
		}

		// Process specific widgets that typically hold text
		$allowed_widgets = array( 'text-editor', 'icon-box' );
		if ( in_array( $widget->get_name(), $allowed_widgets ) ) {
			return $this->processor->process_content( $content );
		}

		return $content;
	}
}
