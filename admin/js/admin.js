jQuery(document).ready(function($) {
    function update_status_counts(counts) {
        $('#active-count').text(counts.active || 0);
        $('#expired-count').text(counts.expired || 0);
        $('#pending-cancel-count').text(counts['pending-cancel'] || 0);
        $('#cancelled-count').text(counts.cancelled || 0);
        $('#total-count').text(counts.total || 0);
    }

    function load_memberships() {
        var status = $('#status-filter').val();
        var renewal_date = $('#renewal-date-filter').val();

        $('#memberships-list-container').html('<tr><td colspan="6">Loading...</td></tr>');

        $.ajax({
            url: membership_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_memberships',
                nonce: membership_ajax.nonce,
                status: status,
                renewal_date: renewal_date
            },
            success: function(response) {
                if (response.success) {
                    $('#memberships-list-container').html(response.data.html);
                    
                    // Update status counts
                    if (response.data.counts) {
                        update_status_counts(response.data.counts);
                    }
                } else {
                    $('#memberships-list-container').html('<tr><td colspan="6">' + (response.data || 'An error occurred.') + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', status, error);
                $('#memberships-list-container').html('<tr><td colspan="6">An error occurred while loading memberships.</td></tr>');
            }
        });
    }

    $('#filter-button').on('click', function() {
        load_memberships();
    });

    // Filter change events
    $('#status-filter').on('change', function() {
        load_memberships();
    });

    // Initial load
    load_memberships();
});
