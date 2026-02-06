# Email Queue System Documentation

## Overview

The email queue system provides asynchronous email sending with automatic retry mechanism for the WordPress Membership Plugin. This addresses the following issues:

- **Synchronous Blocking**: Emails are now queued and sent asynchronously via WordPress cron
- **Failed Email Handling**: Automatic retry mechanism with configurable attempts
- **Batch Processing**: Processes emails in batches to avoid timeout issues
- **Extensibility**: Filter hooks allow custom email types and modifications

## Features

### 1. Asynchronous Email Processing
All emails are added to a queue and processed in the background using WordPress cron jobs. This prevents page load delays when sending emails.

### 2. Automatic Retry Mechanism
- Failed emails are automatically retried up to 3 times
- 5-minute delay between retry attempts
- Emails that reach max attempts are marked as "failed"
- Failed emails can be manually retried from the admin interface

### 3. Queue Management
- Stores queue in `wp_options` table (option name: `membership_email_queue`)
- Automatic cleanup of old emails (older than 7 days)
- Batch processing (10 emails per batch by default)
- Hourly automatic processing via WordPress cron

### 4. Admin Interface
Location: **Membership Manager → E-mail Kø**

Features:
- View queue statistics (Total, Pending, Retry, Failed)
- View all queued emails with details
- Manually trigger queue processing
- Retry failed emails
- Clear entire queue
- Real-time status tracking

### 5. Email Types and Filtering
Each email has a type identifier for categorization:
- `welcome` - Welcome emails for new memberships
- `automatic_renewal_reminder_*` - Automatic renewal reminders
- `manual_renewal_reminder_*` - Manual renewal reminders
- `legacy` - Legacy emails from old code

## Usage

### Queuing an Email

```php
// Basic usage
Membership_Email_Queue::enqueue(
    'user@example.com',           // To
    'Email Subject',               // Subject
    'Email message content',       // Message (HTML)
    array(),                       // Headers (optional)
    'custom_type'                  // Type (optional, default: 'general')
);

// With custom headers
$headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: Your Site <noreply@yoursite.com>'
);

Membership_Email_Queue::enqueue(
    'user@example.com',
    'Subject',
    '<h1>HTML Message</h1>',
    $headers,
    'newsletter'
);
```

### Filter Hooks

#### Modify Email Before Queuing
```php
add_filter( 'membership_email_queue_entry', function( $email, $type ) {
    // Add custom metadata
    $email['custom_field'] = 'value';
    
    // Modify email based on type
    if ( $type === 'welcome' ) {
        $email['priority'] = 'high';
    }
    
    return $email;
}, 10, 2 );
```

#### Modify Email Before Sending
```php
add_filter( 'membership_email_queue_before_send', function( $email ) {
    // Last-minute modifications before sending
    $email['message'] = str_replace( '[signature]', get_option( 'email_signature' ), $email['message'] );
    return $email;
});
```

### Action Hooks

#### After Queue Processing
```php
add_action( 'membership_email_queue_processed', function( $sent, $failed, $remaining ) {
    // Log or notify about queue processing
    error_log( sprintf( 'Email queue processed: %d sent, %d failed, %d remaining', $sent, $failed, $remaining ) );
}, 10, 3 );
```

#### After Email Sent
```php
add_action( 'membership_email_queue_sent', function( $email, $success ) {
    if ( $success ) {
        // Track successful email
        update_option( 'emails_sent_count', get_option( 'emails_sent_count', 0 ) + 1 );
    }
}, 10, 2 );
```

## Configuration

### Constants (in `class-membership-email-queue.php`)

```php
const MAX_ATTEMPTS = 3;        // Maximum retry attempts
const MAX_AGE = 604800;        // Maximum age in seconds (7 days)
const BATCH_SIZE = 10;         // Emails processed per batch
```

To customize these values, you can use filters:

```php
add_filter( 'membership_email_queue_max_attempts', function() {
    return 5; // Increase max attempts
});

add_filter( 'membership_email_queue_batch_size', function() {
    return 20; // Process more emails per batch
});
```

## Cron Schedule

