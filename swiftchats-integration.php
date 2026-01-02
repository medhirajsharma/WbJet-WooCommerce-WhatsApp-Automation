<?php
/*
 * Plugin Name: wbjet-woocommerce-whatsapp-automation
 * Description: Automated Order Updates – Notify customers of order status changes (new, processing, completed, etc.).
Real-Time WhatsApp Alerts – Deliver instant notifications directly to your customer’s WhatsApp.
Chat Widget Support – Enable direct communication from your Wordpress site to your Swiftchats inbox.
Easy Setup – Seamlessly install and connect with your existing Swiftchats and WooCommerce setup.
Reduce Cart Abandonment – Engage customers at the right time with proactive messages.
Fully Compatible – Works with the latest versions of WooCommerce and WordPress.
 * Version: 1.0.0
 * Author: Dhiraj Sharma
 * Author URI: https://app.wbjet.com/
 * License: GPL v2 or later
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SWIFTCHATS_WC_VERSION', '1.0.0');
define('SWIFTCHATS_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SWIFTCHATS_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SWIFTCHATSWC_API_BASE_URL', 'https://app.wbjet.com');
define('SWIFTCHATS_PLUGIN_NAME', 'wbjet-woocommerce-whatsapp-automation');

// Include required files
require_once SWIFTCHATS_WC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once SWIFTCHATS_WC_PLUGIN_DIR . 'includes/chat-widget.php';
require_once SWIFTCHATS_WC_PLUGIN_DIR . 'includes/country-codes.php';
require_once SWIFTCHATS_WC_PLUGIN_DIR . 'includes/trigger-handler.php';
// Activation hook
register_activation_hook(__FILE__, 'swiftchatswc_activate');

function swiftchatswc_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Triggers table
    $table_name = $wpdb->prefix . 'swiftchats_triggers';
    $sql        = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_status varchar(50) NOT NULL,
        message_template text NOT NULL,
        message_template_name varchar(255) NOT NULL,
        template_metadata text DEFAULT NULL,
        variable_mappings text DEFAULT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Notifications table
    $notifications_table = $wpdb->prefix . 'swiftchats_notifications';
    $sql_notifications   = "CREATE TABLE IF NOT EXISTS $notifications_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_status varchar(50) NOT NULL,
        message_template text NOT NULL,
        message_template_name varchar(255) NOT NULL,
        template_metadata text DEFAULT NULL,
        variable_mappings text DEFAULT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_notifications);
}

function swiftchatswc_update_db_check()
{
    if (get_option('swiftchatswc_db_version') != SWIFTCHATS_WC_VERSION) {
        swiftchatswc_create_tables();
        update_option('swiftchatswc_db_version', SWIFTCHATS_WC_VERSION);
    }
}
add_action('plugins_loaded', 'swiftchatswc_update_db_check');

function swiftchatswc_activate()
{
    // Set default options
    $default_options = [
        'enable_widget'          => 1,
        'widget_position'        => 'right',
        'country_code'           => '+1',
        'business_phone'         => '',
        'enable_notifications'   => 0,
        'enable_optin'           => 0,
        'optin_text'             => 'I agree to receive WhatsApp messages about my order',
        'enable_abandoned_cart'  => 0,
        'abandoned_cart_timeout' => 60,
    ];

    swiftchatswc_create_tables();
    add_option('swiftchatswc_options', $default_options);
    add_option('swiftchatswc_db_version', SWIFTCHATS_WC_VERSION);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'swiftchatswc_deactivate');

function swiftchatswc_deactivate()
{
    // Cleanup if needed
}

// Initialize plugin
function swiftchatswc_init()
{
    load_plugin_textdomain('swiftchats-wc', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'swiftchatswc_init');
