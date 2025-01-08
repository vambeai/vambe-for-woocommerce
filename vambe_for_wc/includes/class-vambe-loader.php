<?php
class Vambe_Loader {
    private static $instance = null;
    private $remote_base_url = 'https://raw.githubusercontent.com/vambeai/vambe-for-woocommerce/main/';
    private $required_files = array(
        'includes/class-vambe-settings.php',
        'includes/class-vambe-for-wc.php',
        'includes/class-vambe-cart-tracker.php'
    );
    private $cache_time = 3600; // 1 hour cache
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_plugin'));
        add_action('init', array($this, 'maybe_check_updates'));
    }
    
    private function get_cache_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/vambe-temp';
    }
    
    public static function get_temp_dir() {
        return self::init()->get_cache_path();
    }
    
    public function maybe_check_updates() {
        $last_check = get_transient('vambe_last_update_check');
        if (!$last_check) {
            $this->load_plugin(true); // Force reload
            set_transient('vambe_last_update_check', time(), $this->cache_time);
        }
    }
    
    public function load_plugin($force_reload = false) {
        $temp_dir = $this->get_cache_path();
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        foreach ($this->required_files as $file) {
            $remote_url = $this->remote_base_url . $file;
            $temp_file = $temp_dir . '/' . basename($file);
            
            // Check if we need to download the file
            $download_file = $force_reload || !file_exists($temp_file);
            
            if ($download_file) {
                $response = wp_remote_get($remote_url);
                if (is_wp_error($response)) {
                    error_log('Vambe: Failed to download ' . $file);
                    continue;
                }
                
                $content = wp_remote_retrieve_body($response);
                if (empty($content)) {
                    error_log('Vambe: Empty content for ' . $file);
                    continue;
                }
                
                // Save to temporary file
                file_put_contents($temp_file, $content);
            }
            
            // Include the file
            require_once $temp_file;
        }

        // Initialize components
        if (class_exists('Vambe_Settings')) {
            Vambe_Settings::get_instance();
        }
        
        return true;
    }
    
    // Cleanup method - call this on plugin deactivation
    public static function cleanup() {
        $temp_dir = self::init()->get_cache_path();
        if (file_exists($temp_dir)) {
            array_map('unlink', glob("$temp_dir/*.*"));
            rmdir($temp_dir);
        }
        delete_transient('vambe_last_update_check');
    }
}

// Register cleanup on deactivation
register_deactivation_hook(VAMBE_FOR_WOOCOMMERCE_PLUGIN_FILE, array('Vambe_Loader', 'cleanup'));
