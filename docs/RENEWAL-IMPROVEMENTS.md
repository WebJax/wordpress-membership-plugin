# Renewal Logic Improvements

This document describes the improvements made to the membership renewal logic in `includes/class-membership-renewals.php` and related files.

## Issues Addressed

### 1. No Transaction Support ✅
**Problem:** The original code could leave the database in an inconsistent state if order creation failed partway through.

**Solution:** Implemented database transactions using `START TRANSACTION`, `COMMIT`, and `ROLLBACK`.

```php
public function create_renewal_order( $subscription ) {
    global $wpdb;
    
    // Start transaction
    $wpdb->query( 'START TRANSACTION' );
    
    try {
        // Create order...
        $wpdb->query( 'COMMIT' );
        return $order_id;
    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        // Handle error...
        return false;
    }
}
```

**Requirements:**
- InnoDB table engine (MyISAM does not support transactions)
- WordPress default installation uses InnoDB

### 2. Complex Renewal Logic ✅
**Problem:** The `create_renewal_order()` method was doing too many things, making it hard to understand and maintain.

**Solution:** Refactored into smaller, focused methods:

- `get_renewal_product()` - Validates and retrieves the renewal product
- `create_wc_order()` - Creates the WooCommerce order
- `get_customer_payment_token()` - Retrieves customer's payment token
- `set_order_payment_method()` - Sets payment method on order

**Benefits:**
- Easier to test individual components
- Better error handling
- Improved code readability
- Single Responsibility Principle

### 3. Hardcoded 1-Year Duration ✅
**Problem:** Membership duration was hardcoded to 1 year throughout the codebase.

**Solution:** Implemented flexible, configurable duration system:

**New Constants:**
```php
const OPTION_DURATION_VALUE = 'membership_duration_value';
const OPTION_DURATION_UNIT = 'membership_duration_unit';
```

**New Methods in `Membership_Constants`:**
```php
// Get configured duration
public static function get_membership_duration()
// Returns: array( 'value' => 1, 'unit' => 'year' )

// Apply duration to a DateTime object
public static function apply_membership_duration( $date )

// Get valid duration units
public static function get_valid_duration_units()
// Returns: array( 'day', 'week', 'month', 'year' )
```

**Usage Example:**
```php
// Old way (hardcoded)
$end_date->modify( '+1 year' );

// New way (flexible)
Membership_Constants::apply_membership_duration( $end_date );
```

**Configuration:**
Duration can be configured via WordPress options:
```php
update_option( 'membership_duration_value', 6 );
update_option( 'membership_duration_unit', 'month' );
```

**Supported Units:**
- `day` - Daily memberships
- `week` - Weekly memberships  
- `month` - Monthly memberships
- `year` - Annual memberships (default)

### 4. Limited Payment Gateway Support ✅
**Problem:** Payment processing was tightly coupled to WooCommerce's token system, making it difficult to support other payment gateways.

**Solution:** Added abstraction layer and extensibility hooks:

**New Filter:**
```php
apply_filters( 'membership_manager_renewal_payment_processed', false, $order, $subscription, $payment_token );
```

**Usage:**
Payment gateways can hook into this filter to handle renewal payments:

```php
add_filter( 'membership_manager_renewal_payment_processed', function( $processed, $order, $subscription, $token ) {
    if ( $order->get_payment_method() === 'my_custom_gateway' ) {
        // Process payment with custom gateway
        $success = my_gateway_process_renewal( $order, $token );
        return $success; // Return true if payment was processed
    }
    return $processed;
}, 10, 4 );
```

**Existing Action Hook:**
```php
do_action( 'membership_manager_process_renewal_payment', $order, $subscription );
```

Payment gateways can also hook into this action for compatibility.

## Code Quality Improvements

### Constants Usage
All magic strings replaced with constants from `Membership_Constants`:

- `ORDER_META_SUBSCRIPTION_ID` - Order meta key for subscription ID
- `ORDER_META_IS_RENEWAL` - Order meta key for renewal flag
- `OPTION_AUTO_PRODUCTS` - Option key for automatic renewal products
- `HOOK_PROCESS_RENEWAL_PAYMENT` - Action hook name

### Error Handling
Improved error handling with:
- Transaction rollback on failures
- Detailed error logging
- Graceful degradation (e.g., fallback to default duration if invalid unit)

### DateTime Handling
Proper pluralization for DateTime::modify():
```php
// Correctly handles singular and plural forms
$unit_str = ( $value === 1 ) ? $unit : $unit . 's';
$date->modify( "+{$value} {$unit_str}" );
```

## Testing

### Verification Script
A verification script (`verify-changes.php`) validates:
- PHP syntax in all modified files
- Transaction support implementation
- Refactored method presence
- Flexible duration support
- Constants usage

Run with:
```bash
php verify-changes.php
```

### Unit Tests
New test file: `tests/test-membership-duration.php`

Tests cover:
- Default duration settings
- Valid duration units
- Duration application with different units (day, week, month, year)
- Invalid unit handling
- Pluralization

## Migration Guide

### For Existing Installations
No migration needed! The system uses sensible defaults:
- Default duration: 1 year
- Default unit: year
- Backward compatible with existing memberships

### For Custom Implementations
If you have custom code that:
1. **Directly modifies membership end dates:** Update to use `Membership_Constants::apply_membership_duration()`
2. **Implements custom payment processing:** Add a filter for `membership_manager_renewal_payment_processed`
3. **Uses magic strings:** Replace with constants from `Membership_Constants`

## Performance Considerations

### Transaction Overhead
- Minimal performance impact
- Transactions are short-lived (milliseconds)
- Only used during order creation
- Benefits far outweigh any overhead

### Database Requirements
- Requires InnoDB table engine
- WordPress uses InnoDB by default since version 5.5

## Future Enhancements

Potential improvements for future versions:
1. Admin UI for configuring membership duration
2. Per-product duration settings
3. Grace period configuration
4. Retry logic for failed automatic payments
5. Support for trial periods

## Support

For issues or questions:
1. Check the logs in `/logs/membership-manager.log`
2. Enable WP_DEBUG for detailed error messages
3. Review transaction failures in database error logs

## References

- WordPress Database Class: https://developer.wordpress.org/reference/classes/wpdb/
- WooCommerce Orders: https://woocommerce.com/document/orders/
- PHP DateTime: https://www.php.net/manual/en/class.datetime.php
