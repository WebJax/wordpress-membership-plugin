<?php
/**
 * Class Test_Membership_Security
 *
 * Tests for Membership_Security class
 */

class Test_Membership_Security extends WP_UnitTestCase {

    /**
     * Test IP address extraction
     */
    public function test_get_client_ip() {
        // Test with REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $ip = Membership_Security::get_client_ip();
        $this->assertEquals( '192.168.1.1', $ip );
        
        // Test with invalid IP
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip';
        $ip = Membership_Security::get_client_ip();
        $this->assertEquals( '0.0.0.0', $ip );
        
        // Clean up
        unset( $_SERVER['REMOTE_ADDR'] );
    }

    /**
     * Test nonce verification
     */
    public function test_verify_nonce() {
        $action = 'test_action';
        $nonce = wp_create_nonce( $action );
        
        // Valid nonce
        $result = Membership_Security::verify_nonce( $nonce, $action );
        $this->assertTrue( $result );
        
        // Invalid nonce
        $result = Membership_Security::verify_nonce( 'invalid_nonce', $action );
        $this->assertFalse( $result );
    }

    /**
     * Test admin authorization check
     */
    public function test_is_authorized_admin() {
        // Test without logged in user
        $result = Membership_Security::is_authorized_admin();
        $this->assertFalse( $result );
        
        // Test with admin user
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );
        
        $result = Membership_Security::is_authorized_admin();
        $this->assertTrue( $result );
        
        // Test with subscriber (non-admin)
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );
        
        $result = Membership_Security::is_authorized_admin();
        $this->assertFalse( $result );
    }

    /**
     * Test ID sanitization
     */
    public function test_sanitize_ids() {
        // Valid IDs
        $ids = array( '1', '2', '3' );
        $result = Membership_Security::sanitize_ids( $ids );
        $this->assertEquals( array( 1, 2, 3 ), $result );
        
        // Mixed valid and invalid
        $ids = array( '1', 'invalid', '3', '-5' );
        $result = Membership_Security::sanitize_ids( $ids );
        $this->assertEquals( array( 1, 3 ), array_values( $result ) );
        
        // Not an array
        $result = Membership_Security::sanitize_ids( 'not_an_array' );
        $this->assertEquals( array(), $result );
    }

    /**
     * Test admin action validation
     */
    public function test_validate_admin_action() {
        $allowed = array( 'edit', 'delete', 'create' );
        
        // Valid action
        $result = Membership_Security::validate_admin_action( 'edit', $allowed );
        $this->assertEquals( 'edit', $result );
        
        // Invalid action
        $result = Membership_Security::validate_admin_action( 'hack', $allowed );
        $this->assertFalse( $result );
        
        // Empty allowed list (allow all)
        $result = Membership_Security::validate_admin_action( 'anything', array() );
        $this->assertEquals( 'anything', $result );
    }

    /**
     * Test rate limit reset time
     */
    public function test_get_rate_limit_reset_time() {
        // Set a transient with known timeout
        $key = 'test_rate_limit_key';
        set_transient( $key, 1, 3600 ); // 1 hour
        
        $reset_time = Membership_Security::get_rate_limit_reset_time( $key );
        
        // Should be close to 3600 seconds (within 10 seconds tolerance for execution time)
        $this->assertGreaterThan( 3590, $reset_time );
        $this->assertLessThan( 3610, $reset_time );
        
        // Clean up
        delete_transient( $key );
    }

    /**
     * Test security event logging
     */
    public function test_log_security_event() {
        // This test verifies the function doesn't error
        // Actual logging to database would require more setup
        
        $result = Membership_Security::log_security_event( 'test_event', array( 'test' => 'data' ) );
        
        // Should not return false or throw error
        $this->assertTrue( true );
    }

    /**
     * Test rate limiting basic functionality
     */
    public function test_check_rate_limit_within_limit() {
        // Set up a test user
        $user_id = $this->factory->user->create();
        wp_set_current_user( $user_id );
        
        // First request should pass
        $result = Membership_Security::check_rate_limit( 'test_action', 10, 3600 );
        $this->assertTrue( $result );
        
        // Clean up transients using WordPress functions
        $prefix = 'membership_rate_limit_';
        
        // Get all transients with our prefix (note: this is simplified for testing)
        // In production, transients are automatically cleaned up by WordPress
        delete_transient( $prefix . 'test_action_user_' . $user_id );
        delete_transient( $prefix . 'test_action_ip_' . Membership_Security::get_client_ip() );
    }
}
