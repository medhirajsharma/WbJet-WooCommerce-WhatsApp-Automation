<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle incoming webhooks from wbjet.com
 */
add_action('rest_api_init', function () {
    register_rest_route('wbwwa/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'WBWWAwc_handle_incoming_webhook',
        'permission_callback' => '__return_true', // You might want to add a secret token check here later
    ));
});

function WBWWAwc_handle_incoming_webhook($request) {
    $params = $request->get_json_params();
    
    // Log incoming webhook for debugging
    WBWWAwc_debug_log('[Webhook Incoming] Received: ' . wp_json_encode($params));

    if (empty($params) || !isset($params['event'])) {
        return new WP_REST_Response(['message' => 'Invalid payload'], 400);
    }

    $event = $params['event'];
    $data = $params['data'] ?? [];

    switch ($event) {
        case 'message.received':
            return WBWWAwc_process_message_received($data);
        
        case 'message.status.update':
            // Optional: track if message was delivered/read
            break;
            
        default:
            WBWWAwc_debug_log("[Webhook] Unhandled event: {$event}");
            break;
    }

    return new WP_REST_Response(['status' => 'success'], 200);
}

/**
 * Process incoming WhatsApp messages (replies/button clicks)
 */
function WBWWAwc_process_message_received($data) {
    $phone = $data['from'] ?? ''; // Sender's phone number
    $body = trim($data['body'] ?? ''); // Message content or button text
    
    if (empty($phone) || empty($body)) {
        return new WP_REST_Response(['message' => 'Missing data'], 200);
    }

    WBWWAwc_debug_log("[Webhook] Message from {$phone}: {$body}");

    // --- COD VERIFICATION LOGIC (Initial Draft) ---
    // Search for a recent "on-hold" order for this phone number
    if (strtolower($body) === 'confirm' || strtolower($body) === 'approve') {
        WBWWAwc_try_confirm_order($phone);
    } elseif (strtolower($body) === 'cancel' || strtolower($body) === 'reject') {
        WBWWAwc_try_cancel_order($phone);
    }

    return new WP_REST_Response(['status' => 'processed'], 200);
}

/**
 * Helper to confirm the latest pending order for a phone number
 */
function WBWWAwc_try_confirm_order($phone) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Find latest order with this phone AND it must be awaiting reply
    $orders = wc_get_orders([
        'billing_phone' => $clean_phone,
        'status' => ['on-hold', 'pending'],
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_key' => '_wbwwa_awaiting_reply',
        'meta_value' => '1',
    ]);

    if (!empty($orders)) {
        $order = $orders[0];
        $order->update_status('processing', 'Confirmed via WhatsApp interactive button.');
        // Remove the flag so accidental replies don't trigger it again
        $order->delete_meta_data('_wbwwa_awaiting_reply');
        $order->save();
        WBWWAwc_debug_log("[Webhook] Order #{$order->get_id()} confirmed for {$phone}");
    } else {
         WBWWAwc_debug_log("[Webhook] No order awaiting confirmation found for {$phone}");
    }
}

/**
 * Helper to cancel the latest pending order for a phone number
 */
function WBWWAwc_try_cancel_order($phone) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    $orders = wc_get_orders([
        'billing_phone' => $clean_phone,
        'status' => ['on-hold', 'pending'],
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_key' => '_wbwwa_awaiting_reply',
        'meta_value' => '1',
    ]);

    if (!empty($orders)) {
        $order = $orders[0];
        $order->update_status('cancelled', 'Cancelled via WhatsApp interactive button.');
        $order->delete_meta_data('_wbwwa_awaiting_reply');
        $order->save();
        WBWWAwc_debug_log("[Webhook] Order #{$order->get_id()} cancelled for {$phone}");
    }
}
