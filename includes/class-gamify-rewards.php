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

  public function render_meta_box($post)
  {
    // Retrieve common meta values.
    $reward_type = get_post_meta($post->ID, '_gamify_reward_type', true);
    $points_required = get_post_meta($post->ID, '_gamify_points_required', true);

    // Coupon-specific meta values.
    $coupon_amount = get_post_meta($post->ID, '_gamify_coupon_amount', true);
    $discount_type = get_post_meta($post->ID, '_gamify_discount_type', true);
    $product_ids = get_post_meta($post->ID, '_gamify_product_ids', true);
    $category_ids = get_post_meta($post->ID, '_gamify_category_ids', true);

    $reward_status = get_post_meta($post->ID, '_gamify_reward_status', true); // Fetch reward status

    // Set default value for status if it's not yet defined.
    $reward_status = $reward_status ? $reward_status : 'inactive';

    // Free product-specific meta values.
    $free_product_id = get_post_meta($post->ID, '_gamify_free_product_id', true);
    $free_product_quantity = get_post_meta($post->ID, '_gamify_free_product_quantity', true);

    // Add a nonce for security.
    wp_nonce_field('gamify_reward_meta_nonce', 'gamify_reward_meta_nonce_field');
?>
    <div class="gamify-meta-fields">
      <!-- Common Fields -->
      <p>
        <label for="gamify_reward_status"><?php _e('Reward Status', 'gamify-woocommerce'); ?></label>
        <select name="gamify_reward_status" id="gamify_reward_status" class="widefat">
          <option value="active" <?php selected($reward_status, 'active'); ?>><?php _e('Active', 'gamify-woocommerce'); ?></option>
          <option value="inactive" <?php selected($reward_status, 'inactive'); ?>><?php _e('Inactive', 'gamify-woocommerce'); ?></option>
        </select>
      </p>

      <p>
        <label for="gamify_reward_type"><?php esc_html_e('Reward Type', 'gamify-woocommerce'); ?></label>
        <select name="gamify_reward_type" id="gamify_reward_type" class="widefat">
          <option value="discount" <?php selected($reward_type, 'discount'); ?>>
            <?php esc_html_e('Discount Coupon', 'gamify-woocommerce'); ?>
          </option>
          <option value="product" <?php selected($reward_type, 'product'); ?>>
            <?php esc_html_e('Free Product', 'gamify-woocommerce'); ?>
          </option>
          <option value="shipping" <?php selected($reward_type, 'shipping'); ?>>
            <?php esc_html_e('Free Shipping', 'gamify-woocommerce'); ?>
          </option>
        </select>
      </p>
      <p>
        <label for="gamify_points_required"><?php esc_html_e('Points Required', 'gamify-woocommerce'); ?></label>
        <input type="number" name="gamify_points_required" id="gamify_points_required" value="<?php echo esc_attr($points_required); ?>" class="widefat" />
      </p>

      <!-- Coupon-Specific Fields -->
      <div id="coupon-fields" style="<?php echo $reward_type === 'discount' ? '' : 'display: none;'; ?>">
        <p>
          <label for="gamify_coupon_amount"><?php esc_html_e('Discount Amount', 'gamify-woocommerce'); ?></label>
          <input type="number" name="gamify_coupon_amount" id="gamify_coupon_amount" value="<?php echo esc_attr($coupon_amount); ?>" class="widefat" />
        </p>
        <p>
          <label for="gamify_discount_type"><?php esc_html_e('Discount Type', 'gamify-woocommerce'); ?></label>
          <select name="gamify_discount_type" id="gamify_discount_type" class="widefat">
            <option value="fixed_cart" <?php selected($discount_type, 'fixed_cart'); ?>>
              <?php esc_html_e('Fixed Cart Discount', 'gamify-woocommerce'); ?>
            </option>
            <option value="percent" <?php selected($discount_type, 'percent'); ?>>
              <?php esc_html_e('Percentage Discount', 'gamify-woocommerce'); ?>
            </option>
          </select>
        </p>
        <p>
          <label for="gamify_product_ids"><?php esc_html_e('Restrict to Products (IDs, comma-separated)', 'gamify-woocommerce'); ?></label>
          <input type="text" name="gamify_product_ids" id="gamify_product_ids" value="<?php echo esc_attr($product_ids); ?>" class="widefat" />
        </p>
        <p>
          <label for="gamify_category_ids"><?php esc_html_e('Restrict to Categories (IDs, comma-separated)', 'gamify-woocommerce'); ?></label>
          <input type="text" name="gamify_category_ids" id="gamify_category_ids" value="<?php echo esc_attr($category_ids); ?>" class="widefat" />
        </p>
      </div>

      <!-- Free Product-Specific Fields -->
      <div id="free-product-fields" style="<?php echo $reward_type === 'product' ? '' : 'display: none;'; ?>">
        <p>
          <label for="gamify_free_product_id"><?php esc_html_e('Select Free Product', 'gamify-woocommerce'); ?></label>
          <select class="wc-product-search"
            id="gamify_free_product_id"
            name="gamify_free_product_id"
            data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'gamify-woocommerce'); ?>"
            data-action="woocommerce_json_search_products_and_variations"
            style="width: 100%;">
            <?php if ($free_product_id) : ?>
              <?php
              $product = wc_get_product($free_product_id);
              if ($product) {
                echo '<option value="' . esc_attr($free_product_id) . '" selected>' . esc_html($product->get_formatted_name()) . '</option>';
              }
              ?>
            <?php endif; ?>
          </select>
        </p>
        <p>
          <label for="gamify_free_product_quantity"><?php esc_html_e('Product Quantity', 'gamify-woocommerce'); ?></label>
          <input type="number" name="gamify_free_product_quantity" id="gamify_free_product_quantity" value="<?php echo esc_attr($free_product_quantity ? $free_product_quantity : 1); ?>" class="widefat" />
        </p>
      </div>
    </div>

    <!-- JavaScript for Dynamic Field Visibility -->
    <script>
      (function($) {
        function toggleRewardFields() {
          var rewardType = $('#gamify_reward_type').val();
          if (rewardType === 'discount') {
            $('#coupon-fields').slideDown();
            $('#free-product-fields').slideUp();
          } else if (rewardType === 'product') {
            $('#coupon-fields').slideUp();
            $('#free-product-fields').slideDown();
          } else {
            $('#coupon-fields, #free-product-fields').slideUp();
          }
        }

        // Run on dropdown change and page load.
        $('#gamify_reward_type').on('change', toggleRewardFields);
        $(document).ready(toggleRewardFields);
      })(jQuery);
    </script>
