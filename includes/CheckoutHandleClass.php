<?php

namespace MEMBERSHIPDASHBOARD\INCLUDES;

class CheckoutHandleClass
{
    public function __construct()
    {
        add_action('woocommerce_checkout_update_order_review', [$this, 'ump_force_existing_plan_checkout']);
        // add_action('woocommerce_checkout_create_order_line_item', [$this, 'ump_force_existing_plan_checkout_cart'], 10, 4);
        add_action('woocommerce_checkout_order_created',[$this , 'create_custom_discount'], 30);

    }

    public function create_custom_discount($order)
    {

        $userID = get_current_user_id();
        $plan_id = \Indeed\Ihc\UserSubscriptions::getAllForUser($userID, false);
        $plan_id = array_key_first($plan_id);
        if (empty($plan_id)) return;

        $product_id = \Ihc_Db::get_woo_product_id_for_lid($plan_id);
        if (!$product_id) return;

        $discount_amount =  get_user_meta($userID, 'membership-discount', true);
        if (!$discount_amount || $discount_amount <= 0) return;

        $product = wc_get_product($product_id);
        $original_price = $product->get_price();

        $item = new \WC_Order_Item_Fee();
        $item->set_name(__('Renewal Discount', 'your-textdomain'));
        $item->set_amount(-1 * $discount_amount);
        $item->set_total(-1 * $discount_amount);
        $order->add_item($item);
        $item->save(); // Important!

        $order->calculate_totals();
        $order->save();
        update_user_meta($userID, 'membership-discount', 0);
    }

    public function ump_force_existing_plan_checkout_cart($item, $cart_item_key, $values, $order) {
        $userID = get_current_user_id();
        $plan_id = \Indeed\Ihc\UserSubscriptions::getAllForUser($userID, false);
        $plan_id = array_key_first($plan_id);

        if (empty($plan_id)) return;

        $product_id = \Ihc_Db::get_woo_product_id_for_lid($plan_id);
        if ((int)$values['product_id'] !== (int)$product_id) return;

        $discount_type = get_option('renew_discount_type', 'percentage');
        $discount_value = get_option('renew_discount_value', 10);
        $discount_active = get_option('renew_discount_active', 0);
        if (!$discount_active) return;

        $product = wc_get_product($product_id);
        $original_price = $product->get_price();

        $discount_amount = ($discount_type === 'percentage')
            ? ($original_price * $discount_value) / 100
            : $discount_value;

        $new_price = max(0, $original_price - $discount_amount);

        // Forcefully update all price fields
        $item->set_subtotal($new_price);
        $item->set_total($new_price);
        $item->set_subtotal_tax(0);  // if tax isn't recalculated
        $item->set_total_tax(0);
        $item->set_taxes(['total' => [], 'subtotal' => []]);  // Reset tax array if needed
    }

    public function ump_force_existing_plan_checkout()
    {

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

        $discount_amount =  get_user_meta($userID, 'membership-discount', true);

        if ($discount_amount && $discount_amount > 0) {
            $product = wc_get_product($product_id);
            $original_price = $product->get_price();
            $new_price = $original_price - $discount_amount;

            // Set the discounted price for the cart item
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    WC()->cart->cart_contents[$cart_item_key]['data']->set_price($new_price);
                }
            }
        }
    }

}