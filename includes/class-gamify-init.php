<?php
/**
 * Main Initialization Class for Gamify WooCommerce
 */

namespace Gamify;

use Gamify\Traits\Singleton;

class Gamify_Init {
    use Singleton;

    /**
     * Constructor for setting up the plugin.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Load required files and dependencies.
     *
     * @return void
     */
    private function load_dependencies() {
        require_once GAMIFY_WC_PLUGIN_DIR . 'includes/class-gamify-points.php';
        require_once GAMIFY_WC_PLUGIN_DIR . 'includes/class-gamify-dashboard.php';
        require_once GAMIFY_WC_PLUGIN_DIR . 'includes/class-gamify-rewards.php';
        require_once GAMIFY_WC_PLUGIN_DIR . 'includes/class-gamify-ajax.php';
    
        Gamify_Points::instance();
        Gamify_Dashboard::instance();
        Gamify_Rewards::instance();
        Gamify_Ajax::init();
    }
  

    /**
     * Register hooks and filters.
     *
     * @return void
     */
    private function register_hooks() {
        // Example: Add actions or filters for WooCommerce integration in the future.
        add_action( 'init', [ $this, 'register_custom_post_types' ] );
    }

    /**
     * Register custom post types (e.g., for badges).
     *
     * @return void
     */
    public function register_custom_post_types() {
        // Example: Register a custom post type for badges.
    }
    
}
