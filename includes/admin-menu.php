<?php
if (!defined('ABSPATH')) exit;

// Add plugin name constant for easy rebranding
if (!defined('WBWWA_PLUGIN_NAME')) {
    define('WBWWA_PLUGIN_NAME', 'WBWWA');
}

// At the top of the file, after the ABSPATH check:
require_once plugin_dir_path(__FILE__) . 'country-codes.php';

// Add settings menu
add_action('admin_menu', 'WBWWAwc_add_menu');

function WBWWAwc_add_menu() {
    // Add main menu item
    add_menu_page(
        WBWWA_PLUGIN_NAME, 
        WBWWA_PLUGIN_NAME, 
        'manage_options', 
        'WBWWAwc', 
        'WBWWAwc_dashboard_page', 
        'dashicons-whatsapp', 
        25
    );

    // Add "Home" submenu (replacing default duplicate submenu)
    add_submenu_page(
        'WBWWAwc',
        WBWWA_PLUGIN_NAME . ' Home',
        'Home',
        'manage_options',
        'WBWWAwc',
        'WBWWAwc_dashboard_page'
    );

    // Add Triggers submenu
    add_submenu_page(
        'WBWWAwc',
        WBWWA_PLUGIN_NAME . ' Triggers',
        'Triggers',
        'manage_options',
        'WBWWAwc-triggers',
        'WBWWAwc_triggers_page'
    );

    // Add Notifications submenu
    add_submenu_page(
        'WBWWAwc',
        WBWWA_PLUGIN_NAME . ' Notifications',
        'Notifications',
        'manage_options',
        'WBWWAwc-notifications',
        'WBWWAwc_notifications_page'
    );

    // Add Settings submenu
    add_submenu_page(
        'WBWWAwc',
        WBWWA_PLUGIN_NAME . ' Settings',
        'Settings',
        'manage_options',
        'WBWWAwc-settings',
        'WBWWAwc_settings_page'
    );

    // Hidden submenu for add/edit trigger
    add_submenu_page(
        null,
        'Add/Edit Trigger',
        'Add/Edit Trigger',
        'manage_options',
        'WBWWAwc-trigger-edit',
        'WBWWAwc_trigger_edit_page'
    );

    // Hidden submenu for add/edit notification
    add_submenu_page(
        null,
        'Add/Edit Notification',
        'Add/Edit Notification',
        'manage_options',
        'WBWWAwc-notification-edit',
        'WBWWAwc_notification_edit_page'
    );
}

// Page callback functions just require the new files
function WBWWAwc_dashboard_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-dashboard-page.php';
}
function WBWWAwc_triggers_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-triggers-page.php';
}
function WBWWAwc_notifications_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-notifications-page.php';
}
function WBWWAwc_settings_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-settings-page.php';
}
function WBWWAwc_trigger_edit_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-trigger-edit-page.php';
}
function WBWWAwc_notification_edit_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-notification-edit-page.php';
}
