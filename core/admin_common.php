<?php

@session_start();

if (!isset($settings)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/config.php';
}

include $_SERVER['DOCUMENT_ROOT'] . "/admin/core/functions.php";

if (!class_exists('Autoloader')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/core/Autoloader.class.php';
    Autoloader::Register();
}
Config::getInstance();
Cfg::add($settings);

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/lib/classes/class.history.php';

if (!isset($db)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/core/class.mysql.php';
    $db = new mysql();
}
global $no_check_user;
if (empty($no_check_user)) {//В форме авторизации изначально не проверять пользователя
    $admD = NULL;
    if (isset($_SESSION[$_SERVER['HTTP_HOST']]['admin']['login'])) {
        $user = $_SESSION[$_SERVER['HTTP_HOST']]['admin'] ['login'];
        $admD = $db->GetRow("SELECT `id`, `name`, `photo` FROM `adm_users` WHERE `login`='$user'");
        if ($admD == NULL) {
            die('Ошибка авторизации');
        } else {
            $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'] = intval($admD['id']);
            $_SESSION[$_SERVER['HTTP_HOST']]['admin']['name'] = $admD['name'];
            $_SESSION[$_SERVER['HTTP_HOST']]['admin']['photo'] = $admD['photo'];
        }
    } else {
        die('Ошибка авторизации');
    }
}


//$ans = $db->getTable("SELECT `id`, `title` FROM `adm_panel_sections`");
//$__sections = array();
//foreach ($ans as $row) {
//    $__sections[$row['title']] = $row['id'];
//}
define("SECTION_FIRST_LOAN", 1);//first loan
define("SECTION_RE_LOANS", 2);//re-loans
define("SECTION_TAKEN_LOANS", 3);//taken loans
define("SECTION_COLLECTING", 4);//collecting
define("SECTION_ACCOUNTANCY", 5);//accountancy
define("SECTION_CMS", 6);//settings
define("SECTION_UNLOADS", 7);//unloads
define("SECTION_REPORTS", 8);//reports
define("SECTION_EMPLOYERS", 9);//employers
define("SECTION_AUTH_RULES", 10);//users, authorization rules and permissions
define("SECTION_ANKETA", 11);//Регистрация клиента через админку
define("SECTION_KASSA", 12);//Раздел "Касса"
define("SECTION_CHAT", 13);//Чат. Иными словами онлайн-консультант
define("SECTION_NOTICE_REPORT", 14);//Архив уведомлений
define("SECTION_NOTICE_TEMPLATE", 15);//Шаблоны уведомлений
define("SECTION_SCORISTA_TQ", 16);//Скориста, вопросы правды
define("SECTION_CALL_CENTER", 17);//Раздел "История звонков"
define("SECTION_CLIENTS", 18);//Раздел "Клиенты"
define("SECTION_SCORING", 19);//Раздел "Скоринг"
//$ans = $db->getTable("SELECT `id`, `title` FROM `adm_links`");
//$__securedLinks = array();
//foreach ($ans as $row) {
//    $__securedLinks[$row['title']] = $row['id'];
//}
define("LINK_REMOVE_LOAN_REQUEST", 1);
define("LINK_CHANGE_POINTS_AND_STATUS", 2);
define("LINK_SKORISTA_SCORRING_REUQEST", 3);
define("LINK_SKORISTA_SCORRING_VIEW", 4);
define("LINK_GET_BANK_INFO", 5);
define("LINK_GET_LOAN_INFO", 6);
define("LINK_GET_CREDIT_HISTORY", 7);
define("LINK_REQUEST_NBKI",8);
define("LINK_REQUEST_EKVIFAKS",9);
define("LINK_GET_ANKETA_DATA", 10);
define("LINK_MODIFY_ANKETA_DATA", 11);
define("LINK_VIEW_COMMENTS", 12);
define("LINK_ADD_NEW_COMMENT", 13);
define("LINK_PRINT_DOCS", 14);
define("LINK_PRINT_ANKETA", 15);
define("LINK_PRINT_TREB", 16);
define("LINK_CHANGE_PAYMENT_STATUS", 17);
define("LINK_CORRECTION_BUTTONS",18);
define("LINK_SCR_BUTTONS", 19);
define("LINK_BACK_MONEY_BUTTON", 20);
define("LINK_TAKE_MONEY_BUTTON", 21);
define("LINK_GET_CRONOS_HISTORY", 22);//Permission for cronos history viewing/requesting
define("LINK_GET_FINKARTA_HISTORY",23);//Fin Karta
define("LINK_PROLONGATE_ORDER", 24);//Право производить реструктуризацию займа
define("LINK_PAY_ORDER", 26);//Право производить реструктуризацию займа
define("LINK_SIMPLE_SEARCH", 25);//Упрощенный поиск (по вхождению)

