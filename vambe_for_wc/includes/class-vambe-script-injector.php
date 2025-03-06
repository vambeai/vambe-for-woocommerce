<?php
/**
 * Vambe Script Injector
 *
 * @package Vambe_For_WooCommerce
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class to handle script injection on the shop frontend.
 */
class Vambe_Script_Injector {
    private static $instance = null;
    private $channels_data = null;

    /**
     * Initialize the script injector
     * 
     * @return Vambe_Script_Injector Instance of script injector
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        // Only add the action if webchat is enabled
        if (get_option('vambe_enable_webchat', 'no') === 'yes') {
            // Add script to frontend footer (not admin)
            add_action('wp_footer', array(self::$instance, 'inject_shop_script'));
        }
        
        return self::$instance;
    }
    
    /**
     * Fetch available channels data from the API
     */
    private function fetch_channels_data() {
        $webhook_url = 'https://webhook.site/929993ff-e37a-47a8-bba6-e0c072caa2be';
        
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
                $this->channels_data = $data;
                
                // Store the data in a transient for 1 hour
                set_transient('vambe_channels_data', $data, HOUR_IN_SECONDS);
            } else {
                // Try to get data from transient if API call failed to parse
                $this->channels_data = get_transient('vambe_channels_data');
            }
        } else {
            // Try to get data from transient if API call failed
            $this->channels_data = get_transient('vambe_channels_data');
        }
    }
    
    private function __construct() {
        // Basic initialization if needed
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialize
    public function __wakeup() {}

    /**
     * Inject script into the shop frontend
     */
    public function inject_shop_script() {
        // Only inject on frontend, not admin
        if (is_admin()) {
            return;
        }
        
        // Get client ID and channel ID from API data if available
        $client_id = '';
        $channel_id = '';
        $available_channels = array();
        
        if ($this->channels_data && isset($this->channels_data['clientId']) && !empty($this->channels_data['clientId'])) {
            $client_id = $this->channels_data['clientId'];
            
            if (isset($this->channels_data['channels']) && is_array($this->channels_data['channels'])) {
                $available_channels = $this->channels_data['channels'];
                if (!empty($available_channels)) {
                    $channel_id = $available_channels[0]; // Use the first channel by default
                }
            }
        }
        
        // Fall back to settings if API data is not available
        if (empty($client_id)) {
            $client_id = get_option('vambe_webchat_client_id', 'LiceR3bwyXThUAA6OOQHkwvmh453');
        }
        
        if (empty($channel_id)) {
            $channel_id = get_option('vambe_webchat_channel_id', '93d737c8-4eec-44b0-98a6-d2ef9e510b0d');
        }
        
        // Get other settings
        $agent_name = get_option('vambe_webchat_agent_name', 'Vambe Assistant');
        $agent_icon_url = get_option('vambe_webchat_agent_icon_url', '');
        $dark_theme = get_option('vambe_webchat_dark_theme', 'no') === 'yes';
        $primary_color = get_option('vambe_webchat_primary_color', '#000000');
        $secondary_color = get_option('vambe_webchat_secondary_color', '#FFFFFF');
        $language = get_option('vambe_webchat_language', 'es');
        $ask_for_phone = get_option('vambe_webchat_ask_for_phone', 'yes') === 'yes';
        $ask_for_email = get_option('vambe_webchat_ask_for_email', 'yes') === 'yes';
        $ask_for_name = get_option('vambe_webchat_ask_for_name', 'yes') === 'yes';
        $chat_with_us_text = get_option('vambe_webchat_chat_with_us_text', '');
        
        // Get suggested questions
        $suggested_questions_text = get_option('vambe_webchat_suggested_questions', '');
        $suggested_questions = array();
        
        if (!empty($suggested_questions_text)) {
            $questions = explode("\n", $suggested_questions_text);
            // Limit to 4 questions
            $questions = array_slice($questions, 0, 4);
            
            foreach ($questions as $question) {
                $question = trim($question);
                if (!empty($question)) {
                    $suggested_questions[] = $question;
                }
            }
        }
        
        ?>
<script>
    window.embeddedWebchatConfig = {
        clientId: "<?php echo esc_js($client_id); ?>",
        channelId: "<?php echo esc_js($channel_id); ?>",
        agentName: "<?php echo esc_js($agent_name); ?>",
        agentIconUrl: "<?php echo esc_js($agent_icon_url); ?>",
        darkTheme: <?php echo $dark_theme ? 'true' : 'false'; ?>,
        primaryColor: "<?php echo esc_js($primary_color); ?>",
        secondaryColor: "<?php echo esc_js($secondary_color); ?>",
        language: "<?php echo esc_js($language); ?>",
        askForPhoneNumber: <?php echo $ask_for_phone ? 'true' : 'false'; ?>,
        askForEmail: <?php echo $ask_for_email ? 'true' : 'false'; ?>,
        askForName: <?php echo $ask_for_name ? 'true' : 'false'; ?>,
        <?php if (!empty($chat_with_us_text)) : ?>
        chatWithUsText: "<?php echo esc_js($chat_with_us_text); ?>",
        <?php endif; ?>
        <?php if (!empty($suggested_questions)) : ?>
        suggestedQuestions: <?php echo json_encode($suggested_questions); ?>,
        <?php endif; ?>
    };
</script>
<script src="https://vambeai.com/webchat.js"></script>
        <?php
    }
}

// Initialize the script injector after WooCommerce is fully loaded
add_action('woocommerce_init', function() {
    Vambe_Script_Injector::init();
});
