<?php
/*
Plugin Name: Ultimate Membership Pro dashboard
Plugin URI: https://github.com/AhmedHany2021
Description: this plugin add new dashboard for membership
Author: Ahmed Hany
Version: 1.1.0
Author URI: https://github.com/AhmedHany2021
GitHub Plugin URI: https://github.com/AhmedHany2021
*/

namespace MEMBERSHIPDASHBOARD;

use MEMBERSHIPDASHBOARD\INCLUDES\autoload;
use MEMBERSHIPDASHBOARD\INCLUDES\DataTableClass;
use MEMBERSHIPDASHBOARD\INCLUDES\InitClas;
use MEMBERSHIPDASHBOARD\INCLUDES\CheckoutHandleClass;

if (!defined('ABSPATH'))
{
    die();
}

if ( !in_array( 'indeed-membership-pro/indeed-membership-pro.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    die("NO ACCESS From Here Fine");
}

if(!defined("MDAN_BASEDIR")) { define("MDAN_BASEDIR",__DIR__ . '/'); }
if(!defined("MDAN_INC")) { define("MDAN_INC",MDAN_BASEDIR.'includes' . '/'); }
if(!defined("MDAN_TEMPLATES")) { define("MDAN_TEMPLATES",MDAN_BASEDIR.'templates' . '/'); }
if(!defined("MDAN_URI")) { define("MDAN_URI",plugin_dir_url(__FILE__) ); }
if(!defined("MDAN_ASSETS")) { define("MDAN_ASSETS", MDAN_URI.'assets' . '/'); }
if(!defined("MDAN_ORIGINAL_DIR")) { define("MDAN_ORIGINAL_DIR", WP_PLUGIN_DIR . '/indeed-membership-pro' . '/'); }
if (!defined("IHCCUSTOM_URL")) {define("IHCCUSTOM_URL", plugins_url('indeed-membership-pro') . '/');}


try {
    require_once MDAN_INC . 'autoload.php';
    autoload::fire();
    add_action('plugins_loaded', function() {
        if (is_plugin_active('indeed-membership-pro/indeed-membership-pro.php')) {
            new InitClas();
            new CheckoutHandleClass();
        }
    });
    new DataTableClass();
}
catch (\Exception $e)
{
    var_dump($e->getMessage());
}
