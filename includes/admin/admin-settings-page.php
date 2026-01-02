<?php
// This file renders the Settings admin page for SwiftChats
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . '../api-handler.php';
// Handle manual save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swiftchatswc_options'])) {
    check_admin_referer('swiftchatswc_save_settings', 'swiftchatswc_nonce');
    $current_tab = isset($_POST['swiftchatswc_current_tab']) ? sanitize_text_field($_POST['swiftchatswc_current_tab']) : 'general';
    
    $new = $_POST['swiftchatswc_options'];

    // If a checkbox is not in the POST data, it means it was unchecked. We need to explicitly set its value to '0'.
    if ($current_tab === 'widget') {
        if (!isset($new['enable_widget'])) {
            $new['enable_widget'] = '0';
        }
    }
    if ($current_tab === 'woocommerce') {
        if (!isset($new['enable_notifications'])) {
            $new['enable_notifications'] = '0';
        }
        if (!isset($new['enable_abandoned_cart'])) {
            $new['enable_abandoned_cart'] = '0';
        }
    }

    $new = array_map('sanitize_text_field', $new);
    $existing = get_option('swiftchatswc_options', array());

    // API key validation if on API tab
    if ($current_tab === 'api' && isset($new['api_key'])) {
        $api_handler = new SwiftChatsWC_API_Handler();
        $verify_result = $api_handler->verify_api_key($new['api_key']);
        if (is_wp_error($verify_result)) {
            $error_message = $verify_result->get_error_message();
            add_settings_error('swiftchatswc_messages', 'swiftchatswc_api_key_invalid', __('API Key is invalid: ', 'swiftchatswc') . esc_html($error_message), 'error');
            $options = array_merge($existing, $new); // Show entered value but do not save
        } else {
            $options = array_merge($existing, $new);
            update_option('swiftchatswc_options', $options);
            add_settings_error('swiftchatswc_messages', 'swiftchatswc_message', __('Settings Saved', 'swiftchatswc'), 'updated');
        }
    } else {
        $options = array_merge($existing, $new);
        update_option('swiftchatswc_options', $options);
        add_settings_error('swiftchatswc_messages', 'swiftchatswc_message', __('Settings Saved', 'swiftchatswc'), 'updated');
    }
} else {
    $options = get_option('swiftchatswc_options', array());
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
}
if (isset($_GET['settings-updated'])) {
    $api_errors = get_settings_errors('swiftchatswc_options');
    if (empty($api_errors)) {
        add_settings_error('swiftchatswc_messages', 'swiftchatswc_message', __('Settings Saved', 'swiftchatswc'), 'updated');
    }
}
settings_errors('swiftchatswc_options');

