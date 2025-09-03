<div class="wrap">
    <h1><?php _e( 'Memberships', 'membership-manager' ); ?></h1>

    <div id="filters">
        <select id="status-filter">
            <option value=""><?php _e( 'All Statuses', 'membership-manager' ); ?></option>
            <option value="active"><?php _e( 'Active', 'membership-manager' ); ?></option>
            <option value="expired"><?php _e( 'Expired', 'membership-manager' ); ?></option>
        </select>

        <input type="date" id="renewal-date-filter">

        <button id="filter-button" class="button"><?php _e( 'Filter', 'membership-manager' ); ?></button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'User ID', 'membership-manager' ); ?></th>
                <th><?php _e( 'Start Date', 'membership-manager' ); ?></th>
                <th><?php _e( 'End Date', 'membership-manager' ); ?></th>
                <th><?php _e( 'Status', 'membership-manager' ); ?></th>
                <th><?php _e( 'Renewal Type', 'membership-manager' ); ?></th>
            </tr>
        </thead>
        <tbody id="memberships-list-container">
            <!-- Initial list will be loaded here -->
        </tbody>
    </table>
</div>
