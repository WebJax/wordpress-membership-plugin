# Test Tools Usage Examples

## Example 1: Testing Reminder Emails for All Intervals

### Scenario
You want to verify that all reminder emails (30, 14, 7, and 1 day before expiration) are sent correctly for both automatic and manual renewal types.

### Steps
1. Navigate to WordPress Admin → Memberships → Test Tools
2. In the "Test Reminder Emails" section:
   - Email Address: `admin@example.com`
   - Reminder Type: `All Reminders (30, 14, 7, 1 days)`
   - Renewal Type: `Both (Manual & Automatic)`
3. Click "Send Test Reminder Emails"

### Expected Result
```
✓ Successfully sent 8 test reminder email(s) to admin@example.com. Check your inbox and spam folder.
```

You should receive 8 emails:
- Automatic Renewal - 30 Days Before Expiration
- Automatic Renewal - 14 Days Before Expiration
- Automatic Renewal - 7 Days Before Expiration
- Automatic Renewal - 1 Day Before Expiration
- Manual Renewal - 30 Days Before Expiration
- Manual Renewal - 14 Days Before Expiration
- Manual Renewal - 7 Days Before Expiration
- Manual Renewal - 1 Day Before Expiration

### Log Output
```
[2024-01-19 19:45:12] [INFO] - Starting test reminder email process. Target: admin@example.com, Type: all, Renewal: both
[2024-01-19 19:45:12] [INFO] - Sent test email: Automatic Renewal - 30 Days Before Expiration to admin@example.com
[2024-01-19 19:45:13] [INFO] - Sent test email: Automatic Renewal - 14 Days Before Expiration to admin@example.com
[2024-01-19 19:45:13] [INFO] - Sent test email: Automatic Renewal - 7 Days Before Expiration to admin@example.com
[2024-01-19 19:45:14] [INFO] - Sent test email: Automatic Renewal - 1 Day Before Expiration to admin@example.com
[2024-01-19 19:45:14] [INFO] - Sent test email: Manual Renewal - 30 Days Before Expiration to admin@example.com
[2024-01-19 19:45:15] [INFO] - Sent test email: Manual Renewal - 14 Days Before Expiration to admin@example.com
[2024-01-19 19:45:15] [INFO] - Sent test email: Manual Renewal - 7 Days Before Expiration to admin@example.com
[2024-01-19 19:45:16] [INFO] - Sent test email: Manual Renewal - 1 Day Before Expiration to admin@example.com
[2024-01-19 19:45:16] [INFO] - Test reminder email process completed. Sent 8 emails.
```

---

## Example 2: Testing Automatic Renewal Order Creation

### Scenario
You have a membership with automatic renewal set up and you want to test that the system can create a WooCommerce renewal order.

### Prerequisites
- At least one active membership with automatic renewal
- Automatic renewal product configured in Settings
- WooCommerce installed and activated

