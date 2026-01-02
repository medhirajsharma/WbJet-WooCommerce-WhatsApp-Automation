<?php
if (! defined('ABSPATH')) {
    exit;
}

// Hook into WooCommerce order status changes
add_action('woocommerce_order_status_changed', 'swiftchatswc_handle_order_status_change', 10, 4);

// Abandoned Cart Logic
add_action('woocommerce_cart_updated', 'swiftchatswc_maybe_schedule_abandoned_cart_check', 999);
add_action('swiftchatswc_check_abandoned_cart', 'swiftchatswc_process_abandoned_cart', 10, 1);

function swiftchatswc_handle_order_status_change($order_id, $old_status, $new_status, $order)
{
    global $wpdb;

    // Ensure we have valid parameters
    if (empty($order_id) || empty($new_status)) {
        return;
    }

    // Get the trigger for this status
    $table_name = $wpdb->prefix . 'swiftchats_triggers';
    $trigger    = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE order_status = %s AND is_active = 1",
        'wc-' . sanitize_text_field($new_status)
    ));

    // Also check for abandoned_cart pseudo-status
    if (! $trigger && $new_status === 'abandoned_cart') {
        $trigger = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_status = %s AND is_active = 1",
            'abandoned_cart'
        ));
    }

    if ($trigger) {
        $order_obj = wc_get_order($order_id);
        if ($order_obj) {
            $phone = $order_obj->get_billing_phone();
            if (! empty($phone)) {
                $variable_mappings = $trigger->variable_mappings ? json_decode($trigger->variable_mappings, true) : [];
                require_once plugin_dir_path(__FILE__) . 'api-handler.php';
                $api_handler       = new SwiftChatsWC_API_Handler();
                $template_metadata = $trigger->template_metadata ? json_decode($trigger->template_metadata, true) : null;
                if ($template_metadata) {
                    $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $order_obj);
                }
            }
        }
    }

    // --- NOTIFICATION LOGIC (business phone) ---
    $notifications_table = $wpdb->prefix . 'swiftchats_notifications';
    // Try both with and without wc- prefix for order_status
    $notification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $notifications_table WHERE (order_status = %s OR order_status = %s) AND is_active = 1",
        'wc-' . sanitize_text_field($new_status),
        sanitize_text_field($new_status)
    ));
    if ($notification) {
        $options        = get_option('swiftchatswc_options', []);
        $country_code   = $options['country_code'] ?? '+1';
        $business_phone = $options['business_phone'] ?? '';
        // Log business_phone and to
        $log_message = '[notification] ' . date('[Y-m-d H:i:s] ') . 'business_phone: ' . $business_phone . PHP_EOL;
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);
        if (! empty($business_phone)) {
            $to          = $country_code . preg_replace('/[^0-9]/', '', $business_phone);
            $log_message = '[notification] ' . date('[Y-m-d H:i:s] ') . 'to: ' . $to . PHP_EOL;
            file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);
            $variable_mappings = $notification->variable_mappings ? json_decode($notification->variable_mappings, true) : [];
            require_once plugin_dir_path(__FILE__) . 'api-handler.php';
            $api_handler       = new SwiftChatsWC_API_Handler();
            $template_metadata = $notification->template_metadata ? json_decode($notification->template_metadata, true) : null;
            if ($template_metadata) {
                $api_handler->send_template_with_metadata($to, $template_metadata, $variable_mappings, $order);
            }
        }
    }
}

function swiftchatswc_send_whatsapp_message($to, $message)
{
    $options = get_option('swiftchatswc_options', []);
    $api_key = $options['api_key'] ?? '';

    if (empty($api_key) || empty($to) || empty($message)) {
        return false;
    }

    // Format phone number
    $country_code = $options['country_code'] ?? '+1';
    $to           = preg_replace('/[^0-9]/', '', (string) $to);
    $to           = $country_code . $to;

    // TODO: Implement actual API call to WhatsApp service
    // This is a placeholder for the actual API implementation
    // You would need to implement the actual API call based on your WhatsApp service provider

    return true;
}

