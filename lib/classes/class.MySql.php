<?php

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    //@session_start();
    include dirname(__FILE__) . '/../../../config.php';
}


define("DB_NAME", $db_base); // имя базы данных
define("DB_USER", $db_user); // имя пользователя
define("DB_PASSWORD", $db_pass); // пароль к бд
define("DB_HOST", $db_host); // адрес сервера баз данных
//define("DB_CHARSET", $config['dbcharset']); // кодировка бд
define("DB_COLLATE", "");
define("DB_DEBUG", false); // дебаг (true или false)
define("DB_MODE", 2); // 0 - в ручную откр/закр,
// 1 - автоматически при запросе (не рекомендуется, т.к. много конектов),
// 2 - открывает само и закрывает когда нужно (рекомендуется)


/*
  Менеджер баз данных
  Автор: Немиро Алексей
  08 марта 2007 года
  17 ноябра 2008 года
  Copyright (c) Nemiro AS, 2007-2008
  http://kbyte.ru/ru/Programming/Sources.aspx?id=1130&mode=show
  -----------------------------------------------------------------------
  | ExecuteNonQuery     | выполнить запрос                              |
  | ExecuteScalar       | выполнить и вернуть идентификатор             |
  | GetTable            | вернуть таблицу                               |
  | GetRow              | вернуть строку (через таблицу, для удобства)  |
  -----------------------------------------------------------------------
  | Run                 | ExecuteNonQuery                               |
  | ReturnId            | ExecuteScalar                                 |
  -----------------------------------------------------------------------



 */

class MySql {

    public $hostname;    // имя хоста
    public $db;       // имя базы
    public $user;      // логин
    public $password;    // пароль
    public $sql;      // запрос
    public $rowsCount;   // количество данных (строк)
    public $columnsCount;   // количество колонок
    public $errorMessage;  // сообщение об ошибке
    public $parameters;     // параметры
    public $openCloseMode; // рижим открытия/закрытия БД 
    // 0 - ручной (открывать/закрывать руками Open/Close);
    // 1 - автоматический (открыли и сразу закрыли);
    // 2 - автоматический (открыли соединение при инициализации и не закрыли (сам закроется, потом, мб :) ..))
    // рекомендуется использовать - 2
    public $conn;      // соединение
    public $debug;       // режим дебага
    public $queries;     // отчет по выполнению запросов (если включен дебаг)

    function MySql() {
        if (defined("DB_NAME")) {
            $this->db = DB_NAME;
        } else {
            $this->db = "";
        }
        if (defined("DB_USER")) {
            $this->user = DB_USER;
        } else {
            $this->user = "";
        }
        if (defined("DB_PASSWORD")) {
            $this->password = DB_PASSWORD;
        } else {
            $this->password = "";
        }
        if (defined("DB_HOST")) {
            $this->hostname = DB_HOST;
        } else {
            $this->hostname = "";
        }
        if (defined("DB_HOST")) {
            $this->hostname = DB_HOST;
        } else {
            $this->hostname = "";
        }
        if (defined("DB_MODE")) {
            $this->openCloseMode = DB_MODE;
        } else {
            $this->openCloseMode = 2;
        }
        if (defined("DB_DEBUG")) {
            $this->debug = DB_DEBUG;
        } else {
            $this->debug = false;
        }
        $this->errorMessage = "";
        $this->parameters = array();
        $this->queries = array();
        if ($this->openCloseMode === 2) { // создаем соединение
            $this->Open();
        }
    }

    // Функция экранирования переменных
    function quote_smart($value) {
        /* if (!get_magic_quotes_gpc()) 
          {
          return addslashes($value);
          } */
        // если magic_quotes_gpc включена - используем stripslashes
        if (get_magic_quotes_gpc()) {
            /* #$value = stripslashes($value); // удаляем экранирование */
        }
        // Если переменная - число, то экранировать её не нужно
        // если нет - то окружем её кавычками, и экранируем
        if (!is_numeric($value)) {
            $value = "'" . mysql_real_escape_string($value) . "'";
        }
        return $value;
    }

    // сборс параметров
    function Clear() {
        $this->parameters = NULL;
        $this->parameters = array();
    }

    // выполняет запрос и ничего не возвращает
    function Run($sql = NULL, $parameters = NULL) {
        $this->ExecuteNonQuery($sql, $parameters);
    }

    function ExecuteNonQuery($sql = NULL, $parameters = NULL) {
        if ($this->debug)
            $this->TimerStart();
        $this->errorMessage = "";
        if ($this->openCloseMode === 1)
            $this->Open();

        if ($sql == NULL)
            $sql = $this->sql;
        if ($parameters == NULL)
            $parameters = $this->parameters;

        $this->P2SQL($sql, $parameters);

        $r = @mysql_query($sql, $this->conn);
        if (!$r) {
            $this->errorMessage = "Неудалось выполнить запрос ExecuteNonQuery"; //mysql_error($this->conn);
        }

        if ($this->openCloseMode === 1)
            $this->Close();
        $this->Clear();
        if ($this->debug) {
            $this->queries[] = array($sql, $this->TimerStop());
        }
    }

    // выполняет запрос и возвращает id последней записи
    function ReturnId($sql = NULL, $parameters = NULL) {
        return $this->ExecuteScalar($sql, $parameters);
    }

