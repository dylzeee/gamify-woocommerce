<?php

namespace Gamify;

use Gamify\Traits\Singleton;

class Gamify_Dashboard
{
    use Singleton;

    /**
     * Constructor to hook into WooCommerce.
     */
    private function __construct()
    {
        add_action('init', [$this, 'register_my_points_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_my_points_tab']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_redeem_rewards_tab']);
        add_action('woocommerce_account_my-points_endpoint', [$this, 'render_my_points_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('init', [$this, 'register_redeem_rewards_endpoint']);
        add_action('woocommerce_account_redeem-rewards_endpoint', [$this, 'render_redeem_rewards_page']);
        add_action('pre_get_posts', [$this, 'fix_pagination_for_redeem_rewards']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('init', [$this, 'register_my_rewards_endpoint']);
        add_action('woocommerce_account_my-rewards_endpoint', [$this, 'render_my_rewards_page']);
    }

    /**
     * Enqueue custom CSS for the My Points dashboard.
     */
    public function enqueue_styles()
    {
        if (is_account_page()) {
            wp_enqueue_style(
                'gamify-dashboard-css',
                GAMIFY_WC_PLUGIN_URL . 'assets/css/gamify-dashboard.css',
                [],
                GAMIFY_WC_VERSION
            );
            wp_enqueue_script(
                'gamify-copy-to-clipboard',
                GAMIFY_WC_PLUGIN_URL . 'assets/js/copy-to-clipboard.js',
                [],
                GAMIFY_WC_VERSION,
                true
            );
        }
    }

    public function enqueue_scripts()
    {
        if (is_account_page()) {
            wp_enqueue_script(
                'gamify-rewards-ajax',
                GAMIFY_WC_PLUGIN_URL . 'assets/js/rewards-ajax.js',
                ['jquery'],
                GAMIFY_WC_VERSION,
                true
            );

            wp_localize_script('gamify-rewards-ajax', 'gamifyRewards', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('gamify_rewards_nonce'),
            ]);

            wp_enqueue_script(
                'gamify-transaction-pagination',
                GAMIFY_WC_PLUGIN_URL . 'assets/js/transaction-pagination.js',
                ['jquery'],
                GAMIFY_WC_VERSION,
                true
            );

            wp_localize_script('gamify-transaction-pagination', 'gamifyTransactions', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('gamify_transaction_nonce'),
            ]);
        }
    }

    /**
     * Add "My Points" tab to WooCommerce My Account menu.
     *
     * @param array $items Existing menu items.
     * @return array Modified menu items.
     */
    public function add_my_points_tab($items)
    {
        $items['my-points'] = __('My Points', 'gamify-woocommerce');
        return $items;
    }

    /**
     * Add "Redeem Rewards" tab to WooCommerce My Account menu.
     *
     * @param array $items Existing menu items.
     * @return array Modified menu items.
     */
    public function add_redeem_rewards_tab($items)
    {
        $items['redeem-rewards'] = __('Redeem Rewards', 'gamify-woocommerce');
        return $items;
    }


    /**
     * Render the "My Points" page on the WooCommerce My Account dashboard.
     *
     * This method displays the user's total points, links to other pages, and the transaction
     * history with AJAX-powered pagination.
     *
     * @return void
     */
    public function render_my_points_page()
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            echo '<p>' . __('You must be logged in to view your points.', 'gamify-woocommerce') . '</p>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gamification_points';

        // Get total points.
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        // Display total points and links.
        echo '<div class="gamify-dashboard">';
        echo '<div class="gamify-points-summary">';
        echo '<h2>' . __('My Points', 'gamify-woocommerce') . '</h2>';
        echo '<p class="points-total">' . sprintf(__('You have <strong>%d</strong> points.', 'gamify-woocommerce'), intval($total_points)) . '</p>';
        echo '<div class="gamify-links">';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url('redeem-rewards')) . '" class="gamify-link">' . __('Redeem Points', 'gamify-woocommerce') . '</a>';
        echo ' | ';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url('my-rewards')) . '" class="gamify-link">' . __('My Rewards', 'gamify-woocommerce') . '</a>';
        echo '</div>';
        echo '</div>';