function swiftchatswc_maybe_schedule_abandoned_cart_check()
{
    if (! is_user_logged_in() && ! isset($_COOKIE['woocommerce_cart_hash'])) {
        return;
    }
    $options = get_option('swiftchatswc_options', []);
    if (empty($options['enable_abandoned_cart'])) {
        return;
    }
    if (! WC()->session || ! WC()->session->has_session()) {
        return;
    }
    $timeout     = isset($options['abandoned_cart_timeout']) ? (int) $options['abandoned_cart_timeout'] : 60;
    $timeout     = max($timeout, 1) * MINUTE_IN_SECONDS;
    $session_key = WC()->session->get_customer_id();
    if (! $session_key) {
        return;
    }
    // Cancel any previous scheduled action for this session
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('swiftchatswc_check_abandoned_cart', [$session_key]);
    }
    // Only schedule if cart is not empty
    if (! WC()->cart->is_empty()) {
        if (function_exists('as_next_scheduled_action') && ! as_next_scheduled_action('swiftchatswc_check_abandoned_cart', [$session_key])) {
            as_schedule_single_action(time() + $timeout, 'swiftchatswc_check_abandoned_cart', [$session_key]);
        }
    }
}

function swiftchatswc_process_abandoned_cart($session_key)
{
    $log_message = '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . "Processing session_key: $session_key" . PHP_EOL;
    file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);

    $options = get_option('swiftchatswc_options', []);
    if (empty($options['enable_abandoned_cart'])) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: Abandoned cart feature is disabled.' . PHP_EOL, FILE_APPEND);
        return;
    }
    // Get Woo session handler
    $session_handler = WC()->session;
    if (! $session_handler) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: WC_Session handler not found.' . PHP_EOL, FILE_APPEND);
        return;
    }
    // Try to load the session for this user
    $cart = null;
    if (method_exists($session_handler, 'get_session')) {
        $cart = $session_handler->get_session($session_key);
    }
    if (! $cart || empty($cart['cart']) || empty($cart['cart_totals'])) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: Cart is empty or could not be retrieved.' . PHP_EOL, FILE_APPEND);
        return; // No cart to process
    }

    // Unserialize customer data if it's a string
    $customer_data = $cart['customer'] ?? null;
    if (is_string($customer_data)) {
        $customer_data = maybe_unserialize($customer_data);
    }

    // Ensure we have an array to work with
    if (! is_array($customer_data)) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: Customer data is not in a readable format.' . PHP_EOL, FILE_APPEND);
        return;
    }

    // --- RE-ENABLED DEBUG LOG ---
    $customer_data_log = '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Customer data in session (after unserialize): ' . print_r($customer_data, true) . PHP_EOL;
    file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $customer_data_log, FILE_APPEND);
    // --- END RE-ENABLED DEBUG LOG ---

    // Get phone/email if possible (for guests, this may be missing)
    $phone = $customer_data['billing_phone'] ?? $customer_data['phone'] ?? '';
    if (empty($phone)) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: No billing phone number found in session.' . PHP_EOL, FILE_APPEND);
        return;
    }

    $log_message = '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . "Found phone number: $phone" . PHP_EOL;
    file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);

    // Fire the abandoned_cart trigger
    global $wpdb;
    $table_name = $wpdb->prefix . 'swiftchats_triggers';
    $trigger    = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE order_status = %s AND is_active = 1",
        'abandoned_cart'
    ));
    if (! $trigger) {
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . 'Exiting: No active trigger found for abandoned_cart.' . PHP_EOL, FILE_APPEND);
        return;
    }

    $log_message = '[abandoned_cart] ' . date('[Y-m-d H:i:s] ') . "Found active trigger. Preparing to send message." . PHP_EOL;
    file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);

    $variable_mappings = $trigger->variable_mappings ? json_decode($trigger->variable_mappings, true) : [];
    require_once plugin_dir_path(__FILE__) . 'api-handler.php';
    $api_handler       = new SwiftChatsWC_API_Handler();
    $template_metadata = $trigger->template_metadata ? json_decode($trigger->template_metadata, true) : null;
    if ($template_metadata) {
        // For abandoned cart, pass customer data array as the last param
        $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $customer_data);
    }
}
