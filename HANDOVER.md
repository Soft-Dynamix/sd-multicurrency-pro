# SD MultiCurrency Pro - Project Handover Document

## Project Overview

**Project Name:** SD MultiCurrency Pro  
**Type:** WordPress/WooCommerce Plugin  
**Version:** 1.3.0  
**Date:** May 7, 2025  
**Status:** Production Ready

---

## Executive Summary

SD MultiCurrency Pro is a WordPress plugin that enables WooCommerce stores (using Yoco payment gateway) to display prices in multiple currencies while processing payments in ZAR (South African Rand). The plugin automatically detects customer location, shows prices in their preferred currency, and handles seamless currency conversion for payment processing.

### Key Business Value
- **Increased Conversions**: Customers see prices in their familiar currency
- **Reduced Support**: Clear pricing eliminates confusion
- **Payment Compliance**: Yoco gateway receives correct ZAR amounts
- **Flexible Pricing**: Set specific prices per currency or use automatic conversion

---

## Technical Architecture

### Technology Stack
- **Language:** PHP 7.4+
- **Framework:** WordPress Plugin API
- **Integration:** WooCommerce, Tutor LMS
- **External APIs:** Frankfurter Exchange Rate API
- **Payment Gateway:** Yoco (ZAR-only)

### Core Dependencies
- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+ with cURL extension

### File Structure
```
sd-multicurrency-pro/
├── sd-multicurrency-pro.php      # Main plugin file (entry point)
├── includes/
│   ├── core/                     # Core functionality
│   ├── frontend/                 # Frontend display
│   ├── integrations/             # Third-party integrations
│   └── admin/                    # Admin interface
├── assets/                       # CSS, JS, images
└── uninstall.php                 # Cleanup on uninstall
```

---

## Key Components

### 1. Currency Management (`class-currency.php`)
- Currency detection via IP geolocation
- Cookie-based currency persistence
- Manual currency switching via widget

### 2. Exchange Rates (`class-exchange-rates.php`)
- Frankfurter API integration
- Hourly automatic updates
- Rate caching in WordPress options

### 3. WooCommerce Integration (`class-woocommerce.php`)
- Price display filtering
- Cart/checkout currency conversion
- Yoco payment gateway compatibility
- Coupon handling for multi-currency

### 4. Tutor LMS Integration (`class-tutor.php`)
- Course price display
- Course archive filtering

---

## Price Calculation Logic

### Display Price Priority
1. **Currency-specific price** → If `_sd_price_{currency}` exists, use it directly
2. **USD fallback** → If USD price exists, convert to target currency
3. **ZAR conversion** → Convert base ZAR price using exchange rate

### Payment Flow (Yoco Gateway)
1. Customer views prices in selected currency (e.g., $35 USD)
2. On checkout, cart prices convert to ZAR internally
3. Yoco receives correct ZAR amount (e.g., R648)
4. Order meta stores original currency and converted prices

### Coupon Handling
- Fixed coupons: Display converted amount, calculate in ZAR
- Percentage coupons: Apply to ZAR prices, display converted result
- Payment processing uses ZAR amounts internally

---

## Configuration

### Settings (wp-admin > MultiCurrency > Settings)
| Setting | Description | Default |
|---------|-------------|---------|
| Base Currency | Store's base currency | ZAR |
| Active Currencies | Enabled currencies | USD, GBP |
| Checkout Notice | Show conversion notice | Enabled |
| Switcher Position | Currency switcher location | Top Right |

### Product Meta Fields
| Field | Description | Example |
|-------|-------------|---------|
| `_price` | Base ZAR price | 800 |
| `_sd_price_usd` | USD price (optional) | 35 |
| `_sd_price_gbp` | GBP price (optional) | 28 |

### Order Meta Fields
| Field | Description | Example |
|-------|-------------|---------|
| `_sdmc_customer_currency` | Customer's currency | USD |
| `_sdmc_exchange_rate` | Rate at order time | 0.054 |
| `_sdmc_discount_total` | Discount in customer currency | 6.48 |

---

## Deployment

### Requirements
- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- cURL PHP extension (for API calls)

