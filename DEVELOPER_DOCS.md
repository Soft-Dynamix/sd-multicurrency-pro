# SD MultiCurrency Pro - Developer Documentation

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Core Classes](#core-classes)
3. [Price Calculation Flow](#price-calculation-flow)
4. [Hooks and Filters](#hooks-and-filters)
5. [Database Schema](#database-schema)
6. [API Reference](#api-reference)
7. [Extending the Plugin](#extending-the-plugin)
8. [Testing Guide](#testing-guide)

---

## Architecture Overview

### Plugin Structure

```
sd-multicurrency-pro/
├── sd-multicurrency-pro.php      # Main plugin file
├── includes/
│   ├── core/
│   │   ├── class-currency.php    # Currency selection/detection
│   │   ├── class-exchange-rates.php  # Rate management
│   │   ├── class-geolocation.php # IP-based location
│   │   ├── class-settings.php    # Plugin settings
│   │   ├── class-helpers.php     # Utility functions
│   │   └── class-license.php     # License management
│   ├── frontend/
│   │   ├── class-display.php     # Frontend price display
│   │   └── class-switcher.php    # Currency switcher widget
│   ├── integrations/
│   │   ├── class-woocommerce.php # WooCommerce integration
│   │   └── class-tutor.php       # Tutor LMS integration
│   └── admin/
│       ├── class-admin-menu.php  # Admin menus
│       ├── class-product-fields.php  # Product meta boxes
│       └── views/                # Admin templates
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
└── uninstall.php
```

### Design Patterns

- **Singleton Pattern**: All main classes use singleton pattern for single instance
- **Filter-based Architecture**: Extensive use of WordPress hooks for extensibility
- **Separation of Concerns**: Core logic, display, and integrations are separated

---

## Core Classes

### SDMC_Currency (`class-currency.php`)

Handles currency selection, detection, and switching.

```php
// Get current customer's selected currency
$currency = SDMC_Currency::get_currency(); // Returns: 'USD', 'GBP', 'ZAR', etc.

// Get currency symbol
$symbol = SDMC_Currency::get_symbol('USD'); // Returns: '$'

// Get all active currencies
$currencies = SDMC_Currency::get_active_currencies();

// Detect currency from geolocation
$detected = SDMC_Currency::detect_from_geolocation();

// Set currency (with cookie persistence)
SDMC_Currency::set_currency('USD');

// Reset to detected currency
SDMC_Currency::reset_currency();
```

### SDMC_Exchange_Rates (`class-exchange-rates.php`)

Manages exchange rates from Frankfurter API.

```php
// Get exchange rate (1 ZAR = X units of currency)
$rate = SDMC_Exchange_Rates::get_rate('USD'); // Returns: 0.054 (example)

// Get inverse rate (1 currency = X ZAR)
$inverse = SDMC_Exchange_Rates::get_inverse_rate('USD'); // Returns: 18.52 (example)

// Get all rates
$rates = SDMC_Exchange_Rates::get_all_rates();

// Force refresh rates
SDMC_Exchange_Rates::refresh_rates();

// Get last update time
$last_update = SDMC_Exchange_Rates::get_last_update();
```

### SDMC_Integrations_Woocommerce (`class-woocommerce.php`)

Main WooCommerce integration for price display and payment processing.

#### Key Methods:

```php
// Get converted price for a product
$price = $woocommerce_integration->get_converted_price($product_id, 'USD');

// Convert between any two currencies
$converted = $woocommerce_integration->convert_between_currencies(100, 'USD', 'GBP');

// Get ZAR price for payment processing
$zar_price = $woocommerce_integration->get_zar_price_for_payment($product_id, 'USD');

// Convert amount to display currency
$display_amount = $woocommerce_integration->convert_amount_to_display_currency(100);

// Check if payment is being processed
$is_payment = $woocommerce_integration->is_payment_processing();
```

---

## Price Calculation Flow

### Display Price Calculation

```
┌─────────────────────────────────────────────────────────────┐
│                    Price Request                             │
│              get_converted_price($product_id, $currency)     │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Step 1: Check currency-specific price                      │
│  _sd_price_{currency} meta field                            │
│  Example: _sd_price_usd = 35                                │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Found?
                              ├──── YES ───► Return price
                              │
                              ▼ No
┌─────────────────────────────────────────────────────────────┐
│  Step 2: Check USD price and convert                        │
│  _sd_price_usd meta field                                   │
│  Convert USD → target currency using rates                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Found?
                              ├──── YES ───► Return converted price
                              │
                              ▼ No
┌─────────────────────────────────────────────────────────────┐
│  Step 3: Convert from ZAR base price                        │
│  _price meta field (ZAR)                                    │
│  Convert ZAR → target currency using rates                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                        Return price
```

### Payment Flow (Yoco Gateway)

```
┌─────────────────────────────────────────────────────────────┐
│                  Customer Checkout                           │
│         (Viewing prices in USD, GBP, etc.)                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│           convert_cart_prices_to_zar()                       │
│   Converts all cart item prices to ZAR for Yoco             │
│   Uses get_zar_price_for_payment() for each item            │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│           WooCommerce Calculates Totals                      │
│           All calculations in ZAR                            │
│           Coupons applied in ZAR                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│           Yoco Payment Gateway                               │
│           Receives ZAR amount                                │
│           Processes payment in ZAR                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│           Order Created                                      │
│           Stores:                                            │
│           - _sdmc_customer_currency (e.g., 'USD')           │
│           - _sdmc_exchange_rate                              │
│           - _sdmc_discount_total (in customer currency)     │
│           - _sdmc_original_price (per item)                 │
└─────────────────────────────────────────────────────────────┘
```

### ZAR Price Calculation for Payment

```php
private function get_zar_price_for_payment($product_id, $display_currency) {
    // 1. If currency-specific price exists, convert to ZAR
    if ($currency_price) {
        return $currency_price / $exchange_rate;
    }
    
    // 2. If USD price exists, convert to ZAR
    if ($usd_price) {
        return $usd_price / $usd_rate;
    }
    
    // 3. Use base ZAR price
    return $base_zar_price;
}
```

---

## Hooks and Filters

### Currency Detection Hooks

```php
// Filter detected currency
add_filter('sdmc_detected_currency', function($currency, $country) {
    // Custom logic for currency detection
    return $currency;
}, 10, 2);

// Filter active currencies
add_filter('sdmc_active_currencies', function($currencies) {
    // Add or remove currencies
    return $currencies;
});
```

### Price Display Hooks

```php
// Filter converted price
add_filter('sdmc_converted_price', function($price, $product_id, $currency) {
    // Custom price modification
    return $price;
}, 10, 3);

// Filter exchange rate
add_filter('sdmc_exchange_rate', function($rate, $currency) {
    // Apply markup or custom rates
    return $rate * 1.02; // 2% markup
}, 10, 2);
```

### WooCommerce Filters Used

| Filter | Purpose | Priority |
|--------|---------|----------|
| `woocommerce_currency_symbol` | Change displayed symbol | 10 |
| `woocommerce_currency` | Change displayed currency code | 99 |
| `woocommerce_get_price_html` | Filter product price HTML | 99 |
| `woocommerce_cart_item_price` | Filter cart item price | 99 |
| `woocommerce_cart_item_subtotal` | Filter cart item subtotal | 99 |
| `woocommerce_cart_subtotal` | Filter cart subtotal | 99 |
| `woocommerce_cart_total` | Filter cart total | 99 |
| `woocommerce_cart_totals_coupon_html` | Filter coupon display | 99 |
| `woocommerce_coupon_amount` | Filter coupon amount | 99 |
| `woocommerce_cart_totals_discount_total_html` | Filter discount total | 99 |
| `woocommerce_cart_totals_fee_html` | Filter fee display | 99 |
| `woocommerce_cart_shipping_method_full_label` | Filter shipping label | 99 |
| `woocommerce_cart_totals_taxes_total_html` | Filter tax display | 99 |
| `woocommerce_order_formatted_line_subtotal` | Filter order line subtotal | 99 |
| `woocommerce_get_formatted_order_total` | Filter order total | 99 |
| `woocommerce_order_get_discount_total` | Filter order discount | 99 |
| `woocommerce_coupon_discount_amount_html` | Filter coupon discount HTML | 99 |

### Actions Used

| Action | Purpose |
|--------|---------|
| `woocommerce_checkout_create_order` | Add order meta data |
| `woocommerce_admin_order_data_after_billing_address` | Display conversion info |
| `woocommerce_email_after_order_table` | Add conversion info to emails |
| `woocommerce_before_calculate_totals` | Convert cart to ZAR |
| `woocommerce_before_checkout_form` | Show checkout notice |

---

## Database Schema

### Options Table

```sql
-- Plugin settings
sdmc_settings = [
    'base_currency' => 'ZAR',
    'active_currencies' => ['USD', 'GBP', 'EUR'],
    'checkout_notice' => true,
    'switcher_position' => 'top_right',
    // ...
]

-- Exchange rates (cached)
sdmc_exchange_rates = [
    'USD' => 0.054,
    'GBP' => 0.043,
    'EUR' => 0.050,
    // ...
]

-- Last rate update
sdmc_rates_last_update = '2025-05-07 10:00:00'
```

### Post Meta (Products)

```sql
-- Base price (WooCommerce default)
_price = '800'  -- ZAR

-- Currency-specific prices
_sd_price_usd = '35'
_sd_price_gbp = '28'
_sd_price_eur = '32'
```

### Order Meta

```sql
-- Customer's selected currency
_sdmc_customer_currency = 'USD'

-- Base currency (always ZAR)
_sdmc_base_currency = 'ZAR'

-- Exchange rate at order time (1 ZAR = X currency)
_sdmc_exchange_rate = '0.054'

-- Inverse rate (1 currency = X ZAR)
_sdmc_inverse_rate = '18.52'

-- Rate last update time
_sdmc_rate_last_update = '2025-05-07 10:00:00'

-- Total discount in customer currency
_sdmc_discount_total = '6.48'

-- Individual coupon discounts
_sdmc_coupon_SUMMER20 = '6.48'

-- Shipping total in customer currency
_sdmc_shipping_total = '5.40'
```

### Order Item Meta

```sql
-- Original currency
_sdmc_original_currency = 'USD'

-- Original price in customer currency
_sdmc_original_price = '35'

-- Price source: 'direct', 'usd', 'zar'
_sdmc_price_source = 'usd'

-- Whether price was converted
_sdmc_is_converted = '1'

-- Source price (if converted)
_sdmc_source_price = '35'
```

---

## API Reference

### Class: SDMC_Currency

#### Static Methods

```php
/**
 * Get singleton instance
 */
public static function get_instance(): SDMC_Currency

/**
 * Get currently selected currency
 * @return string Currency code (e.g., 'USD')
 */
public static function get_currency(): string

/**
 * Get currency symbol
 * @param string $currency Currency code
 * @return string Symbol (e.g., '$')
 */
public static function get_symbol(string $currency): string

/**
 * Get all active currencies
 * @return array List of currency codes
 */
public static function get_active_currencies(): array

/**
 * Set customer's currency (persists in cookie)
 * @param string $currency Currency code
 */
public static function set_currency(string $currency): void

/**
 * Reset to geolocation-detected currency
 */
public static function reset_currency(): void

/**
 * Detect currency from customer's IP
 * @return string|false Currency code or false
 */
public static function detect_from_geolocation()
```

### Class: SDMC_Exchange_Rates

#### Static Methods

```php
/**
 * Get exchange rate for a currency
 * @param string $currency Currency code
 * @return float|false Rate (1 ZAR = X currency) or false
 */
public static function get_rate(string $currency)

/**
 * Get inverse rate (1 currency = X ZAR)
 * @param string $currency Currency code
 * @return float|false Rate or false
 */
public static function get_inverse_rate(string $currency)

/**
 * Get all exchange rates
 * @return array Associative array of rates
 */
public static function get_all_rates(): array

/**
 * Refresh rates from API
 * @return bool Success
 */
public static function refresh_rates(): bool

/**
 * Get last update timestamp
 * @return string Formatted datetime
 */
public static function get_last_update(): string
```

### Class: SDMC_Integrations_Woocommerce

#### Public Methods

```php
/**
 * Get singleton instance
 */
public static function get_instance(): SDMC_Integrations_Woocommerce

/**
 * Filter price HTML for display
 * @param string $price_html Original HTML
 * @param WC_Product $product Product object
 * @return string Modified HTML
 */
public function filter_price_html(string $price_html, WC_Product $product): string

/**
 * Filter coupon HTML in cart
 * @param string $html Original HTML
 * @param WC_Coupon $coupon Coupon object
 * @param string $discount_amount_html Discount HTML
 * @return string Modified HTML
 */
public function filter_coupon_html(string $html, WC_Coupon $coupon, string $discount_amount_html): string

/**
 * Filter cart total for display
 * @param string $total Original total
 * @return string Modified total
 */
public function filter_cart_total(string $total): string
```

---

## Extending the Plugin

### Adding a New Currency

```php
// Add currency to active list
add_filter('sdmc_active_currencies', function($currencies) {
    $currencies[] = 'CAD';
    return $currencies;
});

// Add currency symbol
add_filter('sdmc_currency_symbol', function($symbol, $currency) {
    if ($currency === 'CAD') {
        return 'C$';
    }
    return $symbol;
}, 10, 2);
```

### Custom Exchange Rate Provider

```php
// Override exchange rates
add_filter('sdmc_exchange_rate', function($rate, $currency) {
    // Fetch from custom API
    $custom_rate = get_custom_rate($currency);
    return $custom_rate ?? $rate;
}, 10, 2);
```

### Adding Payment Gateway Support

```php
// Detect custom gateway processing
add_filter('sdmc_is_payment_processing', function($is_processing) {
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'custom_gateway') {
        return true;
    }
    return $is_processing;
});
```

---

## Testing Guide

### Manual Testing Checklist

#### Currency Detection
- [ ] Clear cookies, visit site, verify geolocation currency
- [ ] Switch currency manually, verify persistence
- [ ] Click reset button, verify geolocation redetection
- [ ] Test with VPN for different countries

#### Price Display
- [ ] Product page shows correct currency
- [ ] Shop/archive pages show correct currency
- [ ] Variable products display correctly
- [ ] Sale prices convert properly
- [ ] Currency-specific prices override exchange rates

#### Cart and Checkout
- [ ] Cart shows prices in selected currency
- [ ] Cart totals calculate correctly
- [ ] Coupons apply and show converted amounts
- [ ] Shipping displays in selected currency
- [ ] Taxes display in selected currency
- [ ] Total is correct after all calculations

#### Payment (Yoco)
- [ ] Payment processes successfully
- [ ] Correct ZAR amount is charged
- [ ] Order created with correct totals
- [ ] Customer currency stored in order meta

#### Order Display
- [ ] Thank you page shows customer currency
- [ ] Order emails show conversion info
- [ ] Admin shows both ZAR and customer currency
- [ ] Order items show original prices

### Unit Test Examples

```php
class SDMC_Price_Conversion_Test extends WP_UnitTestCase {
    
    public function test_direct_currency_price() {
        $product_id = $this->create_product(['price' => 800]);
        update_post_meta($product_id, '_sd_price_usd', 35);
        
        $price = $this->get_converted_price($product_id, 'USD');
        
        $this->assertEquals(35.0, $price);
    }
    
    public function test_usd_fallback() {
        $product_id = $this->create_product(['price' => 800]);
        update_post_meta($product_id, '_sd_price_usd', 35);
        
        // GBP should convert from USD
        $price = $this->get_converted_price($product_id, 'GBP');
        
        $this->assertGreaterThan(0, $price);
        $this->assertNotEquals(800, $price); // Should not be ZAR price
    }
    
    public function test_zar_payment_conversion() {
        $product_id = $this->create_product(['price' => 800]);
        update_post_meta($product_id, '_sd_price_usd', 35);
        
        // USD rate: 1 ZAR = 0.054 USD
        SDMC_Exchange_Rates::update_rate('USD', 0.054);
        
        $zar_price = $this->get_zar_price_for_payment($product_id, 'USD');
        
        // 35 USD / 0.054 = ~648 ZAR
        $this->assertEqualsWithDelta(648.15, $zar_price, 1.0);
    }
}
```

### Debug Mode

Enable debug mode in wp-config.php:

```php
define('SDMC_DEBUG', true);
```

Debug logs are written to:
- `/wp-content/uploads/sdmc-debug.log`

---

## Support

For issues and feature requests:
- GitHub Issues: [repository-url]
- Email: support@example.com

## License

Proprietary license - see LICENSE file for details.
