<?php
// This file renders the Notifications admin page for SwiftChats
// (Copy the notifications page code from admin-menu.php here)
if (!defined('ABSPATH')) exit;
global $wpdb;
$table_name = $wpdb->prefix . 'swiftchats_notifications';
require_once plugin_dir_path(__FILE__) . '../api-handler.php';
$api_handler = new SwiftChatsWC_API_Handler();
$templates = $api_handler->get_cached_templates();
$has_api_error = is_wp_error($templates);
$notifications_per_page = 5;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$total_pages = ceil($total_notifications / $notifications_per_page);
$offset = ($paged - 1) * $notifications_per_page;
$notifications = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d", $notifications_per_page, $offset));
settings_errors('swiftchatswc_messages');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['notification_id'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'swiftchats_notification_nonce')) {
        add_settings_error('swiftchatswc_messages', 'nonce', 'Security check failed. Please try again.', 'error');
    } else {
        $notification_id = absint($_POST['notification_id']);
        $deleted = $wpdb->delete($table_name, array('id' => $notification_id));
        if ($deleted) {
            wp_redirect(admin_url('admin.php?page=swiftchatswc-notifications&msg=deleted'));
            exit;
        } else {
            add_settings_error('swiftchatswc_messages', 'db', 'Failed to delete notification.', 'error');
        }
    }
}
?>
<div class="wrap swiftchats-admin">
    <div class="swiftchats-hero-card">
        <div class="hero-icon">
            <span class="dashicons dashicons-megaphone"></span>
        </div>
        <div class="hero-content">
            <h1>Notifications</h1>
            <p class="hero-subtitle">
                WhatsApp notifications sent to your business (to the business phone number in settings) when orders are made or their status changes.
            </p>
        </div>
        <?php if (!$has_api_error): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=swiftchatswc-notification-edit')); ?>" class="button swiftchats-modern-btn add-notification-btn">
                <span class="dashicons dashicons-format-chat"></span> <span>Add New Notification</span>
            </a>
        <?php endif; ?>
    </div>
    <?php if ($has_api_error): ?>
        <div class="swiftchats-card swiftchats-api-notice">
            <h2><span class="dashicons dashicons-warning"></span> API Configuration Required</h2>
            <p>To start creating notifications, you need to configure the API settings first.</p>
            <p>Please go to the <a href="<?php echo esc_url(admin_url('admin.php?page=swiftchatswc-settings&tab=api')); ?>">API Settings</a> page and configure your API key.</p>
        </div>
    <?php endif; ?>
    <?php if (!$has_api_error): ?>
        <div class="swiftchats-card notifications-table-card">
            <table class="swiftchats-table notifications-table">
                <thead>
                    <tr>
                        <th>Order Status</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)) : ?>
                        <tr>
                            <td colspan="4" class="empty-table">
                                <span class="dashicons dashicons-info"></span>
                                No notifications found. <a href="<?php echo esc_url(admin_url('admin.php?page=swiftchatswc-notification-edit')); ?>">Add one?</a>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($notifications as $notification) : ?>
                            <tr>
                                <td>
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    $status_key = $notification->order_status;
                                    if ($status_key === 'abandoned_cart') {
                                        echo 'Abandoned Cart';
                                    } elseif (isset($order_statuses[$status_key])) {
                                        echo esc_html($order_statuses[$status_key]);
                                    } elseif (isset($order_statuses['wc-' . $status_key])) {
                                        echo esc_html($order_statuses['wc-' . $status_key]);
                                    } else {
                                        echo esc_html(ucwords(str_replace(['-', '_'], ' ', preg_replace('/^wc-/', '', $status_key))));
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($notification->message_template_name ?? ''); ?></td>
                                <td>
                                    <?php if ($notification->is_active) : ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else : ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=swiftchatswc-notification-edit&id=' . $notification->id)); ?>"
                                       class="table-action edit" title="Edit"><span class="dashicons dashicons-edit"></span></a>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field('swiftchats_notification_nonce'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?php echo esc_attr($notification->id); ?>">
                                        <button type="submit" class="table-action delete" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this notification?')">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
            <div class="swiftchats-pagination">
                <?php if ($paged > 1): ?>
                    <a class="page-numbers prev" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="page-numbers<?php if ($i == $paged) echo ' current'; ?>" href="<?php echo esc_url(add_query_arg('paged', $i)); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($paged < $total_pages): ?>
                    <a class="page-numbers next" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<style>
.notifications-table-card {
    padding: 0;
    margin-top: 20px;
    overflow-x: auto;
}
.notifications-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    min-width: 600px;
}
.notifications-table thead tr {
    background: #fff;
}
.notifications-table th, .notifications-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
    font-size: 15px;
}
.notifications-table th {
    font-weight: 700;
    color: #1d2327;
    letter-spacing: 0.01em;
}
.notifications-table tr:last-child td {
    border-bottom: none;
}
.notifications-table tbody tr:hover {
    background: #f8fff6;
    transition: background 0.2s;
}
.badge {
    display: inline-block;
    padding: 3px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.01em;
    vertical-align: middle;
}
.badge-active {
    background: #eafbe7;
    color: #2e7d32;
    border: 1px solid #b6e2c1;
}
.badge-inactive {
    background: #fbeaea;
    color: #c62828;
    border: 1px solid #f5bdbd;
}
.table-action {
    display: inline-block;
    margin-right: 8px;
    color: #2271b1;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: 18px;
    vertical-align: middle;
    transition: color 0.2s;
}
.table-action.edit:hover { color: #25d366; }
.table-action.delete:hover { color: #c62828; }
.table-action .dashicons { vertical-align: middle; }
.empty-table {
    text-align: center;
    color: #888;
    font-size: 1.08em;
    padding: 40px 0;
}
.empty-table .dashicons {
    font-size: 22px;
    color: #bdbdbd;
    margin-right: 6px;
    vertical-align: middle;
}
@media (max-width: 700px) {
    .notifications-table { min-width: 500px; }
}
.swiftchats-hero-card {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    padding: 32px 32px 28px 32px;
    margin-bottom: 28px;
    position: relative;
    gap: 28px;
    flex-wrap: wrap;
}
.swiftchats-hero-card .hero-icon {
    font-size: 48px;
    color: #25d366;
    background: #eafbe7;
    border-radius: 50%;
    width: 72px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(37,211,102,0.07);
}
.swiftchats-hero-card .hero-content {
    flex: 1 1 300px;
    min-width: 220px;
}
.swiftchats-hero-card h1 {
    margin: 0 0 8px 0;
    font-size: 2.1rem;
    font-weight: 700;
    color: #1d2327;
    letter-spacing: -1px;
}
.swiftchats-hero-card .hero-subtitle {
    margin: 0;
    color: #4a5568;
    font-size: 1.08rem;
    opacity: 0.92;
    font-weight: 400;
}
.add-notification-btn {
    margin-left: auto;
    font-size: 1.13rem;
    padding: 10px 15px !important;
    border: none !important;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-weight: 700;
    border-radius: 5px !important;
    background: #25d366 !important;
    color: #fff !important;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    letter-spacing: 0.01em;
    text-shadow: none;
    cursor: pointer;
    outline: none;
    position: relative;
    overflow: hidden;
}
.add-notification-btn .dashicons {
    font-size: 22px;
    margin-right: 6px;
    font-weight: bold;
    color: #fff;
    transition: color 0.18s;
}
@media (max-width: 700px) {
    .swiftchats-hero-card {
        flex-direction: column;
        align-items: flex-start;
        padding: 22px 12px 18px 12px;
        gap: 16px;
    }
    .add-notification-btn {
        width: 100%;
        justify-content: center;
        margin-left: 0;
        margin-top: 16px;
        padding: 14px 0;
    }
}
.swiftchats-pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 6px;
    margin: 18px 0 0 0;
}
.swiftchats-pagination .page-numbers {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 7px;
    background: #f7fafd;
    color: #2271b1;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.18s, color 0.18s;
    border: 1px solid #e0e0e0;
    font-size: 1em;
}
.swiftchats-pagination .page-numbers.current, .swiftchats-pagination .page-numbers:hover {
    background: #25d366;
    color: #fff;
    border-color: #25d366;
}
.swiftchats-pagination .page-numbers.prev, .swiftchats-pagination .page-numbers.next {
    font-size: 1.08em;
    font-weight: 700;
}
</style> 