function isUserAuthorizedForSectionBrowsing($aSectionId, $aOptUserId = null) {
    global $db;
    if (!isset($aOptUserId) || $aOptUserId == null) {
        $aOptUserId = get_user_id();
    }
    $query = "
        SELECT permission 
        FROM adm_authorization_rules_for_sections
        WHERE adm_id = $aOptUserId AND section_id = $aSectionId
        LIMIT 1";
    $ans = $db->getRow($query);

    if ($ans && (bool) $ans['permission'] === true) {
        return true;
    } else {
        return false;
    }
}

function isUserHavePermissionForSectionLinkUsing($aSectionId, $aLinkId, $aOptUserId = null) {
    global $db;

    if (!isset($aOptUserId) || $aOptUserId == null) {
        $aOptUserId = get_user_id();
    }
    $query = "
        SELECT permission 
        FROM adm_authorization_rules_for_section_links
        WHERE adm_id=$aOptUserId AND section_id=$aSectionId AND link_id=$aLinkId
        LIMIT 1";
    $ans = $db->getRow($query);
    return $ans && (bool) $ans['permission'] === true;
}

function checkUserPermissionsForSectionLinkUsing($aSectionId, $aLinkId, $aOptUserId = null) {
    if (!isUserHavePermissionForSectionLinkUsing($aSectionId, $aLinkId, $aOptUserId)) {
        die("У вас недостаточно прав для просмотра содержимого");
    }
}

function get_user_id() {
    return (int) $_SESSION[$_SERVER['HTTP_HOST']]['admin']['id'];
}

function get_user($key = null) {
    global $_USER;
    $user = array();
    if (!empty($_USER)) {
        $user = $_USER;
    } else {
        $rs = DB::select("SELECT * FROM adm_users WHERE id=" . get_user_id());
        if ($rs->next()) {
            $user = $_USER = $rs->getRow();
        }
    }
    if ($key) {
        return isset($user[$key]) ? $user[$key] : null;
    }
    return $user;
}

function get_user_roles() {
    global $_USER_ROLES;
    $user_roles = array();
    if (!empty($_USER_ROLES)) {
        $user_roles = $_USER_ROLES;
    } else {
        $rs = DB::select("SELECT * FROM adm_users WHERE id=" . get_user_id());
        if ($rs->next() && $rs->get('role_id')) {
            $user_roles[] = $rs->get('role_id');
        }
        $rs = DB::select("SELECT * FROM adm_users2role WHERE user_id=" . get_user_id());
        while ($rs->next()) {
            $user_roles[] = $rs->get('role_id');
        }
    }
    return $_USER_ROLES = $user_roles;
}



function is_sadmin() {
    global $settings;
    $sa = array('admin');
    if (!empty($settings['adminPanel']['sadmin'])) {
        $sa = $settings['adminPanel']['sadmin'];
    }
    return in_array(get_user('login'), $sa);
}

