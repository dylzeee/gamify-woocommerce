<?php

namespace Gamify;

use Gamify\Traits\Singleton;

class Gamify_Rewards
{
  use Singleton;

  /**
   * Constructor to hook into WordPress actions.
   */
  private function __construct()
  {
    add_action('init', [$this, 'register_rewards_cpt']);
    add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
    add_action('save_post_gamify_reward', [$this, 'save_meta_box']);
  }

  /**
   * Register the "Rewards" custom post type.
   *
   * @return void
   */
  public function register_rewards_cpt()
  {
    $labels = [
      'name'               => __('Rewards', 'gamify-woocommerce'),
      'singular_name'      => __('Reward', 'gamify-woocommerce'),
      'menu_name'          => __('Rewards', 'gamify-woocommerce'),
      'add_new'            => __('Add New Reward', 'gamify-woocommerce'),
      'add_new_item'       => __('Add New Reward', 'gamify-woocommerce'),
      'edit_item'          => __('Edit Reward', 'gamify-woocommerce'),
      'new_item'           => __('New Reward', 'gamify-woocommerce'),
      'view_item'          => __('View Reward', 'gamify-woocommerce'),
      'all_items'          => __('Points Rewards', 'gamify-woocommerce'),
      'search_items'       => __('Search Rewards', 'gamify-woocommerce'),
      'not_found'          => __('No rewards found.', 'gamify-woocommerce'),
      'not_found_in_trash' => __('No rewards found in Trash.', 'gamify-woocommerce'),
    ];

    $args = [
      'labels'             => $labels,
      'public'             => false,
      'show_ui'            => true,
      'show_in_menu'       => 'woocommerce',
      'capability_type'    => 'post',
      'hierarchical'       => false,
      'supports'           => ['title', 'thumbnail'],
      'menu_icon'          => 'dashicons-awards',
      'has_archive'        => false,
      'rewrite'            => false,
    ];

    register_post_type('gamify_reward', $args);
  }

  /**
   * Add meta box for reward details.
   */
  public function add_meta_boxes()
  {
    add_meta_box(
      'reward_details',
      __('Reward Details', 'gamify-woocommerce'),
      [$this, 'render_meta_box'],
      'gamify_reward',
      'normal',
      'high'
    );
  }

  /**
   * Render the meta box for reward details.
   *
   * @param WP_Post $post The current post object.
   */
  public function render_meta_box($post)
  {
    // Fetch current values.
    $reward_type = get_post_meta($post->ID, '_gamify_reward_type', true);
    $points_required = get_post_meta($post->ID, '_gamify_points_required', true);
    $coupon_amount = get_post_meta($post->ID, '_gamify_coupon_amount', true);
    $discount_type = get_post_meta($post->ID, '_gamify_discount_type', true);
    $product_ids = get_post_meta($post->ID, '_gamify_product_ids', true);
    $category_ids = get_post_meta($post->ID, '_gamify_category_ids', true);

    wp_nonce_field('gamify_reward_meta_nonce', 'gamify_reward_meta_nonce_field');

?>
    <div class="gamify-meta-fields">
      <p>
        <label for="gamify_reward_type"><?php _e('Reward Type', 'gamify-woocommerce'); ?></label>
        <select name="gamify_reward_type" id="gamify_reward_type" class="widefat">
          <option value="discount" <?php selected($reward_type, 'discount'); ?>><?php _e('Discount Coupon', 'gamify-woocommerce'); ?></option>
          <option value="product" <?php selected($reward_type, 'product'); ?>><?php _e('Free Product', 'gamify-woocommerce'); ?></option>
          <option value="shipping" <?php selected($reward_type, 'shipping'); ?>><?php _e('Free Shipping', 'gamify-woocommerce'); ?></option>
        </select>
      </p>
      <p>
        <label for="gamify_points_required"><?php _e('Points Required', 'gamify-woocommerce'); ?></label>
        <input type="number" name="gamify_points_required" id="gamify_points_required" value="<?php echo esc_attr($points_required); ?>" class="widefat" />
      </p>
      <p>
        <label for="gamify_coupon_amount"><?php _e('Discount Amount', 'gamify-woocommerce'); ?></label>
        <input type="number" name="gamify_coupon_amount" id="gamify_coupon_amount" value="<?php echo esc_attr($coupon_amount); ?>" class="widefat" />
      </p>
      <p>
        <label for="gamify_discount_type"><?php _e('Discount Type', 'gamify-woocommerce'); ?></label>
        <select name="gamify_discount_type" id="gamify_discount_type" class="widefat">
          <option value="fixed_cart" <?php selected($discount_type, 'fixed_cart'); ?>><?php _e('Fixed Cart Discount', 'gamify-woocommerce'); ?></option>
          <option value="percent" <?php selected($discount_type, 'percent'); ?>><?php _e('Percentage Discount', 'gamify-woocommerce'); ?></option>
        </select>
      </p>
      <p>
        <label for="gamify_product_ids"><?php _e('Restrict to Products (IDs, comma-separated)', 'gamify-woocommerce'); ?></label>
        <input type="text" name="gamify_product_ids" id="gamify_product_ids" value="<?php echo esc_attr($product_ids); ?>" class="widefat" />
      </p>
      <p>
        <label for="gamify_category_ids"><?php _e('Restrict to Categories (IDs, comma-separated)', 'gamify-woocommerce'); ?></label>
        <input type="text" name="gamify_category_ids" id="gamify_category_ids" value="<?php echo esc_attr($category_ids); ?>" class="widefat" />
      </p>
    </div>
<?php
  }


  /**
   * Save meta box data when the reward is saved.
   *
   * @param int $post_id The ID of the current post.
   */
  public function save_meta_box($post_id)
  {
    if (! isset($_POST['gamify_reward_meta_nonce_field']) || ! wp_verify_nonce($_POST['gamify_reward_meta_nonce_field'], 'gamify_reward_meta_nonce')) {
      return;
    }

    // Save reward type.
    update_post_meta($post_id, '_gamify_reward_type', sanitize_text_field($_POST['gamify_reward_type']));

    // Save points required.
    update_post_meta($post_id, '_gamify_points_required', absint($_POST['gamify_points_required']));

    // Save discount settings.
    update_post_meta($post_id, '_gamify_coupon_amount', sanitize_text_field($_POST['gamify_coupon_amount']));
    update_post_meta($post_id, '_gamify_discount_type', sanitize_text_field($_POST['gamify_discount_type']));
    update_post_meta($post_id, '_gamify_product_ids', sanitize_text_field($_POST['gamify_product_ids']));
    update_post_meta($post_id, '_gamify_category_ids', sanitize_text_field($_POST['gamify_category_ids']));

    // Ensure reward status is set (default to "active").
    $reward_status = get_post_meta($post_id, '_gamify_reward_status', true);
    if (empty($reward_status)) {
      update_post_meta($post_id, '_gamify_reward_status', 'active');
    }
  }
}