    function ExecuteScalar($sql = NULL, $parameters = NULL) {
        if ($this->debug)
            $this->TimerStart();
        $this->errorMessage = "";
        if ($this->openCloseMode === 1)
            $this->Open();

        if ($sql == NULL)
            $sql = $this->sql;
        if ($parameters == NULL)
            $parameters = $this->parameters;

        $this->P2SQL($sql, $parameters);

        $r = @mysql_query($sql, $this->conn);
        if (!$r) {
            $this->errorMessage .= "<br />ExecuteScalar: Неудалось выполнить запрос"; //.mysql_error($this->conn);
            $lastId = -1;
        } else {
            $lastId = mysql_insert_id($this->conn);
        }

        if ($this->openCloseMode === 1)
            $this->Close();
        $this->Clear();
        if ($this->debug) {
            $this->queries[] = array($sql, $this->TimerStop());
        }

        return $lastId;
    }

    // выполняет запрос возвращает строку данных
    function GetRow($sql = NULL, $parameters = NULL) {
        $d = $this->GetTable($sql, $parameters);
        if (count($d) > 0) {
            return $d[0];
        }
        return NULL;
    }

    // выполняет запрос и возвращает ассициативный массив
    function GetTable($sql = NULL, $parameters = NULL) {
        if ($this->debug)
            $this->TimerStart();
        $this->errorMessage = "";
        if ($this->openCloseMode === 1)
            $this->Open();

        if ($sql == NULL)
            $sql = $this->sql;
        if ($parameters == NULL)
            $parameters = $this->parameters;
        $this->P2SQL($sql, $parameters);

        $r = @mysql_query($sql, $this->conn);

        $data = NULL;
        if (!$r) {
            $this->errorMessage = "Неудалось выполнить запрос GetTable"; //mysql_error($this->conn);
        } else {
            // вовзращаем результат выполнения запроса
            $this->rowsCount = mysql_num_rows($r);
            $this->columnsCount = mysql_num_fields($r);
            for ($i = 0; $i < $this->rowsCount; $i++) {
                $data[] = mysql_fetch_array($r);
            }
        }

        if ($this->openCloseMode === 1)
            $this->Close();
        $this->Clear();
        if ($this->debug) {
            $this->queries[] = array($sql, $this->TimerStop());
        }

        return $data;
    }

    // возвращает безопасный запрос
    function P2SQL(&$sql, $param) {
        if (!empty($param)) {
            foreach ($param as $k => $v) {
                $param[$k] = $this->quote_smart($v);
            }

            $sql = call_user_func_array('sprintf', array_merge((array) $sql, $param));
        }
        return $sql;
    }

    // открывает соединение
    function Open() {
        //if ($this->conn == NULL || !is_int($this->conn) || !mysql_thread_id($this->conn))
        //{
        // @ - отменяет вывод ошибок
        $this->conn = @mysql_connect($this->hostname, $this->user, $this->password);
        if (!$this->conn) {
            $this->errorMessage .= "<br />Open: ошикба mysql"; //.mysql_error($this->conn);
            return false;
        } else {
            mysql_query("set names utf8");
            mysql_query("SET AUTOCOMMIT=1;");
            mysql_select_db($this->db, $this->conn);
            return true;
        }
        /* else 
          {
          mysql_select_db($this->db, $this->conn);
          return true;
          } */
        //}
        //mysql_ping($this->conn);
    }

    // закрывает соединение
    function Close() {
        if ($this->conn != NULL && is_int($this->conn)) {
            if (mysql_ping($this->conn))
                mysql_close($this->conn);
        }
    }

    ###############################################################
    # Дебаг                                                       #
    ###############################################################

    function TimerStart() {
        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $this->timeStart = $mtime[1] + $mtime[0];
        return true;
    }

    function TimerStop() {
        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $timeEnd = $mtime[1] + $mtime[0];
        $timeTotal = $timeEnd - $this->timeStart;
        return $timeTotal;
    }

    // возвращает информацию об ошибках
    function GetLog() {
        $result = "";
        if ($this->errorMessage)
            $result = "<div style=\"background-color:#FF8080;border:solid 1px #FF0000;padding:2px;\">" . $this->errorMessage . "</div><br />";
        if (count($this->queries) > 0) {
            $totalTime = 0;
            $result .= "<div style=\"background-color:#8AF4F4;border:solid 1px #0FA4A4;padding:2px;\">";
            $result .= "<table width=\"100%\" cellpadding=\"2\" cellspace=\"0\" border=\"0\">";
            foreach ($this->queries as $k => $v) {
                $result .= "<tr><td style='width:30px;text-align:center;border-bottom:solid 1px #0FA4A4;'>" . ($k + 1) . "</td><td style='border-bottom:solid 1px #0FA4A4;'>$v[0]</td><td style=\"width:80px;border-bottom:solid 1px #0FA4A4;\">$v[1]</td></tr>";
                $totalTime += $v[1];
            }
            $result .= "</table>";
            $result .= "<strong>Всего затрачено времени: $totalTime сек.</strong>";
            $result .= "</div>";
        }
        return $result;
    }

    ###############################################################
}

?>