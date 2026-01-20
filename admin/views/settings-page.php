<div class="wrap">
    <h1><?php _e( 'Medlemskabsindstillinger', 'membership-manager' ); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'membership_settings' ); ?>

        <h2><?php _e( 'Automatisk fornyelse', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Produkter', 'membership-manager' ); ?></th>
                <td>
                    <div id="automatic-renewal-products">
                        <!-- Product fields will be added here -->
                    </div>
                    <button type="button" class="button" id="add-automatic-product"><?php _e( 'Tilføj produkt', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Produkter der automatisk fornyer medlemskaber ved udløb.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'Manuel fornyelse', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Produkter', 'membership-manager' ); ?></th>
                <td>
                    <div id="manual-renewal-products">
                        <!-- Product fields will be added here -->
                    </div>
                    <button type="button" class="button" id="add-manual-product"><?php _e( 'Tilføj produkt', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Produkter der kræver manuel fornyelse af medlemmer.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'Brugerroller og rettigheder', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Medlemsrolle', 'membership-manager' ); ?></th>
                <td>
                    <?php 
                    $current_role = get_option( 'membership_member_role', 'subscriber' );
                    wp_dropdown_roles( $current_role );
                    ?>
                    <input type="hidden" name="membership_member_role" id="membership_member_role" value="<?php echo esc_attr( $current_role ); ?>">
                    <p class="description"><?php _e( 'WordPress rolle der tildeles medlemmer med aktive medlemskaber.', 'membership-manager' ); ?></p>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#role').attr('name', 'membership_member_role');
                        $('#membership_member_role').remove();
                    });
                    </script>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e( 'Fjern rolle ved udløb', 'membership-manager' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="membership_remove_role_on_expiration" value="yes" <?php checked( get_option( 'membership_remove_role_on_expiration', 'yes' ), 'yes' ); ?>>
                        <?php _e( 'Fjern automatisk medlemsrolle når medlemskabet udløber', 'membership-manager' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Hvis aktiveret, vil medlemsrollen blive fjernet når medlemskabet udløber. Brugere vil vende tilbage til standard WordPress-rollen.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e( 'E-mailindstillinger', 'membership-manager' ); ?></h2>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Aktiver e-mailpåmindelser', 'membership-manager' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="membership_enable_reminders" value="yes" <?php checked( get_option( 'membership_enable_reminders', 'yes' ), 'yes' ); ?>>
                        <?php _e( 'Send automatiske e-mailpåmindelser før medlemskabet udløber', 'membership-manager' ); ?>
                    </label>
                    <p class="description"><?php _e( 'E-mails sendes 30, 14, 7 og 1 dag før udløb.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_email_from_name"><?php _e( 'Afsendernavn', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_email_from_name" name="membership_email_from_name" value="<?php echo esc_attr( get_option( 'membership_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'Navnet der vises i "Fra" feltet i e-mails.', 'membership-manager' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_email_from_address"><?php _e( 'Afsender e-mailadresse', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="email" id="membership_email_from_address" name="membership_email_from_address" value="<?php echo esc_attr( get_option( 'membership_email_from_address', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'E-mailadressen der vises i "Fra" feltet.', 'membership-manager' ); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e( 'E-mail emnelinjer', 'membership-manager' ); ?></h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_30_subject"><?php _e( '30-dages påmindelse emne', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_30_subject" name="membership_reminder_30_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_30_subject', __( 'Dit medlemskab udløber om 30 dage', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_14_subject"><?php _e( '14-dages påmindelse emne', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_14_subject" name="membership_reminder_14_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_14_subject', __( 'Dit medlemskab udløber om 14 dage', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_7_subject"><?php _e( '7-dages påmindelse emne', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_7_subject" name="membership_reminder_7_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_7_subject', __( 'Dit medlemskab udløber om 7 dage', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="membership_reminder_1_subject"><?php _e( '1-dages påmindelse emne', 'membership-manager' ); ?></label></th>
                <td>
                    <input type="text" id="membership_reminder_1_subject" name="membership_reminder_1_subject" value="<?php echo esc_attr( get_option( 'membership_reminder_1_subject', __( 'Dit medlemskab udløber i morgen', 'membership-manager' ) ) ); ?>" class="large-text">
                </td>
            </tr>
        </table>
        
        <h3><?php _e( 'Test e-mail', 'membership-manager' ); ?></h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e( 'Send test e-mail', 'membership-manager' ); ?></th>
                <td>
                    <input type="email" id="test_email_address" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text">
                    <button type="button" id="send_test_email" class="button button-secondary"><?php _e( 'Send test e-mail', 'membership-manager' ); ?></button>
                    <p class="description"><?php _e( 'Send en test påmindelses-e-mail for at verificere dine indstillinger.', 'membership-manager' ); ?></p>
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
                
                button.prop('disabled', true).text('<?php _e( 'Sender...', 'membership-manager' ); ?>');
                result.html('');
                
                $.post(ajaxurl, {
                    action: 'send_test_membership_email',
                    email: email,
                    nonce: '<?php echo wp_create_nonce( 'send_test_email' ); ?>'
                }, function(response) {
                    button.prop('disabled', false).text('<?php _e( 'Send test e-mail', 'membership-manager' ); ?>');
                    
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
