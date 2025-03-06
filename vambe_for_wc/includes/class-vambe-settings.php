<?php

if (!defined('ABSPATH')) {
    exit;
}

class Vambe_Settings {
    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            
            // Admin hooks
            add_action('admin_menu', array(self::$instance, 'add_settings_page'));
            add_action('admin_init', array(self::$instance, 'register_settings'));
        }
        return self::$instance;
    }

    private function __construct() {
        // Basic initialization if needed
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Vambe Settings', 'vambe-for-woocommerce'),
            __('Vambe Settings', 'vambe-for-woocommerce'),
            'manage_options',
            'vambe-settings',
            array($this, 'render_settings_page')
        );
        
        // Fetch channels data when entering the settings page
        add_action('load-woocommerce_page_vambe-settings', array($this, 'fetch_channels_data'));
    }
    
    /**
     * Fetch available channels data from the API
     */
    public function fetch_channels_data() {
        $webhook_url = 'https://5803-186-10-44-110.ngrok-free.app/api/webchat/channel/get-all-woocommerce';
        
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'x-wc-webhook-source' => get_site_url(),
                'Content-Type' => 'application/json',
            ),
        );
        
        $response = wp_remote_get($webhook_url, $args);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Store the data in a transient for 1 hour
                set_transient('vambe_channels_data', $data, HOUR_IN_SECONDS);
                
                // Update client ID in options
                if (isset($data['clientId']) && !empty($data['clientId'])) {
                    update_option('vambe_webchat_client_id', $data['clientId']);
                }
            }
        }
    }

    public function register_settings() {
        // Cart tracking settings
        register_setting('vambe_settings', 'vambe_cart_timeout');
        register_setting('vambe_settings', 'vambe_enable_tracking');
        
        // Webchat settings
        register_setting('vambe_settings', 'vambe_enable_webchat');
        register_setting('vambe_settings', 'vambe_webchat_client_id');
        register_setting('vambe_settings', 'vambe_webchat_channel_id');
        register_setting('vambe_settings', 'vambe_webchat_agent_name');
        register_setting('vambe_settings', 'vambe_webchat_agent_icon_url');
        register_setting('vambe_settings', 'vambe_webchat_dark_theme');
        register_setting('vambe_settings', 'vambe_webchat_primary_color');
        register_setting('vambe_settings', 'vambe_webchat_secondary_color');
        register_setting('vambe_settings', 'vambe_webchat_language');
        register_setting('vambe_settings', 'vambe_webchat_ask_for_phone');
        register_setting('vambe_settings', 'vambe_webchat_ask_for_email');
        register_setting('vambe_settings', 'vambe_webchat_ask_for_name');
        register_setting('vambe_settings', 'vambe_webchat_suggested_questions');
        register_setting('vambe_settings', 'vambe_webchat_chat_with_us_text');

        // Cart tracking section
        add_settings_section(
            'vambe_cart_tracking_section',
            __('Cart Tracking', 'vambe-for-woocommerce'),
            null,
            'vambe-settings'
        );

        add_settings_field(
            'vambe_enable_tracking',
            __('Enable Cart Tracking', 'vambe-for-woocommerce'),
            array($this, 'render_enable_tracking_field'),
            'vambe-settings',
            'vambe_cart_tracking_section'
        );

        add_settings_field(
            'vambe_cart_timeout',
            __('Abandoned Cart Timeout (hours)', 'vambe-for-woocommerce'),
            array($this, 'render_timeout_field'),
            'vambe-settings',
            'vambe_cart_tracking_section'
        );
        
        // Webchat section
        add_settings_section(
            'vambe_webchat_section',
            __('Webchat Settings', 'vambe-for-woocommerce'),
            null,
            'vambe-settings'
        );
        
        add_settings_field(
            'vambe_enable_webchat',
            __('Enable Webchat', 'vambe-for-woocommerce'),
            array($this, 'render_enable_webchat_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_client_id',
            __('Client ID', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_client_id_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_channel_id',
            __('Channel ID', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_channel_id_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_agent_name',
            __('Agent Name', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_agent_name_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_agent_icon_url',
            __('Agent Icon URL', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_agent_icon_url_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_primary_color',
            __('Primary Color', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_primary_color_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_secondary_color',
            __('Secondary Color', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_secondary_color_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_dark_theme',
            __('Dark Theme', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_dark_theme_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_language',
            __('Language', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_language_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_ask_for_name',
            __('Ask for Name', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_ask_for_name_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_ask_for_email',
            __('Ask for Email', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_ask_for_email_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_ask_for_phone',
            __('Ask for Phone Number', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_ask_for_phone_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_suggested_questions',
            __('Suggested Questions', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_suggested_questions_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
        
        add_settings_field(
            'vambe_webchat_chat_with_us_text',
            __('Chat With Us Text', 'vambe-for-woocommerce'),
            array($this, 'render_webchat_chat_with_us_text_field'),
            'vambe-settings',
            'vambe_webchat_section'
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap vambe-settings-wrap">
            <div class="vambe-header">
                <div class="vambe-logo">
                    <?php 
                    $svg_path = VAMBE_PLUGIN_URL . 'assets/images/vambe-logo.svg';
                    echo file_get_contents($svg_path); 
                    ?>
                </div>
                <h1><?php echo esc_html__('Settings', 'vambe-for-woocommerce'); ?></h1>
            </div>
            
            <div class="vambe-tabs">
                <button class="vambe-tab-link" data-tab="cart-tracking"><?php esc_html_e('Cart Tracking (Beta)', 'vambe-for-woocommerce'); ?></button>
                <button class="vambe-tab-link active" data-tab="webchat"><?php esc_html_e('Webchat', 'vambe-for-woocommerce'); ?></button>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('vambe_settings'); ?>
                
                <div id="webchat" class="vambe-tab-content active">
                    <h2><?php esc_html_e('Webchat Settings', 'vambe-for-woocommerce'); ?></h2>
                    <p class="vambe-section-description">
                        <?php esc_html_e('Configure the Vambe webchat widget that appears on your store.', 'vambe-for-woocommerce'); ?>
                    </p>
                    <table class="form-table">
                        <?php do_settings_fields('vambe-settings', 'vambe_webchat_section'); ?>
                    </table>
                </div>
                <div id="cart-tracking" class="vambe-tab-content">
                    <h2><?php esc_html_e('Cart Tracking Settings (Beta)', 'vambe-for-woocommerce'); ?></h2>
                    <p class="vambe-section-description">
                        <?php esc_html_e('Configure how Vambe tracks abandoned carts on your store.', 'vambe-for-woocommerce'); ?>
                    </p>
                    <table class="form-table">
                        <?php do_settings_fields('vambe-settings', 'vambe_cart_tracking_section'); ?>
                    </table>
                </div>
                
                
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
            .vambe-settings-wrap {
                max-width: 900px;
                margin: 20px auto;
                padding: 30px;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .vambe-header {
                display: flex;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
            }
            .vambe-logo {
                width: 160px;
                height: 32px;
                margin-right: 15px;
            }
            .vambe-logo svg {
                width: 100%;
                height: 100%;
            }
            .vambe-header h1 {
                margin: 0;
                padding: 0;
                font-size: 24px;
                font-weight: 500;
                line-height: 1.3;
            }
            .vambe-tabs {
                display: flex;
                margin-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
            .vambe-tab-link {
                background: none;
                border: none;
                padding: 12px 20px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                border-bottom: 3px solid transparent;
                color: #555;
                transition: all 0.3s;
            }
            .vambe-tab-link:hover {
                color: #2054F8;
            }
            .vambe-tab-link.active {
                color: #2054F8;
                border-bottom-color: #2054F8;
            }
            .vambe-tab-content {
                display: none;
                padding: 20px 0;
            }
            .vambe-tab-content.active {
                display: block;
            }
            .vambe-section-description {
                font-size: 14px;
                color: #666;
                margin-bottom: 20px;
            }
            .form-table {
                background: #f9f9f9;
                border-radius: 6px;
                padding: 20px;
                margin-bottom: 30px;
            }
            .form-table th {
                width: 250px;
                padding: 20px 10px 20px 0;
                vertical-align: top;
                font-weight: 500;
            }
            .form-table td {
                padding: 15px 10px;
            }
            .vambe-settings-wrap .button-primary {
                background: #2054F8;
                border-color: #1844D6;
                box-shadow: 0 1px 0 #1844D6;
            }
            .vambe-settings-wrap .button-primary:hover,
            .vambe-settings-wrap .button-primary:focus {
                background: #1844D6;
                border-color: #1233C5;
                box-shadow: 0 1px 0 #1233C5;
            }
            .disabled-field {
                opacity: 0.5;
                pointer-events: none;
            }
            input[type="text"], 
            input[type="url"], 
            input[type="number"],
            select {
                width: 100%;
                max-width: 400px;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            input[type="color"] {
                width: 60px;
                height: 30px;
                padding: 0;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .description {
                margin-top: 8px;
                font-size: 13px;
                color: #666;
            }
            .switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 22px;
            }
            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
            }
            .slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 2px;
                bottom: 2px;
                background-color: white;
                transition: .4s;
            }
            input:checked + .slider {
                background-color: #2054F8;
            }
            input:checked + .slider:before {
                transform: translateX(18px);
            }
            .slider.round {
                border-radius: 34px;
            }
            .slider.round:before {
                border-radius: 50%;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.vambe-tab-link').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).data('tab');
                
                // Update active tab
                $('.vambe-tab-link').removeClass('active');
                $(this).addClass('active');
                
                // Show active content
                $('.vambe-tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
            });
            
            // Toggle cart tracking fields
            function toggleCartTrackingFields() {
                var isEnabled = $('input[name="vambe_enable_tracking"]').is(':checked');
                var fields = $('input[name="vambe_cart_timeout"]');
                var rows = fields.closest('tr');
                
                if (isEnabled) {
                    rows.removeClass('disabled-field');
                    fields.prop('disabled', false);
                } else {
                    rows.addClass('disabled-field');
                    fields.prop('disabled', true);
                }
            }
            
            // Toggle webchat fields
            function toggleWebchatFields() {
                var isEnabled = $('input[name="vambe_enable_webchat"]').is(':checked');
                var webchatFields = $('#webchat .form-table tr').not(':first-child');
                
                if (isEnabled) {
                    webchatFields.removeClass('disabled-field');
                    webchatFields.find('input, select').prop('disabled', false);
                } else {
                    webchatFields.addClass('disabled-field');
                    webchatFields.find('input, select').prop('disabled', true);
                }
            }
            
            // Bind change events
            $('input[name="vambe_enable_tracking"]').on('change', toggleCartTrackingFields);
            $('input[name="vambe_enable_webchat"]').on('change', toggleWebchatFields);
            
            // Initialize on page load
            toggleCartTrackingFields();
            toggleWebchatFields();
        });
        </script>
        <?php
    }

    public function render_timeout_field() {
        $value = get_option('vambe_cart_timeout', 1);
        ?>
        <input type="number" 
               name="vambe_cart_timeout" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               step="1" 
               class="regular-text">
        <p class="description">
            <?php esc_html_e('Time in hours before a cart is considered abandoned (minimum 1 hour)', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }

    public function render_enable_tracking_field() {
        $value = get_option('vambe_enable_tracking', 'no');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_enable_tracking" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Enable or disable cart tracking functionality', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_enable_webchat_field() {
        $value = get_option('vambe_enable_webchat', 'no');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_enable_webchat" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Enable or disable webchat on your store', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_client_id_field() {
        $value = get_option('vambe_webchat_client_id', '');
        ?>
        <input type="text" 
               name="vambe_webchat_client_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               readonly="readonly"
               style="background-color: #f0f0f0;">
        <p class="description">
            <?php esc_html_e('Your Vambe client ID', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_channel_id_field() {
        $value = get_option('vambe_webchat_channel_id', '');
        $channels_data = get_transient('vambe_channels_data');
        $available_channels = array();
        
        if ($channels_data && isset($channels_data['channels']) && is_array($channels_data['channels'])) {
            $available_channels = $channels_data['channels'];
        }
        
        if (!empty($available_channels)) {
            ?>
            <select name="vambe_webchat_channel_id" class="regular-text">
                <option value=""><?php esc_html_e('-- Select a channel --', 'vambe-for-woocommerce'); ?></option>
                <?php foreach ($available_channels as $channel) : 
                    // Check if channel is an object with id and name properties
                    if (is_array($channel) && isset($channel['id']) && isset($channel['name'])) {
                        $channel_id = $channel['id'];
                        $channel_name = $channel['name'];
                    } else {
                        $channel_id = $channel;
                        $channel_name = $channel;
                    }
                ?>
                    <option value="<?php echo esc_attr($channel_id); ?>" <?php selected($value, $channel_id); ?>>
                        <?php echo esc_html($channel_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('Select a channel from the list. If you don\'t see any channel, create one in your Vambe dashboard and refresh this page.', 'vambe-for-woocommerce'); ?>
            </p>
            <?php
        } else {
            ?>
            <input type="text" 
                   name="vambe_webchat_channel_id" 
                   value="<?php echo esc_attr($value); ?>" 
                   class="regular-text">
            <button type="button" id="fetch-channels-btn" class="button button-secondary">
                <?php esc_html_e('Fetch Available Channels', 'vambe-for-woocommerce'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('Your Vambe channel ID (e.g., "93d737c8-4eec-44b0-98a6-d2ef9e510b0d") or click the button to fetch available channels', 'vambe-for-woocommerce'); ?>
            </p>
            <div id="channels-loading" style="display: none;">
                <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                <?php esc_html_e('Fetching channels...', 'vambe-for-woocommerce'); ?>
            </div>
            <div id="channels-result" style="display: none; margin-top: 10px;"></div>
            <script>
            jQuery(document).ready(function($) {
                $('#fetch-channels-btn').on('click', function() {
                    var btn = $(this);
                    var loading = $('#channels-loading');
                    var result = $('#channels-result');
                    
                    btn.prop('disabled', true);
                    loading.show();
                    result.hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'vambe_fetch_channels',
                            nonce: '<?php echo wp_create_nonce('vambe_fetch_channels'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var channels = response.data.channels || [];
                                var clientId = response.data.clientId || '';
                                
                                if (channels.length > 0) {
                                    var select = $('<select name="vambe_webchat_channel_id" class="regular-text"></select>');
                                    select.append('<option value=""><?php esc_html_e('-- Select a channel --', 'vambe-for-woocommerce'); ?></option>');
                                    
                                    $.each(channels, function(i, channel) {
                                        // Check if channel is an object with id and name properties
                                        var channelId, channelName;
                                        if (typeof channel === 'object' && channel.id && channel.name) {
                                            channelId = channel.id;
                                            channelName = channel.name;
                                        } else {
                                            channelId = channel;
                                            channelName = channel;
                                        }
                                        var option = $('<option></option>').val(channelId).text(channelName);
                                        select.append(option);
                                    });
                                    
                                    $('input[name="vambe_webchat_channel_id"]').replaceWith(select);
                                    
                                    if (clientId) {
                                        $('input[name="vambe_webchat_client_id"]').val(clientId);
                                    }
                                    
                                    result.html('<div class="notice notice-success inline"><p><?php esc_html_e('Channels fetched successfully!', 'vambe-for-woocommerce'); ?></p></div>');
                                } else {
                                    result.html('<div class="notice notice-warning inline"><p><?php esc_html_e('No channels found.', 'vambe-for-woocommerce'); ?></p></div>');
                                }
                            } else {
                                result.html('<div class="notice notice-error inline"><p>' + (response.data || '<?php esc_html_e('Error fetching channels.', 'vambe-for-woocommerce'); ?>') + '</p></div>');
                            }
                        },
                        error: function() {
                            result.html('<div class="notice notice-error inline"><p><?php esc_html_e('Error connecting to server.', 'vambe-for-woocommerce'); ?></p></div>');
                        },
                        complete: function() {
                            btn.prop('disabled', false);
                            loading.hide();
                            result.show();
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    public function render_webchat_agent_name_field() {
        $value = get_option('vambe_webchat_agent_name', '');
        ?>
        <input type="text" 
               name="vambe_webchat_agent_name" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php esc_html_e('Name of the agent that will appear in the chat', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_agent_icon_url_field() {
        $value = get_option('vambe_webchat_agent_icon_url', '');
        ?>
        <input type="url" 
               name="vambe_webchat_agent_icon_url" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php esc_html_e('URL to the agent\'s icon image (recommended size: 100x100px)', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_primary_color_field() {
        $value = get_option('vambe_webchat_primary_color', '#000000');
        ?>
        <input type="color" 
               name="vambe_webchat_primary_color" 
               value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Primary color for the chat widget', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_secondary_color_field() {
        $value = get_option('vambe_webchat_secondary_color', '#FFFFFF');
        ?>
        <input type="color" 
               name="vambe_webchat_secondary_color" 
               value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Secondary color for the chat widget', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_dark_theme_field() {
        $value = get_option('vambe_webchat_dark_theme', 'no');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_webchat_dark_theme" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Use dark theme for the chat widget', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_language_field() {
        $value = get_option('vambe_webchat_language', 'es');
        $languages = array(
            'es' => __('Spanish', 'vambe-for-woocommerce'),
            'en' => __('English', 'vambe-for-woocommerce'),
            'pt' => __('Portuguese', 'vambe-for-woocommerce'),
        );
        ?>
        <select name="vambe_webchat_language">
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Language for the chat interface', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_ask_for_name_field() {
        $value = get_option('vambe_webchat_ask_for_name', 'yes');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_webchat_ask_for_name" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Ask for customer\'s name before starting chat', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_ask_for_email_field() {
        $value = get_option('vambe_webchat_ask_for_email', 'yes');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_webchat_ask_for_email" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Ask for customer\'s email before starting chat', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_ask_for_phone_field() {
        $value = get_option('vambe_webchat_ask_for_phone', 'yes');
        ?>
        <label class="switch">
            <input type="checkbox" 
                   name="vambe_webchat_ask_for_phone" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?>>
            <span class="slider round"></span>
        </label>
        <p class="description">
            <?php esc_html_e('Ask for customer\'s phone number before starting chat', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_suggested_questions_field() {
        $value = get_option('vambe_webchat_suggested_questions', '');
        $questions = $value ? explode("\n", $value) : array();
        ?>
        <textarea name="vambe_webchat_suggested_questions" 
                  rows="4" 
                  cols="50" 
                  class="large-text"
                  placeholder="<?php esc_attr_e('Enter one question per line (maximum 4)', 'vambe-for-woocommerce'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Suggested questions to show in the webchat (maximum 4). Enter one question per line.', 'vambe-for-woocommerce'); ?>
            <br>
            <?php esc_html_e('Example: "What services do you offer?", "How can I get started?", "What are your pricing plans?", "How can I contact support?"', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
    
    public function render_webchat_chat_with_us_text_field() {
        $value = get_option('vambe_webchat_chat_with_us_text', '');
        ?>
        <input type="text" 
               name="vambe_webchat_chat_with_us_text" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="<?php esc_attr_e('Chat with us', 'vambe-for-woocommerce'); ?>">
        <p class="description">
            <?php esc_html_e('Text to display next to the chat button. Example: "Chat with us"', 'vambe-for-woocommerce'); ?>
        </p>
        <?php
    }
}
