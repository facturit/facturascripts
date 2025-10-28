function widgetTagBuildRequest(form, baseData, term) {
    const data = Object.assign({}, baseData);

    if (form && form.length > 0) {
        const rawForm = form.serializeArray();
        rawForm.forEach(function (input) {
            if (typeof data[input.name] === 'undefined') {
                data[input.name] = input.value;
            }
        });

        const activeTab = form.find('input[name="activetab"]').val();
        if (activeTab !== undefined) {
            data.activetab = activeTab;
        }
    }

    data.action = 'autocomplete';
    data.term = term || '';
    return data;
}

$(document).ready(function () {
    $('.widget-tag-select').each(function () {
        const $select = $(this);
        const form = $select.closest('form');
        const baseData = {
            field: $select.attr('data-field'),
            fieldcode: $select.attr('data-fieldcode'),
            fieldfilter: $select.attr('data-fieldfilter'),
            fieldtitle: $select.attr('data-fieldtitle'),
            source: $select.attr('data-source'),
            strict: $select.attr('data-strict') || '1'
        };
        const allowClear = !$select.prop('required');
        const isStrict = baseData.strict === '1';

        const select2Options = {
            width: 'style',
            theme: 'bootstrap-5',
            placeholder: $select.attr('data-placeholder') || '',
            allowClear: allowClear,
            multiple: true,
            closeOnSelect: false,
            ajax: {
                url: window.location.href,
                method: 'POST',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return widgetTagBuildRequest(form, baseData, params.term);
                },
                processResults: function (results) {
                    return {
                        results: results.map(function (element) {
                            const id = element.key === null ? '' : element.key;
                            return {
                                id: id,
                                text: element.value,
                                disabled: element.key === null
                            };
                        })
                    };
                }
            }
        };

        if (!isStrict) {
            select2Options.tags = true;
            select2Options.createTag = function (params) {
                const term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return {
                    id: term,
                    text: term
                };
            };
        }

        $select.select2(select2Options);
    });
});
