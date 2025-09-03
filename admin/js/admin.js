jQuery(document).ready(function($) {
    function load_memberships() {
        var status = $('#status-filter').val();
        var renewal_date = $('#renewal-date-filter').val();

        $('#memberships-list-container').html('<tr><td colspan="5">Loading...</td></tr>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'filter_memberships',
                status: status,
                renewal_date: renewal_date
            },
            success: function(response) {
                if (response.success) {
                    $('#memberships-list-container').html(response.data.html);
                } else {
                    $('#memberships-list-container').html('<tr><td colspan="5">An error occurred.</td></tr>');
                }
            },
            error: function() {
                $('#memberships-list-container').html('<tr><td colspan="5">An error occurred.</td></tr>');
            }
        });
    }

    $('#filter-button').on('click', function() {
        load_memberships();
    });

    // Initial load
    load_memberships();
});
