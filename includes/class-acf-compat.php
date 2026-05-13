<?php

class OILM_ACF_Compat {

	private $processor;

	public function __construct( $processor ) {
		$this->processor = $processor;
	}

	public function init() {
		// ACF fields are processed at the full HTML level through the existing
		// content filters (the_content, elementor/frontend/the_content, widget_text, etc.)
		// where the XPath structural exclusions (header, nav, footer, .sub-menu, etc.)
		// can properly prevent links inside nav/header/footer areas.
		//
		// We do NOT hook into acf/format_value here because field-level processing
		// happens before the HTML structure context exists, so links added at the
		// field level cannot be excluded by XPath rules and end up inside alt
		// attributes, nav items, etc.
	}
}
