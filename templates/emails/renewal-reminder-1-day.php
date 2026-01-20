<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<p><?php printf( __( 'Hej %s,', 'membership-manager' ), '[user_name]' ); ?></p>
<p><?php _e( 'Dette er en påmindelse om, at dit abonnement bliver fornyet i morgen.', 'membership-manager' ); ?></p>
<p><?php _e( 'Abonnementsdetaljer:', 'membership-manager' ); ?></p>
<ul>
    <li><?php _e( 'Slutdato:', 'membership-manager' ); ?> [end_date]</li>
</ul>
<p><?php _e( 'Tak!', 'membership-manager' ); ?></p>
