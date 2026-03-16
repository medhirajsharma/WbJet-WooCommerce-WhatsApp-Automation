<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper function for logging
function WBWWAwc_debug_log($message) {
    $log_file = dirname(__FILE__) . '/WBWWA-debug.log';
    $timestamp = date('[Y-m-d H:i:s] ');
    $log_message = $timestamp . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Hook into WooCommerce order status changes
add_action('woocommerce_order_status_changed', 'WBWWAwc_handle_order_status_change', 10, 4);

// Abandoned Cart Logic
add_action('woocommerce_cart_updated', 'WBWWAwc_maybe_schedule_abandoned_cart_check', 999);
add_action('WBWWAwc_check_abandoned_cart', 'WBWWAwc_process_abandoned_cart', 10, 2);
add_action('woocommerce_checkout_order_processed', 'WBWWAwc_cancel_abandoned_cart_on_purchase', 10, 1);


function WBWWAwc_handle_order_status_change($order_id, $old_status, $new_status, $order)
{
    global $wpdb;

    if (empty($order_id) || empty($new_status)) {
        return;
    }

    $table_name = $wpdb->prefix . 'WBWWA_triggers';
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
                $api_handler = new WBWWAWC_API_Handler();
                $template_metadata = $trigger->template_metadata ? json_decode($trigger->template_metadata, true) : null;
                if ($template_metadata) {
                    $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $order_obj);
                }
            }
        }
    }
}

function WBWWAwc_maybe_schedule_abandoned_cart_check()
{
    static $already_triggered = false;
    if ($already_triggered) {
        return;
    }
    $already_triggered = true;

    WBWWAwc_debug_log('[AC Start] `WBWWAwc_maybe_schedule_abandoned_cart_check` triggered.');

    if (!WC()->session || !WC()->session->has_session()) {
        WBWWAwc_debug_log('[AC Start] Exiting: No WC_Session found.');
        return;
    }

    $options = get_option('WBWWAwc_options', []);
    if (empty($options['enable_abandoned_cart'])) {
        WBWWAwc_debug_log('[AC Start] Exiting: Abandoned Cart feature is disabled in settings.');
        return;
    }

    $session_key = WC()->session->get_customer_id();
    if (!$session_key) {
        WBWWAwc_debug_log('[AC Start] Exiting: No session_key (customer ID) found.');
        return;
    }
    WBWWAwc_debug_log("[AC Start] Session Key: {$session_key}");

    // Cancel any previously scheduled actions for this session to restart the sequence.
    WBWWAwc_unschedule_all_abandoned_cart_actions($session_key);

    if (WC()->cart->is_empty()) {
        WBWWAwc_debug_log('[AC Start] Cart is empty. No new sequence will be scheduled.');
        return;
    }

    global $wpdb;
    $trigger_table = $wpdb->prefix . 'WBWWA_triggers';
    $sequence_table = $wpdb->prefix . 'WBWWA_abandoned_cart_sequence';

    $trigger = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $trigger_table WHERE order_status = %s AND is_active = 1",
        'abandoned_cart'
    ));

    if (!$trigger) {
        WBWWAwc_debug_log('[AC Start] Exiting: No active "abandoned_cart" trigger found.');
        return;
    }
    WBWWAwc_debug_log("[AC Start] Found active trigger with ID: {$trigger->id}");

    $first_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d ORDER BY sequence_order ASC LIMIT 1",
        $trigger->id
    ));

    if (!$first_sequence_item) {
        WBWWAwc_debug_log("[AC Start] Exiting: No sequence items found for trigger ID: {$trigger->id}.");
        return;
    }
    WBWWAwc_debug_log("[AC Start] Found first sequence item (ID: {$first_sequence_item->id}) with order: {$first_sequence_item->sequence_order}.");

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
            'WBWWAwc_check_abandoned_cart',
            array(
                'session_key' => $session_key,
                'sequence_item_id' => (int)$first_sequence_item->id,
            ),
            'WBWWA'
        );
        WBWWAwc_debug_log("[AC Start] Scheduled action ID {$action_id} for " . date('Y-m-d H:i:s', $scheduled_time) . " with sequence item ID: {$first_sequence_item->id}.");
    } else {
        WBWWAwc_debug_log('[AC Start] Exiting: Delay was 0 seconds. No action scheduled.');
    }
}