function hide_str($str, $hide = false, $sym = 'X') {
    if ($hide) {
        return preg_replace('/[^\s\(\)\-\.]/', $sym, $str);
    }
    return $str;
}

function phone_format($phone) {
    return preg_replace('/^(\d{1})(\d{3})(\d{3})(\d{2})/', '+$1 ($2) $3-$4-', $phone);
}

function getStatData() {
    $data = array(
        'time' => date('H:i'),
        'orders' => 0,
        'orders_o' => 0,
        'orders_o_perc' => 0,
        'new_orders_perc' => 0,
        'take_loan' => 0,
        'take_loan_day' => 0,
        'sum_yesterday' => 0,
        'sum_day' => 0,
        'sum' => 0,
        'sum_avg' => 0,
        'paid' => 0,
        'paid_day' => 0,
        'paid_yesterday' => 0,
        'paid_avg' => 0,
        'stat_date_from' => date('d.m.Y', time() - 3600 * 24 * 30),
        'stat_date_to' => date('d.m.Y'),
    );
    $p_cond = '';
    $o_cond = '';
    $stat_date_from = $data['stat_date_from'];
    if (isset($_POST['stat_date_from'])) {
        $stat_date_from = $_POST['stat_date_from'];

        $o_cond.=" AND o.date>='" . dte($_POST['stat_date_from'], DTE_FORMAT_SQL) . "'";
        $p_cond.=" AND p.date>='" . dte($_POST['stat_date_from'], DTE_FORMAT_SQL) . "'";
    }
    $stat_date_to = $data['stat_date_to'];
    if (isset($_POST['stat_date_to'])) {
        $stat_date_to = $_POST['stat_date_to'];

        $o_cond.=" AND o.date<='" . dte($_POST['stat_date_to'], DTE_FORMAT_SQL) . "'";
        $p_cond.=" AND p.date<='" . dte($_POST['stat_date_to'], DTE_FORMAT_SQL) . "'";
    }
    $now_day_cond = " AND o.date='" . date('Y-m-d') . "'";
    $yesterday_cond = " AND o.date='" . date('Y-m-d', time() - 3600 * 24) . "'";
    $p_now_day_cond = " AND p.date='" . date('Y-m-d') . "'";
    $p_yesterday_cond = " AND p.date='" . date('Y-m-d', time() - 3600 * 24) . "'";
    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o WHERE  1=1 $o_cond");
    if ($rs->next()) {
        $data['orders']+=$rs->getInt('c');
    }
    //новые
    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o WHERE status='Т' $o_cond");
    if ($rs->next()) {
        $data['new_orders_perc'] = number_format($rs->getInt('c') / $data['orders'] * 100, 1);
    }
    //одобрено
    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o WHERE status='О' $o_cond");
    if ($rs->next()) {
        $data['orders_o']+=$rs->getInt('c');
    }
    if ($data['orders']) {
        $data['orders_o_perc'] = number_format($data['orders_o'] / $data['orders'] * 100, 1);
    }

    //Оплачено    
    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o WHERE is_paid='1' ");
    if ($rs->next()) {
        $data['paid']+=$rs->getInt('c');
    }

    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o,payments p WHERE p.order_id=o.id AND o.is_paid='1' $p_now_day_cond");
    if ($rs->next()) {
        $data['paid_day']+=$rs->getInt('c');
    }

    if ($data['paid']) {
        $data['paid_day_perc'] = number_format($data['paid_day'] / $data['paid'] * 100, 2);
    }
    $rs = DB::select("SELECT COUNT(*) AS c FROM all_orders o,payments p WHERE p.order_id=o.id AND o.is_paid='1' $p_yesterday_cond");
    if ($rs->next()) {
        $data['paid_yesterday']+=$rs->getInt('c');
    }

    $rs = DB::select("SELECT p.date,count(o.id) AS s FROM all_orders o,payments p WHERE  p.order_id=o.id AND o.is_paid='1' $p_cond GROUP BY p.date");
    $c = 0;
    $s = 0;
    while ($rs->next()) {
        $c++;
        $s+=$rs->getInt('s');
    }
    if ($c) {
        $data['paid_avg'] = number_format($s / $c, 2);
    }
    ///     
    //выдано сегодня
    $rs = DB::select("SELECT SUM(sum_request) AS s FROM all_orders o WHERE 1=1 AND o.take_loan='1' $now_day_cond");
    if ($rs->next()) {
        $data['take_loan_day']+=$rs->getInt('s');
    }
    //Выручка вчера
    $rs = DB::select("SELECT SUM(sum_request) AS s FROM all_orders o WHERE take_loan='1' $yesterday_cond");
    if ($rs->next()) {
//        $data['sum_yesterday'] -= $rs->getInt('s');//16.07.2015 сказали не надо
    }
    $rs = DB::select("SELECT SUM(paysum) AS s FROM payments p WHERE 1=1 $p_yesterday_cond");
    if ($rs->next()) {
        $data['sum_yesterday'] += $rs->getInt('s');
    }
    //Выручка сегодня
    $rs = DB::select("SELECT SUM(sum_request) AS s FROM all_orders o WHERE take_loan='1' $now_day_cond");
    if ($rs->next()) {
//        $data['sum_day'] -= $rs->getInt('s');//16.07.2015 сказали не надо
    }
    $rs = DB::select("SELECT SUM(paysum) AS s FROM payments p WHERE 1=1 $p_now_day_cond");
    if ($rs->next()) {
        $data['sum_day'] += $rs->getInt('s');
    }
    //Выручка
    $rs = DB::select("SELECT SUM(sum_request) AS s FROM all_orders o WHERE take_loan='1' $o_cond");
    if ($rs->next()) {
//        $data['sum']-= $rs->getInt('s');//16.07.2015 сказали не надо
    }
    $rs = DB::select("SELECT SUM(paysum) AS s FROM payments p WHERE 1=1 $p_cond");
    if ($rs->next()) {
        $data['sum']+= $rs->getInt('s');
    }

    $d = (strtotime($_POST['stat_date_to']) - strtotime($_POST['stat_date_from'])) / 3600 / 24;
    $data['sum_avg'] = number_format($data['sum'] / $d, 2);

    //Выручка сегодня
    $sum = array();
    $rs = DB::select("SELECT SUM(sum_request) AS s,date FROM all_orders o WHERE take_loan='1' $o_cond GROUP BY date");
    while ($rs->next()) {
        $sum_out[$rs->get('date')] = $rs->getInt('s');
    }
    $rs = DB::select("SELECT SUM(paysum) AS s,date FROM payments p WHERE 1=1 $p_cond GROUP BY date");
    while ($rs->next()) {
        $sum_in[$rs->get('date')] = $rs->getInt('s');
    }
    $d = strtotime($_POST['stat_date_from']);
    $d2 = strtotime($_POST['stat_date_to']);

    $data['sum_per_day'] = array();

    while ($d < $d2) {
        $dd = date('Y-m-d', $d);
        $data['sum_per_day'][$dd] = 0;
        if (isset($sum_out[$dd])) {
//            $data['sum_per_day'][$dd]-=$sum_out[$dd];//16.07.2015 сказали не надо
        }
        if (isset($sum_in[$dd])) {
            $data['sum_per_day'][$dd]+=$sum_in[$dd];
        }
        $d+=3600 * 24;
    }
    $data['browsers'] = array();
    if ($browsers = Yandex::getInstance()->getStatTech('browsers', dte($stat_date_from, 'Ymd'), dte($stat_date_to, 'Ymd'))) {
        $b = array();
        $bs = 0;
        foreach ($browsers['data'] as $row) {
            if (empty($b[$row['name']])) {
                $b[$row['name']] = 0;
            }
            $b[$row['name']]+=$row['visits'];
            $bs+=$row['visits'];
        }
        foreach ($b as $k => $v) {
            $data['browsers'][] = array('label' => $k, 'value' => number_format($v / $bs * 100, 1));
        }
    }

    $data['total_visits'] = 0;
    $data['visits'] = array();
    $data['second_visits'] = array();

    $data['month_visits'] = array();
//    $data['month_visits']=array(array(1,2),array(2,3));
    $data['month_second_visits'] = array();
    $data['visits_yesterday'] = 0;
    if ($visits = Yandex::getInstance()->getStat('traffic/summary', dte($stat_date_from, 'Ymd'), dte($stat_date_to, 'Ymd'))) {
        $data['total_visits'] = $visits['totals']['visitors'];
        $month_visits = array();
        $month_second_visits = array();
        foreach ($visits['data'] as $row) {
            if ($row['date'] == date('Ymd', time() - 3600 * 24)) {
                $data['visits_yesterday'] = $row['visitors'];
            }
            $data['visits'][] = $row['visitors'];
            $data['second_visits'][] = $row['visitors'] - $row['new_visitors'];

            $month = preg_replace('/\d{4}(\d{2})\d{2}/', '\1', $row['date']) - 1;
            $data['month'] = $month;

            if (empty($month_visits[$month])) {
                $month_visits[$month] = 0;
            }
            if (empty($month_second_visits[$month])) {
                $month_second_visits[$month] = 0;
            }
            $month_visits[$month]+=$row['visitors'];
            $month_second_visits[$month]+=$row['visitors'] - $row['new_visitors'];
        }
        foreach ($month_visits as $k => $v) {
            $data['month_visits'][] = array($k, $v);
        }
        foreach ($month_second_visits as $k => $v) {
            $data['month_second_visits'][] = array($k, $v);
        }
    }




    $status = array();
    if (isset($_POST['status'])) {
        $status = $_POST['status'];
    }
    $status1 = array();
    if (isset($_POST['status1'])) {
        $status1 = $_POST['status1'];
    }
    $data['orders_address'] = getOrdersAddress($stat_date_from, $stat_date_to, $status, $status1);
    return $data;
}

