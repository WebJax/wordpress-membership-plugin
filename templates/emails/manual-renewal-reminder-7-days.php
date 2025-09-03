<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<p><?php printf( __( 'Hi %s,', 'membership-manager' ), '[user_name]' ); ?></p>
<p><?php _e( 'This is a reminder that your subscription will expire in 7 days.', 'membership-manager' ); ?></p>
<p><?php _e( 'Please renew your subscription manually to continue receiving benefits.', 'membership-manager' ); ?></p>
<p><a href="[renewal_link]"><?php _e( 'Renew now', 'membership-manager' ); ?></a></p>
<p><?php _e( 'Subscription details:', 'membership-manager' ); ?></p>
<ul>
    <li><?php _e( 'End date:', 'membership-manager' ); ?> [end_date]</li>
</ul>
<p><?php _e( 'Thank you!', 'membership-manager' ); ?></p>
