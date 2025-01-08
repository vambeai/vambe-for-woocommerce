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
        // Try remote files first, fall back to local if needed
        $core_files = array(
            'includes/class-vambe-for-wc.php',
            'includes/class-vambe-settings.php',
            'includes/class-vambe-cart-tracker.php'
        );

        foreach ($core_files as $file) {
            $this->load_file($file);
        }
        
        // Initialize main plugin class
        if (class_exists('Vambe_For_WooCommerce')) {
            Vambe_For_WooCommerce::init();
        }
    }
    
    private function load_file($file_path) {
        // Try remote file first
        if ($this->load_remote_file($file_path)) {
            return true;
        }
        
        // Fall back to local file if remote fails
        return $this->load_local_file($file_path);
    }
    
    private function load_remote_file($file_path) {
        $cache_key = 'vambe_remote_' . md5($file_path);
        $cached_content = get_transient($cache_key);
        
        if ($cached_content !== false) {
            // Use cached version if available
            return $this->evaluate_php_content($cached_content, $file_path);
        }
        
        $response = wp_remote_get($this->remote_base_url . $file_path, array(
            'timeout' => 15,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3.raw'
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            
            // Verify we got valid PHP code
            if (strpos($content, '<?php') === 0) {
                // Cache the content
                set_transient($cache_key, $content, HOUR_IN_SECONDS); // Cache for 1 hour
                return $this->evaluate_php_content($content, $file_path);
            } else {
                error_log('Vambe: Invalid PHP file content received for: ' . $file_path);
            }
        }
        
        return false;
    }
    
    private function load_local_file($file_path) {
        $full_path = VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH . $file_path;
        
        if (file_exists($full_path)) {
            require_once $full_path;
            return true;
        }
        
        error_log('Vambe: Failed to load local file: ' . $full_path);
        return false;
    }
    
    private function evaluate_php_content($content, $file_path) {
        try {
            // Remove PHP opening tag if present
            $content = preg_replace('/^<\?php\s+/', '', $content);
            
            // Create anonymous function instead of using create_function
            $execute = function() use ($content) {
                return eval($content);
            };
            $execute();
            
            return true;
        } catch (Exception $e) {
            error_log('Vambe: Error evaluating remote file: ' . $file_path . ' - ' . $e->getMessage());
            return false;
        }
    }
}
