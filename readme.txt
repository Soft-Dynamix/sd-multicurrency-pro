=== SD MultiCurrency Pro ===
Contributors: softdynamix
Tags: woocommerce, multi-currency, currency switcher, yoco, tutor lms, south africa
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Multi-currency pricing for WooCommerce + Tutor LMS with Yoco gateway support. Perfect for South African businesses.

== Description ==

SD MultiCurrency Pro enables you to sell WooCommerce products and Tutor LMS courses in multiple currencies with per-product fixed pricing. Perfect for South African businesses using the Yoco payment gateway.

= Key Features =

* **Per-Currency Fixed Pricing** - Set individual prices per product/course for each currency (e.g., R800 ZAR, $35 USD, £28 GBP)
* **USD Fallback Currency** - Automatically use USD prices when no specific currency price is set
* **Currency Switcher** - Let customers choose their preferred display currency
* **Geolocation Detection** - Automatic currency detection based on customer location
* **Yoco Compatible** - Checkout always processes in ZAR for Yoco gateway compatibility
* **Coupon Support** - Full multi-currency coupon support (fixed and percentage)
* **WooCommerce Integration** - Full support for products, carts, and checkout
* **Tutor LMS Integration** - Multi-currency course pricing
* **Setup Wizard** - Easy 4-step onboarding process

= How It Works =

1. Customer visits your site
2. Currency is detected from their location (or manually selected)
3. Prices display in their currency throughout the site
4. At checkout, prices convert to ZAR for Yoco payment
5. Order stores both ZAR (charged) and customer currency (displayed)

= Price Priority =

When displaying prices, the plugin follows this priority:
1. **Currency-specific price** - If a price is set for the customer's currency, use it directly
2. **USD fallback** - If no currency price but USD price exists, convert to target currency
3. **ZAR conversion** - Convert the base ZAR price using current exchange rates

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sd-multicurrency-pro/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Follow the setup wizard to configure currencies
4. Set prices for each product/course in your preferred currencies

== Configuration ==

= Setting Up Currencies =

1. Go to **SD MultiCurrency Pro > Settings**
2. Add your supported currencies (ZAR, GBP, USD, EUR, etc.)
3. Set default currency and display format

= Per-Product Pricing =

1. Edit any WooCommerce product or Tutor LMS course
2. Find the "Multi-Currency Pricing" meta box
3. Enter prices for each enabled currency

= Currency Switcher =

Use the shortcode `[sd_currency_switcher]` to display the currency selector.

Available styles:
* `style="dropdown"` - Dropdown select (default)
* `style="buttons"` - Button group
* `style="flags"` - Flag icons

Example: `[sd_currency_switcher style="buttons"]`

== Yoco Gateway Compatibility ==

When using Yoco payment gateway, the plugin automatically:

1. Displays prices in the customer's selected currency
2. Converts cart to ZAR at checkout
3. Shows conversion notice to customer
4. Processes payment in ZAR
5. Stores original currency prices in order meta

== Coupon Support ==

The plugin fully supports WooCommerce coupons in multi-currency:

* **Fixed Coupons** - Display converted amount in customer's currency
* **Percentage Coupons** - Apply to prices correctly
* **Cart/Checkout** - Discounts calculated and displayed correctly
* **Order Emails** - Show discounts in customer's currency

== Frequently Asked Questions ==

= Does this work with any payment gateway? =

Yes! While optimized for Yoco (ZAR-only), it works with any gateway. The currency conversion happens transparently.

= Can I use automatic exchange rates? =

Yes, the plugin fetches exchange rates from the Frankfurter API and updates them hourly. You can also set fixed per-product prices for precise control.

= Does it support variable products? =

Yes, multi-currency pricing works with both simple and variable WooCommerce products.

= How do coupons work with different currencies? =

Fixed-amount coupons are displayed in the customer's currency (converted from ZAR). Percentage coupons work the same across all currencies.

= What happens if a currency doesn't have a specific price set? =

The plugin falls back to USD price (converted), then to ZAR base price (converted) using current exchange rates.

== Changelog ==

= 1.3.0 (May 7, 2025) =
* **Added**: Comprehensive coupon support for non-ZAR currencies
* **Fixed**: Cart total calculation now accounts for discounts, shipping, fees, taxes
* **Fixed**: Coupon display showing ZAR values instead of converted currency
* **Improved**: Order emails and thank you page display discounts correctly

= 1.2.0 (May 7, 2025) =
* **Added**: USD fallback currency support
* **Added**: `convert_between_currencies()` method
* **Fixed**: Geolocation fallback to USD instead of ZAR
* **Fixed**: Yoco payment processing errors

= 1.1.0 (May 6, 2025) =
* **Added**: Yoco payment gateway silent conversion
* **Fixed**: Payment errors with non-ZAR currencies

= 1.0.0 (May 6, 2025) =
* Initial release
* Multi-currency support for WooCommerce
* Tutor LMS integration
* Per-product fixed pricing
* Currency switcher shortcode
* Yoco gateway compatibility
* Setup wizard
* License management

== Upgrade Notice ==

= 1.3.0 =
Important update! This version fixes coupon display and calculation issues for non-ZAR currencies. Highly recommended for all users.

= 1.2.0 =
Important fix for Yoco payment processing. Update required if you accept payments in non-ZAR currencies.

== Requirements ==

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* Tutor LMS 2.0 or higher (optional)
* PHP 7.4 or higher with cURL extension

== Credits ==

Developed by [Soft Dynamix](https://softdynamix.co.za)

== License ==

GPL v2.0 or later
