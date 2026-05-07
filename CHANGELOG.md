# SD MultiCurrency Pro - Changelog

## [1.3.0] - 2025-05-07

### Added
- **Comprehensive Coupon Support for Non-ZAR Currencies**
  - Fixed cart discount display in customer's selected currency
  - Added `filter_coupon_amount()` for fixed coupon value display
  - Added `filter_discount_total_html()` for total discount display
  - Added `filter_order_discount_total()` for order discount on thank you page/emails
  - Added `filter_order_coupon_discount_html()` for coupon line item display in orders
  - Percentage coupons work correctly without modification

### Fixed
- **Cart Total Calculation**: Now properly accounts for coupons, shipping, fees, and taxes in display currency
- **Cart Subtotal Display**: Added payment processing check to prevent interference with Yoco
- **Coupon Display**: Fixed coupons showing ZAR values instead of converted currency values

### Technical
- All coupon filters skip processing during payment to ensure Yoco receives correct ZAR amounts
- Internal WooCommerce calculations remain in ZAR for payment compatibility
- Display layer only converts amounts for customer viewing

---

## [1.2.0] - 2025-05-07

### Added
- **USD Fallback Currency Support**
  - Added `convert_between_currencies()` method for currency-to-currency conversion
  - Price priority: Direct currency price → USD price (convert to target) → ZAR price (convert to target)

### Fixed
- **Geolocation Fallback**: When detected currency (e.g., CAD) is not in active currencies, fallback to USD instead of ZAR
- **Yoco Payment Processing**: Added `is_payment_processing()` method to detect payment context
- Currency symbol and code now return to ZAR during Yoco payment processing

---

## [1.1.0] - 2025-05-06

### Added
- **Yoco Payment Gateway Integration**
  - Silent currency conversion to ZAR during checkout
  - Customer sees selected currency throughout the entire purchase flow
  - Yoco receives correct ZAR amount for payment
  - `get_zar_price_for_payment()` calculates correct ZAR from currency-specific prices

### Fixed
- Payment errors when using non-ZAR currencies with Yoco gateway
- Currency detection and fallback logic

---

## [1.0.0] - 2025-05-06

### Added
- Initial release
- Multi-currency support for WooCommerce
- Currency-specific product pricing (`_sd_price_{currency}`)
- Exchange rate management with Frankfurter API
- Hourly automatic rate updates
- IP geolocation for automatic currency detection
- Manual currency switcher widget
- Tutor LMS integration
- Admin dashboard with exchange rate overview
- Product edit screen with currency price fields
- Order meta storage for currency conversion tracking
- Email notifications with conversion details

### Features
- **Price Priority System**:
  1. Currency-specific price if set
  2. USD price converted to target currency
  3. ZAR base price converted using exchange rate

- **Display Locations**:
  - Product pages
  - Shop archives
  - Cart
  - Checkout
  - Order emails
  - Thank you page

- **Admin Features**:
  - Settings page
  - Exchange rates management
  - Onboarding wizard
  - License management

---

## Upcoming Features

- [ ] Currency-specific coupon creation
- [ ] Multi-currency reports
- [ ] Additional payment gateway integrations
- [ ] Currency switcher styling options
- [ ] A/B testing for currency display
