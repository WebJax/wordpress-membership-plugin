<?php
/**
 * Class Test_Membership_Utils
 *
 * Tests for Membership_Utils class
 */

class Test_Membership_Utils extends WP_UnitTestCase {

    /**
     * Test date sanitization
     */
    public function test_sanitize_date() {
        // Valid date
        $result = Membership_Utils::sanitize_date( '2024-01-15 12:00:00' );
        $this->assertNotFalse( $result );
        $this->assertEquals( '2024-01-15 12:00:00', $result );

        // Invalid date
        $result = Membership_Utils::sanitize_date( 'invalid-date' );
        $this->assertFalse( $result );

        // Empty date
        $result = Membership_Utils::sanitize_date( '' );
        $this->assertFalse( $result );
    }

    /**
     * Test days until expiration calculation
     */
    public function test_get_days_until_expiration() {
        // Future date (30 days from now)
        $future_date = date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
        $days = Membership_Utils::get_days_until_expiration( $future_date );
        $this->assertEquals( 30, $days );

        // Past date
        $past_date = date( 'Y-m-d H:i:s', strtotime( '-5 days' ) );
        $days = Membership_Utils::get_days_until_expiration( $past_date );
        $this->assertEquals( -5, $days );

        // Invalid date
        $days = Membership_Utils::get_days_until_expiration( 'invalid' );
        $this->assertFalse( $days );
    }

    /**
     * Test expiring soon check
     */
    public function test_is_expiring_soon() {
        // 15 days until expiration - should be expiring soon (within 30 days)
        $date = date( 'Y-m-d H:i:s', strtotime( '+15 days' ) );
        $this->assertTrue( Membership_Utils::is_expiring_soon( $date, 30 ) );

        // 45 days until expiration - should NOT be expiring soon (within 30 days)
        $date = date( 'Y-m-d H:i:s', strtotime( '+45 days' ) );
        $this->assertFalse( Membership_Utils::is_expiring_soon( $date, 30 ) );

        // Past date - should NOT be expiring soon
        $date = date( 'Y-m-d H:i:s', strtotime( '-5 days' ) );
        $this->assertFalse( Membership_Utils::is_expiring_soon( $date, 30 ) );
    }

    /**
     * Test token generation
     */
    public function test_generate_token() {
        $token = Membership_Utils::generate_token();
        
        // Token should be 64 characters (32 bytes in hex)
        $this->assertEquals( 64, strlen( $token ) );
        
        // Token should be hexadecimal
        $this->assertRegExp( '/^[a-f0-9]+$/', $token );
        
        // Two generated tokens should be different
        $token2 = Membership_Utils::generate_token();
        $this->assertNotEquals( $token, $token2 );
    }

    /**
     * Test subscription data validation
     */
    public function test_validate_subscription_data() {
        // Valid data
        $data = array(
            'user_id' => 1,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2025-01-01 00:00:00',
            'status' => 'active',
            'renewal_type' => 'manual',
        );
        
        $result = Membership_Utils::validate_subscription_data( $data );
        $this->assertTrue( $result['valid'] );
        $this->assertEmpty( $result['errors'] );

        // Invalid status
        $data['status'] = 'invalid_status';
        $result = Membership_Utils::validate_subscription_data( $data );
        $this->assertFalse( $result['valid'] );
        $this->assertNotEmpty( $result['errors'] );

        // End date before start date
        $data = array(
            'user_id' => 1,
            'start_date' => '2025-01-01 00:00:00',
            'end_date' => '2024-01-01 00:00:00',
            'status' => 'active',
            'renewal_type' => 'manual',
        );
        
        $result = Membership_Utils::validate_subscription_data( $data );
        $this->assertFalse( $result['valid'] );
        $this->assertContains( 'End date must be after start date', $result['errors'] );
    }

    /**
     * Test table name getter
     */
    public function test_get_table_name() {
        global $wpdb;
        $table_name = Membership_Utils::get_table_name();
        
        $expected = $wpdb->prefix . 'membership_subscriptions';
        $this->assertEquals( $expected, $table_name );
    }
}
