/* Internal Link Manager Admin JS */

jQuery(document).ready(function($) {
	// Initialize Select2 on select boxes with class oilm-select2
	if ($.fn.select2) {
		$('.oilm-select2').select2({
			placeholder: "Select options...",
			allowClear: true
		});
	}
});