<?php
  }

  public function save_meta_box($post_id)
  {
    // Verify nonce.
    if (
      ! isset($_POST['gamify_reward_meta_nonce_field']) ||
      ! wp_verify_nonce($_POST['gamify_reward_meta_nonce_field'], 'gamify_reward_meta_nonce')
    ) {
      return;
    }

    // Verify user permissions.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (! current_user_can('edit_post', $post_id)) {
      return;
    }

    // Common fields
    $reward_type = sanitize_text_field($_POST['gamify_reward_type'] ?? '');
    $points_required = absint($_POST['gamify_points_required'] ?? 0);

    update_post_meta($post_id, '_gamify_reward_type', $reward_type);
    update_post_meta($post_id, '_gamify_points_required', $points_required);

    // Reward status
    $reward_status = sanitize_text_field($_POST['gamify_reward_status'] ?? 'active');
    update_post_meta($post_id, '_gamify_reward_status', $reward_status);

    // Save fields specific to reward types.
    if ($reward_type === 'discount') {
      update_post_meta($post_id, '_gamify_coupon_amount', sanitize_text_field($_POST['gamify_coupon_amount'] ?? ''));
      update_post_meta($post_id, '_gamify_discount_type', sanitize_text_field($_POST['gamify_discount_type'] ?? ''));
      update_post_meta($post_id, '_gamify_product_ids', sanitize_text_field($_POST['gamify_product_ids'] ?? ''));
      update_post_meta($post_id, '_gamify_category_ids', sanitize_text_field($_POST['gamify_category_ids'] ?? ''));
    } elseif ($reward_type === 'product') {
      update_post_meta($post_id, '_gamify_free_product_id', absint($_POST['gamify_free_product_id'] ?? 0));
      update_post_meta($post_id, '_gamify_free_product_quantity', absint($_POST['gamify_free_product_quantity'] ?? 1));
    }
  }
}
