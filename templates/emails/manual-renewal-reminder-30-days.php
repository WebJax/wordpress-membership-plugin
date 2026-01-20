<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<p><?php printf( __( 'Hej %s,', 'membership-manager' ), '[user_name]' ); ?></p>
<p><?php _e( 'Dette er en påmindelse om, at dit abonnement udløber om 30 dage.', 'membership-manager' ); ?></p>
<p><?php _e( 'Fornå venligst dit abonnement manuelt for at fortsætte med at modtage fordele.', 'membership-manager' ); ?></p>
<p><a href="[renewal_link]"><?php _e( 'Fornå nu', 'membership-manager' ); ?></a></p>
<p><?php _e( 'Abonnementsdetaljer:', 'membership-manager' ); ?></p>
<ul>
    <li><?php _e( 'Slutdato:', 'membership-manager' ); ?> [end_date]</li>
</ul>
<p><?php _e( 'Tak!', 'membership-manager' ); ?></p>
