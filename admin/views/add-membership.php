<div class="wrap">
    <h1><?php _e( 'Tilføj nyt medlemskab', 'membership-manager' ); ?></h1>

    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
        <input type="hidden" name="action" value="add_new_membership">
        <?php wp_nonce_field( 'add_new_membership_nonce', '_wpnonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="user_id"><?php _e( 'Bruger-ID', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="number" id="user_id" name="user_id" class="regular-text" required>
                    <p class="description"><?php _e( 'Indtast WordPress bruger-ID for medlemmet.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="start_date"><?php _e( 'Startdato', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="datetime-local" id="start_date" name="start_date" value="<?php echo date('Y-m-d\TH:i'); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="end_date"><?php _e( 'Slutdato', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="datetime-local" id="end_date" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime('+1 year')); ?>" class="regular-text">
                    <p class="description"><?php _e( 'Lad stå tomt for ingen udløbsdato.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="status"><?php _e( 'Status', 'membership-manager' ); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <option value="active"><?php _e( 'Aktiv', 'membership-manager' ); ?></option>
                        <option value="expired"><?php _e( 'Udløbet', 'membership-manager' ); ?></option>
                        <option value="pending-cancel"><?php _e( 'Afventer annullering', 'membership-manager' ); ?></option>
                        <option value="cancelled"><?php _e( 'Annulleret', 'membership-manager' ); ?></option>
                        <option value="on-hold"><?php _e( 'På hold', 'membership-manager' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="renewal_type"><?php _e( 'Fornyelsestype', 'membership-manager' ); ?></label></th>
                <td>
                    <select id="renewal_type" name="renewal_type">
                        <option value="manual"><?php _e( 'Manuel', 'membership-manager' ); ?></option>
                        <option value="automatic"><?php _e( 'Automatisk', 'membership-manager' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Tilføj medlemskab', 'membership-manager' ) ); ?>
    </form>
</div>