The email queue is processed automatically using WordPress cron:

- **Hook**: `membership_process_email_queue`
- **Schedule**: Hourly
- **Callback**: `Membership_Email_Queue::process_queue()`

### Manual Triggering

Process queue immediately via code:
```php
Membership_Email_Queue::process_queue();
```

Or via WP-CLI:
```bash
wp cron event run membership_process_email_queue
```

## Queue Management Functions

### Get Queue Statistics
```php
$stats = Membership_Email_Queue::get_stats();
// Returns: array(
//     'total' => 15,
//     'pending' => 10,
//     'retry' => 3,
//     'failed' => 2
// )
```

### Clear Queue
```php
Membership_Email_Queue::clear_queue();
```

### Retry Failed Emails
```php
$count = Membership_Email_Queue::retry_failed();
// Returns number of emails reset for retry
```

## Staging Mode

When `MEMBERSHIP_STAGING_MODE` is enabled, the email queue will not process emails. Emails can still be added to the queue, but they won't be sent until staging mode is disabled.

```php
// In wp-config.php
define( 'MEMBERSHIP_STAGING_MODE', true );
```

## Database Structure

Queue is stored as a serialized array in the `wp_options` table:

**Option Name**: `membership_email_queue`

**Entry Structure**:
```php
array(
    'id' => 'email_abc123',                   // Unique ID
    'to' => 'user@example.com',               // Recipient
    'subject' => 'Email Subject',             // Subject line
    'message' => 'Email content',             // Message body (HTML)
    'headers' => array(...),                  // Email headers
    'type' => 'welcome',                      // Email type
    'attempts' => 0,                          // Number of send attempts
    'queued_at' => 1234567890,               // Unix timestamp
    'last_attempt' => 0,                      // Unix timestamp of last attempt
    'status' => 'pending'                     // pending|retry|failed
)
```

## Backward Compatibility

The old `send_email()` method has been updated to use the queue system by default. If you need immediate sending (not recommended), you can pass `true` as the 4th parameter:

```php
// Old code (still works, now uses queue)
$emails->send_email( 'to@example.com', 'Subject', 'Message' );

// Force immediate sending (not recommended)
$emails->send_email( 'to@example.com', 'Subject', 'Message', true );
```

## Monitoring and Troubleshooting

### Check Queue Status
1. Go to **Membership Manager → E-mail Kø** in WordPress admin
2. View statistics and queued emails
3. Check for failed emails

### Common Issues

**Emails not being sent**:
- Check if WordPress cron is working: `wp cron test`
- Verify staging mode is disabled
- Check WordPress logs for errors
- Manually trigger processing from admin interface

**Too many emails in queue**:
- Increase batch size (see Configuration)
- Schedule more frequent processing
- Check for email sending errors in logs

**Emails marked as failed**:
- Check WordPress mail configuration
- Verify SMTP settings if using SMTP plugin
- Review error logs
- Use "Retry Failed" button in admin interface

## Testing

Run PHPUnit tests:
```bash
phpunit tests/test-membership-email-queue.php
```

Manual testing:
1. Enable staging mode
2. Trigger actions that send emails
3. Check queue in admin interface
4. Disable staging mode
5. Manually process queue
6. Verify emails are sent

## Performance Considerations

- Queue is stored in `wp_options`, suitable for moderate volume (< 1000 emails)
- For high volume (> 1000 emails), consider using a custom database table
- Batch processing prevents timeout issues
- Hourly cron schedule is suitable for most use cases
- Can be adjusted to run more frequently if needed

## Security

- All email addresses are validated before queuing
- Email content is not sanitized (HTML emails supported)
- Admin interface requires `manage_options` capability
- Nonce verification for all admin actions
- Queue is only accessible to administrators

## Migration from Old System

The migration is automatic. All existing email sending code now uses the queue system:
- `send_welcome_email()` - Uses queue with type 'welcome'
- `send_automatic_renewal_reminders()` - Uses queue with type 'automatic_renewal_reminder_*'
- `send_manual_renewal_reminders()` - Uses queue with type 'manual_renewal_reminder_*'

No code changes required in other parts of the plugin.
