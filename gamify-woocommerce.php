<?php
/**
 * Plugin Name: Gamify WooCommerce
 * Plugin URI:  https://yourwebsite.com
 * Description: Add gamification features like points, rewards, and badges to your WooCommerce store to boost customer engagement.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: gamify-woocommerce
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'GAMIFY_WC_VERSION', '1.0.0' );
define( 'GAMIFY_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAMIFY_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader function for loading classes and traits dynamically.
 *
 * @param string $class Class or trait name to load.
 * @return void
 */
function gamify_wc_autoloader( $class ) {
  // Only autoload classes from the Gamify namespace.
  if ( 0 !== strpos( $class, 'Gamify' ) ) {
      return;
  }

  // Remove the namespace prefix.
  $class_name = str_replace( 'Gamify\\', '', $class );

  // Determine if the class is a trait or a regular class.
  $subdirectory = strpos( $class_name, 'Traits\\' ) === 0 ? 'traits/' : '';

  // Normalize the file name: Convert to lowercase and replace underscores or backslashes with hyphens.
  $file_name = strtolower(
      str_replace( [ '_', '\\' ], '-', str_replace( 'Traits\\', '', $class_name ) )
  );

  // Build the file path.
  $file = GAMIFY_WC_PLUGIN_DIR . 'includes/' . $subdirectory . 'class-' . $file_name . '.php';

  // Adjust the naming convention for traits.
  if ( $subdirectory === 'traits/' ) {
      $file = GAMIFY_WC_PLUGIN_DIR . 'includes/' . $subdirectory . 'trait-' . $file_name . '.php';
  }

  // Include the file if it exists.
  if ( file_exists( $file ) ) {
      require_once $file;
  }
}

spl_autoload_register( 'gamify_wc_autoloader' );

// Initialize the plugin.
add_action( 'plugins_loaded', function() {
  \Gamify\Gamify_Init::instance();
} );

/**
 * Create the database table for storing points transactions during plugin activation.
 */
function gamify_wc_create_points_table() {
  global $wpdb;

  $table_name = $wpdb->prefix . 'gamification_points';
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table_name (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT(20) UNSIGNED NOT NULL,
      action VARCHAR(255) NOT NULL,
      points INT(11) NOT NULL,
      log_message TEXT NOT NULL,
      date_earned DATETIME NOT NULL,
      order_id BIGINT(20) UNSIGNED DEFAULT NULL,
      PRIMARY KEY  (id),
      KEY user_id (user_id),
      KEY date_earned (date_earned)
  ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta( $sql );
}

// Hook to run the table creation on plugin activation.
register_activation_hook( __FILE__, 'gamify_wc_create_points_table' );

