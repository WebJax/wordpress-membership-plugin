<?php
/**
 * Tests for Membership Email Queue
 */

class Test_Membership_Email_Queue extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        
        // Clear queue before each test
        delete_option( 'membership_email_queue' );
        
        // Clear any scheduled cron events
        $timestamp = wp_next_scheduled( 'membership_process_email_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'membership_process_email_queue' );
        }
    }

    public function tearDown(): void {
        // Clean up after tests
        delete_option( 'membership_email_queue' );
        
        $timestamp = wp_next_scheduled( 'membership_process_email_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'membership_process_email_queue' );
        }
        
        parent::tearDown();
    }

    /**
     * Test enqueuing an email
     */
    public function test_enqueue_email() {
        $result = Membership_Email_Queue::enqueue(
            'test@example.com',
            'Test Subject',
            'Test Message',
            array(),
            'test'
        );

        $this->assertTrue( $result, 'Email should be enqueued successfully' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertCount( 1, $queue, 'Queue should contain 1 email' );
        
        $email = $queue[0];
        $this->assertEquals( 'test@example.com', $email['to'] );
        $this->assertEquals( 'Test Subject', $email['subject'] );
        $this->assertEquals( 'Test Message', $email['message'] );
        $this->assertEquals( 'test', $email['type'] );
        $this->assertEquals( 0, $email['attempts'] );
        $this->assertEquals( 'pending', $email['status'] );
    }

    /**
     * Test enqueuing with invalid email
     */
    public function test_enqueue_invalid_email() {
        $result = Membership_Email_Queue::enqueue(
            'invalid-email',
            'Test Subject',
            'Test Message'
        );

        $this->assertFalse( $result, 'Should fail with invalid email' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEmpty( $queue, 'Queue should be empty' );
    }

    /**
     * Test enqueuing with empty subject
     */
    public function test_enqueue_empty_subject() {
        $result = Membership_Email_Queue::enqueue(
            'test@example.com',
            '',
            'Test Message'
        );

        $this->assertFalse( $result, 'Should fail with empty subject' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEmpty( $queue, 'Queue should be empty' );
    }

    /**
     * Test enqueuing with empty message
     */
    public function test_enqueue_empty_message() {
        $result = Membership_Email_Queue::enqueue(
            'test@example.com',
            'Test Subject',
            ''
        );

        $this->assertFalse( $result, 'Should fail with empty message' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEmpty( $queue, 'Queue should be empty' );
    }

    /**
     * Test multiple emails in queue
     */
    public function test_multiple_emails() {
        Membership_Email_Queue::enqueue( 'test1@example.com', 'Subject 1', 'Message 1' );
        Membership_Email_Queue::enqueue( 'test2@example.com', 'Subject 2', 'Message 2' );
        Membership_Email_Queue::enqueue( 'test3@example.com', 'Subject 3', 'Message 3' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertCount( 3, $queue, 'Queue should contain 3 emails' );
    }

    /**
     * Test get_stats method
     */
    public function test_get_stats() {
        // Add emails with different statuses
        Membership_Email_Queue::enqueue( 'test1@example.com', 'Subject 1', 'Message 1' );
        Membership_Email_Queue::enqueue( 'test2@example.com', 'Subject 2', 'Message 2' );
        
        // Manually modify one to be in retry state
        $queue = get_option( 'membership_email_queue', array() );
        $queue[1]['status'] = 'retry';
        $queue[1]['attempts'] = 1;
        update_option( 'membership_email_queue', $queue );

        $stats = Membership_Email_Queue::get_stats();

        $this->assertEquals( 2, $stats['total'], 'Total should be 2' );
        $this->assertEquals( 1, $stats['pending'], 'Pending should be 1' );
        $this->assertEquals( 1, $stats['retry'], 'Retry should be 1' );
        $this->assertEquals( 0, $stats['failed'], 'Failed should be 0' );
    }

    /**
     * Test clear_queue method
     */
    public function test_clear_queue() {
        // Add some emails
        Membership_Email_Queue::enqueue( 'test1@example.com', 'Subject 1', 'Message 1' );
        Membership_Email_Queue::enqueue( 'test2@example.com', 'Subject 2', 'Message 2' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertCount( 2, $queue, 'Queue should have 2 emails before clear' );

        Membership_Email_Queue::clear_queue();

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEmpty( $queue, 'Queue should be empty after clear' );
    }

    /**
     * Test retry_failed method
     */
    public function test_retry_failed() {
        // Add an email and mark it as failed
        Membership_Email_Queue::enqueue( 'test@example.com', 'Subject', 'Message' );
        
        $queue = get_option( 'membership_email_queue', array() );
        $queue[0]['status'] = 'failed';
        $queue[0]['attempts'] = 2;
        update_option( 'membership_email_queue', $queue );

        $reset_count = Membership_Email_Queue::retry_failed();

        $this->assertEquals( 1, $reset_count, 'Should reset 1 email' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEquals( 'retry', $queue[0]['status'], 'Status should be retry' );
        $this->assertEquals( 0, $queue[0]['last_attempt'], 'Last attempt should be reset' );
    }

    /**
     * Test that emails past max attempts are not retried
     */
    public function test_retry_failed_with_max_attempts() {
        // Add an email that has reached max attempts
        Membership_Email_Queue::enqueue( 'test@example.com', 'Subject', 'Message' );
        
        $queue = get_option( 'membership_email_queue', array() );
        $queue[0]['status'] = 'failed';
        $queue[0]['attempts'] = Membership_Email_Queue::MAX_ATTEMPTS;
        update_option( 'membership_email_queue', $queue );

        $reset_count = Membership_Email_Queue::retry_failed();

        $this->assertEquals( 0, $reset_count, 'Should not reset email at max attempts' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEquals( 'failed', $queue[0]['status'], 'Status should remain failed' );
    }

    /**
     * Test cron scheduling
     */
    public function test_cron_scheduled() {
        // Initialize the queue
        Membership_Email_Queue::init();

        // Check if cron is scheduled
        $scheduled = wp_next_scheduled( 'membership_process_email_queue' );
        $this->assertNotFalse( $scheduled, 'Cron should be scheduled' );
    }

    /**
     * Test email type filtering
     */
    public function test_email_type() {
        Membership_Email_Queue::enqueue( 'test@example.com', 'Welcome', 'Message', array(), 'welcome' );
        Membership_Email_Queue::enqueue( 'test@example.com', 'Reminder', 'Message', array(), 'reminder' );

        $queue = get_option( 'membership_email_queue', array() );
        
        $this->assertEquals( 'welcome', $queue[0]['type'] );
        $this->assertEquals( 'reminder', $queue[1]['type'] );
    }

    /**
     * Test email headers are stored
     */
    public function test_email_headers() {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Test <test@example.com>'
        );

        Membership_Email_Queue::enqueue( 'recipient@example.com', 'Subject', 'Message', $headers );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEquals( $headers, $queue[0]['headers'] );
    }

    /**
     * Test that filter hooks work
     */
    public function test_filter_hook() {
        add_filter( 'membership_email_queue_entry', function( $email ) {
            $email['custom_field'] = 'test_value';
            return $email;
        });

        Membership_Email_Queue::enqueue( 'test@example.com', 'Subject', 'Message' );

        $queue = get_option( 'membership_email_queue', array() );
        $this->assertEquals( 'test_value', $queue[0]['custom_field'] );
    }
}
