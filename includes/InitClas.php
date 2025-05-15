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

<?php
    private function ihc_make_csv_user_list($attributes = [])
    {
        global $wpdb;
        $levelDetails = \Ihc_Db::getLevelsDetails();
        $possibles = [
            'search_user', 'levels', 'roles', 'order', 'levelStatus', 'approvelRequest', 'emailVerification', 'advancedOrder'
        ];
        $applyFilters = false;
        foreach ($possibles as $possible) {
            if (!empty($attributes[$possible])) {
                $applyFilters = true;
                break;
            }
        }

        $searchUsers = new \Indeed\Ihc\Db\SearchUsers();
        $searchUsers->setLimit(0)->setOffset(0)->setLid(-1);

        if ($applyFilters) {
            $searchUsers
                ->setOrder($attributes['order'] ?? 'user_registered_desc')
                ->setLid($attributes['levels'] ?? -1)
                ->setSearchWord($attributes['search_user'] ?? '')
                ->setRole($attributes['roles'] ?? '')
                ->setAdvancedOrder($attributes['advancedOrder'] ?? '')
                ->setLevelStatus($attributes['levelStatus'] ?? '')
                ->setOnlyDoubleEmailVerification(!empty($attributes['emailVerification']) ? 1 : 0)
                ->setApprovelRequest(!empty($attributes['approvelRequest']));
        }

        $users = $searchUsers->getResults();
        if (!$users) return 'nill';

        $hash = bin2hex(random_bytes(20));
        $file_path = IHC_PATH . 'temporary/' . $hash . '.csv';
        $file_link = IHC_URL . 'temporary/' . $hash . '.csv';

        array_map('unlink', glob(IHC_PATH . 'temporary/*.csv'));
        $file_resource = fopen($file_path, 'w');

        $data[] = esc_html__('User ID', 'ihc');
        $register_fields = ihc_get_user_reg_fields();
        $exclude = ['pass1', 'pass2', 'tos', 'recaptcha', 'confirm_email', 'ihc_social_media', 'ihc_dynamic_price'];
        $filtered_fields = [];

        foreach ($register_fields as $v) {
            if (in_array($v['name'], $exclude)) continue;
            $label = $v['label'];
            $label = isset($v['native_wp']) && $v['native_wp']
                ? esc_html__($label, 'ihc')
                : $label;
            $label = iconv(mb_detect_encoding($label, mb_detect_order(), true), "UTF-8", $label);
            if (!in_array($label, $data)) $data[] = $label;
            $filtered_fields[] = $v;
        }

        $data = array_merge($data, [
            esc_html__('Membership ID', 'ihc'),
            esc_html__('Membership', 'ihc'),
            esc_html__('Start time', 'ihc'),
            esc_html__('Expire time', 'ihc'),
            esc_html__('WP User Roles', 'ihc'),
            esc_html__('Join Date', 'ihc')
        ]);
        fputcsv($file_resource, $data, ",");

        $user_ids = wp_list_pluck($users, 'ID');
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $sql = $wpdb->prepare("SELECT * FROM {$wpdb->usermeta} WHERE user_id IN ($placeholders)", ...$user_ids);
        $raw_meta = $wpdb->get_results($sql, ARRAY_A);

        $meta_by_user = [];
        foreach ($raw_meta as $row) {
            $meta_by_user[$row['user_id']][$row['meta_key']] = maybe_unserialize($row['meta_value']);
        }

        $ihc_fields = get_option('ihc_user_fields');
        $field_logic = [];
        foreach ($ihc_fields as $field) {
            $field_logic[$field['label']] = $field;
        }

        foreach ($users as $user) {
            $base_data = [$user->ID];
            $user_meta = $meta_by_user[$user->ID] ?? [];

            foreach ($filtered_fields as $v) {
                $value = ' ';
                if (isset($user_meta[$v['name']])) {
                    $value = is_array($user_meta[$v['name']]) ? implode(',', $user_meta[$v['name']]) : $user_meta[$v['name']];
                }
                if (isset($field_logic[$v['label']])) {
                    $field = $field_logic[$v['label']];
                    if (!empty($field['conditional_logic_corresp_field']) && isset($field['conditional_logic_corresp_field_value'])) {
                        $getter = $field['conditional_logic_corresp_field'];
                        $region_value = $user_meta[$getter] ?? '';
                        $expected_value = $field['conditional_logic_corresp_field_value'];
                        $translated_region = $this->get_wpml_original_text_en($region_value);
                        if ($expected_value == $region_value || $expected_value == $translated_region) {
                            $value = $user_meta[$field['name']] ?? ' ';
                        }
                    }
                }
                $base_data[] = $value;
            }

            $levels = explode(',', $user->levels ?: '-1');
            foreach ($levels as $level_str) {
                $data = $base_data;
                if ($level_str == '-1') {
                    $data = array_merge($data, ['-', '-', '-', '-', $user->roles, $user->user_registered]);
                } else {
                    list($lid, $start, $expire) = explode('|', $level_str) + [null, null, null];
                    $label = $levelDetails[$lid]['label'] ?? '-';
                    $data = array_merge($data, [$lid, $label, $start, $expire, $user->roles, $user->user_registered]);
                }
                fputcsv($file_resource, $data, ",");
            }
        }

        fclose($file_resource);
        return $file_link;
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