<?

class Autoloader {

    public static function Register() {
        if (function_exists('__autoload')) {
            //    Register any existing autoloader function with SPL, so we don't get any clashes
            spl_autoload_register('__autoload');
        }
        //    Register ourselves with SPL
        spl_autoload_register(array('Autoloader', 'LoadCore'));
        spl_autoload_register(array('Autoloader', 'Load'));
        spl_autoload_register(array('Autoloader', 'Load2'));
        spl_autoload_register(array('Autoloader', 'AdminLoad'));
    }

    /**
     * Autoload a class identified by name
     *
     * @param    string    $pClassName        Name of the object to load
     */
    public static function Load($pClassName) {
        if ((class_exists($pClassName, FALSE))) {
            //    Either already loaded, or not a PHPExcel class request
            return FALSE;
        }

        $pClassFilePath = "core/$pClassName.class.php";

        $pClassFilePath = ROOT . "/" . $pClassFilePath;
        if ((file_exists($pClassFilePath) === FALSE) || (is_readable($pClassFilePath) === FALSE)) {
            //    Can't load
            return FALSE;
        }

        require_once($pClassFilePath);
    }

    public static function LoadCore($pClassName) {
        if ((class_exists($pClassName, FALSE))) {
            //    Either already loaded, or not a PHPExcel class request
            return FALSE;
        }

        $pClassFilePath = "admin/core/classes/$pClassName.class.php";

        $pClassFilePath = ROOT . "/" . $pClassFilePath;
        if ((file_exists($pClassFilePath) === FALSE) || (is_readable($pClassFilePath) === FALSE)) {
            //    Can't load
            return FALSE;
        }

        require_once($pClassFilePath);
    }

    public static function Load2($pClassName) {
        if ((class_exists($pClassName, FALSE))) {
            //    Either already loaded, or not a PHPExcel class request
            return FALSE;
        }

        $pClassFilePath = "core/class.$pClassName.php";

        $pClassFilePath = ROOT . "/" . $pClassFilePath;
        if ((file_exists($pClassFilePath) === FALSE) || (is_readable($pClassFilePath) === FALSE)) {
            //    Can't load
            return FALSE;
        }

        require_once($pClassFilePath);
    }

    public static function AdminLoad($pClassName) {
        if ((class_exists($pClassName, FALSE))) {
            return FALSE;
        }

        if (strpos($pClassName, "\\") !== false) {
            $path = str_replace('\\', '/', $pClassName);
            $path = ROOT . '/' . $path . '.php';
            if (file_exists($path))
                include_once($path);
        }

        $pClassFilePath = ROOT . "/admin/lib/classes/class.{$pClassName}.php";

        if (file_exists($pClassFilePath)) {
            include_once($pClassFilePath);
        } else if (file_exists($fname = ROOT . '/admin/lib/extended/' . $pClassName . '.php')) {
            include_once($fname);
        }
    }

}

?>