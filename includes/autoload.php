<?php

namespace MEMBERSHIPDASHBOARD\INCLUDES;

class autoload
{
    public static function fire()
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload ($class)
    {
        $prefixMain = 'MEMBERSHIPDASHBOARD\\INCLUDES\\';
        $lenMain = strlen($prefixMain);

        $prefixInddedAdmin = 'Indeed\\Ihc\\Admin';
        $lemInddedAdmin = strlen($prefixInddedAdmin);

        $prefixInddedFront = 'Indeed\\Ihc';
        $lemInddedFront = strlen($prefixInddedAdmin);

        if (strncmp($prefixMain, $class, $lenMain) === 0)
        {
            $relative_class = substr($class, $lenMain);
            $relative_class = str_replace('\\', '/', $relative_class);
            $base_dir = __DIR__ . '/';
            $file = $base_dir . $relative_class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        elseif (strncmp($prefixInddedAdmin, $class, $lemInddedAdmin) === 0)
        {
            $relative_class = substr($class, $lemInddedAdmin);
            $relative_class = str_replace('\\', '/', $relative_class);
            $base_dir = MDAN_ORIGINAL_DIR . 'admin/classes/';
            $file = $base_dir . $relative_class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        elseif (strncmp($prefixInddedFront, $class, $lemInddedFront) === 0)
        {
            $relative_class = substr($class, $lemInddedFront);
            $relative_class = str_replace('\\', '/', $relative_class);
            $base_dir = MDAN_ORIGINAL_DIR . 'classes/';
            $file = $base_dir . $relative_class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        else
        {
            return;
        }

    }
}