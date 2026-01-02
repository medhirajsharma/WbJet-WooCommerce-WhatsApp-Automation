<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper function for logging
function swiftchatswc_debug_log($message) {
    $log_file = dirname(__FILE__) . '/swiftchats-debug.log';
    $timestamp = date('[Y-m-d H:i:s] ');
    $log_message = $timestamp . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
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
    swiftchatswc_debug_log('[AC Start] `swiftchatswc_maybe_schedule_abandoned_cart_check` triggered.');

    if (!WC()->session || !WC()->session->has_session()) {
        swiftchatswc_debug_log('[AC Start] Exiting: No WC_Session found.');
        return;
    }

    $options = get_option('swiftchatswc_options', []);
    if (empty($options['enable_abandoned_cart'])) {
        swiftchatswc_debug_log('[AC Start] Exiting: Abandoned Cart feature is disabled in settings.');
        return;
    }

    $session_key = WC()->session->get_customer_id();
    if (!$session_key) {
        swiftchatswc_debug_log('[AC Start] Exiting: No session_key (customer ID) found.');
        return;
    }
    swiftchatswc_debug_log("[AC Start] Session Key: {$session_key}");

    // Cancel any previously scheduled actions for this session to restart the sequence.
    $unscheduled_count = as_unschedule_all_actions('swiftchatswc_check_abandoned_cart', array('session_key' => $session_key), 'swiftchats');
    if ($unscheduled_count > 0) {
        swiftchatswc_debug_log("[AC Start] Unscheduled {$unscheduled_count} existing actions for this session.");
    }

    if (WC()->cart->is_empty()) {
        swiftchatswc_debug_log('[AC Start] Cart is empty. No new sequence will be scheduled.');
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
        swiftchatswc_debug_log('[AC Start] Exiting: No active "abandoned_cart" trigger found.');
        return;
    }
    swiftchatswc_debug_log("[AC Start] Found active trigger with ID: {$trigger->id}");

    $first_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d ORDER BY sequence_order ASC LIMIT 1",
        $trigger->id
    ));

    if (!$first_sequence_item) {
        swiftchatswc_debug_log("[AC Start] Exiting: No sequence items found for trigger ID: {$trigger->id}.");
        return;
    }
    swiftchatswc_debug_log("[AC Start] Found first sequence item (ID: {$first_sequence_item->id}) with order: {$first_sequence_item->sequence_order}.");

    $time_interval = (int)$first_sequence_item->time_interval;
    $time_unit = $first_sequence_item->time_unit;
    $delay_in_seconds = 0;

    switch ($time_unit) {
        case 'minutes': $delay_in_seconds = $time_interval * MINUTE_IN_SECONDS; break;
        case 'hours': $delay_in_seconds = $time_interval * HOUR_IN_SECONDS; break;
        case 'days': $delay_in_seconds = $time_interval * DAY_IN_SECONDS; break;
    }
    
    if ($delay_in_seconds > 0) {
        $scheduled_time = time() + $delay_in_seconds;
        $action_id = as_schedule_single_action(
            $scheduled_time,
            'swiftchatswc_check_abandoned_cart',
            array(
                'session_key' => $session_key,
                'sequence_item_id' => $first_sequence_item->id,
            ),
            'swiftchats'
        );
        swiftchatswc_debug_log("[AC Start] Scheduled action ID {$action_id} for " . date('Y-m-d H:i:s', $scheduled_time) . " with sequence item ID: {$first_sequence_item->id}.");
    } else {
        swiftchatswc_debug_log('[AC Start] Exiting: Delay was 0 seconds. No action scheduled.');
    }
}