function getCommentsList($search = '') {
    $q = "SELECT * FROM orders_comments c,adm_users u WHERE u.id=c.adm_users_id ";
    if ($search) {
        $q.=" AND (u.name LIKE '%" . SQL::slashes($search) . "%' OR comment_text LIKE '%" . SQL::slashes($search) . "%')";
    }
    $q.=" ORDER BY id DESC LIMIT 20";
    return array_reverse(DB::select($q)->toArray());
}

function getContactsList($search = '') {
    $q = "SELECT * FROM adm_users";
    if ($search) {
        $q.=" WHERE name LIKE '%" . SQL::slashes($search) . "%'";
    }
    return DB::select($q)->toArray();
}

function getOrdersAddress($dte_from = '', $dte_to = '', $status = array(), $status1 = array()) {
    $cond = '';
    if ($dte_from) {
        $cond.=" AND date>='" . dte($dte_from, DTE_FORMAT_SQL) . "'";
    }
    if ($dte_to) {
        $cond.=" AND date<='" . dte($dte_to, DTE_FORMAT_SQL) . "'";
    }
    if ($status) {
        $cond.=" AND o.status IN('" . implode("','", $status) . "')";
    }
    $orCondArr = array();
    if (in_array(1, $status1)) {
        $orCondArr[] = "(o.take_loan =0 AND o.is_paid =0)";
    }
    if (in_array(3, $status1)) {
        $orCondArr[] = " o.take_loan =1";
    }
    if (in_array(2, $status1)) {
        $orCondArr[] = " o.is_paid =0 ";
    }
    if ($orCondArr) {
        $cond.=" AND (" . implode(' OR ', $orCondArr) . ")";
    }
    $q = "
        SELECT 
            u.*,
            uac.*
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        LEFT JOIN users_address_coordinates uac ON uac.user_id = o.user_id
        WHERE uac.type='prop'" . $cond;

    $addrs = array();
    $rs = DB::select($q);
    while ($rs->next()) {
        $addr = array(
            'lat' => $rs->get('latitude'),
            'lng' => $rs->get('longitude')
        );
        $addrs[] = $addr;
    }
    return $addrs;
}

