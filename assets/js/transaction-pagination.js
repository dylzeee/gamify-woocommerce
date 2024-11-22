jQuery(document).ready(function ($) {
    function loadTransactions(page = 1) {
        const container = $('#gamify-transaction-container');
        const userId = container.data('user-id');

        $.ajax({
            url: gamifyTransactions.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_transactions',
                security: gamifyTransactions.security,
                user_id: userId,
                page: page,
            },
            beforeSend: function () {
                container.html('<p>Loading transactions...</p>');
            },
            success: function (response) {
                if (response.success) {
                    container.html(response.data.table_html);

                    // Render pagination.
                    const pagination = response.data.pagination;
                    const paginationContainer = $('#gamify-transaction-pagination');
                    paginationContainer.empty();

                    for (let i = 1; i <= pagination.max_pages; i++) {
                        const activeClass = i === pagination.current_page ? ' active' : '';
                        paginationContainer.append(`<button class="gamify-pagination-button${activeClass}" data-page="${i}">${i}</button>`);
                    }
                } else {
                    container.html('<p>Error loading transactions. Please try again.</p>');
                }
            },
        });
    }

    // Initial load.
    loadTransactions();

    // Handle pagination clicks.
    $(document).on('click', '.gamify-pagination-button', function () {
        const page = $(this).data('page');
        loadTransactions(page);
    });
});
