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
        // Simply load the main plugin
        $this->load_main_plugin();
    }
    
    private function load_main_plugin() {
        $remote_url = $this->remote_base_url . 'vambe-for-wc.php';
        
        $response = wp_remote_get($remote_url, array(
            'timeout' => 15,
            'sslverify' => true
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            
            // Create anonymous function to execute the code
            $execute = function() use ($content) {
                return eval('?>' . $content);
            };
            $execute();
            
            return true;
        }
        
        error_log('Vambe: Failed to load main plugin file from remote source');
        return false;
    }
}
