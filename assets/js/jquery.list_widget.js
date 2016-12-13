(function ($) {
    ListWidget = {
        init: function () {
            this.initWidget();
        },
        initWidget: function () {
            $('.list-widget table').each(function () {
                var language = $('html').attr('lang');

                if ($.fn.dataTable.isDataTable(this)) {
                    $(this).DataTable();
                } else {
                    $(this).DataTable({
                        language: DATATABLE_MESSAGES[language] ? DATATABLE_MESSAGES[language] : DATATABLE_MESSAGES['en'],
                        stateSave: true,
                        columnDefs: [{
                            searchable: false,
                            orderable: false,
                            targets: 0
                        },
                            {
                                targets: 'no-sort',
                                orderable: false
                            }],
                        pagingType: 'full_numbers'
                    });
                }
            });
        }
    };

    $(document).ready(function () {
        ListWidget.init();
    });

})(jQuery);