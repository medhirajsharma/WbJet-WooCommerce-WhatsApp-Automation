<?php
if (!defined('ABSPATH')) exit;

class SwiftChatsWC_API_Handler {
    private $base_url;
    private $api_key;

    public function __construct() {
        $options = get_option('swiftchatswc_options', array());
        $this->base_url = defined('SWIFTCHATSWC_API_BASE_URL') ? SWIFTCHATSWC_API_BASE_URL : '';
        $this->api_key = isset($options['api_key']) && is_string($options['api_key']) ? $options['api_key'] : '';
    }

    public function verify_api_key($api_key) {
        if (empty($this->base_url) || !is_string($api_key) || empty($api_key)) {
            return new WP_Error('api_config_missing', 'API configuration is incomplete');
        }

        $response = wp_remote_get(
            trailingslashit($this->base_url) . 'api/verify',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . sanitize_text_field($api_key),
                    'Accept' => 'application/json'
                ),
                'timeout' => 15
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to connect to API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return new WP_Error('api_error', 'Empty response from API');
        }

        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $error_message = '';
            if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                $error_message = $data['message'];
            } else {
                $error_message = 'Invalid API key';
            }
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $response_code)
            );
        }

        return true;
    }

    public function get_templates() {
        if (empty($this->base_url) || !is_string($this->api_key) || empty($this->api_key)) {
            return new WP_Error('api_config_missing', 'API configuration is incomplete');
        }

        $all_templates = array();
        $page = 1;
        $per_page = 100;
        $to = 0;
        $total = null;
        do {
            $url = trailingslashit($this->base_url) . 'api/templates?page=' . $page . '&per_page=' . $per_page;
            $response = wp_remote_get(
                $url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . sanitize_text_field($this->api_key),
                        'Accept' => 'application/json'
                    ),
                    'timeout' => 15
                )
            );

            if (is_wp_error($response)) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return new WP_Error('api_error', 'Empty response from API');
            }
            $data = json_decode($body, true);
            if ($response_code !== 200) {
                $error_message = '';
                if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                    $error_message = $data['message'];
                } else {
                    $error_message = 'Failed to fetch templates';
                }
                return new WP_Error(
                    'api_error',
                    $error_message,
                    array('status' => $response_code)
                );
            }
            $templates = is_array($data) && isset($data['data']) ? $data['data'] : array();
            $all_templates = array_merge($all_templates, $templates);
            // Use per_page, to, total from response for next iteration
            $per_page = isset($data['per_page']) ? (int)$data['per_page'] : $per_page;
            $to = isset($data['to']) ? (int)$data['to'] : ($page * $per_page);
            $total = isset($data['total']) ? (int)$data['total'] : null;
            $page++;
        } while ($total !== null && $to < $total);
        return $all_templates;
    }

    // Cache templates for 5 minutes to avoid too many API calls
    public function get_cached_templates() {
        $cached = get_transient('swiftchatswc_templates');
        if ($cached !== false) {
            return $cached;
        }

        $templates = $this->get_templates();
        if (!is_wp_error($templates)) {
            set_transient('swiftchatswc_templates', $templates, 5 * MINUTE_IN_SECONDS);
        }

        return $templates;
    }

    public function send_template_with_metadata($phone, $template_metadata, $variable_mappings, $data) {
        if (empty($this->base_url) || empty($this->api_key) || empty($phone) || empty($template_metadata)) {
            return false;
        }
        // Format phone number
        $options = get_option('swiftchatswc_options', array());
        $phone_trimmed = trim((string)$phone);
        $final_phone = '';

        // Check if the number already starts with a '+'
        if (substr($phone_trimmed, 0, 1) === '+') {
            $final_phone = $phone_trimmed;
        } else {
            // --- DYNAMIC COUNTRY CODE LOGIC ---
            // Default to the global country code
            $country_code_to_use = $options['country_code'] ?? '';

            // If we have customer data (abandoned cart), try to get their specific country code
            if (!($data instanceof WC_Order) && is_array($data) && !empty($data['country'])) {
                if (function_exists('WC') && WC()->countries) {
                    $customer_country = $data['country'];
                    $dynamic_code = WC()->countries->get_country_calling_code($customer_country);
                    if ($dynamic_code) {
                        $country_code_to_use = $dynamic_code;
                    }
                }
            }
            // --- END DYNAMIC LOGIC ---

            // Ensure country code has a '+'
            if (!empty($country_code_to_use) && substr($country_code_to_use, 0, 1) !== '+') {
                $country_code_to_use = '+' . $country_code_to_use;
            }
            
            $final_phone = $country_code_to_use . $phone_trimmed;
        }

        // Clean the final number to only have a leading '+' and digits
        $cleaned_digits = preg_replace('/[^0-9]/', '', $final_phone);
        $to = '+' . $cleaned_digits;
        
        // Prepare template payload
        $template = $template_metadata;
        $is_order = (class_exists('WC_Order') && $data instanceof WC_Order);
        // Use only body and header variable mappings if present
        if (is_array($variable_mappings) && isset($variable_mappings['body'])) {
            $body_variable_mappings = $variable_mappings['body'];
        } else {
            $body_variable_mappings = $variable_mappings;
        }
        if (is_array($variable_mappings) && isset($variable_mappings['header'])) {
            $header_variable_mappings = $variable_mappings['header'];
        } else {
            $header_variable_mappings = $variable_mappings;
        }
        // Fill in variables in components
        if (isset($template['components']) && is_array($template['components'])) {
            $new_components = array();
            $header_component = null;
            $body_component = null;
            // First, find header and body components and process them
            foreach ($template['components'] as $component) {
                $type = strtolower($component['type'] ?? '');
                $parameters = array();
                if ($type === 'header') {
                    $header_examples = array();
                    if (isset($component['example']['header_text']) && is_array($component['example']['header_text'])) {
                        $header_examples = $component['example']['header_text'];
                    }
                    if (!empty($header_examples)) {
                        foreach ($header_examples as $idx => $example_value) {
                            $var_key = $header_variable_mappings[$idx + 1] ?? null;
                            $value = $example_value;
                            if ($var_key) {
                                switch ($var_key) {
                                    case 'order_id': $value = $is_order ? $data->get_id() : 'N/A'; break;
                                    case 'order_total':
                                        if ($is_order) {
                                            $amount = $data->get_total();
                                            $currency = $data->get_currency();
                                            $value = wc_price($amount, array('currency' => $currency, 'decimals' => wc_get_price_decimals(), 'html_format' => false));
                                            $value = strip_tags($value);
                                            $value = str_replace(["\xC2\xA0", "\xA0", '&nbsp;'], ' ', $value);
                                            $value = preg_replace('/\s+/', ' ', $value);
                                            $value = trim($value);
                                        } else {
                                            $value = 'N/A';
                                        }
                                        break;
                                    case 'customer_name': $value = $is_order ? trim($data->get_billing_first_name() . ' ' . $data->get_billing_last_name()) : trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')); break;
                                    case 'billing_first_name': $value = $is_order ? $data->get_billing_first_name() : ($data['first_name'] ?? ''); break;
                                    case 'billing_last_name': $value = $is_order ? $data->get_billing_last_name() : ($data['last_name'] ?? ''); break;
                                    case 'shipping_address':
                                        if ($is_order) {
                                            $value = $data->get_formatted_shipping_address();
                                            $value = preg_replace('/<br\s*\/?\s*>/i', ",", $value);
                                            $value = strip_tags($value);
                                            $value = trim($value);
                                        } else {
                                            $value = ($data['shipping_address_1'] ?? '') . ', ' . ($data['shipping_city'] ?? '');
                                        }
                                        break;
                                    case 'payment_method': $value = $is_order ? $data->get_payment_method_title() : 'N/A'; break;
                                    case 'order_status': $value = $is_order ? wc_get_order_status_name($data->get_status()) : 'N/A'; break;
                                    case 'order_items':
                                        if ($is_order) {
                                            $items = $data->get_items();
                                            $item_names = array();
                                            foreach ($items as $item) {
                                                $item_names[] = method_exists($item, 'get_name') ? $item->get_name() : (is_array($item) && isset($item['name']) ? $item['name'] : '');
                                            }
                                            $value = implode(', ', $item_names);
                                        } else {
                                            $value = 'N/A';
                                        }
                                        break;
                                    case 'order_date': $value = $is_order && $data->get_date_created() ? $data->get_date_created()->date('Y-m-d H:i') : 'N/A'; break;
                                    case 'tracking_number': $value = $is_order ? $data->get_meta('tracking_number') : 'N/A'; break;
                                    case 'tracking_url': $value = $is_order ? $data->get_meta('tracking_url') : 'N_A'; break;
                                    default: $value = $example_value;
                                }
                                if (empty($value)) {
                                    $value = 'N/A';
                                }
                            }
                            $parameters[] = array(
                                'type' => 'text',
                                'text' => (string)$value
                            );
                        }
                        if (!empty($parameters)) {
                            $component['parameters'] = $parameters;
                        }
                        if (isset($component['format'])) {
                            unset($component['format']);
                        }
                        unset($component['text'], $component['example']);
                        $header_component = $component;
                    }
                } elseif ($type === 'body') {
                    $body_examples = array();
                    if (isset($component['example']['body_text'][0]) && is_array($component['example']['body_text'][0])) {
                        $body_examples = $component['example']['body_text'][0];
                    }
                    if (!empty($body_examples)) {
                        foreach ($body_examples as $idx => $example_value) {
                            $var_key = $body_variable_mappings[$idx + 1] ?? null;
                            $value = $example_value;
                            if ($var_key) {
                                switch ($var_key) {
                                    case 'order_id': $value = $is_order ? $data->get_id() : 'N/A'; break;
                                    case 'order_total':
                                        if ($is_order) {
                                            $amount = $data->get_total();
                                            $currency = $data->get_currency();
                                            $value = wc_price($amount, array('currency' => $currency, 'decimals' => wc_get_price_decimals(), 'html_format' => false));
                                            $value = strip_tags($value);
                                            $value = str_replace(["\xC2\xA0", "\xA0", '&nbsp;'], ' ', $value);
                                            $value = preg_replace('/\s+/', ' ', $value);
                                            $value = trim($value);
                                        } else {
                                            $value = 'N/A';
                                        }
                                        break;
                                    case 'customer_name': $value = $is_order ? trim($data->get_billing_first_name() . ' ' . $data->get_billing_last_name()) : trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')); break;
                                    case 'billing_first_name': $value = $is_order ? $data->get_billing_first_name() : ($data['first_name'] ?? ''); break;
                                    case 'billing_last_name': $value = $is_order ? $data->get_billing_last_name() : ($data['last_name'] ?? ''); break;
                                    case 'shipping_address':
                                        if ($is_order) {
                                            $value = $data->get_formatted_shipping_address();
                                            $value = preg_replace('/<br\s*\/?\s*>/i', ",", $value);
                                            $value = strip_tags($value);
                                            $value = trim($value);
                                        } else {
                                            $value = ($data['shipping_address_1'] ?? '') . ', ' . ($data['shipping_city'] ?? '');
                                        }
                                        break;
                                    case 'payment_method': $value = $is_order ? $data->get_payment_method_title() : 'N/A'; break;
                                    case 'order_status': $value = $is_order ? wc_get_order_status_name($data->get_status()) : 'N/A'; break;
                                    case 'order_items':
                                        if ($is_order) {
                                            $items = $data->get_items();
                                            $item_names = array();
                                            foreach ($items as $item) {
                                                $item_names[] = method_exists($item, 'get_name') ? $item->get_name() : (is_array($item) && isset($item['name']) ? $item['name'] : '');
                                            }
                                            $value = implode(', ', $item_names);
                                        } else {
                                            $value = 'N/A';
                                        }
                                        break;
                                    case 'order_date': $value = $is_order && $data->get_date_created() ? $data->get_date_created()->date('Y-m-d H:i') : 'N/A'; break;
                                    case 'tracking_number': $value = $is_order ? $data->get_meta('tracking_number') : 'N/A'; break;
                                    case 'tracking_url': $value = $is_order ? $data->get_meta('tracking_url') : 'N_A'; break;
                                    default: $value = $example_value;
                                }
                                if (empty($value)) {
                                    $value = 'N/A';
                                }
                            }
                            $parameters[] = array(
                                'type' => 'text',
                                'text' => (string)$value
                            );
                        }
                        if (!empty($parameters)) {
                            $component['parameters'] = $parameters;
                        }
                        unset($component['text'], $component['example']);
                        $body_component = $component;
                    }
                }
                // Skip footer always
            }
            // Add both header and body components if present
            if ($header_component) {
                $new_components[] = $header_component;
            }
            if ($body_component) {
                $new_components[] = $body_component;
            }
            $template['components'] = $new_components;
        }
        // Remove unwanted keys from template
        unset($template['parameter_format'], $template['id'], $template['status']);
        // Build the template payload in the required format
        $formatted_template = array(
            'name' => $template['name'] ?? '',
            'language' => isset($template['language']) ? array('code' => $template['language']) : array('code' => 'en'),
        );
        if (!empty($template['components'])) {
            $formatted_template['components'] = $template['components'];
        }
        $payload = array(
            'phone' => (string)$to,
            'template' => $formatted_template
        );
        // Log the formatted payload to swiftchats-debug.log
        $log_message = '[send_template_with_metadata] ' . date('[Y-m-d H:i:s] ') . print_r($payload, true) . PHP_EOL;
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);
        $response = wp_remote_post(
            trailingslashit($this->base_url) . 'api/send/template',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . sanitize_text_field($this->api_key),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'body' => wp_json_encode($payload),
                'timeout' => 20
            )
        );
        $log_message = '[send_template_with_metadata] ' . date('[Y-m-d H:i:s] ') . print_r($response, true) . PHP_EOL;
        file_put_contents(dirname(__FILE__) . '/swiftchats-debug.log', $log_message, FILE_APPEND);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
} 