function get_comments_template($name) {
    $out = array();
    $rs = DB::select("SELECT * FROM comments_template WHERE name='$name'");
    while ($rs->next()) {
        $out[] = $rs->get('text');
    }
    return $out;
}

function render_template($data = array(), $tplName) {
    foreach ($data as $k => $v) {
        $$k = is_string($v) ? htmlspecialchars($v) : $v;
    }
    ob_start();

    $tpl = 'admin/tpl/' . $tplName;
    include($_SERVER['DOCUMENT_ROOT'] . '/' . $tpl . '.tpl.php');

    $output = ob_get_contents();
    ob_end_clean();
    return $output;
}

function getPostIndexByCityAndRegion($city, $region) {
    global $db;
    $result = "101000";
    $query = "select `INDEX` from zip_code where `city` like '%s' and `REGION` like '%s' limit 1";
    $res = $db->getRow($query, array($city, $region));
    if ($res == null) {
        $query = "select `INDEX` from zip_code where `REGION` like '%s' limit 1";
        $res1 = $db->getRow($query, array($city));
        if ($res1 == null) {
            $query = "select `INDEX` from zip_code where `city` like '%s' limit 1";
            $res2 = $db->getRow($query, array($city));
            if ($res2 == null) {
                $query = "select `INDEX` from zip_code where `city_1` like '%s' limit 1";
                $res3 = $db->getRow($query, array($city));
                if ($res3 == null) {
                    $city1 = preg_replace('/[^a-zа-яё]+/iu', ' ', $city);
                    $city1 = preg_replace('/ {2,}/', ' ', $city1);
                    $city1 = trim($city1);
                    $query = "select `INDEX` from zip_code where `OPSNAME` like '%s' limit 1";
                    $res4 = $db->getRow($query, array($city1));
                    if ($res4 == null) {
                        $city_arr = explode(" ", $city1);
                        $where = '(`OPSNAME` like \'%' . $city_arr[0] . '%\')';
                        for ($i = 1; $i < sizeof($city_arr); $i++) {
                            if (strlen($city_arr[$i]) > 3) {
                                $where = 'OR (`OPSNAME` like \'%' . $city_arr[$i] . '%\')';
                            }
                        }
                        $query = "select `INDEX` from zip_code where " . $where . " limit 1";
                        $res5 = $db->getRow($query);
                        if ($res5 != null) {
                            $result = $res5['INDEX'];
                        }
                    } else {
                        $result = $res4['INDEX'];
                    }
                } else {
                    $result = $res3['INDEX'];
                }
            } else {
                $result = $res2['INDEX'];
            }
        } else {
            $result = $res1['INDEX'];
        }
    } else {
        $result = $res['INDEX'];
    }
    return $result;
}

//=======
/**
 * @param string $str
 * @return string
 */
function trimmToStr($str) {
    $str = preg_replace('/^\s+|\s+$/', '', $str);
    $str = preg_replace('/\s{2,}/', '', $str);
    $str = str_replace(array("\r", "\n", "\t"), '', $str);

    return $str;
}

/**
 * Считает количество полных лет с даты
 *
 * @param $birthdayDate
 * @return string
 */
function getFullYears($birthdayDate) {
    $datetime = new DateTime($birthdayDate);
    $interval = $datetime->diff(new DateTime(date("Y-m-d")));
    return (int) $interval->format("%Y");
}

function custom_path($path) {
    $custom = str_replace(DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR, '/custom/admin/', $path);
    if (file_exists($custom)) {
        return $custom;
    }
    return $path;
}
