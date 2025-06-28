<?php
/**
 * Plugin Name: Wine Shipping Temperature Checker
 * Description: Warns customers if the forecasted temperature at their shipping address is too high for wine shipping. Offers handling options and stores choice in the order. Includes admin settings.
 * Version: 1.9
 * Author: Lo-Fi Wines
 */

if (!defined('ABSPATH')) exit;

// ---------------------------
// 1. Register Admin Settings
// ---------------------------

function wtc_register_settings() {
    add_option('wtc_temp_threshold', 85);
    add_option('wtc_enabled', 'yes');
    add_option('wtc_api_key', '');
    add_option('wtc_ups_client_id', '');
    add_option('wtc_ups_client_secret', '');
    add_option('wtc_ups_username', '');
    add_option('wtc_ups_password', '');
    add_option('wtc_origin_zip', '93401');

    register_setting('wtc_options_group', 'wtc_temp_threshold', 'intval');
    register_setting('wtc_options_group', 'wtc_enabled');
    register_setting('wtc_options_group', 'wtc_api_key');
    register_setting('wtc_options_group', 'wtc_ups_client_id');
    register_setting('wtc_options_group', 'wtc_ups_client_secret');
    register_setting('wtc_options_group', 'wtc_ups_username');
    register_setting('wtc_options_group', 'wtc_ups_password');
    register_setting('wtc_options_group', 'wtc_origin_zip');
}
add_action('admin_init', 'wtc_register_settings');

// ---------------------------
// 2. Add Admin Settings Page
// ---------------------------

function wtc_register_options_page() {
    add_submenu_page(
        'woocommerce',
        'Shipping Temp Check',
        'Shipping Temp Check',
        'manage_options',
        'wtc-settings',
        'wtc_options_page'
    );
}
add_action('admin_menu', 'wtc_register_options_page');

function wtc_options_page() {
    ?>
    <div class="wrap">
        <h1>Shipping Temperature Check Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wtc_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Temperature Check</th>
                    <td>
                        <select name="wtc_enabled">
                            <option value="yes" <?php selected(get_option('wtc_enabled'), 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected(get_option('wtc_enabled'), 'no'); ?>>No</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Temperature Threshold (°F)</th>
                    <td><input type="number" name="wtc_temp_threshold" value="<?php echo esc_attr(get_option('wtc_temp_threshold')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">OpenWeatherMap API Key</th>
                    <td><input type="text" name="wtc_api_key" value="<?php echo esc_attr(get_option('wtc_api_key')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">UPS Client ID</th>
                    <td><input type="text" name="wtc_ups_client_id" value="<?php echo esc_attr(get_option('wtc_ups_client_id')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">UPS Client Secret</th>
                    <td><input type="text" name="wtc_ups_client_secret" value="<?php echo esc_attr(get_option('wtc_ups_client_secret')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">UPS Username</th>
                    <td><input type="text" name="wtc_ups_username" value="<?php echo esc_attr(get_option('wtc_ups_username')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">UPS Password</th>
                    <td><input type="password" name="wtc_ups_password" value="<?php echo esc_attr(get_option('wtc_ups_password')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Origin ZIP Code</th>
                    <td><input type="text" name="wtc_origin_zip" value="<?php echo esc_attr(get_option('wtc_origin_zip')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ---------------------------
// 2.5 UPS Delivery Estimate with OAuth
// ---------------------------

function wtc_estimate_delivery_day_offset($dest_zip) {
    $client_id = get_option('wtc_ups_client_id');
    $client_secret = get_option('wtc_ups_client_secret');
    $username = get_option('wtc_ups_username');
    $password = get_option('wtc_ups_password');
    $origin_zip = get_option('wtc_origin_zip');

    if (!$client_id || !$client_secret || !$username || !$password || !$origin_zip) {
        return 2; // fallback if any credentials missing
    }

    // Get OAuth token
    $auth_response = wp_remote_post('https://wwwcie.ups.com/security/v1/oauth/token', [
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode("$client_id:$client_secret"),
        ],
        'body' => [
            'grant_type' => 'client_credentials'
        ]
    ]);

    if (is_wp_error($auth_response)) return 2;
    $auth_body = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_body['access_token'] ?? null;
    if (!$access_token) return 2;

    $body = [
        'Request' => [
            'RequestOption' => 'TNT'
        ],
        'ShipFrom' => [
            'Address' => ['PostalCode' => $origin_zip, 'CountryCode' => 'US']
        ],
        'ShipTo' => [
            'Address' => ['PostalCode' => $dest_zip, 'CountryCode' => 'US']
        ],
        'Pickup' => [
            'Date' => date('Ymd', strtotime('+1 day')) // next-day shipment
        ],
        'ShipmentWeight' => [
            'UnitOfMeasurement' => ['Code' => 'LBS'],
            'Weight' => '5'
        ]
    ];

    $rate_response = wp_remote_post('https://wwwcie.ups.com/api/shipments/v1/transittimes', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token
        ],
        'body' => json_encode($body)
    ]);

    if (is_wp_error($rate_response)) return 2;
    $rate_body = json_decode(wp_remote_retrieve_body($rate_response), true);

    $days = $rate_body['TransitResponse']['ServiceSummary'][0]['EstimatedArrival']['BusinessDaysInTransit'] ?? null;
    if ($days !== null && is_numeric($days)) {
        return intval($days) + 1; // add one for warehouse processing day
    }

    return 2; // fallback if no estimate
}

