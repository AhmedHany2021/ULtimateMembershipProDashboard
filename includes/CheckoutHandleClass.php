<?php

class CheckoutHandleClass
{
    public function __construct()
    {
        add_action('woocommerce_before_checkout_process', [$this,'ump_force_existing_plan_checkout']);
    }

    public function ump_force_existing_plan_checkout() {
        if (!is_user_logged_in()) return;

        $userID = get_current_user_id();

        // Get the user's current membership plan ID (level ID)
        $plan_id = get_user_meta($userID, 'ihc_user_level', true);
        if (empty($plan_id)) return;

        // Use IHC method to get the associated WooCommerce product ID
        $product_id = \Ihc_Db::get_woo_product_id_for_lid($plan_id);
        if (empty($product_id)) return;

        // Check the cart for mismatching products
        $cart_modified = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ((int)$cart_item['product_id'] !== (int)$product_id) {
                WC()->cart->remove_cart_item($cart_item_key);
                $cart_modified = true;
            }
        }

        if ($cart_modified) {
            WC()->cart->add_to_cart($product_id);
            wc_add_notice(__('You already have a membership plan. The selected product has been replaced with your current plan.'), 'notice');
        }
    }

}