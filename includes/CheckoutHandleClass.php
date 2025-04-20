<?php
namespace MEMBERSHIPDASHBOARD\INCLUDES;

class CheckoutHandleClass
{
    public function __construct()
    {
        add_action('woocommerce_checkout_update_order_review', [$this,'ump_force_existing_plan_checkout']);
    }

    public function ump_force_existing_plan_checkout() {

        if (!is_user_logged_in()) return;

        $userID = get_current_user_id();
        $plan_id = \Indeed\Ihc\UserSubscriptions::getAllForUser($userID, false);
        $plan_id = array_key_first($plan_id);

        if (empty($plan_id)) return;

        $product_id = \Ihc_Db::get_woo_product_id_for_lid($plan_id);
        if (empty($product_id)) return;


        // Continue with cart modifications
        $cart_modified = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ((int)$cart_item['product_id'] !== (int)$product_id) {
                WC()->cart->remove_cart_item($cart_item_key);
                $cart_modified = true;
            }
        }

        if ($cart_modified) {
            WC()->cart->add_to_cart($product_id);
            wc_add_notice(__('Your cart has been updated to match your current membership plan.'), 'notice');
        }
    }

}