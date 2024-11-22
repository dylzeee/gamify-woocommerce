<?php

namespace Gamify;

use Gamify\Traits\Singleton;

class Gamify_Cart
{
    use Singleton;

    /**
     * Constructor to hook into WooCommerce actions.
     */
    protected function __construct()
    {
        // Hook into WooCommerce's calculate totals to make reward products free.
        add_action('woocommerce_before_calculate_totals', [$this, 'adjust_cart_prices'], 10, 1);
    }

    /**
     * Adjust the price of reward-tagged products to zero.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     */
    public function adjust_cart_prices($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Loop through cart items to check for reward-tagged products.
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['gamify_reward']) && $cart_item['gamify_reward'] === true) {
                $cart_item['data']->set_price(0); // Set the price to zero.
            }
        }
    }
}
