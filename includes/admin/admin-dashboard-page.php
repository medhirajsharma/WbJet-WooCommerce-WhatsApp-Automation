<?php
$options = get_option('WBWWAwc_options', array());
$api_key = $options['api_key'] ?? '';
$is_configured = !empty($api_key);
$plugin_name = defined('WBWWA_PLUGIN_NAME') ? WBWWA_PLUGIN_NAME : 'WBWWA';
?>
<div class="wrap WBWWA-admin-home">
    <div class="WBWWA-hero">
        <div class="hero-icon"><span class="dashicons dashicons-whatsapp"></span></div>
        <div class="hero-content">
            <h1><?php echo esc_html($plugin_name); ?> for WooCommerce</h1>
            <p class="hero-tagline">Seamlessly connect with your customers on WhatsApp. Automate order updates, abandoned cart reminders, and more!</p>
        </div>
    </div>

    <?php if (!$is_configured): ?>
        <div class="WBWWA-get-started">
            <h2>Get Started</h2>
            <ol class="get-started-list">
                <li><span class="dashicons dashicons-admin-network"></span> <strong>Configure your API Key</strong> in <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-settings&tab=api')); ?>">API Settings</a></li>
                <li><span class="dashicons dashicons-admin-generic"></span> <strong>Set your business phone</strong> in <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-settings&tab=general')); ?>">General Settings</a></li>
                <li><span class="dashicons dashicons-cart"></span> <strong>Enable WooCommerce notifications</strong> in <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-settings&tab=woocommerce')); ?>">WooCommerce Settings</a></li>
                <li><span class="dashicons dashicons-format-chat"></span> <strong>Customize your chat widget</strong> in <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-settings&tab=widget')); ?>">Chat Widget</a></li>
            </ol>
            <div class="get-started-tip"><span class="dashicons dashicons-info"></span> Complete the steps above to unlock all features.</div>
        </div>
    <?php endif; ?>

    <div class="WBWWA-modules">
        <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-triggers')); ?>" class="module-card">
            <span class="dashicons dashicons-controls-repeat"></span>
            <h3>Triggers</h3>
            <p>Automate WhatsApp messages for order status, abandoned carts, and more.</p>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-notifications')); ?>" class="module-card">
            <span class="dashicons dashicons-megaphone"></span>
            <h3>Notifications</h3>
            <p>Send WhatsApp notifications to your business for new orders and status changes.</p>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=WBWWAwc-settings')); ?>" class="module-card">
            <span class="dashicons dashicons-admin-generic"></span>
            <h3>Settings</h3>
            <p>Configure API, business info, WooCommerce, and chat widget settings.</p>
        </a>
    </div>
</div>

<style>
.WBWWA-admin-home {
    max-width: 1100px;
    margin: 30px auto;
}
.WBWWA-hero {
    display: flex;
    align-items: center;
    background: white;
    position: relative;
    border-radius: 16px;
    padding: 40px 30px 30px 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    overflow: hidden;
}
.WBWWA-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url('data:image/svg+xml;utf8,<svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="none"/><circle cx="60" cy="60" r="40" fill="%2325d36622"/><circle cx="200" cy="100" r="30" fill="%23075e5422"/><circle cx="400" cy="80" r="60" fill="%2325d36611"/></svg>');
    opacity: 0.12;
    z-index: 0;
    pointer-events: none;
}
.WBWWA-hero .hero-icon, .WBWWA-hero .hero-content {
    position: relative;
    z-index: 1;
}
.WBWWA-hero .hero-icon {
    font-size: 64px;
    margin-right: 30px;
    display: flex;
    align-items: center;
}
.WBWWA-hero .dashicons-whatsapp {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: green;
}
.WBWWA-hero .hero-content h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
    letter-spacing: -1px;
}
.WBWWA-hero .hero-tagline {
    font-size: 1.2rem;
    margin: 0;
    opacity: 0.95;
}
.WBWWA-get-started {
    background: #fff3cd;
    border-left: 5px solid #ffe066;
    border-radius: 8px;
    padding: 25px 30px;
    margin-bottom: 30px;
}
.WBWWA-get-started h2 {
    margin-top: 0;
    color: #856404;
}
.get-started-list {
    margin: 0 0 10px 0;
    padding-left: 20px;
}
.get-started-list li {
    margin-bottom: 10px;
    font-size: 1.08rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.get-started-list .dashicons {
    color: #ffe066;
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.get-started-tip {
    margin-top: 10px;
    color: #856404;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 6px;
}
.get-started-tip .dashicons {
    color: #856404;
    font-size: 18px;
}
.WBWWA-modules {
    display: flex;
    gap: 30px;
    margin-top: 10px;
    flex-wrap: wrap;
    justify-content: flex-start;
}
.module-card {
    flex: 1 1 260px;
    min-width: 260px;
    max-width: 340px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    padding: 32px 24px 28px 24px;
    text-align: left;
    text-decoration: none;
    color: #1d2327;
    transition: box-shadow 0.2s, transform 0.2s;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 20px;
    border: 1px solid #f0f0f0;
}
.module-card:hover {
    box-shadow: 0 4px 16px rgba(37,211,102,0.13);
    transform: translateY(-2px) scale(1.02);
    border-color: #25d36633;
}
.module-card .dashicons {
    font-size: 36px;
    margin-bottom: 18px;
    color: #25d366;
    width: 36px;
    height: 36px;
}
.module-card h3 {
    margin: 0 0 8px 0;
    font-size: 1.3rem;
    font-weight: 600;
}
.module-card p {
    margin: 0;
    color: #555;
    font-size: 1.02rem;
    opacity: 0.92;
}
@media (max-width: 900px) {
    .WBWWA-modules {
        flex-direction: column;
        gap: 18px;
    }
    .module-card {
        max-width: 100%;
    }
    .WBWWA-hero {
        flex-direction: column;
        align-items: flex-start;
        padding: 30px 18px 18px 18px;
    }
    .WBWWA-hero .hero-icon {
        margin-bottom: 18px;
        margin-right: 0;
    }
}
</style> 
