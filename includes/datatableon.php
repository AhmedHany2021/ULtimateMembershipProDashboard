function get_wpml_original_text_en($translated_text, $context = 'admin_texts_ihc_user_fields')
{
global $wpdb;

$query = $wpdb->prepare(
"SELECT s.value FROM {$wpdb->prefix}icl_strings s
JOIN {$wpdb->prefix}icl_string_translations t ON s.id = t.string_id
WHERE t.value = %s AND s.context = %s AND t.language = 'ar'",
$translated_text, $context
);

return $wpdb->get_var($query);
}


/**
* generate csv file with all users
* @param none
* @return string, link to csv file or empty string
*/
if (!function_exists('ihc_make_csv_user_list')):
function ihc_make_csv_user_list($attributes = [])
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
foreach ($users as $user) {

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
// $origional_data = get_wpml_original_text_en($userMetaArray[$getter]);
if ($ihc_f[$k]['conditional_logic_corresp_field_value'] == $userMetaArray[$getter]) {
$needed_v = $userMetaArray[$ihc_f[$k]['name']];
}
}
}
}
if ($needed_v != "") {
$the_user_data[] = $needed_v;
} else {
$the_user_data[] = $userMetaArray[$v['name']];
}
}
} else {
$the_user_data[] = ' ';
}
}
}
$bef = $v['label'];
}

$levels = array();
if ($user->levels && stripos($user->levels, ',') !== false) {
$levels = explode(',', $user->levels);
} else {
$levels[] = $user->levels;
}

if ($levels) {
/// with levels
foreach ($levels as $level_data) {
if ($level_data == -1) {
/// NO LEVELS
$data = $the_user_data;
$data[] = '-'; /// Membership ID
$data[] = '-'; /// Membership
$data[] = '-'; /// start TIME
$data[] = '-'; /// Expire TIME
$data[] = $user->roles;
$data[] = $user->user_registered;
fputcsv($file_resource, $data, ",");
unset($data);
continue;
}
if (strpos($level_data, '|') !== false) {
$levelDataArray = explode('|', $level_data);
} else {
$levelDataArray = array();
}

$lid = isset($levelDataArray[0]) ? $levelDataArray[0] : '';
$level_data = array(
'level_id' => $lid,
'start_time' => isset($levelDataArray[1]) ? $levelDataArray[1] : '',
'expire_time' => isset($levelDataArray[2]) ? $levelDataArray[2] : '',
'level_slug' => isset($levelDetails[$lid]['slug']) ? $levelDetails[$lid]['slug'] : '',
'label' => isset($levelDetails[$lid]['label']) ? $levelDetails[$lid]['label'] : '',
);

$data = $the_user_data;
$data[] = $level_data['level_id']; /// Membership ID
$data[] = $level_data['label']; /// Membership
$data[] = $level_data['start_time']; /// start TIME
$data[] = $level_data['expire_time']; /// Expire TIME
$data[] = $user->roles;
$data[] = $user->user_registered;
fputcsv($file_resource, $data, ",");
unset($data);
}
} else {
/// NO LEVELS
$data = $the_user_data;
$data[] = '-'; /// Membership ID
$data[] = '-'; /// Membership
$data[] = '-'; /// start TIME
$data[] = '-'; /// Expire TIME
$data[] = $user->roles;
$data[] = $user->user_registered;
fputcsv($file_resource, $data, ",");
unset($data);
}
unset($the_user_data);
} /// end of foreach  users
fclose($file_resource);
return $file_link;
}
return 'nill';

}

endif;