        // Transaction History with AJAX pagination.
        echo '<div class="gamify-transactions">';
        echo '<h3>' . __('Transaction History', 'gamify-woocommerce') . '</h3>';
        echo '<div id="gamify-transaction-container" data-user-id="' . esc_attr($user_id) . '"></div>'; // Table placeholder.
        echo '<div id="gamify-transaction-pagination"></div>'; // Pagination placeholder.
        echo '</div>';
        echo '</div>';
    }



    /**
     * Display rewards available for redemption.
     *
     * @param int $user_points Total points the user has.
     */
    private function display_rewards($user_points)
    {
        $paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1; // Get the current page.

        $rewards = new \WP_Query([
            'post_type'      => 'gamify_reward',
            'post_status'    => 'publish',
            'meta_key'       => '_gamify_reward_status',
            'meta_value'     => 'active',
            'posts_per_page' => 2, // Adjust the number of rewards per page.
            'paged'          => $paged,
        ]);

        if (! $rewards->have_posts()) {
            echo '<p>' . __('No rewards available at the moment.', 'gamify-woocommerce') . '</p>';
            return;
        }

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
                echo '<form method="post" action="">';
                echo '<input type="hidden" name="reward_id" value="' . esc_attr($reward_id) . '">';
                echo '<button type="submit" class="button">' . __('Redeem Now', 'gamify-woocommerce') . '</button>';
                echo '</form>';
            } else {
                echo '<p class="gamify-insufficient-points">' . __('Not enough points to redeem this reward.', 'gamify-woocommerce') . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';

        // Add pagination.
        $this->display_pagination($rewards);
        wp_reset_postdata();
    }

    /**
     * Display pagination for the rewards.
     *
     * @param WP_Query $query The WP_Query object.
     */
    private function display_pagination($query)
    {
        $big = 999999999; // Need an unlikely integer for the pagination base.

        $pagination_links = paginate_links([
            'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format'    => '?paged=%#%',
            'current'   => max(1, get_query_var('paged')),
            'total'     => $query->max_num_pages,
            'prev_text' => __('&laquo; Previous', 'gamify-woocommerce'),
            'next_text' => __('Next &raquo;', 'gamify-woocommerce'),
            'type'      => 'list',
        ]);

        if ($pagination_links) {
            echo '<div class="gamify-pagination">';
            echo wp_kses_post($pagination_links);
            echo '</div>';
        }
    }

    /**
     * Render the "My Rewards" page on the WooCommerce My Account dashboard.
     *
     * This method displays a list of redeemed rewards for the currently logged-in user in a table format.
     * Each reward is shown with its type, coupon code, discount amount, and expiration date.
     *
     * @return void
     */
    public function render_my_rewards_page()
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            echo '<p>' . __('You must be logged in to view your rewards.', 'gamify-woocommerce') . '</p>';
            return;
        }

        // Retrieve coupon IDs from user meta.
        $user_coupons = get_user_meta($user_id, '_gamify_redeemed_coupons', true);

        // Sort coupons by creation date (latest to oldest).
        if (is_array($user_coupons) && ! empty($user_coupons)) {
            usort($user_coupons, function ($a, $b) {
                $date_a = get_post_field('post_date', $a);
                $date_b = get_post_field('post_date', $b);
                return strtotime($date_b) - strtotime($date_a);
            });
        }

        echo '<h2>' . __('My Rewards', 'gamify-woocommerce') . '</h2>';

        if (is_array($user_coupons) && ! empty($user_coupons)) {
            echo '<table class="gamify-rewards-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Type', 'gamify-woocommerce') . '</th>';
            echo '<th>' . __('Coupon Code', 'gamify-woocommerce') . '</th>';
            echo '<th>' . __('Discount', 'gamify-woocommerce') . '</th>';
            echo '<th>' . __('Expiration Date', 'gamify-woocommerce') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($user_coupons as $coupon_id) {
                $coupon = new \WC_Coupon($coupon_id);

                echo '<tr>';
                echo '<td>' . __('Coupon', 'gamify-woocommerce') . '</td>'; // Currently "Coupon" for all rewards.
                echo '<td>';
                echo '<span class="gamify-coupon-code">' . esc_html($coupon->get_code()) . '</span>';
                echo '<button class="gamify-copy-button" data-code="' . esc_attr($coupon->get_code()) . '" aria-label="' . __('Copy coupon code', 'gamify-woocommerce') . '">';
                echo '<span class="gamify-copy-icon gamify-icon-clipboard"></span>';
                echo '</button>';
                echo '</td>';
                echo '<td>' . esc_html(wp_strip_all_tags(wc_price($coupon->get_amount()))) . '</td>';
                echo '<td>' . esc_html($coupon->get_date_expires() ? $coupon->get_date_expires()->date('Y-m-d') : __('No expiration', 'gamify-woocommerce')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . __('No rewards redeemed yet.', 'gamify-woocommerce') . '</p>';
        }
    }



    /**
     * Fix pagination for custom endpoint.
     *
     * @param WP_Query $query The main query.
     */
    public function fix_pagination_for_redeem_rewards($query)
    {
        // Bail early if not the main query or not on the redeem rewards endpoint.
        if (! $query->is_main_query() || ! is_account_page() || get_query_var('redeem-rewards') === '') {
            return;
        }

        // Set the correct page for the custom endpoint.
        $paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;
        $query->set('paged', $paged);
    }


    /**
     * Render the "Redeem Rewards" page content.
     */
    public function render_redeem_rewards_page()
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            echo '<p>' . __('You must be logged in to view rewards.', 'gamify-woocommerce') . '</p>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gamification_points';

        // Get total points.
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        echo '<div class="gamify-dashboard">';
        echo '<div class="gamify-points-summary">';
        echo '<h2>' . __('Redeem Rewards', 'gamify-woocommerce') . '</h2>';
        echo '<p>' . sprintf(__('You have <strong>%d</strong> points.', 'gamify-woocommerce'), intval($total_points)) . '</p>';
        echo '</div>';

        // Rewards container for AJAX.
        echo '<div id="gamify-rewards-container" data-user-points="' . esc_attr($total_points) . '"></div>';

        // Pagination controls.
        echo '<div id="gamify-pagination-controls"></div>';
        echo '</div>';
    }



    /**
     * Register the "Redeem Rewards" endpoint.
     */
    public function register_redeem_rewards_endpoint()
    {
        add_rewrite_endpoint('redeem-rewards', EP_ROOT | EP_PAGES);
    }
    /**
     * Register the "My Rewards" endpoint.
     */
    public function register_my_rewards_endpoint()
    {
        add_rewrite_endpoint('my-rewards', EP_ROOT | EP_PAGES);
    }



    /**
     * Register the "My Points" endpoint with WooCommerce.
     *
     * @return void
     */
    public function register_my_points_endpoint()
    {
        add_rewrite_endpoint('my-points', EP_ROOT | EP_PAGES);
    }
}
