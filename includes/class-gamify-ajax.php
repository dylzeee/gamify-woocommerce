<?php

namespace Gamify;

use WP_Query;

class Gamify_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_load_rewards', [__CLASS__, 'load_rewards']);
        add_action('wp_ajax_nopriv_load_rewards', [__CLASS__, 'load_rewards']);
        add_action('wp_ajax_redeem_reward', [__CLASS__, 'redeem_reward']);
        add_action('wp_ajax_nopriv_redeem_reward', [__CLASS__, 'redeem_reward']);
        add_action('wp_ajax_fetch_transactions', [__CLASS__, 'fetch_transactions']);
        add_action('wp_ajax_nopriv_fetch_transactions', [__CLASS__, 'fetch_transactions']);
    }

    /**
     * AJAX callback to load rewards for a specific page.
     */
    public static function load_rewards()
    {
        check_ajax_referer('gamify_rewards_nonce', 'security');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $user_points = isset($_POST['user_points']) ? absint($_POST['user_points']) : 0;

        $rewards = new WP_Query([
            'post_type'      => 'gamify_reward',
            'post_status'    => 'publish',
            'meta_key'       => '_gamify_reward_status',
            'meta_value'     => 'active',
            'posts_per_page' => 6,
            'paged'          => $page,
        ]);

        if ($rewards->have_posts()) {
            // Wrap cards in a grid container.
            echo '<div class="gamify-rewards-grid">';

            while ($rewards->have_posts()) {
                $rewards->the_post();
                $reward_id = get_the_ID();
                $reward_name = get_the_title();
                $points_required = get_post_meta($reward_id, '_gamify_points_required', true);
                $reward_image = get_the_post_thumbnail_url($reward_id, 'medium');

                echo '<div class="gamify-reward-card">';
                if ($reward_image) {
                    echo '<img src="' . esc_url($reward_image) . '" alt="' . esc_attr($reward_name) . '" class="gamify-reward-image">';
                }
                echo '<h4>' . esc_html($reward_name) . '</h4>';
                echo '<p>' . sprintf(__('Requires %d points.', 'gamify-woocommerce'), intval($points_required)) . '</p>';
                if ($user_points >= $points_required) {
                    echo '<button class="button gamify-redeem-button" data-reward-id="' . esc_attr($reward_id) . '">' . __('Redeem Now', 'gamify-woocommerce') . '</button>';
                } else {
                    echo '<p class="gamify-insufficient-points">' . __('Not enough points to redeem this reward.', 'gamify-woocommerce') . '</p>';
                }
                echo '</div>';
            }

            echo '</div>'; // Close the grid container.

            // Add pagination controls.
            if ($rewards->max_num_pages > 1) {
                echo '<div class="gamify-pagination-controls">';
                for ($i = 1; $i <= $rewards->max_num_pages; $i++) {
                    $active_class = $i === $page ? ' active' : '';
                    echo '<button class="gamify-pagination-button' . $active_class . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</button>';
                }
                echo '</div>';
            }
        } else {
            echo '<p>' . __('No rewards available at the moment.', 'gamify-woocommerce') . '</p>';
        }

        wp_die(); // End AJAX execution.
    }

    public static function redeem_reward()
    {
        check_ajax_referer('gamify_rewards_nonce', 'security');

        $reward_id = isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0;
        $user_id = get_current_user_id();

        if (! $reward_id || ! $user_id) {
            wp_send_json_error(['message' => __('Invalid reward or user.', 'gamify-woocommerce')]);
        }

        // Fetch reward details.
        $points_required = get_post_meta($reward_id, '_gamify_points_required', true);
        $reward_type = get_post_meta($reward_id, '_gamify_reward_type', true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'gamification_points';

        // Get the user's total points.
        $user_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        if ($user_points < $points_required) {
            wp_send_json_error(['message' => __('You do not have enough points to redeem this reward.', 'gamify-woocommerce')]);
        }

        // Deduct points and log the transaction.
        $wpdb->insert(
            $table_name,
            [
                'user_id'      => $user_id,
                'action'       => 'redeem',
                'points'       => -$points_required, // Deduct points.
                'log_message'  => sprintf(__('Redeemed reward: %s', 'gamify-woocommerce'), get_the_title($reward_id)),
                'date_earned'  => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );

        // Handle reward type (e.g., generate WooCommerce coupon).
        if ('discount' === $reward_type) {
            $coupon_code = self::generate_coupon($user_id, $reward_id);

            if ($coupon_code) {
                wp_send_json_success([
                    'message' => sprintf(__('Reward redeemed! Use coupon code: %s', 'gamify-woocommerce'), $coupon_code),
                    'redirect_url' => wc_get_account_endpoint_url('my-rewards'),
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to generate coupon. Please try again.', 'gamify-woocommerce')]);
            }
        }

        // Add logic for other reward types if necessary...

        wp_send_json_success([
            'message' => __('Reward redeemed successfully.', 'gamify-woocommerce'),
            'redirect_url' => wc_get_account_endpoint_url('my-rewards'),
        ]);
    }


    private static function generate_coupon($user_id, $reward_id)
    {
        $coupon_code = 'reward-' . wp_generate_password(8, false);
        $amount = get_post_meta($reward_id, '_gamify_coupon_amount', true);
        $discount_type = get_post_meta($reward_id, '_gamify_discount_type', true);
        $product_ids = get_post_meta($reward_id, '_gamify_product_ids', true);
        $category_ids = get_post_meta($reward_id, '_gamify_category_ids', true);

        $coupon = new \WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type($discount_type ? $discount_type : 'fixed_cart');
        $coupon->set_amount($amount ? $amount : 10); // Default amount.

        // Restrict to user's email.
        $user = get_user_by('id', $user_id);
        if ($user) {
            $coupon->set_email_restrictions([$user->user_email]);
        }

        // Restrict to specific products or categories.
        if ($product_ids) {
            $coupon->set_product_ids(array_map('intval', explode(',', $product_ids)));
        }
        if ($category_ids) {
            $coupon->set_product_categories(array_map('intval', explode(',', $category_ids)));
        }

        // Limit usage to one time.
        $coupon->set_usage_limit(1);

        // Save the coupon and retrieve its ID.
        $coupon_id = $coupon->save();

        // Attach coupon ID to the user.
        $user_coupons = get_user_meta($user_id, '_gamify_redeemed_coupons', true);
        if (! is_array($user_coupons)) {
            $user_coupons = [];
        }
        $user_coupons[] = $coupon_id;

        // Save updated coupons meta for the user.
        update_user_meta($user_id, '_gamify_redeemed_coupons', $user_coupons);

        return $coupon_code;
    }





    /**
     * Handle AJAX request to fetch paginated transaction history.
     */
    public static function fetch_transactions()
    {
        check_ajax_referer('gamify_transaction_nonce', 'security');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        if (! $user_id || ! $page) {
            wp_send_json_error(['message' => __('Invalid request.', 'gamify-woocommerce')]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gamification_points';
        $items_per_page = 10;

        // Calculate the offset.
        $offset = ($page - 1) * $items_per_page;

        // Fetch transactions.
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT action, points, log_message, date_earned FROM $table_name WHERE user_id = %d ORDER BY date_earned DESC LIMIT %d OFFSET %d",
            $user_id,
            $items_per_page,
            $offset
        ));

        // Get total transaction count for pagination.
        $total_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        $max_pages = ceil($total_transactions / $items_per_page);

        // Build response.
        ob_start();
        if ($transactions) {
            echo '<table class="gamify-transactions-table">';
            echo '<thead><tr><th>' . __('Action', 'gamify-woocommerce') . '</th><th>' . __('Points', 'gamify-woocommerce') . '</th><th>' . __('Details', 'gamify-woocommerce') . '</th><th>' . __('Date', 'gamify-woocommerce') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($transactions as $transaction) {
                echo '<tr>';
                echo '<td>' . esc_html(ucfirst($transaction->action)) . '</td>';
                echo '<td>' . esc_html($transaction->points) . '</td>';
                echo '<td>' . esc_html($transaction->log_message) . '</td>';
                echo '<td>' . esc_html(date('F j, Y', strtotime($transaction->date_earned))) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . __('No transactions found.', 'gamify-woocommerce') . '</p>';
        }
        $table_html = ob_get_clean();

        wp_send_json_success([
            'table_html' => $table_html,
            'pagination' => [
                'max_pages' => $max_pages,
                'current_page' => $page,
            ],
        ]);
    }
}
Gamify_Ajax::init();
