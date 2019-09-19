<?php

class SQLResult {

    private $result = null;
    private $counter = 0;
    private $row;
    private $rows = 0;

    function SQLResult($result, $rows = 0) {
        $this->result = $result;
        $this->rows = $rows;
    }

    /**
     * @return integer
     */
    function getNum() {
        return $this->counter - 1;
    }

    /**
     * @return integer
     */
    function getCount() {
        return $this->rows;
    }

    /**
     * @return boolean
     */
    function hasNext() {
        return $this->counter < $this->getCount();
    }

    /**
     * @return boolean
     */
    function next() {
        if ($this->row = mysql_fetch_assoc($this->result)) {
            $this->counter++;
            return true;
        }
        return false;
    }

    /**
     * @param string $key
     * @return mixed
     */
    function get($key) {
        return isset($this->row[$key]) ? $this->row[$key] : null;
    }

    /**
     * @param string $key
     * @return string
     */
    function getString($key) {
        return isset($this->row[$key]) ? $this->row[$key] : '';
    }

    /**
     * @return array
     */
    function getRow() {
        return $this->row;
    }

    /**
     * @param string $key
     * @return int
     */
    function getInt($key) {
        return isset($this->row[$key]) ? intval($this->row[$key]) : 0;
    }

    /**
     * @param string $key
     * @return float
     */
    function getFloat($key) {
        return isset($this->row[$key]) ? (float) floatval($this->row[$key]) : null;
    }

    function reset() {
        mysql_data_seek($this->result, 0);
    }

    function toArray() {
        $res = array();
        while ($this->next())
            $res[] = $this->getRow();
        return $res;
    }

}

class SQL {

//	const MYSQL_KEY_EXISTS_ERROR_NUM=1062;
    function __construct($sqlhost = null, $login = null, $password = null, $base = null) {
        if ($sqlhost && $login && $password !== null && $base) {
            $this->connect($sqlhost, $login, $password, $base);
        }
    }

    private $m_queryCount = 0;
    private $m_queryErrorNum = null;
    private $m_queryError = null;
    private $m_insertId = 0;
    public $m_showError = true;
    public $m_connect = null;

    /**
     * @return integer
     */
    function getCount() {
        return $this->m_queryCount;
    }

    /**
     * @param string $str
     * @return string
     */
    static function slashes($str) {
//		if(get_magic_quotes_gpc()){
//			$str = stripslashes ( $str );
//		}
        $str = mysql_escape_string($str);
        return $str;
    }

    function connect($sqlhost, $login, $password, $base = null) {
        global $query_report, $query_time, $query_count;
        $t1 = microtime(true);
        if (!$this->m_connect = mysql_connect($sqlhost, $login, $password, true)) {
            echo "Cannot connect MySQL DB!<br>";
            echo mysql_error();
            exit;
        }


        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{connect MySQL DB}" . "\n";
        $query_time+=$time;

        $t1 = microtime(true);
        if ($base !== null) {
            $this->selectDB($base);
//			mysql_query("SET NAMES 'cp1251'");
            mysql_query("set names utf8");
            mysql_query("SET AUTOCOMMIT=1;");
        }

        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{SET NAMES 'cp1251'}" . "\n";
        $query_time+=$time;
    }

    function selectDB($dbname) {
        mysql_select_db($dbname, $this->m_connect);
    }

    static function report($message) {
        if(class_exists('Log')){
            Log::write($message);
        }
        $message = str_replace('\\', '\\\\', $message);
        $message = str_replace("\n", '<br>', $message);
        $message = str_replace("\r", '', $message);
        $message = str_replace("\"", '\"', $message);
        ?><script type="text/javascript">
                            var w = window.open("", "Error", 'width=800,height=600,status=yes,resizable=yes,top=200,left=200');
                            w.document.write("<?= $message ?>");
        </script><?
        exit;
    }

    /**
     * @param string $query
     * @return SQLResult
     */
    function select($query, $vars = array()) {
        global $query_report, $query_time, $query_count;
        $t1 = microtime(true);

        foreach ($vars as $k => $v) {
            if (is_string($v)) {
                $v = "'" . SQL::slashes($v) . "'";
            }
            $query = str_replace(":$k", $v, $query);
        }


        $result = mysql_query($query, $this->m_connect);
        $error = mysql_error($this->m_connect);
        if (strlen($error) > 0 && $this->m_showError === true) {
            $exeption = new Exception($error);
            $message = $exeption->getTraceAsString() . '\n\n' . $error . '\n\n' . $query;
            SQL::report($message);
        }
        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{" . $query . "}" . "\n";
        $query_time+=$time;
        return new SQLResult($result, mysql_num_rows($result));
    }

    function selectRow($query) {

        $rs = $this->select($query);
        if ($rs->next()) {
            return $rs->getRow();
        }
        return null;
    }

