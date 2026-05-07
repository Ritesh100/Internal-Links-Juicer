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
            allowClear: true,
            minimumInputLength: 2,
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
            },
            templateResult: function(item) {
                if (item.loading) {
                    return item.text;
                }

                var $result = $('<span class="oilm-link-result"></span>');
                $('<span class="oilm-link-result-title"></span>').text(item.text).appendTo($result);

                if (item.info) {
                    $('<span class="oilm-link-result-meta"></span>').text(item.info).appendTo($result);
                }

                if (item.url) {
                    $('<span class="oilm-link-result-url"></span>').text(item.url).appendTo($result);
                }

                return $result;
            },
            templateSelection: function(item) {
                return item.text || item.id || '';
            }
        }).on('select2:select', function (e) {
            var data = e.params.data;
            $('#url').val(data.id);
        });
    }
});