function swiftchatswc_process_abandoned_cart($session_key, $sequence_item_id)
{
    swiftchatswc_debug_log("[AC Process] `swiftchatswc_process_abandoned_cart` running for Session: {$session_key}, Sequence Item ID: {$sequence_item_id}.");

    global $wpdb;
    $sequence_table = $wpdb->prefix . 'swiftchats_abandoned_cart_sequence';
    $current_sequence_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sequence_table WHERE id = %d", $sequence_item_id));

    if (!$current_sequence_item) {
        swiftchatswc_debug_log("[AC Process] Exiting: Could not find sequence item with ID: {$sequence_item_id}.");
        return;
    }
    swiftchatswc_debug_log("[AC Process] Found current sequence item: " . print_r($current_sequence_item, true));


    $session_handler = WC()->session;
    if (!$session_handler || !method_exists($session_handler, 'get_session')) {
        swiftchatswc_debug_log("[AC Process] Exiting: WC_Session handler not available.");
        return;
    }
    
    $cart = $session_handler->get_session($session_key);
    if (!$cart || empty($cart['cart'])) {
        swiftchatswc_debug_log("[AC Process] Exiting: Cart is empty or session has expired for key: {$session_key}. Sequence terminated.");
        return;
    }

    $customer_data = isset($cart['customer']) ? (is_string($cart['customer']) ? maybe_unserialize($cart['customer']) : $cart['customer']) : null;
    if (!$customer_data || !is_array($customer_data)) {
        swiftchatswc_debug_log("[AC Process] Exiting: Customer data not found or is invalid.");
        return;
    }

    $phone = $customer_data['billing_phone'] ?? $customer_data['phone'] ?? '';
    if (empty($phone)) {
        swiftchatswc_debug_log("[AC Process] Exiting: No phone number found in customer data.");
        return;
    }
    swiftchatswc_debug_log("[AC Process] Found phone number: {$phone}. Preparing to send message.");

    // Send the message
    require_once plugin_dir_path(__FILE__) . 'api-handler.php';
    $api_handler = new SwiftChatsWC_API_Handler();
    $template_info = $api_handler->get_template_by_uuid($current_sequence_item->message_template_id);
    
    if ($template_info && !is_wp_error($template_info)) {
        swiftchatswc_debug_log("[AC Process] Found template '{$template_info['name']}'. Sending notification.");
        $template_metadata = json_decode($template_info['metadata'], true);
        $variable_mappings = $current_sequence_item->variable_mappings ? json_decode($current_sequence_item->variable_mappings, true) : [];
        $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $customer_data);
    } else {
        swiftchatswc_debug_log("[AC Process] Could not send message. Template info not found or is a WP_Error for UUID: {$current_sequence_item->message_template_id}.");
    }
    
    // Schedule the next item in the sequence
    $next_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d AND sequence_order > %d ORDER BY sequence_order ASC LIMIT 1",
        $current_sequence_item->trigger_id,
        $current_sequence_item->sequence_order
    ));

    if (!$next_sequence_item) {
        swiftchatswc_debug_log("[AC Process] End of sequence. No more items to schedule.");
        return;
    }
    
    swiftchatswc_debug_log("[AC Process] Found next sequence item (ID: {$next_sequence_item->id}) with order: {$next_sequence_item->sequence_order}.");

    $time_interval = (int)$next_sequence_item->time_interval;
    $time_unit = $next_sequence_item->time_unit;
    $delay_in_seconds = 0;

    switch ($time_unit) {
        case 'minutes': $delay_in_seconds = $time_interval * MINUTE_IN_SECONDS; break;
        case 'hours': $delay_in_seconds = $time_interval * HOUR_IN_SECONDS; break;
        case 'days': $delay_in_seconds = $time_interval * DAY_IN_SECONDS; break;
    }

    if ($delay_in_seconds > 0) {
        $scheduled_time = time() + $delay_in_seconds;
        $action_id = as_schedule_single_action(
            $scheduled_time,
            'swiftchatswc_check_abandoned_cart',
            array(
                'session_key' => $session_key,
                'sequence_item_id' => $next_sequence_item->id,
            ),
            'swiftchats'
        );
        swiftchatswc_debug_log("[AC Process] Scheduled next action ID {$action_id} for " . date('Y-m-d H:i:s', $scheduled_time) . " with sequence item ID: {$next_sequence_item->id}.");
    } else {
        swiftchatswc_debug_log("[AC Process] Next item had a 0 second delay. No action scheduled.");
    }
}

function swiftchatswc_cancel_abandoned_cart_on_purchase($order_id) {
    swiftchatswc_debug_log("[AC Cancel] `swiftchatswc_cancel_abandoned_cart_on_purchase` triggered for order ID: {$order_id}.");
    if (!WC()->session || !WC()->session->has_session()) {
        swiftchatswc_debug_log("[AC Cancel] Exiting: No WC_Session found.");
        return;
    }
    $session_key = WC()->session->get_customer_id();
    if ($session_key) {
        $unscheduled_count = as_unschedule_all_actions('swiftchatswc_check_abandoned_cart', array('session_key' => $session_key), 'swiftchats');
        if ($unscheduled_count > 0) {
            swiftchatswc_debug_log("[AC Cancel] Successfully unscheduled {$unscheduled_count} pending actions for session_key: {$session_key}.");
        } else {
            swiftchatswc_debug_log("[AC Cancel] No pending actions found to unschedule for session_key: {$session_key}.");
        }
    } else {
        swiftchatswc_debug_log("[AC Cancel] Exiting: No session_key found to cancel.");
    }
}
