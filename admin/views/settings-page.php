<div class="wrap">
    <h1><?php _e( 'Membership Settings', 'membership-manager' ); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'membership_settings' ); ?>

        <h2><?php _e( 'Automatic Renewal', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Products', 'membership-manager' ); ?></th>
                <td>
                    <div id="automatic-renewal-products">
                        <!-- Product fields will be added here -->
                    </div>
                    <button type="button" class="button" id="add-automatic-product"><?php _e( 'Add Product', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Products that will automatically renew memberships on expiration.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'Manual Renewal', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Products', 'membership-manager' ); ?></th>
                <td>
                    <div id="manual-renewal-products">
                        <!-- Product fields will be added here -->
                    </div>
                    <button type="button" class="button" id="add-manual-product"><?php _e( 'Add Product', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Products that require manual renewal by members.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'User Roles & Capabilities', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Member Role', 'membership-manager' ); ?></th>
                <td>
                    <?php 
                    $current_role = get_option( 'membership_member_role', 'subscriber' );
                    wp_dropdown_roles( $current_role );
                    ?>
                    <input type="hidden" name="membership_member_role" id="membership_member_role" value="<?php echo esc_attr( $current_role ); ?>">
                    <p class="description"><?php _e( 'WordPress role to assign to members with active memberships.', 'membership-manager' ); ?></p>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#role').attr('name', 'membership_member_role');
                        $('#membership_member_role').remove();
                    });
                    </script>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e( 'Remove Role on Expiration', 'membership-manager' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="membership_remove_role_on_expiration" value="yes" <?php checked( get_option( 'membership_remove_role_on_expiration', 'yes' ), 'yes' ); ?>>
                        <?php _e( 'Automatically remove member role when membership expires', 'membership-manager' ); ?>
                    </label>
                    <p class="description"><?php _e( 'If enabled, the member role will be removed when the membership expires. Users will revert to the default WordPress role.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'Email Settings', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Enable Email Reminders', 'membership-manager' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="membership_enable_reminders" value="yes" <?php checked( get_option( 'membership_enable_reminders', 'yes' ), 'yes' ); ?>>
                        <?php _e( 'Send automatic email reminders before membership expiration', 'membership-manager' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Emails will be sent 30, 14, 7, and 1 day before expiration.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_email_from_name"><?php _e( 'From Name', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_email_from_name" name="membership_email_from_name" value="<?php echo esc_attr( get_option( 'membership_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'The name that appears in the "From" field of emails.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_email_from_address"><?php _e( 'From Email Address', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="email" id="membership_email_from_address" name="membership_email_from_address" value="<?php echo esc_attr( get_option( 'membership_email_from_address', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'The email address that appears in the "From" field.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e( 'Email Subject Lines', 'membership-manager' ); ?></h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_30_subject"><?php _e( '30-Day Reminder Subject', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_30_subject" name="membership_reminder_30_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_30_subject', __( 'Your membership will expire in 30 days', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_14_subject"><?php _e( '14-Day Reminder Subject', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_14_subject" name="membership_reminder_14_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_14_subject', __( 'Your membership will expire in 14 days', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_7_subject"><?php _e( '7-Day Reminder Subject', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_7_subject" name="membership_reminder_7_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_7_subject', __( 'Your membership will expire in 7 days', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_1_subject"><?php _e( '1-Day Reminder Subject', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_1_subject" name="membership_reminder_1_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_1_subject', __( 'Your membership will expire tomorrow', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
        </table>
        
        <h3><?php _e( 'Test Email', 'membership-manager' ); ?></h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Send Test Email', 'membership-manager' ); ?></th>
                <td>
                    <input type="email" id="test_email_address" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text">
                    <button type="button" id="send_test_email" class="button button-secondary"><?php _e( 'Send Test Email', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Send a test reminder email to verify your settings.', 'membership-manager' ); ?></p>
                    <div id="test_email_result" style="margin-top: 10px;"></div>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#send_test_email').on('click', function() {
                var email = $('#test_email_address').val() || '<?php echo esc_js( get_option( 'admin_email' ) ); ?>';
                var button = $(this);
                var result = $('#test_email_result');
                
                button.prop('disabled', true).text('<?php _e( 'Sending...', 'membership-manager' ); ?>');
                result.html('');
                
                $.post(ajaxurl, {
                    action: 'send_test_membership_email',
                    email: email,
                    nonce: '<?php echo wp_create_nonce( 'send_test_email' ); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('<?php _e( 'Send Test Email', 'membership-manager' ); ?>');
                    
                    if (response.success) {
                        result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                });
            });
        });
        </script>

        <?php submit_button(); ?>
    </form>
</div>
