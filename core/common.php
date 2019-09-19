<?php

function __autoload($class_name) {
    $path = ROOT . '/core/class.' . $class_name . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

function set_client_id() {
    if (!empty($_COOKIE['Client_id'])) {
        $uid = $_COOKIE['Client_id'];
    } else {
        $uid = md5(uniqid());
    }
    setcookie('Client_id', $_COOKIE['Client_id'] = $uid, time() + 3600 * 24 * 365, '/');
}

function get_client_id() {
    return @$_COOKIE['Client_id'];
}

function clearURL($url) {
    return preg_replace("/[^a-zA-Z\d]^_/", "", $url);
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

function redirect($url) {
    if (isAjaxRequest()) {
        echo json_encode(array('redirect' => $url));
        exit;
    } else {
        header('Location: ' . $url);
    }
}

// 1989-08-31 -> 31.08.1989
function convert_date($str) {
    $strMass = explode('-', $str);
    $str = $strMass[2] . '.' . $strMass[1] . '.' . $strMass[0];
    if ($strMass[2] == '00' and $strMass[1] == '00' and $strMass[1] == '0000') {
        $str = '';
    }
    return $str;
}

function convert_date_to_db($str) {
    $strMass = explode('.', $str);
    return $strMass[2] . '-' . $strMass[1] . '-' . $strMass[0];
}

function now() {
    return date('Y-m-d H:i:s');
}

define('DTE_FORMAT_SQL', 'Y-m-d');

function dte($date, $format = 'd.m.Y') {
    if (is_int($date)) {
        return date($format, $date);
    } elseif ($date = strtotime($date)) {
        return date($format, $date);
    }
    return '';
}

function number($str) {
    return preg_replace('/\D/', '', $str);
}
function mobile($str_phone) {
    return number($str_phone);
}

function checkRus($str) {
    if (!preg_match('/^[А-Яа-я]+( ?- ?[А-Яа-я]+)?$/u', $str)) {
        return false;
    }
    return true;
}

function getMaskCardNum($card) {
    $s = substr($card, 0, 3);
    for ($i = 0; $i < strlen($card) - 7; $i++) {
        if ($i == 1 or $i == 5) {
            $s .= " ";
        }
        $s .= "*";
    }
    $s .= " ";
    $s .= substr($card, strlen($card) - 4, strlen($card));
    return $s;
}

function show_message($message) {
    return '<div class="' . $message[0] . '">' . $message[1] . '</div>';
}

function logAriusIntegration($log) {
    DB::insert('arius_integration_log', array('log'=>$log));
}

function clearFIO($str) {
    $s1 = strpos($str, '(');
    $s2 = strpos($str, ')');
    if ($s2 > $s1) {
        $s3 = $s2 - $s1 + 1;
        $str = substr_replace($str, '', $s1, $s3);
    }
    return trim(mb_convert_case($str, MB_CASE_TITLE, 'UTF-8'));
}

function getGender($patronymic) {
    if (preg_match('/(ич|оглы)$/ui', $patronymic)) {
        return 'male';
    } elseif (preg_match('/(на|кызы)$/ui', $patronymic)) {
        return 'female';
    } else {
        return 'unknow';
    }
}

/**
 * Склоняем словоформу
 */
function morph($n, $f1, $f2, $f5) {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20)
        return $f5;
    if ($n1 > 1 && $n1 < 5)
        return $f2;
    if ($n1 == 1)
        return $f1;
    return $f5;
}

/**
 * Сумма прописью
 * @author runcore
 */
function num2str($inn, $stripkop = false, $morph = true) {
    $nol = 'ноль';
    $str[100] = array('', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот');
    $str[11] = array('', 'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать', 'двадцать');
    $str[10] = array('', 'десять', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто');
    $sex = array(
        array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'), // m
        array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять') // f
    );
    $forms = array(
        array('копейка', 'копейки', 'копеек', 1), // 10^-2
        array('рубль', 'рубля', 'рублей', 0), // 10^ 0
        array('тысяча', 'тысячи', 'тысяч', 1), // 10^ 3
        array('миллион', 'миллиона', 'миллионов', 0), // 10^ 6
        array('миллиард', 'миллиарда', 'миллиардов', 0), // 10^ 9
        array('триллион', 'триллиона', 'триллионов', 0), // 10^12
    );
    $out = $tmp = array();
    // Поехали!
    $tmp = explode('.', str_replace(',', '.', $inn));
    $rub = number_format($tmp[0], 0, '', '-');
    if ($rub == 0)
        $out[] = $nol;
    // нормализация копеек
    $kop = isset($tmp[1]) ? substr(str_pad($tmp[1], 2, '0', STR_PAD_RIGHT), 0, 2) : '00';
    $segments = explode('-', $rub);
    $offset = sizeof($segments);
    if ((int) $rub == 0) { // если 0 рублей
        $o[] = $nol;

        $o[] = morph(0, $forms[1][0], $forms[1][1], $forms[1][2]);
    } else {
        foreach ($segments as $k => $lev) {
            $sexi = (int) $forms[$offset][3]; // определяем род
            $ri = (int) $lev; // текущий сегмент
            if ($ri == 0 && $offset > 1) {// если сегмент==0 & не последний уровень(там Units)
                $offset--;
                continue;
            }
            // нормализация
            $ri = str_pad($ri, 3, '0', STR_PAD_LEFT);
            // получаем циферки для анализа
            $r1 = (int) substr($ri, 0, 1); //первая цифра
            $r2 = (int) substr($ri, 1, 1); //вторая
            $r3 = (int) substr($ri, 2, 1); //третья
            $r22 = (int) $r2 . $r3; //вторая и третья
            // разгребаем порядки
            if ($ri > 99)
                $o[] = $str[100][$r1]; // Сотни
            if ($r22 > 20) {// >20
                $o[] = $str[10][$r2];
                $o[] = $sex[$sexi][$r3];
            } else { // <=20
                if ($r22 > 9)
                    $o[] = $str[11][$r22 - 9]; // 10-20
                elseif ($r22 > 0)
                    $o[] = $sex[$sexi][$r3]; // 1-9
            }
            // Рубли
            if ($morph)
                $o[] = morph($ri, $forms[$offset][0], $forms[$offset][1], $forms[$offset][2]);
            $offset--;
        }
    }
    // Копейки
    if (!$stripkop) {
        $o[] = $kop;
        if ($morph)
            $o[] = morph($kop, $forms[0][0], $forms[0][1], $forms[0][2]);
    }
    return preg_replace("/\s{2,}/", ' ', implode(' ', $o));
}

function month_list() {
    $month_text = array('Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
    return $month_text;
}

function month_by_list() {
    $month_text = array('Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря');
    return $month_text;
}

function fdate($date) {
    $date1 = explode("-", $date);
    $mm = month_by_list();
    return $date1[2] . " " . $mm[$date1[1]] . " " . $date1[0] . ' г.';
}

/**
 * Текстовое содержимое HTML по названию
 * @param string $name
 * @param array $data
 * @return string
 * @todo Поправить путь для редактирования
 */
function get_html($name, $data = array()) {
    $path = ROOT . "/storage/html/{$name}.html";

    $out = '';
    if (is_admin()) {
        //$out.='<a class="afk-edit" href="/admin/path_to_edit_'.$path.'">Редактировать</a>';
        $out .= '<a class="afk-edit" href="/admin/?id=1&panel=1main!pageContentManagement^getFileContent=' . $path . '">Редактировать</a>';
    }
    if (file_exists($path)) {
        $html = file_get_contents($path);
        foreach ($data as $k => $v) {
            $html = str_replace("{{$k}}", $v, $html);
        }
        $out .= $html;
    }
    return $out;
}

function get_ref($name) {
    $path = $_SERVER['DOCUMENT_ROOT'] . "/storage/ref/{$name}.csv";
    $out = array();
    if (file_exists($path)) {
        $fp = fopen($path, 'r');
        while (($data = fgetcsv($fp, 1000, ';')) !== false) {
            $out[] = $data;
        }
        fclose($fp);
        return $out;
    }
    return $out;
}

function get_ref_assoc($name) {
    $out = array();
    foreach (get_ref($name) as $row) {
        if ($row[1]) {
            $out[$row[0]] = $row[1];
        }
    }
    return $out;
}

function is_img($f) {
    return preg_match('/(jpe?g|png|gif)(\?.*)?$/i', $f) !== 0;
}

function is_pdf($f) {
    return preg_match('/(pdf)(\?.*)?$/i', $f) !== 0;
}

function file_ext($path) {
    return preg_replace('!.+\.([^\.]+)$!', '\1', $path);
}

function is_admin() {
    return !empty($_SESSION[$_SERVER['HTTP_HOST']]['admin']['id']);
}

function event_deffence($name = 'anysubmit', $timeout = 60) {
    if (isset($_SESSION['event'][$name])) {
        if ($_SESSION['event'][$name] + $timeout > time()) {
            return $_SESSION['event'][$name] + $timeout - time();
        }
    }
    $_SESSION['event'][$name] = time();
    return 0;
}

function get_protocol() {
    $p = 'http';
    if ($_SERVER['SERVER_PORT'] != 80) {
        $p = 'https';
    }
    return $p;
}

function print_jsonp($data, $cb = '_cb') {
    if (isset($_GET['_cb'])) {
        $cb = $_GET['_cb'];
    }
    return '<html><body><script type="text/javascript">parent.' . $cb . '(' . json_encode($data) . ')</script></body></html>';
}

function file_upl_err($code, $require = false) {
    if ($code == 0) {
        
    } elseif ($code == UPLOAD_ERR_INI_SIZE) {//1
        return 'превышен размер ' . ini_get('upload_max_filesize');
    } elseif ($code == UPLOAD_ERR_FORM_SIZE) {//2
        return 'превышен размер ' . $_POST['MAX_FILE_SIZE'];
    } elseif ($code == UPLOAD_ERR_PARTIAL) {//3
        return 'файл был получен только частично';
    } elseif ($require && $code == UPLOAD_ERR_NO_FILE) {//4
        return 'не был загружен';
    } elseif ($code > 4) {
        return 'ошибка загрузки';
    }
}

function check_post_max_size() {
    $max = ini_get('post_max_size');

    $max_s = $max;
    if (preg_match('/m/i', $max_s)) {
        $max_s = preg_replace('/\D/', '', $max_s);
        $max_s *= 1024 * 1024;
    }

    $s = strlen(file_get_contents("php://input"));
    if ($s > $max_s) {
        $s /= 1024 * 1024;
        $s = round($s);
        return "Превышен размер отправляемых данных {$s}M >$max";
    }
}

/**
 * Возвращает куку
 * @param null $name
 * @return null
 */
function getCookies($name = null) {
    if ($name === null)
        return $_COOKIE;
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
}

/**
 * Устанавливает куку
 * @param $name
 * @param $value
 * @param null|int $timeDays Количество дней куки
 * @param bool|true $override
 */
function setCookies($name, $value, $timeDays = 30, $override = true) {
    if (getCookies($name) && !$override) {
        return;
    }
    setcookie($name, $value, time() + $timeDays * 3600 * 24, '/');
}
