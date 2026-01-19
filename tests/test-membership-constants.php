<?php
/**
 * Class Test_Membership_Constants
 *
 * Tests for Membership_Constants class
 */

class Test_Membership_Constants extends WP_UnitTestCase {

    /**
     * Test valid statuses
     */
    public function test_get_valid_statuses() {
        $statuses = Membership_Constants::get_valid_statuses();
        
        $this->assertIsArray( $statuses );
        $this->assertContains( 'active', $statuses );
        $this->assertContains( 'expired', $statuses );
        $this->assertContains( 'pending-cancel', $statuses );
        $this->assertContains( 'cancelled', $statuses );
        $this->assertContains( 'on-hold', $statuses );
    }

    /**
     * Test valid renewal types
     */
    public function test_get_valid_renewal_types() {
        $types = Membership_Constants::get_valid_renewal_types();
        
        $this->assertIsArray( $types );
        $this->assertContains( 'automatic', $types );
        $this->assertContains( 'manual', $types );
    }

    /**
     * Test status validation
     */
    public function test_is_valid_status() {
        // Valid statuses
        $this->assertTrue( Membership_Constants::is_valid_status( 'active' ) );
        $this->assertTrue( Membership_Constants::is_valid_status( 'expired' ) );
        $this->assertTrue( Membership_Constants::is_valid_status( 'on-hold' ) );

        // Invalid statuses
        $this->assertFalse( Membership_Constants::is_valid_status( 'invalid' ) );
        $this->assertFalse( Membership_Constants::is_valid_status( '' ) );
        $this->assertFalse( Membership_Constants::is_valid_status( 'Active' ) ); // Case sensitive
    }

    /**
     * Test renewal type validation
     */
    public function test_is_valid_renewal_type() {
        // Valid types
        $this->assertTrue( Membership_Constants::is_valid_renewal_type( 'automatic' ) );
        $this->assertTrue( Membership_Constants::is_valid_renewal_type( 'manual' ) );

        // Invalid types
        $this->assertFalse( Membership_Constants::is_valid_renewal_type( 'invalid' ) );
        $this->assertFalse( Membership_Constants::is_valid_renewal_type( '' ) );
        $this->assertFalse( Membership_Constants::is_valid_renewal_type( 'Manual' ) ); // Case sensitive
    }

    /**
     * Test constant values
     */
    public function test_constant_values() {
        // Status constants
        $this->assertEquals( 'active', Membership_Constants::STATUS_ACTIVE );
        $this->assertEquals( 'expired', Membership_Constants::STATUS_EXPIRED );
        $this->assertEquals( 'pending-cancel', Membership_Constants::STATUS_PENDING_CANCEL );
        $this->assertEquals( 'cancelled', Membership_Constants::STATUS_CANCELLED );
        $this->assertEquals( 'on-hold', Membership_Constants::STATUS_ON_HOLD );

        // Renewal type constants
        $this->assertEquals( 'automatic', Membership_Constants::RENEWAL_AUTOMATIC );
        $this->assertEquals( 'manual', Membership_Constants::RENEWAL_MANUAL );

        // Product type constants
        $this->assertEquals( 'membership_auto', Membership_Constants::PRODUCT_TYPE_AUTO );
        $this->assertEquals( 'membership_manual', Membership_Constants::PRODUCT_TYPE_MANUAL );

        // Reminder intervals
        $this->assertEquals( 30, Membership_Constants::REMINDER_30_DAYS );
        $this->assertEquals( 14, Membership_Constants::REMINDER_14_DAYS );
        $this->assertEquals( 7, Membership_Constants::REMINDER_7_DAYS );
        $this->assertEquals( 1, Membership_Constants::REMINDER_1_DAY );
    }
}
