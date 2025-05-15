<?php

namespace MEMBERSHIPDASHBOARD\INCLUDES;

class InitClas
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerDashboardPages']);
        add_action('admin_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_init', [$this, 'ump_register_renew_discount_settings']);
        remove_action('wp_ajax_ihc_return_csv_link', 'ihc_return_csv_link');
        add_action('wp_ajax_ihc_return_csv_link', [$this, 'ihc_return_csv_link']);
    }

    public function ihc_return_csv_link()
    {
        if (!ihcIsAdmin()) {
            echo 0;
            die;
        }
        if (!ihcAdminVerifyNonce()) {
            echo 0;
            die;
        }

        if (isset($_POST['filters'])) {
            $attributes = json_decode(stripslashes(sanitize_text_field($_POST['filters'])), true);
        } else {
            $attributes = [];
        }
        echo $this->ihc_make_csv_user_list($attributes);
        die;
    }

    private function get_wpml_original_text_en($translated_text, $context = 'admin_texts_ihc_user_fields')
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "
                SELECT s.value FROM {$wpdb->prefix}icl_strings s
                JOIN {$wpdb->prefix}icl_string_translations t ON s.id = t.string_id
                WHERE t.value = %s AND s.context = %s AND t.language = 'ar'",
            $translated_text, $context
        );
        return $wpdb->get_var($query);
    }

    private function ihc_make_csv_user_list($attributes = [])
    {
        global $wpdb;
        $levelDetails = \Ihc_Db::getLevelsDetails();
        $possibles = array(
            'search_user',
            'levels',
            'roles',
            'order',
            'levelStatus',
            'approvelRequest',
            'emailVerification',
            'advancedOrder',
        );
        $applyFilters = false;
        foreach ($possibles as $possible) {
            if (isset($attributes[$possible])) {
                $applyFilters = true;
            }
        }

        $searchUsers = new \Indeed\Ihc\Db\SearchUsers();
        $searchUsers->setLimit(0)
            ->setOffset(0)
            ->setLid(-1);
        if ($applyFilters) {
            $limit = (isset($attributes['ihc_limit'])) ? $attributes['ihc_limit'] : 25;
            $start = 0;
            if (isset($attributes['ihcdu_page'])) {
                $pg = $attributes['ihcdu_page'] - 1;
                $start = (int)$pg * $limit;
            }
            $search_query = isset($attributes['search_user']) ? $attributes['search_user'] : '';
            $filter_role = isset($attributes['roles']) ? $attributes['roles'] : '';
            $search_level = isset($attributes['levels']) ? $attributes['levels'] : -1;
            $order = isset($attributes['order']) ? $attributes['order'] : 'user_registered_desc'; // user_registered_desc
            $approveRequest = isset($attributes['approvelRequest']) && $attributes['approvelRequest'] ? true : false;
            $advancedOrder = isset($attributes['advancedOrder']) ? $attributes['advancedOrder'] : '';
            $levelStatus = isset($attributes['levelStatus']) ? $attributes['levelStatus'] : '';
            $emailVerification = isset($attributes['emailVerification']) && $attributes['emailVerification'] ? 1 : 0;
            $searchUsers = new \Indeed\Ihc\Db\SearchUsers();

            $searchUsers->setLimit(0)
                //->setOffset( $start )
                ->setOrder($order)
                ->setLid($search_level)
                ->setSearchWord($search_query)
                ->setRole($filter_role)
                ->setAdvancedOrder($advancedOrder)
                ->setLevelStatus($levelStatus)
                ->setOnlyDoubleEmailVerification($emailVerification)
                ->setApprovelRequest($approveRequest);
        }
        $users = $searchUsers->getResults();

        if ($users) {

            $hash = bin2hex(random_bytes(20));
            $file_path = IHC_PATH . 'temporary/' . $hash . '.csv';
            $file_link = IHC_URL . 'temporary/' . $hash . '.csv';

            // remove old files
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $directory = IHC_PATH . 'temporary/';
            $files = scandir($directory);
            foreach ($files as $file) {
                $fileFullPath = $directory . $file;
                if (file_exists($fileFullPath) && filetype($fileFullPath) == 'file') {
                    $extension = pathinfo($fileFullPath, PATHINFO_EXTENSION);
                    if ($extension == 'csv') {
                        unlink($fileFullPath);
                    }
                }
            }

            // create file
            $file_resource = fopen($file_path, 'w');

            $data[] = esc_html__('User ID', 'ihc');

            $register_fields = ihc_get_user_reg_fields();
            $bef = "";
            foreach ($register_fields as $k => $v) {
                if ($bef != $v['label']) {
                    if ($v['name'] == 'pass1' || $v['name'] == 'pass2' || $v['name'] == 'tos' || $v['name'] == 'recaptcha' || $v['name'] == 'confirm_email' || $v['name'] == 'ihc_social_media' || $v['name'] == 'ihc_dynamic_price') {
                        unset($register_fields[$k]);
                    } else {
                        if (isset($v['native_wp']) && $v['native_wp']) {
                            $tvc = iconv(mb_detect_encoding(esc_html__($v['label'], 'ihc'), mb_detect_order(), true), "UTF-8", esc_html__($v['label'], 'ihc'));
                            // $data[] = esc_html__($v['label'], 'ihc');
                            $data[] = $tvc;
                        } else {
                            $tvc = iconv(mb_detect_encoding($v['label'], mb_detect_order(), true), "UTF-8", $v['label']);
                            // $data[] = $v['label'];
                            $data[] = $tvc;
                        }
                    }
                }
                $bef = $v['label'];
            }
            $data[] = esc_html__('Membership ID', 'ihc');
            $data[] = esc_html__('Membership', 'ihc');
            $data[] = esc_html__('Start time', 'ihc');
            $data[] = esc_html__('Expire time', 'ihc');
            $data[] = esc_html__('WP User Roles', 'ihc');
            $data[] = esc_html__('Join Date', 'ihc');

            /// top of CSV file
            fputcsv($file_resource, $data, ",");
            unset($data);

            global $wpdb;
            $query = "SELECT user_id ";
            $exclude = ['pass1', 'pass2', 'tos', 'recaptcha', 'ihc_optin_accept', 'ihc_memberlist_accept', 'confirm_email', 'ihc_dynamic_price', 'ihc_social_media'];
            foreach ($register_fields as $v) {
                if (in_array($v['name'], $exclude)) {
                    continue;
                }
                $query .= " ,max(case when meta_key = '{$v['name']}' then meta_value end) `{$v['name']}` ";
            }
            $query .= " FROM {$wpdb->usermeta} ";

            $reg = null;
            $all_csv_rows = [];

            foreach ($users as $user) {
                $the_user_data = [];
                $the_user_data[] = $user->ID;

                $userQuery = $query . $wpdb->prepare(" WHERE user_id=%d", $user->ID);
                $userMetaArray = $wpdb->get_row($userQuery, ARRAY_A);

                $bef = "";
                foreach ($register_fields as $v) {
                    if ($bef != $v['label']) {
                        if (isset($user->{$v['name']})) {
                            $the_user_data[] = $user->{$v['name']};
                        } else {
                            if (isset($userMetaArray[$v['name']]) && $userMetaArray[$v['name']] !== FALSE) {
                                if (is_array($userMetaArray[$v['name']])) {
                                    $the_user_data[] = implode(",", $userMetaArray[$v['name']]);
                                } else {
                                    $needed_v = "";
                                    $ihc_f = get_option('ihc_user_fields');
                                    foreach ($ihc_f as $k => $ihc) {
                                        if ($ihc_f[$k]['label'] == $v['label']) {
                                            if ($ihc_f[$k]['conditional_logic_corresp_field'] != -1 && $ihc_f[$k]['conditional_logic_corresp_field'] != false) {
                                                $getter = $ihc_f[$k]['conditional_logic_corresp_field'];
                                                $region_value = $userMetaArray[$getter] ?? '';
                                                $expected_value = $ihc_f[$k]['conditional_logic_corresp_field_value'];
                                                $translated_region = $this->get_wpml_original_text_en($region_value);
                                                if ($expected_value == $region_value || $expected_value == $translated_region) {
                                                    $needed_v = $userMetaArray[$ihc_f[$k]['name']] ?? '';
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    $the_user_data[] = ($needed_v !== '') ? $needed_v : ($userMetaArray[$v['name']] ?? ' ');
                                }
                            } else {
                                $the_user_data[] = ' ';
                            }
                        }
                    }
                    $bef = $v['label'];
                }

                $levels = [];
                if ($user->levels && stripos($user->levels, ',') !== false) {
                    $levels = explode(',', $user->levels);
                } else {
                    $levels[] = $user->levels;
                }

                if ($levels) {
                    foreach ($levels as $level_data) {
                        if ($level_data == -1) {
                            $data = $the_user_data;
                            $data[] = '-'; // Membership ID
                            $data[] = '-'; // Membership
                            $data[] = '-'; // Start time
                            $data[] = '-'; // Expire time
                            $data[] = $user->roles;
                            $data[] = $user->user_registered;
                            $all_csv_rows[] = $data;
                            continue;
                        }

                        $levelDataArray = explode('|', $level_data);
                        $lid = $levelDataArray[0] ?? '';
                        $level_data = [
                            'level_id' => $lid,
                            'start_time' => $levelDataArray[1] ?? '',
                            'expire_time' => $levelDataArray[2] ?? '',
                            'level_slug' => $levelDetails[$lid]['slug'] ?? '',
                            'label' => $levelDetails[$lid]['label'] ?? '',
                        ];

                        $data = $the_user_data;
                        $data[] = $level_data['level_id'];
                        $data[] = $level_data['label'];
                        $data[] = $level_data['start_time'];
                        $data[] = $level_data['expire_time'];
                        $data[] = $user->roles;
                        $data[] = $user->user_registered;
                        $all_csv_rows[] = $data;
                    }
                } else {
                    $data = $the_user_data;
                    $data[] = '-';
                    $data[] = '-';
                    $data[] = '-';
                    $data[] = '-';
                    $data[] = $user->roles;
                    $data[] = $user->user_registered;
                    $all_csv_rows[] = $data;
                }
            }
            foreach ($all_csv_rows as $row) {
                fputcsv($file_resource, $row, ",");
            }
            fclose($file_resource);
            return $file_link;
        }
        return 'nill';
    }
    public function addScripts()
    {
        if (is_admin()) {
            wp_enqueue_script('custom_ihc-back_end', MDAN_URI . 'admin/assets/js/back_end.js', ['jquery'], '12.9', true);
        }
    }


    public function registerDashboardPages()
    {
        $access = current_user_can('manage_options');
        if ($access) {

            $capability = 'manage_options';

            add_menu_page(
                'Membership Dashboard',
                'Membership Dashboard',
                $capability,
                'membership_manage',
                [$this, 'membershipManage'],
                'dashicons-universal-access-alt'
            );

            add_submenu_page(
                'membership_manage',
                'Renew Discount Settings',
                'Renew Discount',
                'manage_options',
                'renew_discount_settings',
                [$this, 'ump_renew_discount_settings_page']
            );
        }
    }

    public function ump_renew_discount_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Renewal Discount Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('renew_discount_settings_group'); // Settings group for saving
                do_settings_sections('renew_discount_settings'); // Add sections for settings

                // Fetch current settings from the database
                $discount_type = get_option('renew_discount_type', 'percentage'); // Default to 'percentage'
                $discount_value = get_option('renew_discount_value', 10); // Default to 10%
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Discount Type</th>
                        <td>
                            <select name="renew_discount_type">
                                <option value="percentage" <?php selected($discount_type, 'percentage'); ?>>Percentage
                                </option>
                                <option value="amount" <?php selected($discount_type, 'amount'); ?>>Amount</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Discount Value</th>
                        <td>
                            <input type="number" name="renew_discount_value"
                                   value="<?php echo esc_attr($discount_value); ?>" step="0.01" min="0"/>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Activate Discount</th>
                        <td>
                            <input type="checkbox" name="renew_discount_active"
                                   value="1" <?php checked(get_option('renew_discount_active', 0), 1); ?> />
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function ump_register_renew_discount_settings()
    {
        register_setting('renew_discount_settings_group', 'renew_discount_type');
        register_setting('renew_discount_settings_group', 'renew_discount_value');
        register_setting('renew_discount_settings_group', 'renew_discount_active');
    }

    public function membershipManage()
    {
        if (isset($_GET['tab']) && $_GET['tab'] === 'user-details' && isset($_GET['uid'])) {
            require_once MDAN_TEMPLATES . 'user-details.php';
        } else {
            require_once MDAN_TEMPLATES . 'users.php';
        }
    }

}