    /**
     * @param string $query
     * @return SQLResult
     */
    function execute($query) {
        global $query_report, $query_time, $query_count;
        $t1 = microtime(true);

        $result = mysql_query($query, $this->m_connect);
        $error = mysql_error($this->m_connect);
        if (strlen($error) > 0 && $this->m_showError === true) {
            $exeption = new Exception($error);
            $message = $exeption->getTraceAsString() . '\n\n' . $error . '\n\n' . $query;
            SQL::report($message);
        }
        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{" . $query . "}" . "\n";
        $query_time+=$time;
        return $result;
    }

    function executeInsert($query) {
        global $query_report, $query_time, $query_count;
        $t1 = microtime(true);

        $result = mysql_query($query, $this->m_connect);
        $error = mysql_error($this->m_connect);
        $this->m_queryError = $error;
        $this->m_queryErrorNum = mysql_errno($this->m_connect);

        if (strlen($error) > 0 && $this->m_showError === true) {
            $exeption = new Exception($error);
            $message = $exeption->getTraceAsString() . '\n\n' . $error . '\n\n' . $query;
            SQL::report($message);
        }

        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{" . $query . "}" . "\n";
        $query_time+=$time;
        return mysql_insert_id($this->m_connect);
    }

    function executeDelete($query) {
        global $query_report, $query_time, $query_count;
        $t1 = microtime(true);
        $result = mysql_query($query, $this->m_connect);
        $error = mysql_error($this->m_connect);
        $this->m_queryError = $error;
        $this->m_queryErrorNum = mysql_errno($this->m_connect);

        if (strlen($error) > 0 && $this->m_showError === true) {
            $exeption = new Exception($error);
            $message = $exeption->getTraceAsString() . '\n\n' . $error . '\n\n' . $query;
            SQL::report($message);
        }
        $time = microtime(true) - $t1;
        $query_count++;
        $query_report.=$time . ":{" . $query . "}" . "\n";
        $query_time+=$time;
//		mysql_query("COMMIT");
        return mysql_affected_rows($this->m_connect);
    }

    function insert($table_name, $vars) {
        if (!is_array($vars)) {
            return false;
        }
        foreach ($vars as $key => $val) {
            if (is_int($key)) {
                $tmp = explode('=', $val);
                $names[] = '`' . trim($tmp[0]) . '`';
                $vals[] = trim($tmp[1]);
            } else {
                $names[] = '`' . $key . '`';
                $vals[] = '\'' . SQL::slashes($val) . '\'';
            }
        }
        $names_string = implode(', ', $names);
        $vals_string = implode(', ', $vals);

        $InsertSQL = 'INSERT INTO `' . $table_name . '` (' . $names_string . ') VALUES (' . $vals_string . ')';
        return $this->executeInsert($InsertSQL);
    }

    function insertArr($table_name, $arr) {
        if (!is_array($arr) || !$arr) {
            return false;
        }
        $rowNum = 0;
        foreach ($arr as $vars) {
            if ($rowNum == 0) {
                $rowNum++;
                $names_string = implode(', ', array_keys($vars));
            }
            foreach ($vars as &$val) {
                $val = '\'' . SQL::slashes($val) . '\'';
            }
            $vals_string[] = implode(', ', $vars);
        }

        $InsertSQL = 'INSERT INTO `' . $table_name . '` (' . $names_string . ') VALUES ';
        $rowNum = 0;
        foreach ($vals_string as $row) {
            if ($rowNum++)
                $InsertSQL.=', ';
            $InsertSQL.="($row)";
        }
        return $this->executeInsert($InsertSQL);
    }

    function delete($table_name, $condition = '') {
        $SQL = 'DELETE FROM `' . $table_name . '` ' . (($condition) ? ' WHERE ' . $condition : '');
        return $this->executeDelete($SQL);
    }

    function update($table_name, $values, $condition = '') {
        if (!is_array($values)) {
            return false;
        }
        foreach ($values as $key => $val) {
            if (is_int($key)) {
                $names_ar[] = $val;
            } else {
                if($val===null){
                    $names_ar[] = "`".$key ."`". ' = NULL';
                }else{
                    $names_ar[] = "`".$key ."`" . ' = \'' . SQL::slashes($val) . '\'';
                }
                
            }
        }
        $names_string = implode(', ', $names_ar);
        $UpSQL = 'UPDATE ' . $table_name . ' SET ' . $names_string . (($condition) ? ' WHERE ' . $condition : '');
        return $this->executeUpdate($UpSQL);
    }

    function getOne($query) {
        $rs = $this->select($query);
        if ($rs->next()) {
            foreach ($rs->getRow() AS $v)
                return $v;
        }
        return '';
    }

    function executeUpdate($query) {
        return $this->executeDelete($query);
    }

    /**
     * return last auto incremented id value
     *
     * @return int
     */
    function getInsertId() {
        return $this->m_insertId;
    }

    /**
     * Display query error
     *
     */
    function showQueryError() {
        if (strlen($this->m_queryError) > 0) {
            echo "<font color='red' size='3'>MySQL ERROR: " . $this->m_queryError . "</font><br /><b>" . $this->m_queryString . "</b>";
        }
    }

    function getQueryErrorNum() {
        return $this->m_queryErrorNum;
    }

}
?>