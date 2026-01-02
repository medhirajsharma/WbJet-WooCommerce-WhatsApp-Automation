<?php
    // This file renders the Add/Edit Trigger admin page for SwiftChats
    if (! defined('ABSPATH')) {
        exit;
    }

    global $plugin_page, $parent_file, $wpdb;
    $plugin_page = 'swiftchatswc-triggers';
    $parent_file = 'swiftchatswc';
    $table_name  = $wpdb->prefix . 'swiftchats_triggers';
    require_once plugin_dir_path(__FILE__) . '../api-handler.php';
    $api_handler = new SwiftChatsWC_API_Handler();
    $templates   = $api_handler->get_cached_templates();
    if (is_wp_error($templates)) {
        wp_redirect(admin_url('admin.php?page=swiftchatswc-triggers'));
        exit;
    }
    // Handle add/edit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
        if (!isset($_POST['order_status'], $_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'swiftchats_trigger_nonce')) {
            add_settings_error('swiftchatswc_messages', 'nonce', 'Security check failed. Please try again.', 'error');
        } else {
            $order_status = sanitize_text_field($_POST['order_status']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $variable_mappings = isset($_POST['variable_mapping']) ? wp_json_encode($_POST['variable_mapping']) : '';

            $message_template = '';
            $template_name = '';
            $template_metadata = '';

            if ($order_status === 'abandoned_cart') {
                $message_template = 'abandoned_cart_sequence';
                $template_name = 'Abandoned Cart Sequence';
            } else {
                if (empty($_POST['message_template'])) {
                    add_settings_error('swiftchatswc_messages', 'validation', 'Message template is required.', 'error');
                    return;
                }
                $message_template = sanitize_text_field($_POST['message_template']);
                foreach ($templates as $template) {
                    if (($template['uuid'] ?? '') === $message_template) {
                        $template_name = $template['name'] ?? '';
                        $template_metadata = $template['metadata'] ?? '';
                        break;
                    }
                }
            }

            $trigger_data = [
                'order_status'          => $order_status,
                'message_template'      => $message_template,
                'message_template_name' => $template_name,
                'template_metadata'     => $template_metadata,
                'variable_mappings'     => $variable_mappings,
                'is_active'             => $is_active,
                'updated_at'            => current_time('mysql'),
            ];

            if ($_POST['action'] === 'add') {
                $trigger_data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $trigger_data);
                $trigger_id = $wpdb->insert_id;
            } else {
                $trigger_id = absint($_POST['trigger_id']);
                $result = $wpdb->update($table_name, $trigger_data, ['id' => $trigger_id]);
            }

            if ($result === false) {
                add_settings_error('swiftchatswc_messages', 'db', 'Failed to save trigger.', 'error');
            } else {
                if ($order_status === 'abandoned_cart') {
                    $sequence_table_name = $wpdb->prefix . 'swiftchats_abandoned_cart_sequence';
                    $wpdb->delete($sequence_table_name, ['trigger_id' => $trigger_id]);

                    if (!empty($_POST['sequence'])) {
                        $sequence_items = $_POST['sequence'];
                        $sequence_order = 0;
                        foreach ($sequence_items as $item) {
                            $sequence_order++;
                            $time_interval = intval($item['time_interval']);
                            $time_unit = sanitize_text_field($item['time_unit']);
                            $template_id = sanitize_text_field($item['message_template']);
                            $variable_mappings_seq = isset($item['variable_mapping']) ? wp_json_encode($item['variable_mapping']) : '';
                            
                            $template_name_seq = '';
                            foreach ($templates as $template) {
                                if (($template['uuid'] ?? '') === $template_id) {
                                    $template_name_seq = $template['name'] ?? '';
                                    break;
                                }
                            }

                            $wpdb->insert($sequence_table_name, [
                                'trigger_id' => $trigger_id,
                                'time_interval' => $time_interval,
                                'time_unit' => $time_unit,
                                'message_template_id' => $template_id,
                                'message_template_name' => $template_name_seq,
                                'variable_mappings' => $variable_mappings_seq,
                                'sequence_order' => $sequence_order,
                            ]);
                        }
                    }
                }
                $redirect_url = $_POST['action'] === 'add' ? 'admin.php?page=swiftchatswc-triggers&msg=added' : 'admin.php?page=swiftchatswc-triggers&msg=updated';
                wp_redirect(admin_url($redirect_url));
                exit;
            }
        }
    }
    // Handle form submission (handled in main plugin logic, not here)
    $trigger = null;
    $sequence_items = []; // Initialize empty array
    if (isset($_GET['id'])) {
        $trigger_id = absint($_GET['id']);
        if ($trigger_id > 0) {
            $trigger = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $trigger_id));
            if ($trigger) {
                $trigger->variable_mappings = ! empty($trigger->variable_mappings) ? json_decode($trigger->variable_mappings, true) : [];

                if ($trigger->order_status === 'abandoned_cart') {
                    $sequence_table_name = $wpdb->prefix . 'swiftchats_abandoned_cart_sequence';
                    $sequence_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $sequence_table_name WHERE trigger_id = %d ORDER BY sequence_order ASC", $trigger_id));
                }
            }
        }
    }
    $order_statuses                   = wc_get_order_statuses();
    $order_statuses['abandoned_cart'] = 'Abandoned Cart';
    $page_title                       = $trigger ? 'Edit Trigger' : 'Add New Trigger';