?>
<div class="wrap swiftchats-admin">
    <div class="swiftchats-hero-card">
        <div class="hero-icon">
            <span class="dashicons dashicons-admin-generic"></span>
        </div>
        <div class="hero-content">
            <h1>Settings</h1>
            <p class="hero-subtitle">
                Configure your WhatsApp integration, business info, WooCommerce, and chat widget settings for SwiftChats.
            </p>
        </div>
    </div>
    <?php if ($messages = get_settings_errors('swiftchatswc_options')): ?>
        <div class="swiftchats-message-card">
            <?php foreach ($messages as $msg): ?>
                <div class="swiftchats-message swiftchats-message-<?php echo esc_attr($msg['type']); ?>">
                    <?php if ($msg['type'] === 'error'): ?><span class="dashicons dashicons-warning"></span><?php endif; ?>
                    <?php if ($msg['type'] === 'updated'): ?><span class="dashicons dashicons-yes"></span><?php endif; ?>
                    <?php echo wp_kses_post($msg['message']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <nav class="nav-tab-wrapper swiftchats-tabs">
        <?php
        $tabs = array(
            'general' => array('icon' => 'dashicons-admin-generic', 'label' => 'General'),
            'woocommerce' => array('icon' => 'dashicons-cart', 'label' => 'WooCommerce'),
            'widget' => array('icon' => 'dashicons-format-chat', 'label' => 'Chat Widget'),
            'api' => array('icon' => 'dashicons-admin-network', 'label' => 'API Settings')
        );
        foreach ($tabs as $tab_id => $tab) {
            $active_class = ($current_tab === $tab_id) ? 'nav-tab-active' : '';
            printf(
                '<a href="?page=swiftchatswc-settings&tab=%s" class="nav-tab %s"><span class="dashicons %s"></span> %s</a>',
                esc_attr($tab_id),
                esc_attr($active_class),
                esc_attr($tab['icon']),
                esc_html($tab['label'])
            );
        }
        ?>
    </nav>
    <div class="swiftchats-content">
        <?php settings_errors('swiftchatswc_messages'); ?>
        <form method="post" action="">
            <?php wp_nonce_field('swiftchatswc_save_settings', 'swiftchatswc_nonce'); ?>
            <?php
            echo '<input type="hidden" name="swiftchatswc_current_tab" value="' . esc_attr($current_tab) . '">';
            $messages = get_settings_errors('swiftchatswc_options');
            switch ($current_tab) {
                case 'general':
                    ?>
                    <div class="swiftchats-card">
                        <?php if ($messages): ?>
                        <div class="swiftchats-message-card">
                            <?php foreach ($messages as $msg): ?>
                                <div class="swiftchats-message swiftchats-message-<?php echo esc_attr($msg['type']); ?>">
                                    <?php if ($msg['type'] === 'error'): ?><span class="dashicons dashicons-warning"></span><?php endif; ?>
                                    <?php if ($msg['type'] === 'updated'): ?><span class="dashicons dashicons-yes"></span><?php endif; ?>
                                    <?php echo wp_kses_post($msg['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <h2>Business Information</h2>
                        <div class="form-field phone-field">
                            <label for="business_phone">Business Phone</label>
                            <div class="phone-input-group">
                                <select id="country_code" name="swiftchatswc_options[country_code]">
                                    <?php
                                    $country_codes = swiftchatswc_get_country_codes();
                                    foreach ($country_codes as $code => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($code),
                                            selected($options['country_code'] ?? '+1', $code, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                                <input type="tel" id="business_phone" name="swiftchatswc_options[business_phone]" 
                                       value="<?php echo esc_attr($options['business_phone'] ?? ''); ?>"
                                       pattern="^[0-9]{1,14}$"
                                       placeholder="Enter phone number without country code" />
                            </div>
                            <p class="description">Enter your WhatsApp business phone number. We'll use this number to send notifications to you.</p>
                            <div id="phone_validation_message" class="validation-message"></div>
                        </div>
                    </div>
                    <?php
                    break;
                case 'woocommerce':
                    ?>
                    <div class="swiftchats-card">
                        <?php if ($messages): ?>
                        <div class="swiftchats-message-card">
                            <?php foreach ($messages as $msg): ?>
                                <div class="swiftchats-message swiftchats-message-<?php echo esc_attr($msg['type']); ?>">
                                    <?php if ($msg['type'] === 'error'): ?><span class="dashicons dashicons-warning"></span><?php endif; ?>
                                    <?php if ($msg['type'] === 'updated'): ?><span class="dashicons dashicons-yes"></span><?php endif; ?>
                                    <?php echo wp_kses_post($msg['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <h2>WooCommerce Settings</h2>
                        <div class="form-field checkbox-field">
                            <label for="enable_notifications">Enable Notifications</label>
                            <input type="checkbox" id="enable_notifications" name="swiftchatswc_options[enable_notifications]" 
                                   value="1" <?php checked($options['enable_notifications'] ?? '', 1); ?> />
                        </div>

                        <div class="form-field checkbox-field with-block-description">
                            <label for="enable_abandoned_cart">Enable Abandoned Cart</label>
                            <input type="checkbox" id="enable_abandoned_cart" name="swiftchatswc_options[enable_abandoned_cart]"
                                   value="1" <?php checked($options['enable_abandoned_cart'] ?? '', 1); ?> />
                            <p class="description">Enable this to send a message to customers who abandon their checkout.</p>
                        </div>

                        <div class="form-field">
                            <label for="abandoned_cart_timeout">Cart Timeout (minutes)</label>
                            <input type="number" id="abandoned_cart_timeout" name="swiftchatswc_options[abandoned_cart_timeout]"
                                   value="<?php echo esc_attr($options['abandoned_cart_timeout'] ?? '30'); ?>" min="1" />
                            <p class="description">The time in minutes after which a cart is considered abandoned.</p>
                        </div>
                    </div>
                    <?php
                    break;
                case 'widget':
                    ?>
                    <div class="swiftchats-card">
                        <?php if ($messages): ?>
                        <div class="swiftchats-message-card">
                            <?php foreach ($messages as $msg): ?>
                                <div class="swiftchats-message swiftchats-message-<?php echo esc_attr($msg['type']); ?>">
                                    <?php if ($msg['type'] === 'error'): ?><span class="dashicons dashicons-warning"></span><?php endif; ?>
                                    <?php if ($msg['type'] === 'updated'): ?><span class="dashicons dashicons-yes"></span><?php endif; ?>
                                    <?php echo wp_kses_post($msg['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <h2>Chat Widget Settings</h2>
                        <div class="form-field checkbox-field with-block-description">
                            <label for="enable_widget">Enable Chat Widget</label>
                            <input type="checkbox" id="enable_widget" name="swiftchatswc_options[enable_widget]" 
                                   value="1" <?php checked($options['enable_widget'] ?? '', 1); ?> />
                            <p class="description">Show a WhatsApp chat button on your website that visitors can click to start a conversation.</p>
                        </div>
                        <div class="form-field">
                            <label for="widget_message">Default Message</label>
                            <textarea id="widget_message" name="swiftchatswc_options[widget_message]" rows="3"><?php echo esc_textarea($options['widget_message'] ?? 'Hello! I have a question about your products.'); ?></textarea>
                            <p class="description">This message will be pre-filled when customers click the WhatsApp button.</p>
                        </div>
                        <div class="form-field">
                            <label for="widget_position">Widget Position</label>
                            <select id="widget_position" name="swiftchatswc_options[widget_position]">
                                <option value="right" <?php selected($options['widget_position'] ?? 'right', 'right'); ?>>Bottom Right</option>
                                <option value="left" <?php selected($options['widget_position'] ?? 'right', 'left'); ?>>Bottom Left</option>
                            </select>
                            <p class="description">Choose where the chat widget should appear on your website.</p>
                        </div>
                        <div class="form-field phone-field">
                            <label for="widget_phone">Widget Phone Number</label>
                            <div class="phone-input-group">
                                <select id="widget_country_code" name="swiftchatswc_options[widget_country_code]">
                                    <?php
                                    $country_codes = swiftchatswc_get_country_codes();
                                    foreach ($country_codes as $code => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($code),
                                            selected($options['widget_country_code'] ?? ($options['country_code'] ?? '+1'), $code, false),
                                            esc_html($label)
                                        );
                                    }
                                    ?>
                                </select>
                                <input type="tel" id="widget_phone" name="swiftchatswc_options[widget_phone]" 
                                       value="<?php echo esc_attr($options['widget_phone'] ?? ''); ?>"
                                       pattern="^[0-9]{1,14}$"
                                       placeholder="Enter phone number without country code" />
                            </div>
                            <p class="description">This number will be used for the chat widget. If left empty, your business phone will be used.</p>
                        </div>
                    </div>
                    <?php
                    break;
                case 'api':
                    ?>
                    <div class="swiftchats-card">
                        <?php if ($messages): ?>
                        <div class="swiftchats-message-card">
                            <?php foreach ($messages as $msg): ?>
                                <div class="swiftchats-message swiftchats-message-<?php echo esc_attr($msg['type']); ?>">
                                    <?php if ($msg['type'] === 'error'): ?><span class="dashicons dashicons-warning"></span><?php endif; ?>
                                    <?php if ($msg['type'] === 'updated'): ?><span class="dashicons dashicons-yes"></span><?php endif; ?>
                                    <?php echo wp_kses_post($msg['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <h2>API Settings</h2>
                        <div class="form-field">
                            <label for="api_key">API Key</label>
                            <input type="password" id="api_key" name="swiftchatswc_options[api_key]" 
                                   value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" />
                        </div>
                    </div>
                    <?php
                    break;
            }
            submit_button('Save Changes', 'primary button-hero');
            ?>
        </form>
    </div>
</div>
<style>
.swiftchats-admin {
    max-width: 1100px;
    margin: 30px auto;
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

/* Modern Tabs Design */
.swiftchats-tabs {
    display: flex;
    gap: 0;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    margin-bottom: 28px;
    padding: 0 8px;
    border: 1px solid #f0f0f0;
    overflow-x: auto;
}
.swiftchats-tabs .nav-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 13px 28px 13px 18px;
    font-size: 1.08rem;
    font-weight: 600;
    color: #1d2327;
    background: transparent;
    border: none;
    margin: 0 2px 0 0;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    position: relative;
    top: 0;
    box-shadow: none;
    outline: none;
    cursor: pointer;
}
.swiftchats-tabs .nav-tab .dashicons {
    font-size: 20px;
    color: #25d366;
    margin-right: 2px;
}
.swiftchats-tabs .nav-tab-active {
    color: #25d366;
    border-bottom: 3.5px solid #25d366;
    z-index: 2;
}
.swiftchats-tabs .nav-tab:not(.nav-tab-active):hover {
    background: #f7fafd;
    color: #25d366;
}

.swiftchats-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    padding: 32px 24px 28px 24px;
    margin-bottom: 24px;
    border: 1px solid #f0f0f0;
}
.form-field {
    margin-bottom: 20px;
}
.form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}
.form-field input[type="text"],
.form-field input[type="password"],
.form-field input[type="number"],
.form-field select,
.form-field textarea {
    width: 100%;
    max-width: 650px;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 1.08rem;
    background: #f7fafd;
    color: #1d2327;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.form-field input[type="text"]:focus,
.form-field input[type="password"]:focus,
.form-field input[type="number"]:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #25d366;
    box-shadow: 0 2px 8px rgba(37,211,102,0.13);
    background: #fff;
}
.form-field input[type="number"] {
    max-width: 100px;
}
.form-field textarea {
    min-height: 80px;
    resize: vertical;
}
.form-field .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}
.button-hero {
    margin-top: 20px !important;
}
.form-field.checkbox-field {
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-field.checkbox-field label {
    margin-bottom: 0;
    order: 2;
}
.form-field.checkbox-field input[type="checkbox"] {
    margin: 0;
    order: 1;
}
.form-field.checkbox-field .description {
    flex-basis: 100%;
    order: 3;
    margin-top: 10px;
}
.form-field.checkbox-field.with-block-description {
    flex-wrap: wrap;
}
.form-field.checkbox-field.with-block-description .description {
    flex-basis: 100%;
    margin-top: 10px;
    padding-left: 0;
}
.validation-message {
    color: #dc3232;
    margin-top: 5px;
    font-style: italic;
}
.phone-input-group {
    display: flex;
    gap: 10px;
    max-width: 650px;
}
.phone-input-group select {
    width: 250px !important;
}
.phone-input-group input[type="tel"] {
    flex: 1;
}
.form-field.phone-field .description {
    margin-top: 8px;
}
.swiftchats-message-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    padding: 22px 32px 18px 32px;
    margin-bottom: 24px;
    border: 1px solid #f0f0f0;
    max-width: 100%;
}
.swiftchats-message {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.08rem;
    font-weight: 500;
    margin-bottom: 6px;
    padding: 12px 0 8px 0;
    border-radius: 7px;
}
.swiftchats-message-error {
    color: #c62828;
    background: #fbeaea;
    border: 1px solid #f5bdbd;
}
.swiftchats-message-updated {
    color: #2e7d32;
    background: #eafbe7;
    border: 1px solid #b6e2c1;
}
.swiftchats-message .dashicons {
    font-size: 20px;
    margin-right: 6px;
    vertical-align: middle;
}
</style> 