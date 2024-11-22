<?php
namespace Gamify;

use Gamify\Traits\Singleton;

class Gamify_Points {
    use Singleton;

    /**
     * Constructor to register hooks.
     */
    private function __construct() {
        add_action( 'woocommerce_order_status_completed', [ $this, 'award_points_for_order' ] );
    }

    /**
     * Award points to the user when an order is completed.
     *
     * @param int $order_id WooCommerce order ID.
     * @return void
     */
    public function award_points_for_order( $order_id ) {
        $order = wc_get_order( $order_id );

        // Get the user ID.
        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return; // Skip guest orders.
        }

        // Calculate points (1 point for every $1 spent).
        $order_total = $order->get_total();
        $points = intval( $order_total ); // Simple conversion: 1 point = $1.

        // Log the points in the database.
        $this->log_points( $user_id, $points, 'purchase', "Earned $points points for Order #$order_id", $order_id );
    }

    /**
     * Log points to the database.
     *
     * @param int    $user_id      User ID.
     * @param int    $points       Number of points.
     * @param string $action       Action type (e.g., purchase, referral).
     * @param string $log_message  Description of the transaction.
     * @param int    $order_id     WooCommerce order ID (optional).
     * @return void
     */
    private function log_points( $user_id, $points, $action, $log_message, $order_id = null ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gamification_points';

        $wpdb->insert(
            $table_name,
            [
                'user_id'      => $user_id,
                'action'       => $action,
                'points'       => $points,
                'log_message'  => $log_message,
                'date_earned'  => current_time( 'mysql' ),
                'order_id'     => $order_id,
            ],
            [ '%d', '%s', '%d', '%s', '%s', '%d' ]
        );
    }
}
