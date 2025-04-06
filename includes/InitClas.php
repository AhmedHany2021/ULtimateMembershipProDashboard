<?php

namespace MEMBERSHIPDASHBOARD\INCLUDES;

class InitClas
{
    public function __construct()
    {
        add_action ( 'admin_menu', [$this,'registerDashboardPages']);
        add_action('admin_enqueue_scripts',[$this,'addScripts']);
    }

    public function addScripts()
    {
        if ( is_admin() ) {
            wp_enqueue_script( 'custom_ihc-back_end', MDAN_URI . 'admin/assets/js/back_end.js', [ 'jquery' ], '12.9', true );
        }
    }


    public function registerDashboardPages()
    {
        $access = current_user_can( 'manage_options' );
        if ( $access )
        {

            $capability = 'manage_options';

            add_menu_page(
                'Membership Dashboard 2',
                'Membership Dashboard 2',
                $capability,
                'membership_manage',
                [$this, 'membershipManage'],
                'dashicons-universal-access-alt'
            );
        }
    }

    public function membershipManage()
    {
        if(isset($_GET['tab']) && $_GET['tab'] === 'user-details' && isset($_GET['uid']))
        {
            require_once MDAN_TEMPLATES . 'user-details.php';
        }
        else
        {
            require_once MDAN_TEMPLATES . 'users.php';
        }
    }

}