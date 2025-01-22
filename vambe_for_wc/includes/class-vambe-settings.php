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
    }

    public function register_settings() {
        register_setting('vambe_settings', 'vambe_cart_timeout');
        register_setting('vambe_settings', 'vambe_api_key');

        add_settings_section(
            'vambe_settings_section',
            '',
            null,
            'vambe-settings'
        );

        add_settings_field(
            'vambe_cart_timeout',
            __('Abandoned Cart Timeout (hours)', 'vambe-for-woocommerce'),
            array($this, 'render_timeout_field'),
            'vambe-settings',
            'vambe_settings_section'
        );

        add_settings_field(
            'vambe_api_key',
            __('API Key', 'vambe-for-woocommerce'),
            array($this, 'render_api_key_field'),
            'vambe-settings',
            'vambe_settings_section'
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
            <form method="post" action="options.php">
                <?php
                settings_fields('vambe_settings');
                do_settings_sections('vambe-settings');
                submit_button();
                ?>
            </form>
        </div>
        <style>
            .vambe-settings-wrap {
                max-width: 800px;
                margin: 20px auto;
                padding: 20px;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
                font-size: 23px;
                font-weight: 400;
                line-height: 1.3;
            }
            .form-table th {
                width: 250px;
            }
            .vambe-settings-wrap .button-primary {
                background: #2054F8;
            }
            .vambe-settings-wrap .button-primary:hover,
            .vambe-settings-wrap .button-primary:focus {
                background: #1844D6;
            }
        </style>
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

    public function render_api_key_field() {
        $value = get_option('vambe_api_key', '');
        ?>
        <input type="text" 
               name="vambe_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }
} 