function WBWWAwc_process_abandoned_cart($session_key, $sequence_item_id)
{
    WBWWAwc_debug_log("[AC Process] `WBWWAwc_process_abandoned_cart` running for Session: {$session_key}, Sequence Item ID: {$sequence_item_id}.");

    global $wpdb;
    $sequence_table = $wpdb->prefix . 'WBWWA_abandoned_cart_sequence';
    $current_sequence_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sequence_table WHERE id = %d", $sequence_item_id));

    if (!$current_sequence_item) {
        WBWWAwc_debug_log("[AC Process] Exiting: Could not find sequence item with ID: {$sequence_item_id}.");
        return;
    }
    WBWWAwc_debug_log("[AC Process] Found current sequence item: " . print_r($current_sequence_item, true));


    // Ensure WC_Session is available in background/cron context
    if (null === WC()->session || ! (WC()->session instanceof WC_Session)) {
        require_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        WBWWAwc_debug_log("[AC Process] Initialized WC_Session_Handler manually.");
    }

    $session_handler = WC()->session;
    if (!$session_handler || !method_exists($session_handler, 'get_session')) {
        WBWWAwc_debug_log("[AC Process] Exiting: WC_Session handler STILL NOT available.");
        return;
    }
    
    $cart = $session_handler->get_session($session_key);
    if (!$cart || empty($cart['cart'])) {
        $session_dump = print_r($cart, true);
        WBWWAwc_debug_log("[AC Process] Exiting: Cart is empty or session has expired for key: {$session_key}. Session Dump: {$session_dump}");
        return;
    }

    $customer_data = isset($cart['customer']) ? (is_string($cart['customer']) ? maybe_unserialize($cart['customer']) : $cart['customer']) : null;
    if (!$customer_data || !is_array($customer_data)) {
        WBWWAwc_debug_log("[AC Process] Exiting: Customer data not found in session. Session Data: " . print_r($cart, true));
        return;
    }

    WBWWAwc_debug_log("[AC Process] Customer data found: " . print_r($customer_data, true));

    $phone = $customer_data['billing_phone'] ?? $customer_data['phone'] ?? '';
    if (empty($phone)) {
        WBWWAwc_debug_log("[AC Process] Exiting: No phone number found in customer data.");
        return;
    }
    WBWWAwc_debug_log("[AC Process] Found phone number: {$phone}. Preparing to send message.");

    // Send the message
    require_once plugin_dir_path(__FILE__) . 'api-handler.php';
    $api_handler = new WBWWAWC_API_Handler();
    $template_info = $api_handler->get_template_by_uuid($current_sequence_item->message_template_id);
    
    if ($template_info && !is_wp_error($template_info)) {
        WBWWAwc_debug_log("[AC Process] Found template '{$template_info['name']}'. Sending notification.");
        $template_metadata = json_decode($template_info['metadata'], true);
        $variable_mappings = $current_sequence_item->variable_mappings ? json_decode($current_sequence_item->variable_mappings, true) : [];
        $api_handler->send_template_with_metadata($phone, $template_metadata, $variable_mappings, $customer_data);
    } else {
        WBWWAwc_debug_log("[AC Process] Could not send message. Template info not found or is a WP_Error for UUID: {$current_sequence_item->message_template_id}.");
    }
    
    // Schedule the next item in the sequence
    $next_sequence_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sequence_table WHERE trigger_id = %d AND sequence_order > %d ORDER BY sequence_order ASC LIMIT 1",
        $current_sequence_item->trigger_id,
        $current_sequence_item->sequence_order
    ));

    if (!$next_sequence_item) {
        WBWWAwc_debug_log("[AC Process] End of sequence. No more items to schedule.");
        return;
    }
    
    WBWWAwc_debug_log("[AC Process] Found next sequence item (ID: {$next_sequence_item->id}) with order: {$next_sequence_item->sequence_order}.");

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
            'WBWWAwc_check_abandoned_cart',
            array(
                'session_key' => $session_key,
                'sequence_item_id' => (int)$next_sequence_item->id,
            ),
            'WBWWA'
        );
        WBWWAwc_debug_log("[AC Process] Scheduled next action ID {$action_id} for " . date('Y-m-d H:i:s', $scheduled_time) . " with sequence item ID: {$next_sequence_item->id}.");
    } else {
        WBWWAwc_debug_log("[AC Process] Next item had a 0 second delay. No action scheduled.");
    }
}

function WBWWAwc_cancel_abandoned_cart_on_purchase($order_id) {
    WBWWAwc_debug_log("[AC Cancel] `WBWWAwc_cancel_abandoned_cart_on_purchase` triggered for order ID: {$order_id}.");
    if (!WC()->session || !WC()->session->has_session()) {
        WBWWAwc_debug_log("[AC Cancel] Exiting: No WC_Session found.");
        return;
    }
    $session_key = WC()->session->get_customer_id();
    if ($session_key) {
        WBWWAwc_unschedule_all_abandoned_cart_actions($session_key);
        WBWWAwc_debug_log("[AC Cancel] Successfully unscheduled pending actions for session_key: {$session_key}.");
    } else {
        WBWWAwc_debug_log("[AC Cancel] Exiting: No session_key found to cancel.");
    }
}

/**
 * Helper function to unschedule ALL abandoned cart actions for a session.
 * This is necessary because Action Scheduler requires exact argument matches.
 */
function WBWWAwc_unschedule_all_abandoned_cart_actions($session_key) {
    global $wpdb;
    $sequence_table = $wpdb->prefix . 'WBWWA_abandoned_cart_sequence';
    
    // Get all possible sequence item IDs to ensure we match the exact arguments scheduled
    $ids = $wpdb->get_col("SELECT id FROM $sequence_table");
    
    if (!empty($ids)) {
        foreach ($ids as $id) {
            as_unschedule_all_actions('WBWWAwc_check_abandoned_cart', array(
                'session_key' => $session_key,
                'sequence_item_id' => (int)$id,
            ), 'WBWWA');
        }
    }

    // Also clear any action that might have been scheduled with just the session key
    as_unschedule_all_actions('WBWWAwc_check_abandoned_cart', array(
        'session_key' => $session_key,
    ), 'WBWWA');
}

