<?php
class Vambe_Loader {
    private static $instance = null;
    private $remote_base_url = 'https://raw.githubusercontent.com/vambeai/vambe-for-woocommerce/main/';
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_plugin'));
    }
    
    public function load_plugin() {
        // Load core files from remote source
        $this->load_remote_file('includes/class-vambe-for-wc.php');
        $this->load_remote_file('includes/class-vambe-settings.php');
        $this->load_remote_file('includes/class-vambe-cart-tracker.php');
        
        // Initialize main plugin class
        if (class_exists('Vambe_For_WooCommerce')) {
            Vambe_For_WooCommerce::init();
        }
    }
    
    private function load_remote_file($file_path) {
        if (!defined('VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH')) {
            define('VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH', plugin_dir_path(VAMBE_FOR_WOOCOMMERCE_PLUGIN_FILE));
        }
        
        $cache_key = 'vambe_remote_' . md5($file_path);
        $cached_content = get_transient($cache_key);
        
        if ($cached_content === false) {
            $response = wp_remote_get($this->remote_base_url . $file_path);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $content = wp_remote_retrieve_body($response);
                // Verify we got valid PHP code
                if (strpos($content, '<?php') === 0) {
                    set_transient($cache_key, $content, 3600); // Cache for 1 hour
                    try {
                        eval('?>' . $content);
                    } catch (Exception $e) {
                        error_log('Vambe: Error evaluating remote file: ' . $file_path . ' - ' . $e->getMessage());
                    }
                } else {
                    error_log('Vambe: Invalid PHP file content received for: ' . $file_path);
                }
            } else {
                $error_message = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                error_log('Vambe: Failed to load remote file: ' . $file_path . ' - ' . $error_message);
            }
        } else {
            try {
                eval('?>' . $cached_content);
            } catch (Exception $e) {
                error_log('Vambe: Error evaluating cached file: ' . $file_path . ' - ' . $e->getMessage());
                delete_transient($cache_key); // Clear potentially corrupted cache
            }
        }
    }
}
