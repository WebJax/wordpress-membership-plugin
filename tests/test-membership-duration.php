<?php
/**
 * Class Test_Membership_Duration
 *
 * Tests for membership duration functionality
 */

class Test_Membership_Duration extends WP_UnitTestCase {

    /**
     * Test getting membership duration with defaults
     */
    public function test_get_membership_duration_defaults() {
        // Should return default 1 year if no options set
        $duration = Membership_Constants::get_membership_duration();
        
        $this->assertIsArray( $duration );
        $this->assertArrayHasKey( 'value', $duration );
        $this->assertArrayHasKey( 'unit', $duration );
        $this->assertEquals( 1, $duration['value'] );
        $this->assertEquals( 'year', $duration['unit'] );
    }

    /**
     * Test valid duration units
     */
    public function test_get_valid_duration_units() {
        $units = Membership_Constants::get_valid_duration_units();
        
        $this->assertIsArray( $units );
        $this->assertContains( 'day', $units );
        $this->assertContains( 'week', $units );
        $this->assertContains( 'month', $units );
        $this->assertContains( 'year', $units );
    }

    /**
     * Test applying membership duration with year
     */
    public function test_apply_membership_duration_year() {
        // Set duration to 1 year
        update_option( Membership_Constants::OPTION_DURATION_VALUE, 1 );
        update_option( Membership_Constants::OPTION_DURATION_UNIT, 'year' );
        
        $date = new DateTime( '2024-01-01 00:00:00' );
        $result = Membership_Constants::apply_membership_duration( $date );
        
        $this->assertEquals( '2025-01-01 00:00:00', $result->format( 'Y-m-d H:i:s' ) );
        
        // Clean up
        delete_option( Membership_Constants::OPTION_DURATION_VALUE );
        delete_option( Membership_Constants::OPTION_DURATION_UNIT );
    }

    /**
     * Test applying membership duration with months
     */
    public function test_apply_membership_duration_months() {
        // Set duration to 6 months
        update_option( Membership_Constants::OPTION_DURATION_VALUE, 6 );
        update_option( Membership_Constants::OPTION_DURATION_UNIT, 'month' );
        
        $date = new DateTime( '2024-01-01 00:00:00' );
        $result = Membership_Constants::apply_membership_duration( $date );
        
        $this->assertEquals( '2024-07-01 00:00:00', $result->format( 'Y-m-d H:i:s' ) );
        
        // Clean up
        delete_option( Membership_Constants::OPTION_DURATION_VALUE );
        delete_option( Membership_Constants::OPTION_DURATION_UNIT );
    }

    /**
     * Test applying membership duration with days
     */
    public function test_apply_membership_duration_days() {
        // Set duration to 30 days
        update_option( Membership_Constants::OPTION_DURATION_VALUE, 30 );
        update_option( Membership_Constants::OPTION_DURATION_UNIT, 'day' );
        
        $date = new DateTime( '2024-01-01 00:00:00' );
        $result = Membership_Constants::apply_membership_duration( $date );
        
        $this->assertEquals( '2024-01-31 00:00:00', $result->format( 'Y-m-d H:i:s' ) );
        
        // Clean up
        delete_option( Membership_Constants::OPTION_DURATION_VALUE );
        delete_option( Membership_Constants::OPTION_DURATION_UNIT );
    }

    /**
     * Test applying membership duration with invalid unit defaults to year
     */
    public function test_apply_membership_duration_invalid_unit() {
        // Set invalid unit
        update_option( Membership_Constants::OPTION_DURATION_VALUE, 5 );
        update_option( Membership_Constants::OPTION_DURATION_UNIT, 'invalid' );
        
        $date = new DateTime( '2024-01-01 00:00:00' );
        $result = Membership_Constants::apply_membership_duration( $date );
        
        // Should default to 1 year
        $this->assertEquals( '2025-01-01 00:00:00', $result->format( 'Y-m-d H:i:s' ) );
        
        // Clean up
        delete_option( Membership_Constants::OPTION_DURATION_VALUE );
        delete_option( Membership_Constants::OPTION_DURATION_UNIT );
    }

    /**
     * Test new option constants exist
     */
    public function test_duration_constants_exist() {
        $this->assertEquals( 'membership_duration_value', Membership_Constants::OPTION_DURATION_VALUE );
        $this->assertEquals( 'membership_duration_unit', Membership_Constants::OPTION_DURATION_UNIT );
    }
}
