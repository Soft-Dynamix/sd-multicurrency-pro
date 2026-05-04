# SD MultiCurrency Pro

A powerful WordPress plugin for multi-currency management on WooCommerce linked to Tutor LMS.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-green.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2.0%2B-orange.svg)

## Description

SD MultiCurrency Pro enables you to sell WooCommerce products and Tutor LMS courses in multiple currencies with **per-product fixed pricing**. Perfect for South African businesses using Yoco gateway.

### Key Features

- **Per-Currency Fixed Pricing**: Set individual prices per product/course for each currency (e.g., R800 ZAR, £35 GBP)
- **Currency Switcher**: Let customers choose their preferred display currency
- **Yoco Compatible**: Checkout always processes in ZAR for Yoco gateway compatibility
- **WooCommerce Integration**: Full support for products, carts, and checkout
- **Tutor LMS Integration**: Multi-currency course pricing
- **Setup Wizard**: Easy 4-step onboarding process
- **Professional Admin UI**: Soft Dynamix branded dashboard

## Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- Tutor LMS 2.0 or higher (optional)
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/sd-multicurrency-pro/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Follow the setup wizard to configure currencies
4. Set prices for each product/course in your preferred currencies

## Configuration

### Setting Up Currencies

1. Go to **SD MultiCurrency Pro > Settings**
2. Add your supported currencies (ZAR, GBP, USD, EUR, etc.)
3. Set default currency and display format

### Per-Product Pricing

1. Edit any WooCommerce product or Tutor LMS course
2. Find the "Multi-Currency Pricing" meta box
3. Enter prices for each enabled currency

### Currency Switcher

Use the shortcode `[sd_currency_switcher]` to display the currency selector.

Available styles:
- `style="dropdown"` - Dropdown select (default)
- `style="buttons"` - Button group
- `style="flags"` - Flag icons

Example:
```
[sd_currency_switcher style="buttons"]
```

## Yoco Gateway Compatibility

When using Yoco payment gateway, the plugin automatically:

1. Displays prices in the customer's selected currency
2. Converts cart to ZAR at checkout
3. Shows conversion notice to customer
4. Processes payment in ZAR

## License Activation

1. Go to **SD MultiCurrency Pro > License**
2. Enter your license key from Soft Dynamix
3. Click "Activate License"

## Changelog

### 1.0.0
- Initial release
- Multi-currency support for WooCommerce
- Tutor LMS integration
- Per-product fixed pricing
- Currency switcher shortcode
- Yoco gateway compatibility
- Setup wizard
- License management

## Frequently Asked Questions

### Does this work with any payment gateway?
Yes! While optimized for Yoco (ZAR-only), it works with any gateway.

### Can I use automatic exchange rates?
This plugin uses fixed per-product pricing for precise control. If you need automatic rates, they can be added in a future update.

### Does it support variable products?
Yes, multi-currency pricing works with both simple and variable WooCommerce products.

## Support

For support, please contact Soft Dynamix at support@softdynamix.co.za

## Credits

Developed by [Soft Dynamix](https://softdynamix.co.za)

## License

GPL v2.0 or later
