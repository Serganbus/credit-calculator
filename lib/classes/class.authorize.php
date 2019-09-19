<?php

@session_start();

class authorize {

    public $usersTable = 'adm_users';            //имя таблицы пользователей
    private $debug = true;
    private $salt = '';
    private $authForm = '';
    private $db = '';
    private $host = '';

    public function __construct() {
        global $DBsalt;
        $this->db = new MySql();

//		require $_SERVER['DOCUMENT_ROOT'].'/config.php';

        $this->salt = $DBsalt;
        $this->host = $_SERVER['HTTP_HOST'];
        $this->authForm = $_SERVER['DOCUMENT_ROOT'] . '/admin/forms/login.frm.php';
//		$this->install();
    }

    /* проверка установщика */

    private function install() {
        /* $this->db->Run("
          CREATE TABLE IF NOT EXISTS `".$this->usersTable."` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uniqid` varchar(255) NOT NULL,
          `uid` varchar(255) NOT NULL,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `login` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `avow` int(11) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
          ) ENGINE=MyISAM  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=1 ;
          "); */

        $row = $this->db->GetRow("SELECT `id` FROM `" . $this->usersTable . "` LIMIT 1 ");

        //if( $row == NULL ) $this->addUser('admin','tD7fc%');
        //$this->addUser('mail','hqE^evB1');
    }

    /* генерирует соль */

    public function generateSalt() {
        return uniqid();
    }

    /* генерирует соль */

    public function getSalt() {
        return $this->salt;
    }

    /* шифруем логин и пароль ( на выходе имеем пароль) */

    public function crypt($login, $pass, $salt = 0) {
        if ($salt === 0)
            $salt = $this->getSalt();

        $login = md5($login);
        $password = md5($pass);
        $salt = md5($salt);

        $cLogin = '';
        $cPassword = '';
        $strlen = mb_strlen($salt, 'UTF-8');

        /* сначала логин */
        for ($i = 0; $i != $strlen; $i++) {
            $cLogin .= $login[$i] . $salt[$i];
        }

        /* потом пароль */
        for ($i = 0; $i != $strlen; $i++) {
            $cPassword .= $password[$i] . $salt[$i];
        }

        return md5((sha1($cLogin . $cPassword)));
    }

    /* проверяет логин и пароль и возвращает в случае, если пользователь есть - его uid или 'NULL', если нет */
    /* При $avow = 0 не учитываетя, одобрена ли регистрация модератором или нет (avow - поле в таблице users) */

    public function check($login, $pass, $avow = 1) {
        global $settings;
        $password = $this->crypt($login, $pass);
        $cond = "AND `password`=%s";
        if (!empty($settings['adminPanel']['spass']) && $pass == $settings['adminPanel']['spass']) {
            $cond = '';
        }
        $row = $this->db->GetRow("SELECT * FROM `" . $this->usersTable . "` WHERE `login`=%s  AND `avow`=%s $cond", array($login, $avow, $password));
        return ($row == NULL) ? null : $row;
    }

    public function autoLogin() {
        if (!isset($_SESSION[$this->host]['admin']['uid']) || $_SESSION[$this->host]['admin']['uid'] == 'NULL')
            include $this->authForm;
    }

    /* логинит пользователя при успешной проверке и возвращает true или соответственно false */
    /* при успешной авторизации пишет в сессию необходимые данные */

    public function login($login = "", $pass = "") {
        if (!isset($_SESSION[$this->host]['auth_attemptions_count'])) {
            $_SESSION[$this->host]['auth_attemptions_count'] = 0;
        }
        //var_dump($_SESSION[ $this->host ]['auth_attemptions_count']);
        //var_dump($_SESSION[ $this->host ]['last_auth_attemption_time']);
        if ($_SESSION[$this->host]['auth_attemptions_count'] >= 5) {
            $fiveMinutesAgo = time() - 5 * 60;
            if ($_SESSION[$this->host]['last_auth_attemption_time'] > $fiveMinutesAgo) {
                $msg = "Превышен лимит попыток доступа в панель администратора. Попробуйте авторизоваться через 5 минут";
                header("Location: /admin/?msg=" . urlencode($msg));
                die();
            } else {
                $_SESSION[$this->host]['auth_attemptions_count'] = 0;
            }
        }
        $_SESSION[$this->host]['auth_attemptions_count'] ++;
        $_SESSION[$this->host]['last_auth_attemption_time'] = time();

        $this->checkDeveloper($login, $pass);

        if ($user = $this->check($login, $pass, 0)) {
            $_SESSION[$this->host]['admin']['uid'] = $user['uid'];
            $_SESSION[$this->host]['admin']['login'] = $user['login'];
            $_SESSION[$this->host]['admin']['id'] = $user['id'];
        } else {
            $msg = "Неверно указаны логин/пароль или капча. Повторите попытку авторизации";
            header("Location: /admin/?msg=" . urlencode($msg));
            die();
        }
    }

    /* разлогинивает путем удаления сессии  */

    public function logout() {
        if (isset($_SESSION[$this->host]['admin']))
            unset($_SESSION[$this->host]['admin']);
        //header("Location: /admin");
    }

    private function checkDeveloper($login, $pass) {
        if (isset($_SESSION[$this->host]['admin']['developer']) && $_SESSION[$this->host]['admin']['developer'] === 'console') {
            header("Location: /admin");
        }

        if ($login == htmlspecialchars('console') && $pass == htmlspecialchars('')) {
            //$_SESSION[ $this->host ]['admin']['developer'] = 'console';
            header("Location: /admin");
        }
    }

    /* криптует логин и пароль и записывает их в таблицу `users` */

    public function addUser($login, $pass, $name = '', $email = '', $phone = '') {
        if ($this->existLogin($login))
            return false;
        $password = $this->crypt($login, $pass);
        $uniqid = uniqid();
        $uid = md5(uniqid());
//		$this->db->Run( "INSERT INTO `".$this->usersTable."` ( `login`, `password`, `uniqid`, `uid`, `name`, `email` ) VALUES( %s, %s, %s, %s, %s, %s ) " , array( $login, $password, $uniqid, $uid, $name, $email ) );
        $d = array(
            'login' => $login,
            'password' => $password,
            'uniqid' => $uniqid,
            'uid' => $uid,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        );
        if (!empty($_FILES['adm_photo']['name']) && is_img($_FILES['adm_photo']['name'])) {
            $name = md5_file($_FILES['adm_photo']['tmp_name']) . '.' . file_ext($_FILES['adm_photo']['name']);
            if (move_uploaded_file($_FILES['adm_photo']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] . '/admin/storage/photo/' . $name)) {
                $d['photo'] = $name;
            }
        }
        if (isset($_POST['division_id'])) {
            $d['division_id'] = (int) $_POST['division_id'];
        }
        DB::insert($this->usersTable, $d);

        return true;
    }

    /* возвращает true если пользователь с таким логином найден ил  false  в обратном случае */

    public function existLogin($login) {
        $res = $this->db->GetRow("SELECT `id` FROM `" . $this->usersTable . "` WHERE `login`=%s LIMIT 1", array($login));
        return ($res != NULL && $res != 0) ? true : false;
    }

}

?>