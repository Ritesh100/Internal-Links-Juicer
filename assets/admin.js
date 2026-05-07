jQuery(document).ready(function($) {
    if ($('.oilm-select2').length) {
        $('.oilm-select2').select2({
            width: '100%',
            placeholder: 'Select options...'
        });
    }

    if ($('#oilm-link-search').length) {
        $('#oilm-link-search').select2({
            width: '100%',
            placeholder: 'Search for internal content...',
            minimumInputLength: 3,
            ajax: {
                url: oilm_admin.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'oilm_search_links',
                        q: params.term,
                        nonce: oilm_admin.nonce
                    };
                },
                processResults: function (data) {
                    if (data.success) {
                        return {
                            results: data.data
                        };
                    }
                    return {
                        results: []
                    };
                },
                cache: true
            }
        }).on('select2:select', function (e) {
            var data = e.params.data;
            $('#url').val(data.id);
        });
    }
});
