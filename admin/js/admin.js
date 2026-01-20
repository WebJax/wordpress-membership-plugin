jQuery(document).ready(function($) {
    var currentSortColumn = 'end_date';
    var currentSortOrder = 'ASC';
    var currentPage = 1;
    
    function update_status_counts(counts) {
        $('#active-count').text(counts.active || 0);
        $('#expired-count').text(counts.expired || 0);
        $('#pending-cancel-count').text(counts['pending-cancel'] || 0);
        $('#on-hold-count').text(counts['on-hold'] || 0);
        $('#cancelled-count').text(counts.cancelled || 0);
        $('#total-count').text(counts.total || 0);
    }
    
    function update_pagination(pagination) {
        var html = '';
        
        if (pagination.total_pages > 1) {
            html += '<div class="tablenav-pages">';
            html += '<span class="displaying-num">' + pagination.total_items + ' items</span>';
            html += '<span class="pagination-links">';
            
            // First page
            if (pagination.current_page > 1) {
                html += '<a class="first-page button" data-page="1"><span aria-hidden="true">«</span></a>';
                html += '<a class="prev-page button" data-page="' + (pagination.current_page - 1) + '"><span aria-hidden="true">‹</span></a>';
            } else {
                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
            }
            
            html += '<span class="paging-input">';
            html += '<span class="tablenav-paging-text">' + pagination.current_page + ' of <span class="total-pages">' + pagination.total_pages + '</span></span>';
            html += '</span>';
            
            // Next page
            if (pagination.current_page < pagination.total_pages) {
                html += '<a class="next-page button" data-page="' + (pagination.current_page + 1) + '"><span aria-hidden="true">›</span></a>';
                html += '<a class="last-page button" data-page="' + pagination.total_pages + '"><span aria-hidden="true">»</span></a>';
            } else {
                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
            }
            
            html += '</span>';
            html += '</div>';
        }
        
        $('#pagination-container').html(html);
        
        // Bind pagination click events
        $('.pagination-links a').on('click', function(e) {
            e.preventDefault();
            currentPage = parseInt($(this).data('page'));
            load_memberships();
        });
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
                sort_order: currentSortOrder,
                page: currentPage
            },
            success: function(response) {
                if (response.success) {
                    $('#memberships-list-container').html(response.data.html);
                    
                    // Update status counts
                    if (response.data.counts) {
                        update_status_counts(response.data.counts);
                    }
                    
                    // Update pagination
                    if (response.data.pagination) {
                        update_pagination(response.data.pagination);
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
        
        // Reset to page 1 when sorting
        currentPage = 1;
        load_memberships();
    });

    $('#filter-button').on('click', function() {
        // Reset to page 1 when filtering
        currentPage = 1;
        load_memberships();
    });

    // Filter change events
    $('#status-filter').on('change', function() {
        // Reset to page 1 when filtering
        currentPage = 1;
        load_memberships();
    });

    // Initial load
    load_memberships();
});