// ---------------------------
// 3. Add Warning + Options at Checkout
// ---------------------------

add_action('woocommerce_review_order_before_submit', 'wtc_show_warning_and_options');

function wtc_show_warning_and_options() {
    if (get_option('wtc_enabled') !== 'yes') return;

    $zip = WC()->customer->get_shipping_postcode();
    $country = WC()->customer->get_shipping_country();
    if (!$zip || strtoupper($country) !== 'US') return;

    $threshold = intval(get_option('wtc_temp_threshold', 85));
    $api_key = trim(get_option('wtc_api_key'));
    if (!$api_key) return;

    $offset = wtc_estimate_delivery_day_offset($zip);

    $url = "https://api.openweathermap.org/data/2.5/forecast?zip=$zip,US&appid=$api_key&units=imperial";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($data['list'])) return;

    $highs = [];
    foreach ($data['list'] as $forecast) {
        $date = date('Y-m-d', $forecast['dt']);
        $temp = $forecast['main']['temp_max'];
        if (!isset($highs[$date]) || $temp > $highs[$date]) {
            $highs[$date] = $temp;
        }
    }

    $dates = array_keys($highs);
    if (isset($dates[$offset])) {
        $arrival_date = $dates[$offset];
        $arrival_temp = $highs[$arrival_date] ?? null;

        if ($arrival_temp && $arrival_temp > $threshold) {
            echo '<div style="border: 2px solid red; padding: 15px; margin-bottom: 20px;">';
            echo "<strong>⚠️ Weather Warning:</strong> Forecasted high of <strong>$arrival_temp°F</strong> expected on <strong>$arrival_date</strong> — likely arrival day of your order.<br><br>";
            echo "<strong>📦 Estimated delivery day: $arrival_date</strong><br><br>";

            echo "<strong>📊 7-Day Forecast:</strong><br>";
            echo "<pre style='font-family:monospace; margin-top:5px;'>";
            $day_counter = 0;
            foreach ($highs as $date => $t) {
                if (++$day_counter > 7) break;
                $dayname = date('m/d', strtotime($date));
                $bar_length = max(0, min(30, intval(($t - 60) * 0.75)));
                $bar = str_repeat("▓", $bar_length);
                echo sprintf("%s: %-30s %s°F\n", $dayname, $bar, round($t));
            }
            echo "</pre><br>";

            echo '<p>Please choose how you want us to handle your wine order:</p>';
            echo '<label><input type="radio" name="shipping_temp_option" value="hold"> ✅ Hold my wine until it’s safe to ship.</label><br>';
            echo '<label><input type="radio" name="shipping_temp_option" value="cancel"> ❌ Cancel my order.</label><br>';
            echo '<label><input type="radio" name="shipping_temp_option" value="ship_anyway"> ⚠️ Ship anyway — I accept the risk and release you from responsibility.</label>';
            echo '</div>';
        }
    }
}

// ---------------------------
// 4. Validate Choice
// ---------------------------

add_action('woocommerce_after_checkout_validation', 'wtc_validate_choice', 10, 2);

