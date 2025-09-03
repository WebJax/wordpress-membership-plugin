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
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