?>
<div class="wrap swiftchats-admin">
    <div class="swiftchats-layout">
        <div class="swiftchats-main">
            <div class="trigger-form-premium-card">
                <div class="trigger-form-hero">
                    <span class="hero-icon-premium"><span class="dashicons dashicons-format-chat"></span></span>
                    <div class="hero-content">
                        <h2><?php echo esc_html($page_title); ?></h2>
                        <p class="hero-subtitle">Send WhatsApp notifications to your customer when an order status changes or for special events.</p>
                    </div>
                </div>
                <form method="post" action="" autocomplete="off">
                    <?php wp_nonce_field('swiftchats_trigger_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo $trigger ? 'edit' : 'add'; ?>">
                    <?php if ($trigger): ?>
                        <input type="hidden" name="trigger_id" value="<?php echo esc_attr($trigger->id); ?>">
                    <?php endif; ?>
                    <div class="form-section-premium">
                        <h3>Trigger Details</h3>
                        <div class="form-field form-field-wide">
                            <label for="order_status" style="color: #777; font-size: 13px; line-height: 1.5;">Order Status:</label>
                            <select name="order_status" id="order_status" required class="swiftchats-select">
                                <option value="" disabled                                                          <?php if (! $trigger) {
                                                                  echo 'selected';
                                                          }
                                                          ?>>Select Order Status</option>
                                <?php
                                    foreach ($order_statuses as $status => $label) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($status),
                                            selected($trigger ? $trigger->order_status : '', $status, false),
                                            esc_html($label)
                                        );
                                    }
                                ?>
                            </select>

                        </div>
                        <div id="default_trigger_settings">
                            <div class="form-field form-field-wide">
                                <label for="message_template">Message Template</label>
                                <select name="message_template" id="message_template" required class="swiftchats-select">
                                    <option value="" disabled <?php if (!$trigger) { echo 'selected'; } ?>>Select Template</option>
                                    <?php
                                    foreach ($templates as $template) {
                                        $metadata = json_decode($template['metadata'] ?? '', true);
                                        if ($metadata) {
                                            printf(
                                                '<option value="%s" data-metadata=\'%s\' %s>%s</option>',
                                                esc_attr($template['uuid'] ?? ''),
                                                esc_attr(wp_json_encode($metadata)),
                                                selected($trigger ? $trigger->message_template : '', $template['uuid'] ?? '', false),
                                                esc_html($template['name'] ?? '')
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div id="template_variables" class="form-section-premium" style="display: none;">
                                <div class="section-header">Template Variables</div>
                                <div id="variable_mappings"></div>
                            </div>
                        </div>

                        <div id="abandoned_cart_sequence_settings" style="display: none;">
                            <h4>Abandoned Cart Reminders Sequence</h4>
                            <div id="sequence_items_container">
                                <!-- Sequence items will be added here dynamically -->
                            </div>
                            <button type="button" id="add_sequence_item" class="button">Add Reminder</button>
                        </div>
                    </div>
                    <div class="form-section-premium">
                        <div class="section-header">Activation</div>
                        <div class="form-switch-premium">
                            <label class="switch">
                                <input type="checkbox" name="is_active" value="1"                                                                                  <?php checked($trigger ? $trigger->is_active : true); ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Active</span>
                        </div>
                    </div>
                    <hr class="form-divider-premium" />
                    <div class="sticky-action-bar-premium">
                        <button type="submit" class="button button-primary">Save Trigger</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=swiftchatswc-triggers')); ?>" class="button">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="swiftchats-sidebar">
            <div class="wa-device-frame">
                <div class="wa-header-bar">
                    <span class="wa-logo"><span class="dashicons dashicons-whatsapp"></span></span>
                    <span class="wa-contact-info">
                        <span class="wa-contact-name">Your Business Name</span>
                        <span class="wa-contact-status">online</span>
                    </span>
                </div>
                <div class="wa-chat-bg">
                    <div class="wa-chat-messages" id="wa-chat-messages">
                        <!-- Chat bubbles injected by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
    .swiftchats-layout { display: flex; gap: 20px; margin-top: 20px; }
    .swiftchats-main { flex: 1; }
    .swiftchats-sidebar { width: 350px; }
    .form-field { margin-bottom: 20px; }
    .form-field label { display: block; margin-bottom: 8px; font-weight: 600; }
    .form-field input[type="text"],
    .form-field input[type="password"],
    .form-field input[type="number"],
    .form-field select,
    .form-field textarea { width: 100%; max-width: 600px; }
    .form-field .description { color: #666; font-style: italic; margin-top: 5px; }
    .wa-device-frame { width: 340px; max-width: 100%; margin: 0 auto 20px auto; border-radius: 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 1.5px 4px rgba(0,0,0,0.08); background: #222; overflow: hidden; display: flex; flex-direction: column; min-height: 540px; border: 1.5px solid #e0e0e0; }
    .wa-header-bar { background: #075e54; color: #fff; padding: 18px 18px 14px 18px; display: flex; align-items: center; gap: 12px; }
    .wa-logo { background: #25d366; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .wa-contact-info { display: flex; flex-direction: column; gap: 2px; }
    .wa-contact-name { font-weight: 600; font-size: 1.08em; }
    .wa-contact-status { font-size: 0.93em; color: #d0f8ce; }
    .wa-chat-bg { background: url('https://static.whatsapp.net/rsrc.php/v3/yl/r/8lWQ5FvT6nM.png'), #ece5dd; background-size: 340px 540px; flex: 1; padding: 0; display: flex; flex-direction: column; }
    .wa-chat-messages { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; padding: 10px 18px 24px 18px; min-height: 200px; }
    .wa-bubble { background: #dcf8c6; color: #222; border-radius: 0 16px 16px 16px; padding: 14px 18px; font-size: 0.97rem; line-height: 1.7; max-width: 95%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); word-break: break-word; align-self: flex-end; }
    .wa-header-label, .wa-footer-label { background: #f0f0f0; color: #888; font-size: 0.93em; border-radius: 8px; padding: 6px 14px; margin-bottom: 0; align-self: flex-end; font-weight: 500; max-width: 70%; }
    .wa-footer-label { font-style: italic; margin-top: 2px; }
    .no-template-selected { color: #666; font-style: italic; text-align: center; margin-top: 40px; }
    .wa-bubble-full { display: flex; flex-direction: column; align-items: stretch; padding: 0; overflow: hidden; }
    .wa-bubble-header-inside { background: transparent; color: #888; font-size: 0.89em; font-weight: 500; padding: 10px 18px 2px 18px; border-radius: 0 16px 0 0; }
    .wa-bubble-body-inside { background: none; color: #383838; font-size: 0.87rem; padding: 10px 18px 0 18px; line-height: 1.3; }
    .wa-bubble-footer-inside { background: transparent; color: #888; font-size: 0.7rem; padding: 6px 18px 10px 18px; border-radius: 0 0 16px 16px; }
    .variable-mapping { margin-bottom: 10px; padding: 10px 0; background: none; border-radius: 0; display: flex; align-items: center; gap: 12px; }
    .variable-mapping label { flex: 1 1 50%; margin: 0; font-weight: 500; min-width: 120px; max-width: 220px; }
    .variable-mapping .variable-select { flex: 1 1 50%; min-width: 120px; max-width: 260px; }
    .trigger-form-premium-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 6px 32px rgba(37,211,102,0.10), 0 1.5px 4px rgba(0,0,0,0.04);
        padding: 0;
        margin-bottom: 28px;
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
        border: 1.5px solid #eafbe7;
    }
    .trigger-form-hero {
        display: flex;
        align-items: center;
        gap: 22px;
        background: #f7fafd;
        border-radius: 18px 18px 0 0;
        padding: 36px 36px 20px 36px;
        border-bottom: 1px solid #e0e0e0;
    }
    .hero-icon-premium {
        font-size: 48px;
        color: #fff;
        background: linear-gradient(135deg, #25d366 60%, #128c7e 100%);
        border-radius: 50%;
        width: 72px;
        height: 72px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(37,211,102,0.13);
    }
    .trigger-form-hero .hero-content h2 {
        margin: 0 0 6px 0;
        font-size: 1.7rem;
        font-weight: 800;
        color: #1d2327;
        letter-spacing: -1px;
    }
    .trigger-form-hero .hero-content .hero-subtitle {
        margin: 0;
        color: #4a5568;
        font-size: 1.09rem;
        opacity: 0.92;
        font-weight: 400;
    }
    .form-section-premium {
        padding: 32px 36px 0 36px;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .section-header {
        font-size: 1.09rem;
        font-weight: 700;
        color: #25d366;
        margin-bottom: 10px;
        letter-spacing: 0.01em;
    }
    .form-divider-premium {
        border: none;
        border-top: 1.5px solid #eafbe7;
        margin: 36px 0 0 0;
    }
    .form-floating-premium {
        position: relative;
        margin-bottom: 0;
    }
    .form-floating-premium select.form-control-premium {
        width: 100%;
        padding: 20px 18px 10px 18px;
        font-size: 1.13rem;
        border: 1.5px solid #e0e0e0;
        border-radius: 999px;
        background: #fff;
        color: #222;
        appearance: none;
        outline: none;
        transition: border-color 0.18s, box-shadow 0.18s;
        box-shadow: 0 1.5px 4px rgba(37,211,102,0.04);
    }
    .form-floating-premium label {
        position: absolute;
        top: 16px;
        left: 24px;
        font-size: 1.13rem;
        color: #888;
        background: #fff;
        padding: 0 4px;
        pointer-events: none;
        transition: 0.18s;
        z-index: 2;
    }
    .form-floating-premium select.form-control-premium:focus + label,
    .form-floating-premium select.form-control-premium:not([value=""]):not(:invalid) + label {
        top: -12px;
        left: 18px;
        font-size: 0.99rem;
        color: #25d366;
        background: #fff;
        padding: 0 4px;
    }
    .form-switch-premium {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 8px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 28px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background: #e0e0e0;
        border-radius: 28px;
        transition: background 0.2s;
    }
    .switch input:checked + .slider {
        background: linear-gradient(90deg, #25d366 60%, #128c7e 100%);
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.2s;
        box-shadow: 0 1.5px 4px rgba(37,211,102,0.10);
    }
    .switch input:checked + .slider:before {
        transform: translateX(20px);
    }
    .switch-label {
        font-size: 1.09rem;
        color: #222;
        font-weight: 600;
    }
    .sticky-action-bar-premium {
        position: sticky;
        bottom: 0;
        left: 0;
        background: #fff;
        border-top: 1.5px solid #eafbe7;
        padding: 22px 36px 22px 36px;
        display: flex;
        gap: 18px;
        z-index: 10;
        margin-top: 36px;
        justify-content: flex-end;
    }
    @media (max-width: 900px) {
        .trigger-form-hero, .form-section-premium, .sticky-action-bar-premium {
            padding-left: 16px;
            padding-right: 16px;
        }
    }
    @media (max-width: 700px) {
        .trigger-form-premium-card {
            border-radius: 10px;
        }
        .trigger-form-hero {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 10px 10px 10px;
        }
        .form-section-premium {
            padding: 18px 10px 0 10px;
        }
        .sticky-action-bar-premium {
            padding: 14px 10px 14px 10px;
            border-radius: 0 0 10px 10px;
        }
    }
    .swiftchats-select {
        width: 100%;
        padding: 14px 38px 14px 18px;
        font-size: 1.08rem;
        border: 1.5px solid #e0e0e0;
        border-radius: 999px;
        background: #fff url('data:image/svg+xml;utf8,<svg fill="%2325d366" height="20" viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7.293 7.293a1 1 0 011.414 0L10 8.586l1.293-1.293a1 1 0 111.414 1.414l-2 2a1 1 0 01-1.414 0l-2-2a1 1 0 010-1.414z"/></svg>') no-repeat right 16px center/18px 18px;
        color: #222;
        appearance: none;
        outline: none;
        box-shadow: 0 1.5px 4px rgba(37,211,102,0.04);
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .swiftchats-select:focus {
        border-color: #25d366;
        box-shadow: 0 2px 8px rgba(37,211,102,0.13);
    }
    </style>
    <script>
    jQuery(document).ready(function($) {

        // --- GLOBAL DATA ---
        const allTemplates = <?php echo json_encode($templates); ?>;
        const savedTrigger = <?php echo json_encode($trigger); ?>;
        const savedSequenceItems = <?php echo json_encode($sequence_items); ?>;
        const availableVariables = {
            'order_id': 'Order ID', 'order_total': 'Order Total', 'customer_name': 'Customer Name',
            'billing_first_name': 'Billing First Name', 'billing_last_name': 'Billing Last Name',
            'shipping_address': 'Shipping Address', 'payment_method': 'Payment Method', 'order_status': 'Order Status',
            'order_items': 'Order Items', 'order_date': 'Order Date', 'tracking_number': 'Tracking Number',
            'tracking_url': 'Tracking URL'
        };

        // --- UI TOGGLING ---
        function toggleTriggerFields() {
            const orderStatus = $('#order_status').val();
            if (orderStatus === 'abandoned_cart') {
                $('#default_trigger_settings').hide();
                $('#abandoned_cart_sequence_settings').show();
                $('#message_template').prop('required', false);
            } else {
                $('#default_trigger_settings').show();
                $('#abandoned_cart_sequence_settings').hide();
                $('#message_template').prop('required', true);
            }
        }

        // --- TEMPLATE PREVIEW & VARIABLE MAPPING ---
        function updateTemplateUI(templateSelect) {
            if (!templateSelect.length) return;

            const selectedOption = templateSelect.find('option:selected');
            const metadataString = selectedOption.attr('data-metadata');
            const metadata = metadataString ? JSON.parse(metadataString) : null;
            const previewDiv = $('#wa-chat-messages');

            const isSequence = templateSelect.hasClass('sequence-template');
            const variableContainer = isSequence
                ? templateSelect.closest('.sequence-item').find('.sequence-variable-mappings')
                : $('#variable_mappings');
            
            variableContainer.html('');
            if (!isSequence) $('#template_variables').hide();

            if (!metadata) {
                previewDiv.html('<p class="no-template-selected">Select a template to see its preview</p>');
                return;
            }

            // Update live preview
            let headerText = '', bodyText = '', footerText = '';
            metadata.components.forEach(component => {
                if (component.type === 'HEADER' && component.format === 'TEXT') headerText = component.text;
                if (component.type === 'BODY') bodyText = component.text;
                if (component.type === 'FOOTER') footerText = component.text;
            });
            let waHtml = '<div class="wa-bubble wa-bubble-full">';
            if (headerText) waHtml += `<div class="wa-bubble-header-inside">${headerText}</div>`;
            if (bodyText) waHtml += `<div class="wa-bubble-body-inside">${bodyText.replace(/\n/g, '<br>')}</div>`;
            if (footerText) waHtml += `<div class="wa-bubble-footer-inside">${footerText}</div>`;
            waHtml += '</div>';
            previewDiv.html(waHtml);

            // Generate and render variable mappings
            let mappingsHtml = '';
            const seqIndex = isSequence ? templateSelect.closest('.sequence-item').data('index') : null;
            
            let savedMappingsForThisItem = null;
            if (isSequence && savedSequenceItems) {
                // This is tricky because seqIndex is dynamic. We'll handle this during population.
            } else if (!isSequence && savedTrigger) {
                savedMappingsForThisItem = savedTrigger.variable_mappings;
            }

            ['header', 'body'].forEach(part => {
                const partVariables = [];
                metadata.components.forEach(component => {
                    if (component.type.toLowerCase() === part && component.text) {
                        const matches = component.text.match(/\{\{(\d+)\}\}/g) || [];
                        matches.forEach(match => partVariables.push(match.replace(/[{}]/g, '')));
                    }
                });

                if (partVariables.length > 0) {
                    mappingsHtml += `<div class="variable-card ${part}-variable-card"><h4 style="margin-top:0">${part.charAt(0).toUpperCase() + part.slice(1)} Variables</h4>`;
                    partVariables.forEach(variable => {
                        const name = isSequence
                            ? `sequence[${seqIndex}][variable_mapping][${part}][${variable}]`
                            : `variable_mapping[${part}][${variable}]`;
                        
                        let options = '<option value="">Select Variable</option>';
                        for (const [value, label] of Object.entries(availableVariables)) {
                            options += `<option value="${value}">${label}</option>`;
                        }

                        mappingsHtml += `
                            <div class="variable-mapping">
                                <label>${part.charAt(0).toUpperCase() + part.slice(1)} Variable {{${variable}}}</label>
                                <select name="${name}" class="variable-select swiftchats-select">${options}</select>
                            </div>`;
                    });
                    mappingsHtml += '</div>';
                }
            });

            if (mappingsHtml) {
                variableContainer.html(mappingsHtml);
                if (!isSequence) $('#template_variables').show();
            }
        }

        // --- SEQUENCE ITEM MANAGEMENT ---
        let sequenceIndex = 0;

        function addSequenceItem(itemData = null) {
            sequenceIndex++;
            let optionsHtml = '<option value="">Select Template</option>';
            allTemplates.forEach(template => {
                if (!template.metadata) return;
                const isSelected = itemData && itemData.message_template_id === template.uuid;
                const metadataAttribute = `data-metadata='${JSON.stringify(template.metadata)}'`;
                optionsHtml += `<option value="${template.uuid}" ${isSelected ? 'selected' : ''} ${metadataAttribute}>${template.name}</option>`;
            });

            const timeInterval = itemData ? itemData.time_interval : '';
            const timeUnit = itemData ? itemData.time_unit : 'minutes';

            const itemHtml = `
                <div class="sequence-item" data-index="${sequenceIndex}" style="padding: 10px; border: 1px solid #ccc; margin-bottom: 10px;">
                    <div class="form-field">
                        <label style="font-weight: bold;">Send after</label>
                        <input type="number" name="sequence[${sequenceIndex}][time_interval]" value="${timeInterval}" class="sequence-time-interval" min="1" required style="width: 100px; margin-right: 10px;">
                        <select name="sequence[${sequenceIndex}][time_unit]" class="sequence-time-unit" required>
                            <option value="minutes" ${timeUnit === 'minutes' ? 'selected' : ''}>Minutes</option>
                            <option value="hours" ${timeUnit === 'hours' ? 'selected' : ''}>Hours</option>
                            <option value="days" ${timeUnit === 'days' ? 'selected' : ''}>Days</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label style="font-weight: bold;">Template</label>
                        <select name="sequence[${sequenceIndex}][message_template]" class="sequence-template swiftchats-select" required>${optionsHtml}</select>
                    </div>
                    <div class="sequence-variable-mappings" style="padding: 10px 0;"></div>
                    <button type="button" class="button remove-sequence-item">Remove</button>
                </div>`;
            $('#sequence_items_container').append(itemHtml);

            const newSelect = $(`#sequence_items_container .sequence-item[data-index=${sequenceIndex}] .sequence-template`);
            if (itemData) {
                updateTemplateUI(newSelect);
                // Now, pre-select the saved variable mappings
                const mappings = itemData.variable_mappings ? JSON.parse(itemData.variable_mappings) : null;
                if(mappings) {
                    for (const [part, vars] of Object.entries(mappings)) {
                        for (const [varNum, varName] of Object.entries(vars)) {
                           newSelect.closest('.sequence-item').find(`select[name="sequence[${sequenceIndex}][variable_mapping][${part}][${varNum}]"]`).val(varName);
                        }
                    }
                }
            }
        }

        // --- INITIALIZATION & EVENT LISTENERS ---
        toggleTriggerFields();

        if (savedSequenceItems && savedSequenceItems.length > 0) {
            savedSequenceItems.forEach(item => addSequenceItem(item));
        }
        
        updateTemplateUI($('#message_template'));
        if (savedTrigger && savedTrigger.variable_mappings) {
             for (const [part, vars] of Object.entries(savedTrigger.variable_mappings)) {
                for (const [varNum, varName] of Object.entries(vars)) {
                   $('#variable_mappings').find(`select[name="variable_mapping[${part}][${varNum}]"]`).val(varName);
                }
            }
        }

        $('#order_status').on('change', toggleTriggerFields);
        $('#add_sequence_item').on('click', () => addSequenceItem());
        $('#sequence_items_container').on('click', '.remove-sequence-item', function() { $(this).closest('.sequence-item').remove(); });
        $('.swiftchats-main').on('change', '#message_template, .sequence-template', function() { updateTemplateUI($(this)); });
    });
    </script>
</div>
<?php settings_errors('swiftchatswc_messages'); ?>