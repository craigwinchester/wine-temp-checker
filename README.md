# Wine Shipping Temperature Checker

**Lo-Fi Wines – WooCommerce Plugin**

This plugin helps protect your wine shipments by checking the weather forecast at the customer's shipping address. If high temperatures are predicted on the estimated delivery day, it shows a warning at checkout and gives the customer options:

- ✅ Hold the order until it’s safe to ship
- ❌ Cancel the order
- ⚠️ Ship anyway (at customer's own risk)

---

## Features

- Uses **OpenWeatherMap API** to fetch a 7–8 day forecast by ZIP code
- Connects to **UPS API** to estimate delivery day using OAuth
- Highlights the **estimated arrival day** in the temperature chart
- Sends confirmation emails if a customer chooses to **hold** the order
- Adds customer’s choice to the **order notes** and **admin panel**
- Auto-holds orders in WooCommerce if requested

---

## Requirements

- WordPress + WooCommerce
- OpenWeatherMap API key
- UPS Developer credentials (Client ID, Secret, Username, Password)

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate it via the WordPress Plugins screen
3. Navigate to **WooCommerce > Shipping Temp Check**
4. Enter:
   - OpenWeatherMap API Key
   - UPS OAuth credentials
   - Your shipping origin ZIP code
   - Temp threshold (default: 85°F)

---

## Forecast Chart Example

06/28: ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 87°F

06/29: ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 92°F 

06/30: ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 94°F

07/01: ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 89°F

---

## Developer Notes

- Forecast data is pulled from OpenWeatherMap’s 3-hour forecast and parsed to show **daily highs**
- UPS API is used to calculate **BusinessDaysInTransit**, then +1 day for fulfillment
- Temperature bars scale from 60°F upward
- User choice is saved to `_shipping_temp_option` in order meta

---

## Email Alerts

If a customer chooses to hold the shipment:
- A confirmation email is sent to the customer
- Order is marked **On Hold** in WooCommerce
- Admin gets a note in the new order notification

---

## Why This Matters

Shipping wine in hot weather can ruin the product. This plugin gives the customer control and protects you from liability, while maintaining transparency.

---

## License

**GNU General Public License v2.0**
Do what you want, just don’t blame us if your wine gets cooked.

---

## Created by

[Lo-Fi Wines](https://www.lofi-wines.com) — natural wine producers in California

