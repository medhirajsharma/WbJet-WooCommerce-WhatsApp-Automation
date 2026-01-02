<?php
if (!defined('ABSPATH')) {
    exit;
}

// Hook into WooCommerce order status changes
add_action('woocommerce_order_status_changed', 'swiftchatswc_handle_order_status_change', 10, 4);

// Abandoned Cart Logic
add_action('woocommerce_cart_updated', 'swiftchatswc_maybe_schedule_abandoned_cart_check', 999);
add_action('swiftchatswc_check_abandoned_cart', 'swiftchatswc_process_abandoned_cart', 10, 2);
add_action('woocommerce_checkout_order_processed', 'swiftchatswc_cancel_abandoned_cart_on_purchase', 10, 1);


function swiftchatswc_handle_order_status_change($order_id, $old_status, $new_status, $order)
{
    global $wpdb;

    if (empty($order_id) || empty($new_status)) {
        return;
    }

    $table_name = $wpdb->prefix . 'swiftchats_triggers';
    $trigger = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE order_status = %s AND is_active = 1",
        'wc-' . sanitize_text_field($new_status)
    ));

    if ($trigger && $trigger->order_status !== 'abandoned_cart') {
        $order_obj = wc_get_order($order_id);
        if ($order_obj) {
            $phone = $order_obj->get_billing_phone();
            if (!empty($phone)) {
                $variable_mappings = $trigger->variable_mappings ? json_decode($trigger->variable_mappings, true) : [];
                require_once plugin_dir_path(__FILE__) . 'api-handler.php';
                $api_handler = new SwiftChatsWC_API_Handler();
                $template_metadata = $trigger->template_metadata ? json_decode($trigger->template_metadata, true) : null;
                if ($template_metadata) {
                    $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $order_obj);
                }
            }
        }
    }
}

function swiftchatswc_maybe_schedule_abandoned_cart_check()
{
    if (!WC()->session || !WC()->session->has_session()) {
        return;
    }

    $options = get_option('swiftchatswc_options', []);
    if (empty($options['enable_abandoned_cart'])) {
        return;
    }

    $session_key = WC()->session->get_customer_id();
    if (!$session_key) {
        return;
    }

    // Cancel any previously scheduled actions for this session to restart the sequence.
    as_unschedule_all_actions('swiftchatswc_check_abandoned_cart', array($session_key), 'swiftchats');

    if (WC()->cart->is_empty()) {
        return;
    }

    global $wpdb;
    $trigger_table = $wpdb->prefix . 'swiftchats_triggers';
    $sequence_table = $wpdb->prefix . 'swiftchats_abandoned_cart_sequence';

    $trigger = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $trigger_table WHERE order_status = %s AND is_active = 1",
        'abandoned_cart'
    ));

    if (!$trigger) {
        return;
    }

    $first_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d ORDER BY sequence_order ASC LIMIT 1",
        $trigger->id
    ));

    if (!$first_sequence_item) {
        return;
    }

    $time_interval = (int)$first_sequence_item->time_interval;
    $time_unit = $first_sequence_item->time_unit;
    $delay_in_seconds = 0;

    switch ($time_unit) {
        case 'minutes':
            $delay_in_seconds = $time_interval * MINUTE_IN_SECONDS;
            break;
        case 'hours':
            $delay_in_seconds = $time_interval * HOUR_IN_SECONDS;
            break;
        case 'days':
            $delay_in_seconds = $time_interval * DAY_IN_SECONDS;
            break;
    }
    
    if ($delay_in_seconds > 0) {
        as_schedule_single_action(
            time() + $delay_in_seconds,
            'swiftchatswc_check_abandoned_cart',
            array(
                'session_key' => $session_key,
                'sequence_item_id' => $first_sequence_item->id,
            ),
            'swiftchats'
        );
    }
}

function swiftchatswc_process_abandoned_cart($session_key, $sequence_item_id)
{
    global $wpdb;
    $sequence_table = $wpdb->prefix . 'swiftchats_abandoned_cart_sequence';
    $current_sequence_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sequence_table WHERE id = %d", $sequence_item_id));

    if (!$current_sequence_item) {
        return;
    }

    $session_handler = WC()->session;
    if (!$session_handler || !method_exists($session_handler, 'get_session')) {
        return;
    }
    
    $cart = $session_handler->get_session($session_key);
    if (!$cart || empty($cart['cart'])) {
        return; // Cart is empty or session expired
    }

    $customer_data = isset($cart['customer']) ? (is_string($cart['customer']) ? maybe_unserialize($cart['customer']) : $cart['customer']) : null;
    if (!$customer_data || !is_array($customer_data)) {
        return;
    }

    $phone = $customer_data['billing_phone'] ?? $customer_data['phone'] ?? '';
    if (empty($phone)) {
        return;
    }

    // Send the message for the current sequence item
    require_once plugin_dir_path(__FILE__) . 'api-handler.php';
    $api_handler = new SwiftChatsWC_API_Handler();
    $template_info = $api_handler->get_template_by_uuid($current_sequence_item->message_template_id);
    
    if ($template_info && !is_wp_error($template_info)) {
        $template_metadata = json_decode($template_info['metadata'], true);
        $variable_mappings = $current_sequence_item->variable_mappings ? json_decode($current_sequence_item->variable_mappings, true) : [];

        $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $customer_data);
    }
    
    // Schedule the next item in the sequence
    $next_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d AND sequence_order > %d ORDER BY sequence_order ASC LIMIT 1",
        $current_sequence_item->trigger_id,
        $current_sequence_item->sequence_order
    ));

    if ($next_sequence_item) {
        $time_interval = (int)$next_sequence_item->time_interval;
        $time_unit = $next_sequence_item->time_unit;
        $delay_in_seconds = 0;

        switch ($time_unit) {
            case 'minutes':
                $delay_in_seconds = $time_interval * MINUTE_IN_SECONDS;
                break;
            case 'hours':
                $delay_in_seconds = $time_interval * HOUR_IN_SECONDS;
                break;
            case 'days':
                $delay_in_seconds = $time_interval * DAY_IN_SECONDS;
                break;
        }

        if ($delay_in_seconds > 0) {
            as_schedule_single_action(
                time() + $delay_in_seconds,
                'swiftchatswc_check_abandoned_cart',
                array(
                    'session_key' => $session_key,
                    'sequence_item_id' => $next_sequence_item->id,
                ),
                'swiftchats'
            );
        }
    }
}

function swiftchatswc_cancel_abandoned_cart_on_purchase($order_id) {
    if (!WC()->session || !WC()->session->has_session()) {
        return;
    }
    $session_key = WC()->session->get_customer_id();
    if ($session_key) {
        as_unschedule_all_actions('swiftchatswc_check_abandoned_cart', array('session_key' => $session_key), 'swiftchats');
    }
}
