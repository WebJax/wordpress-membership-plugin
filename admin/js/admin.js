jQuery(document).ready(function($) {
    var currentSortColumn = 'end_date';
    var currentSortOrder = 'ASC';
    
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
                renewal_date: renewal_date,
                sort_column: currentSortColumn,
                sort_order: currentSortOrder
            },
            success: function(response) {
                if (response.success) {
                    $('#memberships-list-container').html(response.data.html);
                    
                    // Update status counts
                    if (response.data.counts) {
                        update_status_counts(response.data.counts);
                    }
                    
                    // Update sort indicators
                    updateSortIndicators();
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

    function updateSortIndicators() {
        // Reset all icons
        $('.sort-link .dashicons').removeClass('dashicons-arrow-up dashicons-arrow-down').addClass('dashicons-sort');
        
        // Update active sort icon
        var $activeHeader = $('.sortable[data-column="' + currentSortColumn + '"]');
        var $icon = $activeHeader.find('.dashicons');
        
        $icon.removeClass('dashicons-sort');
        if (currentSortOrder === 'ASC') {
            $icon.addClass('dashicons-arrow-up');
        } else {
            $icon.addClass('dashicons-arrow-down');
        }
    }

    // Handle column sorting
    $('.sortable .sort-link').on('click', function(e) {
        e.preventDefault();
        
        var column = $(this).closest('.sortable').data('column');
        
        // Toggle sort order if same column, otherwise default to ASC
        if (column === currentSortColumn) {
            currentSortOrder = (currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
        } else {
            currentSortColumn = column;
            currentSortOrder = 'ASC';
        }
        
        load_memberships();
    });

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
