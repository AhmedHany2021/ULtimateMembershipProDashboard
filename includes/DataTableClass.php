<?php

namespace MEMBERSHIPDASHBOARD\INCLUDES;

use Indeed\Ihc\Admin\Datatable;
use MEMBERSHIPDASHBOARD\INCLUDES\SearchUserClass;


class DataTableClass extends Datatable
{
    public function __construct()
    {
        parent::__construct();
        add_action('wp_ajax_ihc_ajax_dt_get_members', [$this, 'getMembers'], 999);
        add_action('wp_ajax_ihc_capture_order', [$this,'handle_ihc_capture_order']);
        add_action('wp_ajax_ihc_cancel_order', [$this,'handle_ihc_cancel_order']);

    }

    public function handle_ihc_capture_order() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        check_ajax_referer('ihc_secure_order_actions', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id || !($order = wc_get_order($order_id))) {
            wp_send_json_error(['message' => 'Invalid order']);
        }

        // Example: mark order as processing
        $order->update_status('completed', 'Captured manually via admin');
        wp_send_json_success(['message' => 'Order captured successfully']);
    }

    public function handle_ihc_cancel_order() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        check_ajax_referer('ihc_secure_order_actions', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id || !($order = wc_get_order($order_id))) {
            wp_send_json_error(['message' => 'Invalid order']);
        }

        $order->update_status('cancelled', 'Cancelled manually via admin');
        wp_send_json_success(['message' => 'Order cancelled successfully']);
    }

    public function getMembers()
    {
        $ascOrDesc = '';
        $orderBy = '';
        if (isset($_POST['order'][0]['column']) && $_POST['order'][0]['column'] !== '') {
            $columnId = sanitize_text_field($_POST['order'][0]['column']);
            $ascOrDesc = sanitize_text_field($_POST['order'][0]['dir']);
            $orderBy = isset($_POST['columns'][$columnId]['data']) ? sanitize_text_field($_POST['columns'][$columnId]['data']) : false;
        }
        switch ($orderBy) {
            case 'user_email':
                $params['order'] = 'user_email_' . $ascOrDesc;
                break;
            case 'full_name':
                $params['order'] = 'display_name_' . $ascOrDesc;
                break;
            case 'uid':
                $params['order'] = 'ID_' . $ascOrDesc;
                break;
            case 'user_registered':
                $params['order'] = 'user_registered_' . $ascOrDesc;
                break;
            default:
                $params['order'] = 'user_registered_desc';
                break;
        }


        // offset and limit
        $params['offset'] = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : 0;
        $params['limit'] = isset($_POST['length']) ? sanitize_text_field($_POST['length']) : 30;

        // search phrase
        if (isset($_POST['search_phrase']) && $_POST['search_phrase'] !== '') {
            $params['search_phrase'] = indeed_sanitize_array($_POST['search_phrase']);
        }
        // memberships
        if (isset($_POST['memberships']) && $_POST['memberships'] !== '') {
            $params['memberships'] = indeed_sanitize_array($_POST['memberships']);
        }
        if (isset($_POST['memberships_status']) && $_POST['memberships_status'] !== '') {
            $params['memberships_status'] = indeed_sanitize_array($_POST['memberships_status']);
        }
        if (isset($_POST['user_roles']) && $_POST['user_roles'] !== '') {
            $params['user_roles'] = indeed_sanitize_array($_POST['user_roles']);
        }
        if (isset($_POST['extra_conditions']) && $_POST['extra_conditions'] !== '') {
            $params['extra_conditions'] = indeed_sanitize_array($_POST['extra_conditions']);
        }

        $users = new SearchUserClass();
        $users->setLimit($params['limit'])
            ->setOffset($params['offset'])
            ->setOrder($params['order']);
        if (isset($params['search_phrase']) && $params['search_phrase'] !== '') {
            $users->setSearchWord($params['search_phrase']);
        }
        if (isset($params['memberships']) && $params['memberships'] !== '' && $params['memberships'] != -1) {
            $users->setLid(implode(',', $params['memberships']));
        }
        if (isset($params['memberships_status']) && $params['memberships_status'] !== '') {
            $users->setLevelStatus(implode(',', $params['memberships_status']));
        }
        if (isset($params['user_roles']) && $params['user_roles'] !== '') {
            $users->setRole(implode(',', $params['user_roles']));
        }
        if (isset($params['extra_conditions']) && $params['extra_conditions'] !== '') {
            if (in_array(1, $params['extra_conditions'])) {
                // approvel request
                $users->setApprovelRequest(true);
            }
            if (in_array(2, $params['extra_conditions'])) {
                // pending email verifications
                $users->setOnlyDoubleEmailVerification(true);
            }
        }

        $total = $users->getCount();

        $results = $users->getResults();
        $data = [];

        $available_roles = ihc_get_wp_roles_list();
        $currency = get_option('ihc_currency');
        $directLogin = get_option('ihc_direct_login_enabled');
        $individual_page = get_option('ihc_individual_page_enabled');
        global $indeed_db;
        $is_uap_active = ihc_is_uap_active();
        $doubleEmailVerfication = get_option('ihc_register_double_email_verification');
        $levelDetails = \Ihc_Db::getLevelsDetails();
        $magic_feat_user_sites = ihc_is_magic_feat_active('user_sites');

        foreach ($results as $userData) {
            if (empty($userData->ID)) {
                continue;
            }
            // memberships

            $levelOutput = '';
            $verified_email = get_user_meta($userData->ID, 'ihc_verification_status', true);

            $levels = \Indeed\Ihc\UserSubscriptions::getAllForUser($userData->ID, false);
            $levels = apply_filters('ihc_admin_filter_user_memberships', $levels, $userData->ID);
            /*
            // deprecated since version 12.6
            $levels = array();
            if ( isset( $userData->levels ) && $userData->levels && stripos( $userData->levels, ',' ) !== false ){
            	$levels = explode( ',', $userData->levels );
            } else {
            	$levels[] = $userData->levels;
            }
            */
            $userLevelsArr = [];

            $levelOutput = '';
            if ($levels) {
                foreach ($levels as $level_data) {

                    /*
                    // deprecated since version 12.6
                        if ( $levelData == -1 ){
                               continue;
                        }
                        if ( strpos( $levelData, '|' ) !== false ){
                               $levelDataArray = explode( '|', $levelData );
                        } else {
                               $levelDataArray = array();
                        }

                        $lid = isset( $levelDataArray[0] ) ? $levelDataArray[0] : '';
                    $userLevelsArr[] = $lid;
                        $level_data = array(
                                        'level_id'		=> $lid,
                                        'start_time'	=> isset( $levelDataArray[1] ) ? $levelDataArray[1] : '',
                                        'expire_time' => isset( $levelDataArray[2] ) ? $levelDataArray[2] : '',
                                        'level_slug'	=> isset( $levelDetails[$lid]['slug'] ) ? $levelDetails[$lid]['slug'] : '',
                                      'label'		    => isset( $levelDetails[$lid]['label'] ) ? $levelDetails[$lid]['label'] : '',
                        );
                    */
                    if (empty($level_data) || empty($level_data['level_id'])) {
                        continue;
                    }
                    $userLevelsArr[] = $level_data['level_id'];
                    if (!isset($level_data['level_slug']) && isset($level_data['slug'])) {
                        $level_data['level_slug'] = $level_data['slug'];
                    }

                    $is_expired_class = '';
                    $level_title = esc_html("Active", 'ihc');

                    /// is expired
                    if (!\Indeed\Ihc\UserSubscriptions::isActive($userData->ID, $level_data['level_id'])) {
                        $is_expired_class = 'ihc-expired-level';
                        $level_title = esc_html("Hold/Expired", 'ihc');
                    }

                    $level_format = ihc_prepare_level_show_format($level_data);
                    $extra_class = '';
                    if (isset($level_format['bar_width']) && $level_format['bar_width'] > 98) {
                        $extra_class = 'ihc-level-skin-bar-full';
                    }
                    $skin_class = '';
                    if (isset($level_data['is_mga']) && $level_data['is_mga'] == TRUE) {
                        $skin_class = 'ihc-level-skin-box-mga';
                    }
                    $levelOutput .= '<div class="ihc-level-skin-wrapper">
                                  <span class="ihc-level-skin-element ihc-level-skin-box ' . esc_attr($skin_class) . '">
                                      <span class="ihc-level-skin-element">
                                      <span class="ihc-level-skin-line"></span>
                                      <span class="ihc-level-skin-min ' . esc_attr($level_format['time_class']) . '">' . esc_html($level_format['start_time_format']) . '</span>
                                      <span class="ihc-level-skin-max ' . esc_attr($level_format['time_class']) . '">' . esc_html($level_format['expire_time_format']) . '</span>
                                  </span>
                                  <span class="ihc-level-skin-bar ' . esc_attr($extra_class) . ' ' . esc_attr($level_format['bar_class']) . '" style="width:' . esc_attr($level_format['bar_width']) . '%;">
                                  <span class="ihc-level-skin-single ' . esc_attr($level_format['tooltip_class']) . '">' . esc_html($level_format['tooltip_message']) . '</span>
                                  </span>
                                  <span class="ihc-level-skin-grid">';

                    if ($level_data['label'] === '' || $level_data['label'] === false) {
                        $level_data['label'] = \Indeed\Ihc\Db\Memberships::getMembershipLabel($level_data['level_id']);
                    }
                    $levelOutput .= esc_html($level_data['label']);
                    $levelOutput .= '</span>';

                    $levelOutput .= '<span class="ihc-level-skin-down-grid">' . esc_html($level_format['extra_message']);
                    if (isset($level_data['is_mga']) && $level_data['is_mga'] == TRUE && isset($level_data['parent_name']) && !empty($level_data['parent_name'])) {
                        $levelOutput .= '<span class="ihc-level-skin-down-grid-mga">' . esc_html__('Hold by ', 'ihc') . esc_html($level_data['parent_name']) . '</span>';
                    }
                    $levelOutput .= '</span>';
                    $levelOutput .= '</span>';
                    $levelOutput .= '</div>';
                    $levelOutput .= '<!--div class="level-type-list ' . esc_attr($is_expired_class) . '" title="' . esc_attr($level_data['level_slug']) . '">' . esc_html($level_data['label']) . '</div-->';
                }

            }
            // end of membership

            $checkbox = '<input type="checkbox" class="ihc-delete-user iump-js-table-select-item" name="delete_users[]" value="' . $userData->ID . '">';

            $roles = isset($userData->roles) ? array_keys(maybe_unserialize($userData->roles)) : $userData->roles;
            $roleHtml = '<div id="user-' . esc_attr($userData->ID) . '-status">';
            if (isset($roles) && isset($roles[0]) && $roles[0] == 'pending_user') {
                $roleHtml .= '<span class="subcr-type-list iump-pending">' . esc_html__('Pending', 'ihc') . '</span>';
            } else {
                $roleHtml .= '<span class="subcr-type-list">';
                if (isset($roles) && isset($roles[0]) && isset($available_roles[$roles[0]])) {
                    $roleHtml .= esc_html($available_roles[$roles[0]]);
                } else {
                    $roleHtml .= esc_html('-');
                }
                $roleHtml .= '</span>';
            }
            if (count($roles) > 1) {
                for ($i = 1; $i < count($roles); $i++) {
                    if (isset($roles[$i]) && isset($available_roles[$roles[$i]])) {
                        $roleHtml .= '<span class="subcr-type-list">';
                        $roleHtml .= esc_html($available_roles[$roles[$i]]);
                        $roleHtml .= '</span>';
                    }
                }
            }
            $roleHtml .= '</div>';


            //details
            $details = '<div class="ihc_frw_button ihc_small_lightgrey_button"><a href="' . admin_url('admin.php?page=ihc_manage&tab=edit-user-subscriptions&uid=' . esc_attr($userData->ID)) . '" target="_blank" class="ihc-manage-plan-link-color">' . esc_html__('Manage Plans', 'ihc') . '</a></div>';
            $details .= '<div class="ihc_frw_button ihc_small_blue_button"><a  class="ihc-white-link" target="_blank" href="' . esc_url(ihcAdminUserDetailsPage($userData->ID)) . '">' . esc_html__('Member Profile', 'ihc') . '</a></div>';
            $ord_count = ihc_get_user_orders_count($userData->ID);
            if (isset($ord_count) && $ord_count > 0) {
                $details .= '<div class="ihc_frw_button"> <a href="' . admin_url('admin.php?page=ihc_manage&tab=orders&uid=' . $userData->ID) . '" target="_blank">' . esc_html__('Payments', 'ihc') . '</a></div>';
            }
            unset($ord_count);
            if ($directLogin) {
                $details .= '<div class="ihc_frw_button ihc_small_yellow_button ihc-admin-direct-login-generator ihc-pointer " data-uid="' . esc_attr($userData->ID) . '">' . esc_html__('Direct Login', 'ihc') . '</div>';
            }
            $details .= '<div class="ihc_frw_button ihc_small_grey_button ihc-admin-do-send-email-via-ump" data-uid="' . esc_attr($userData->ID) . '">' . esc_html__('Direct Email', 'ihc') . '</div>';
            if (ihc_is_magic_feat_active('user_reports') && \Ihc_User_Logs::get_count_logs('user_logs', $userData->ID)) {
                $details .= '<div class="ihc_frw_button ihc_small_red_button"> <a href="' . admin_url('admin.php?page=ihc_manage&tab=view_user_logs&type=user_logs&uid=' . esc_attr($userData->ID)) . '" target="_blank" class="ihc-white-link">' . esc_html__('Member Reports', 'ihc') . '</a></div>';
            }
            if ($individual_page) {
                $details .= '<div class="ihc_frw_button ihc_small_orange_button"> <a href="' . esc_url(ihc_return_individual_page_link($userData->ID)) . '" target="_blank" class="ihc-white-link">' . esc_html__('Individual Page', 'ihc') . '</a></div>';
            }

            // column 3
            $col3 = '<div class="ihc-users-list-avatar-wrapper">';
            $avatar = ihc_get_avatar_for_uid($userData->ID);
            if (!isset($avatar)) {
                $avatar = 'https://secure.gravatar.com/avatar/1cc31b08528740e0d8519581e6bf1b04?s=96&amp;d=mm&amp;r=g';
            }
            $col3 .= '<img src="' . esc_url($avatar) . '" />';
            $col3 .= '</div><div class="ihc-users-list-fullname-wrapper"><div class="ihc-users-list-fullname">';
            $firstName = isset($userData->first_name) ? $userData->first_name : '';
            $lastName = isset($userData->last_name) ? $userData->last_name : '';
            if (!empty($firstName) || !empty($lastName)) {
                $col3 .= esc_html($firstName) . ' ' . esc_html($lastName);
            } else {
                $col3 .= esc_html($userData->user_nicename);
            }
            $col3 .= '</div><div class="ihc-users-list-username-wrapper"><span class="ihc-users-list-username">' . esc_html($userData->user_login) . '</span>';

            $col3 .= '</div>';
            if ($is_uap_active && !empty($indeed_db)) {
                $is_affiliate = $indeed_db->is_user_affiliate_by_uid($userData->ID);
                if ($is_affiliate) {
                    $col3 .= '<div class="ihc-user-is-affiliate">' . esc_html__('Affiliate', 'ihc') . '</div>';
                }
            }
            $col3 .= '<div class="ihc-buttons-rsp ihc-visibility-hidden" id="user_tr_' . esc_attr($userData->ID) . '">';
            $col3 .= '<a class="iump-btns" href="' . admin_url('admin.php?page=ihc_manage&tab=users&ihc-edit-user=' . $userData->ID) . '">' . esc_html__('Edit', 'ihc') . '</a>';
            $col3 .= ' | <a class="iump-btns" target="_blank" href="' . admin_url('admin.php?page=membership_manage&tab=user-details&uid=' . $userData->ID) . '">' . esc_html__('Member Profile', 'ihc') . '</a>';
            $col3 .= ' | <a class="iump-btns" href="' . admin_url('admin.php?page=ihc_manage&tab=edit-user-subscriptions&uid=' . esc_attr($userData->ID)) . '" target="_blank">' . esc_html__('Manage Plans', 'ihc') . '</a>';
            $col3 .= ' | <span class="ihc-delete-link ihc-js-admin-delete-member" data-id="' . $userData->ID . '" >' . esc_html__('Remove', 'ihc') . '</span>';

            if (isset($roles) && isset($roles[0]) && $roles[0] == 'pending_user') {
                $col3 .= '<span id="approveUserLNK' . esc_attr($userData->ID) . '" onClick="ihcApproveUser(' . esc_attr($userData->ID) . ');">
            				 | <span class="iump-btns ihc-approve-link">' . esc_html__('Approve Member', 'ihc') . '</span></span>';
            }
            if ($verified_email == -1) {
                $col3 .= '<span id="approve_email_' . esc_attr($userData->ID) . '" >';
                $col3 .= ' | <span class="iump-btns ihc-approve-link iump-js-do-approve-email" data-id="' . $userData->ID . '"  >' . esc_html__('Approve Email', 'ihc') . '</span></span>';
            }
            $col3 .= '</div></div><div class="ihc-clear"></div>';
            // end of column 3

            // status
            $label = esc_html__('-', 'ihc');
            $div_id = "user_email_" . $userData->ID . "_status";
            $class = 'subcr-type-list';
            if ($verified_email == 1) {
                $label = esc_html__('Verified', 'ihc');
            } else if ($verified_email == -1) {
                $label = esc_html__('Unapproved', 'ihc');
                $class = 'subcr-type-list iump-pending';
            }
            $status = '<div id="' . esc_attr($div_id) . '"><span class="' . esc_attr($class) . '">' . $label . '</span></div>';
            if ($verified_email == -1 && $doubleEmailVerfication) {
                $status .= '<span id="resend_double_email_email_' . esc_attr($userData->ID) . '_verification" ><span class="iump-btns ihc-approve-link ihc-js-resend-email-verification-link" data-user_id="' . esc_attr($userData->ID) . '" >' . esc_html__('Resend Verification link', 'ihc') . '</span></span>';
            }
            // end of status

            $userRow = [
                'checkbox' => $checkbox,
                'uid' => [
                    'display' => $userData->ID,
                    'value' => $userData->ID,
                ],
                'full_name' => [
                    'display' => $col3,
                    'value' => $firstName . ' ' . $lastName,
                ],
                'user_email' => [
                    'display' => '<a class="iump-btns" href="mailto:' . $userData->user_email . '" target="_blank">' . esc_html($userData->user_email) . '</a>',
                    'value' => $userData->user_email,
                ],
                'memberships' => $levelOutput,
                'total_spend' => ihc_format_price_and_currency($currency, $userData->amount_spend),
                'wp_role' => $roleHtml,
                'email_status' => $status,
                'user_registered' => [
                    'display' => ihc_convert_date_to_us_format(esc_html($userData->user_registered)),
                    'value' => strtotime($userData->user_registered),
                ],
                'details' => $this->get_payment_buttons($userData->ID),
            ];


            // users sites
            if ($magic_feat_user_sites) {
                if ($userLevelsArr) {
                    $sites = [];
                    foreach ($userLevelsArr as $lid) {
                        if ($lid == -1) {
                            continue;
                        }
                        $temp['blog_id'] = \Ihc_Db::get_user_site_for_uid_lid($userData->ID, $lid);
                        if (!empty($temp['blog_id'])) {
                            $site_details = get_blog_details($temp['blog_id']);
                            $temp['link'] = untrailingslashit($site_details->domain . $site_details->path);
                            $temp['blogname'] = $site_details->blogname;
                            if (strpos($temp['link'], 'http') === FALSE) {
                                $temp['link'] = 'http://' . $temp['link'];
                            }
                            $temp['extra_class'] = \Ihc_Db::is_blog_available($temp['blog_id']) ? 'fa-sites-is-active' : 'fa-sites-is-not-active';
                            $sites[] = $temp;
                        }
                    }
                    $userRow['user_sites'] = '';
                    if ($sites) {
                        foreach ($sites as $siteData) {
                            $userRow['user_sites'] .= '<a href="' . esc_url($siteData['link']) . '" target="_blank" title="' . esc_attr($siteData['blogname']) . '">
                              <i class="fa-ihc fa-user_sites-ihc ' . esc_attr($siteData['extra_class']) . '"></i>
                            </a>';
                        }
                    }
                } else {
                    $userRow['user_sites'] = '';
                }
            }
            // end of users sites

            $userRow = apply_filters('ihc_admin_filter_datatable_members_each_row_data', $userRow, $userData->ID);
            $data[] = $userRow;

        }
        // output data, recordsTotal, recordsFiltered
        echo json_encode(['data' => $data, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'params' => json_encode($params)]);
        die;
    }

    private function get_payment_buttons($userID)
    {
        global $wpdb;

        $order_table = $wpdb->prefix . 'ihc_orders';
        $order_query = $wpdb->prepare("SELECT * FROM $order_table WHERE uid=%d AND status = %s", $userID, 'Pending');
        $order_data = $wpdb->get_results($order_query);

        if (empty($order_data)) {
            return "<span class='badge bg-secondary'>No Pending Orders</span>";
        }

        $order = $order_data[0];

        $order_meta_table = $wpdb->prefix . 'ihc_orders_meta';
        $order_meta_query = $wpdb->prepare("SELECT * FROM $order_meta_table WHERE order_id=%d", $order->id);
        $order_meta_data = $wpdb->get_results($order_meta_query);

        $woocommerce_order_id = null;
        foreach ($order_meta_data as $meta) {
            if ($meta->meta_key === 'txn_id' && preg_match('/woocommerce_order_(\d+)_/', $meta->meta_value, $matches)) {
                $woocommerce_order_id = $matches[1];
                break;
            }
        }

        if (!$woocommerce_order_id) {
            return "<span class='badge bg-secondary'>No WooCommerce Order Found</span>";
        }

        $woocommerce_order = wc_get_order($woocommerce_order_id);

        if (!$woocommerce_order) {
            return "<span class='badge bg-secondary'>WooCommerce Order Not Found</span>";
        }

        // Create secure AJAX URLs with nonce
        $nonce = wp_create_nonce('ihc_secure_order_actions');

        return '
    <div class="btn-group" data-user-id="' . esc_attr($userID) . '" data-order-id="' . esc_attr($woocommerce_order_id) . '" data-nonce="' . esc_attr($nonce) . '">
        <a href="#" class="btn btn-success btn-sm capture-order-button">Capture Payment</a>
        <a href="#" class="btn btn-danger btn-sm cancel-order-button">Cancel Order</a>
    </div>';
    }

}