### Installation
1. Upload plugin ZIP to WordPress
2. Activate through Plugins menu
3. Complete onboarding wizard
4. Configure active currencies
5. Set currency-specific prices (optional)

### Updates
- Plugin uses WordPress update mechanism
- License validation required for updates
- Exchange rates update hourly via cron

---

## Known Issues & Solutions

### Issue 1: Yoco Payment Errors
**Symptoms:** "Your order could not be processed by Yoco"
**Cause:** Currency not switching to ZAR during payment
**Solution:** `is_payment_processing()` method detects Yoco context and switches currency
**File:** `class-woocommerce.php` lines 294-334

### Issue 2: Coupon Shows ZAR Value
**Symptoms:** Fixed coupon displays R100 instead of $5.40
**Cause:** Display filter not converting coupon amount
**Solution:** `filter_coupon_html()` converts discount to display currency
**File:** `class-woocommerce.php` lines 690-737

### Issue 3: Canada Shows R800 (ZAR)
**Symptoms:** Canadian visitors see ZAR prices instead of USD
**Cause:** CAD not in active currencies, fell back to ZAR
**Solution:** Fallback to USD when detected currency not active
**File:** `class-currency.php` (geolocation detection)

---

## Testing Procedures

### Pre-Deployment Tests
1. **Currency Detection**
   - Test from different IP locations
   - Verify manual switching works
   - Test reset button functionality

2. **Price Display**
   - Product pages show correct currency
   - Cart totals calculate correctly
   - Coupons apply and display properly

3. **Payment Processing**
   - Yoco processes payment successfully
   - Correct ZAR amount charged
   - Order meta saved correctly

4. **Order Emails**
   - Customer receives confirmation
   - Currency conversion info shown
   - Original prices displayed

### Test Accounts
- Use VPN to test different locations
- Test with both logged-in and guest users
- Test with various coupon types

---

## Support & Maintenance

### Log Files
- Location: `/wp-content/uploads/sdmc-debug.log`
- Enable: `define('SDMC_DEBUG', true);` in wp-config.php

### Common Support Queries
1. **Prices not converting**
   - Check exchange rates are updating
   - Verify currency is in active list
   - Check product has base ZAR price

2. **Yoco payment fails**
   - Verify exchange rates exist
   - Check ZAR is base currency
   - Review debug logs

3. **Wrong currency detected**
   - User can manually switch
   - Check geolocation service
   - Verify IP is not localhost

### Maintenance Tasks
- **Weekly:** Check exchange rate updates are working
- **Monthly:** Review error logs
- **Quarterly:** Update currency list based on customer demand

---

## Future Roadmap

### Short Term (1-2 months)
- [ ] Currency-specific coupon creation
- [ ] Admin order editing with currency display
- [ ] Enhanced debug logging

### Medium Term (3-6 months)
- [ ] Multi-currency sales reports
- [ ] Additional payment gateway support
- [ ] Currency switcher styling options

### Long Term (6-12 months)
- [ ] AI-powered currency recommendations
- [ ] A/B testing for currency display
- [ ] Multi-store currency synchronization

---

## Contact Information

### Development Team
- **Lead Developer:** [Contact info]
- **Support:** support@example.com
- **Repository:** [GitHub URL]

### Client Contact
- **Business Owner:** [Contact info]
- **Technical Contact:** [Contact info]

---

## Sign-Off

This handover document represents the complete state of the SD MultiCurrency Pro plugin as of May 7, 2025. All features are tested and production-ready.

**Developer:** _____________________ Date: _____________

**Client:** _____________________ Date: _____________

---

## Appendices

### Appendix A: Exchange Rate API
- Provider: Frankfurter API (https://api.frankfurter.app)
- Update Frequency: Hourly
- Rate Limit: None (free API)
- Fallback: Cached rates used if API unavailable

### Appendix B: Supported Currencies
- USD (US Dollar)
- GBP (British Pound)
- EUR (Euro)
- ZAR (South African Rand) - Base
- Additional currencies can be enabled in settings

### Appendix C: Hook Reference
See DEVELOPER_DOCS.md for complete hook documentation.

### Appendix D: Database Schema
See DEVELOPER_DOCS.md for complete schema documentation.
