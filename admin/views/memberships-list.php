<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e( 'Medlemskaber', 'membership-manager' ); ?></h1>
    <a href="<?php echo admin_url( 'admin.php?page=membership-manager&action=add' ); ?>" class="page-title-action"><?php _e( 'Tilføj ny', 'membership-manager' ); ?></a>
    <hr class="wp-header-end">

    <!-- Status Overview -->
    <div id="status-overview" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'Aktiv', 'membership-manager' ); ?></h3>
                <span id="active-count" style="font-size: 24px; font-weight: 600; color: #00a32a;">-</span>
            </div>
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'Udløbet', 'membership-manager' ); ?></h3>
                <span id="expired-count" style="font-size: 24px; font-weight: 600; color: #d63638;">-</span>
            </div>
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'Afventer annullering', 'membership-manager' ); ?></h3>
                <span id="pending-cancel-count" style="font-size: 24px; font-weight: 600; color: #f0b849;">-</span>
            </div>
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'På hold', 'membership-manager' ); ?></h3>
                <span id="on-hold-count" style="font-size: 24px; font-weight: 600; color: #826eb4;">-</span>
            </div>
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'Annulleret', 'membership-manager' ); ?></h3>
                <span id="cancelled-count" style="font-size: 24px; font-weight: 600; color: #646970;">-</span>
            </div>
            <div class="status-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 120px;">
                <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;"><?php _e( 'I alt', 'membership-manager' ); ?></h3>
                <span id="total-count" style="font-size: 24px; font-weight: 600; color: #2271b1;">-</span>
            </div>
        </div>
    </div>

    <div id="filters">
        <select id="status-filter">
            <option value=""><?php _e( 'Alle statusser', 'membership-manager' ); ?></option>
            <option value="active"><?php _e( 'Aktiv', 'membership-manager' ); ?></option>
            <option value="expired"><?php _e( 'Udløbet', 'membership-manager' ); ?></option>
            <option value="pending-cancel"><?php _e( 'Afventer annullering', 'membership-manager' ); ?></option>
            <option value="cancelled"><?php _e( 'Annulleret', 'membership-manager' ); ?></option>
            <option value="on-hold"><?php _e( 'På hold', 'membership-manager' ); ?></option>
        </select>

        <input type="date" id="renewal-date-filter">

        <button id="filter-button" class="button"><?php _e( 'Filtrer', 'membership-manager' ); ?></button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="sortable" data-column="user_id">
                    <a href="#" class="sort-link">
                        <?php _e( 'Bruger', 'membership-manager' ); ?>
                        <span class="dashicons dashicons-sort" style="font-size: 14px; vertical-align: middle;"></span>
                    </a>
                </th>
                <th class="sortable" data-column="start_date">
                    <a href="#" class="sort-link">
                        <?php _e( 'Startdato', 'membership-manager' ); ?>
                        <span class="dashicons dashicons-sort" style="font-size: 14px; vertical-align: middle;"></span>
                    </a>
                </th>
                <th class="sortable" data-column="end_date">
                    <a href="#" class="sort-link">
                        <?php _e( 'End Date / Status', 'membership-manager' ); ?>
                        <span class="dashicons dashicons-sort" style="font-size: 14px; vertical-align: middle;"></span>
                    </a>
                </th>
                <th class="sortable" data-column="status">
                    <a href="#" class="sort-link">
                        <?php _e( 'Status', 'membership-manager' ); ?>
                        <span class="dashicons dashicons-sort" style="font-size: 14px; vertical-align: middle;"></span>
                    </a>
                </th>
                <th><?php _e( 'Fornyelsestype', 'membership-manager' ); ?></th>
                <th><?php _e( 'Handlinger', 'membership-manager' ); ?></th>
            </tr>
        </thead>
        <tbody id="memberships-list-container">
            <!-- Initial list will be loaded here -->
        </tbody>
    </table>
    
    <!-- Pagination Container -->
    <div id="pagination-container" class="tablenav bottom" style="margin-top: 15px;">
        <!-- Pagination will be loaded here by JavaScript -->
    </div>
</div>