function wtc_validate_choice($fields, $errors) {
    if (get_option('wtc_enabled') !== 'yes') return;

    if (!isset($_POST['shipping_temp_option']) || $_POST['shipping_temp_option'] === '') {
        $errors->add('shipping_temp_option', 'Please choose how you want us to handle your order due to forecasted high temperatures.');
    }

    if (isset($_POST['shipping_temp_option']) && $_POST['shipping_temp_option'] === 'cancel') {
        $errors->add('shipping_temp_option_cancelled', 'You selected “Cancel my order.” Please contact us if you change your mind. Your order has not been placed.');
    }
}

// ---------------------------
// 5. Save to Order Meta
// ---------------------------

add_action('woocommerce_checkout_order_processed', 'wtc_handle_order_choice_after_checkout', 20, 3);

function wtc_handle_order_choice_after_checkout($order_id, $posted_data, $order) {
    if (isset($_POST['shipping_temp_option'])) {
        $choice = sanitize_text_field($_POST['shipping_temp_option']);
        $order->update_meta_data('_shipping_temp_option', $choice);
        $order->save(); // Save meta explicitly

        if ($choice === 'cancel') {
            // Do not cancel — we've already blocked this in validation
            return;
        } elseif ($choice === 'hold') {
            $order->add_order_note('📦 Customer requested to HOLD shipment due to hot weather.');
        } elseif ($choice === 'ship_anyway') {
            $order->add_order_note('⚠️ Customer chose to ship despite high temperatures — liability disclaimed.');
        }
    }
}


// Display temperature option in the admin order screen
add_action('woocommerce_admin_order_data_after_order_details', 'wtc_show_shipping_temp_option_admin');

function wtc_show_shipping_temp_option_admin($order) {
    $choice = $order->get_meta('_shipping_temp_option');
    if (!$choice) return;

    echo '<p><strong>Shipping Temp Option:</strong> ';
    switch ($choice) {
        case 'hold':
            echo '✅ Hold until safe to ship';
            break;
        case 'cancel':
            echo '❌ Cancel my order';
            break;
        case 'ship_anyway':
            echo '⚠️ Ship anyway (customer accepted risk)';
            break;
    }
    echo '</p>';
}


add_action('woocommerce_thankyou', 'wtc_thank_you_message');

function wtc_thank_you_message($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $choice = $order->get_meta('_shipping_temp_option');
    if ($choice === 'hold') {
        echo '<div style="border: 2px dashed orange; padding: 15px; margin: 20px 0;">
            <strong>✅ Your wine order is on hold due to high temperatures.</strong><br>
            We’ll keep your wine safe and notify you when it’s ready to ship. Thank you!
        </div>';
    }
}

add_action('woocommerce_checkout_order_processed', 'wtc_send_hold_email', 20, 1);

function wtc_send_hold_email($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $choice = $order->get_meta('_shipping_temp_option');

    if ($choice === 'hold') {
        $to = $order->get_billing_email();
        $subject = 'Your Wine Order is on Hold for Weather';
        $body = "Thanks for your order!\n\nYou chose to hold your shipment due to high temperatures. We'll keep your wine safe and notify you when it's ready to ship.\n\nCheers,\nLo-Fi Wines";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        wp_mail($to, $subject, $body, $headers);
    }
}

add_action('woocommerce_payment_complete', 'wtc_hold_after_payment');

function wtc_hold_after_payment($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $choice = $order->get_meta('_shipping_temp_option');
    if ($choice === 'hold') {
        $order->update_status('on-hold', '📦 Auto-set to on-hold after payment due to hot weather hold request.');
    }
}

add_action('woocommerce_email_after_order_table', 'wtc_add_hold_notice_to_admin_email', 10, 4);

function wtc_add_hold_notice_to_admin_email($order, $sent_to_admin, $plain_text, $email) {
    if (!$sent_to_admin || $email->id !== 'new_order') return;

    $choice = $order->get_meta('_shipping_temp_option');
    if ($choice === 'hold') {
        $message = '<p style="color: orange;"><strong>⚠️ This order is on a Temperature Hold.</strong><br>';
        $message .= 'Do not ship until weather conditions are safe.</p>';

        if ($plain_text) {
            $message = "⚠️ This order is on a Temperature Hold.\nDo not ship until weather conditions are safe.\n";
        }

        echo wp_kses_post($message);
    }
}