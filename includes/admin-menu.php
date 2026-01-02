<?php
if (!defined('ABSPATH')) exit;

// Add plugin name constant for easy rebranding
if (!defined('SWIFTCHATS_PLUGIN_NAME')) {
    define('SWIFTCHATS_PLUGIN_NAME', 'SwiftChats');
}

// At the top of the file, after the ABSPATH check:
require_once plugin_dir_path(__FILE__) . 'country-codes.php';

// Add settings menu
add_action('admin_menu', 'swiftchatswc_add_menu');

function swiftchatswc_add_menu() {
    // Add main menu item
    add_menu_page(
        SWIFTCHATS_PLUGIN_NAME, 
        SWIFTCHATS_PLUGIN_NAME, 
        'manage_options', 
        'swiftchatswc', 
        'swiftchatswc_dashboard_page', 
        'dashicons-whatsapp', 
        25
    );

    // Add "Home" submenu (replacing default duplicate submenu)
    add_submenu_page(
        'swiftchatswc',
        SWIFTCHATS_PLUGIN_NAME . ' Home',
        'Home',
        'manage_options',
        'swiftchatswc',
        'swiftchatswc_dashboard_page'
    );

    // Add Triggers submenu
    add_submenu_page(
        'swiftchatswc',
        SWIFTCHATS_PLUGIN_NAME . ' Triggers',
        'Triggers',
        'manage_options',
        'swiftchatswc-triggers',
        'swiftchatswc_triggers_page'
    );

    // Add Notifications submenu
    add_submenu_page(
        'swiftchatswc',
        SWIFTCHATS_PLUGIN_NAME . ' Notifications',
        'Notifications',
        'manage_options',
        'swiftchatswc-notifications',
        'swiftchatswc_notifications_page'
    );

    // Add Settings submenu
    add_submenu_page(
        'swiftchatswc',
        SWIFTCHATS_PLUGIN_NAME . ' Settings',
        'Settings',
        'manage_options',
        'swiftchatswc-settings',
        'swiftchatswc_settings_page'
    );

    // Hidden submenu for add/edit trigger
    add_submenu_page(
        null,
        'Add/Edit Trigger',
        'Add/Edit Trigger',
        'manage_options',
        'swiftchatswc-trigger-edit',
        'swiftchatswc_trigger_edit_page'
    );

    // Hidden submenu for add/edit notification
    add_submenu_page(
        null,
        'Add/Edit Notification',
        'Add/Edit Notification',
        'manage_options',
        'swiftchatswc-notification-edit',
        'swiftchatswc_notification_edit_page'
    );
}

// Page callback functions just require the new files
function swiftchatswc_dashboard_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-dashboard-page.php';
}
function swiftchatswc_triggers_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-triggers-page.php';
}
function swiftchatswc_notifications_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-notifications-page.php';
}
function swiftchatswc_settings_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-settings-page.php';
}
function swiftchatswc_trigger_edit_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-trigger-edit-page.php';
}
function swiftchatswc_notification_edit_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-notification-edit-page.php';
}