### Steps
1. Navigate to WordPress Admin → Memberships → Test Tools
2. In the "Test Automatic Renewal Process" section:
   - Select Membership: `John Doe (john@example.com) - Automatic - 45 days until expiry`
   - Check: `Force Renewal` (since we're not waiting for actual expiration)
3. Click "Test Automatic Renewal"

### Expected Result
```
✓ Successfully created test renewal order #12345 for subscription ID 42. [View Order]
```

Clicking "View Order" opens the WooCommerce order page showing:
- Order contains the automatic renewal product
- Order meta data includes:
  - `_membership_subscription_id`: 42
  - `_is_membership_renewal`: yes
- Order status: pending or processing (depending on payment method availability)
- Order note: "Automatic renewal order for membership subscription ID: 42"

### Log Output
```
[2024-01-19 19:50:22] [INFO] - Starting test automatic renewal for subscription ID: 42 (Force: Yes)
[2024-01-19 19:50:22] [INFO] - Attempting to create renewal order for subscription ID: 42 (User: 123)
[2024-01-19 19:50:22] [INFO] - Created renewal order #12345 for subscription ID: 42
[2024-01-19 19:50:22] [INFO] - Payment method set for order #12345, attempting automatic payment
[2024-01-19 19:50:23] [INFO] - Test automatic renewal successful. Created order #12345
```

### If Payment Method Exists
If the user has a saved payment method:
```
[2024-01-19 19:50:22] [INFO] - Payment method set for order #12345, attempting automatic payment
```

### If No Payment Method
If the user has no saved payment method:
```
[2024-01-19 19:50:22] [WARNING] - No saved payment methods for user 123. Manual payment required for order #12345
[2024-01-19 19:50:22] [ERROR] - Failed automatic renewal for subscription ID: 42. Reason: no_payment_method. Status set to pending-cancel.
```

---

## Example 3: Testing Full Renewal Process

### Scenario
You want to test the complete daily renewal process that normally runs via cron.

### Prerequisites
- Multiple active memberships with different expiration dates
- Some memberships expiring in 30, 14, 7, or 1 day(s)

### Steps
1. Navigate to WordPress Admin → Memberships → Test Tools
2. In the "Run Full Renewal Process" section
3. Click "Run Renewal Process Now"
4. Confirm the action
5. Click "View Logs" to see detailed output

### Expected Result
```
✓ Successfully ran the full renewal process. Check the logs below for details.
```

### Log Output Example
```
[2024-01-19 20:00:00] [INFO] - Manually triggered full renewal process from test tools.
[2024-01-19 20:00:00] [INFO] - Starting renewal process.
[2024-01-19 20:00:00] [INFO] - Found 25 active subscriptions to process.
[2024-01-19 20:00:01] [INFO] - Sending 30_days reminder for subscription ID: 15
[2024-01-19 20:00:01] [INFO] - Sent automatic renewal reminder (30_days) to: user1@example.com
[2024-01-19 20:00:02] [INFO] - Sending 14_days reminder for subscription ID: 23
[2024-01-19 20:00:02] [INFO] - Sent manual renewal reminder (14_days) to: user2@example.com
[2024-01-19 20:00:03] [INFO] - Sending 7_days reminder for subscription ID: 31
[2024-01-19 20:00:03] [INFO] - Sent automatic renewal reminder (7_days) to: user3@example.com
[2024-01-19 20:00:04] [INFO] - Processing automatic renewal for subscription ID: 42 on expiration date
[2024-01-19 20:00:04] [INFO] - Created renewal order #12346 for subscription ID: 42
[2024-01-19 20:00:05] [INFO] - Found 2 expired subscriptions. Updating status.
[2024-01-19 20:00:05] [INFO] - Marked subscription ID 18 (User 98) as expired.
[2024-01-19 20:00:05] [INFO] - Marked subscription ID 27 (User 105) as expired.
[2024-01-19 20:00:05] [INFO] - Finished renewal process.
[2024-01-19 20:00:05] [INFO] - Manual renewal process completed.
```

---

## Example 4: Testing with WooCommerce Stripe Gateway

### Scenario
You want to verify that automatic renewal works with Stripe payment gateway.

### Prerequisites
- WooCommerce Stripe Gateway installed and configured
- A membership whose user has a saved Stripe payment method
- Automatic renewal product configured

### Steps
1. Identify a user with saved Stripe payment method:
   - Go to WooCommerce → Customers
   - Find a customer with saved payment methods
2. Create or verify an automatic membership for this user
3. Go to Memberships → Test Tools
4. Select the membership
5. Check "Force Renewal"
6. Click "Test Automatic Renewal"

### Expected Result - Success
```
✓ Successfully created test renewal order #12347 for subscription ID 55. [View Order]
```

Order details:
- Payment method: Stripe
- Order status: Processing or Completed (if auto-charged successfully)
- Payment tokens attached to order

### Expected Result - Payment Declined
If the card is declined:
```
Order created but payment failed. Customer will receive email notification.
```

### Log Output - Success
```
[2024-01-19 20:10:15] [INFO] - Starting test automatic renewal for subscription ID: 55 (Force: Yes)
[2024-01-19 20:10:15] [INFO] - Attempting to create renewal order for subscription ID: 55 (User: 234)
[2024-01-19 20:10:15] [INFO] - Created renewal order #12347 for subscription ID: 55
[2024-01-19 20:10:15] [INFO] - Payment method set for order #12347, attempting automatic payment
[2024-01-19 20:10:16] [INFO] - Test automatic renewal successful. Created order #12347
```

### Log Output - Payment Failed
```
[2024-01-19 20:10:15] [INFO] - Starting test automatic renewal for subscription ID: 55 (Force: Yes)
[2024-01-19 20:10:15] [INFO] - Attempting to create renewal order for subscription ID: 55 (User: 234)
[2024-01-19 20:10:15] [INFO] - Created renewal order #12347 for subscription ID: 55
[2024-01-19 20:10:15] [INFO] - Payment method set for order #12347, attempting automatic payment
[2024-01-19 20:10:16] [ERROR] - Failed automatic renewal for subscription ID: 55. Reason: payment_failed. Status set to pending-cancel.
[2024-01-19 20:10:16] [INFO] - Sent failed renewal email to: customer@example.com
```

---

## Example 5: Troubleshooting Email Delivery Issues

### Scenario
Test emails are not being received.

### Debugging Steps

#### Step 1: Test General Email Configuration
1. Go to Settings → Test Email
2. Send a basic test email
3. Check if received

#### Step 2: Check Email Settings
1. Go to Memberships → Settings
2. Verify:
   - "Enable Email Reminders" is checked
   - "From Name" is set
   - "From Email Address" is valid

#### Step 3: Run Test with Logs
1. Go to Memberships → Test Tools
2. Send test reminder emails
3. Click "View Logs"

#### Step 4: Analyze Log Output

**If logs show:**
```
[2024-01-19 20:20:00] [ERROR] - Failed to send email to: admin@example.com with subject: Your membership will expire in 30 days
```

**Possible causes:**
- Server email configuration issue
- SMTP plugin needed
- Email address blocked by server

**Solution:**
Install and configure an SMTP plugin like "WP Mail SMTP" or "Easy WP SMTP"

**If logs show:**
```
[2024-01-19 20:20:00] [INFO] - Sent automatic renewal reminder (30_days) to: admin@example.com
```

**But email not received:**
- Check spam/junk folder
- Verify email server isn't blocking the sender
- Check email server logs
- Try a different recipient email address

---

## Common Test Scenarios

### Scenario: New Installation
**Test:**
1. Test all reminder email types
2. Test automatic renewal with force
3. View logs to verify system is working

### Scenario: Before Going Live
**Test:**
1. Create test memberships with various expiration dates
2. Run full renewal process
3. Verify all emails sent correctly
4. Verify renewal orders created
5. Test with actual payment gateway in test mode

### Scenario: After WooCommerce Gateway Change
**Test:**
1. Test automatic renewal with new gateway
2. Verify payment method is attached correctly
3. Test with saved payment methods
4. Test without saved payment methods

### Scenario: Troubleshooting Production Issues
**Test:**
1. View logs for recent activity
2. Test specific membership that's having issues
3. Compare log output with expected behavior

---

## Tips for Effective Testing

1. **Always test in staging first** - Never test production emails to real customers
2. **Use dedicated test email addresses** - Create test@yourdomain.com for testing
3. **Document test results** - Keep notes of what works and what doesn't
4. **Test all payment gateways** - Each gateway may behave differently
5. **Verify logs after each test** - Logs provide crucial debugging information
6. **Test both success and failure scenarios** - Don't just test happy path
7. **Check WooCommerce order details** - Verify metadata and notes are correct
8. **Test email deliverability** - Don't assume emails reach inbox

## Verification Checklist

After running tests, verify:

- [ ] Test emails received in inbox (not spam)
- [ ] Email content is correct and well-formatted
- [ ] Renewal orders created in WooCommerce
- [ ] Orders have correct product and metadata
- [ ] Payment methods attached correctly (for automatic)
- [ ] Logs show no unexpected errors
- [ ] All test scenarios covered
- [ ] Production-ready based on test results
