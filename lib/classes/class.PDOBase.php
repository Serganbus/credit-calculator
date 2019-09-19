<?php

/**
 * Class PDOBase
 *
 * ~
 *    $row = db()->query('SELECT id, order_num, prevOrders FROM orders WHERE date > ? LIMIT ?')
 *        ->bind(1, 2)
 *        ->bind(2, 1)
 *        ->getList();
 * ~
 */
class PDOBase
{
	/** @var PDO */
	private $_dbh;

	/** @var PDOStatement */
	private $_stmt;

	/** @var string the SQL statement that this command represents */
	private $_sql;

	/**
	 * @var array pending parameters to be bound to the current PDO statement.
	 */
	private $_pendingParams = array();

	private $_lastSql;

	private static $_instance;

	/**
	 * @param array $config
	 * @return null|PDOBase
	 */
	public static function getInstance(array $config)
	{
		if (!static::$_instance)
			static::$_instance = new self($config);
		return static::$_instance;
	}

	public function __construct(array $config)
	{
		$this->_dbh = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['base'] . ';charset=utf8', $config['user'], $config['pass']);
		$this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	public function cancel()
	{
		$this->_stmt = null;
	}

	public function getSql()
	{
		return $this->_sql;
	}

	public function setSql($sql)
	{
		if ($sql !== $this->_sql) {
			$this->cancel();
			$this->_sql = $sql;
			$this->_pendingParams = array();
		}

		return $this;
	}

	public function prepare()
	{
		if ($this->_stmt) {
			$this->bindPendingParams();
			return;
		}

		$sql = $this->getSql();

		try {
			$this->_stmt = $this->_dbh->prepare($sql);
			$this->bindPendingParams();
		} catch (Exception $e) {
			$message = $e->getMessage() . "\nFailed to prepare SQL: $sql";
			$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
			throw new Exception($message, $errorInfo, (int)$e->getCode(), $e);
		}
	}


	protected function bindPendingParams()
	{
		foreach ($this->_pendingParams as $name => $value) {
			$this->_stmt->bindValue($name, $value[0], $value[1]);
		}
		$this->_lastSql = $this->getRawSql();
		$this->_pendingParams = array();
	}


	/**
	 * @param $query
	 * @param array $params
	 * @return int
	 * @throws Exception
	 */
	public function query($query, array $params = [])
	{
		$this->setSql($query)->bindValues($params);

		return $this->execute();
	}

	public function execute()
	{
		$sql = $this->getSql();

		if ($sql === '') {
			return 0;
		}

		$this->prepare();

		try {
			$this->_stmt->execute();
			return $this->_stmt->rowCount();
		} catch (Exception $e) {
			throw $e;
		}
	}


	/**
	 * @param array $values
	 * @return $this
	 */
	public function bindValues(array $values = [])
	{
		if (!count($values)) {
			return $this;
		}

		foreach ($values as $name => $value) {
			if (is_array($value)) {
				$this->_pendingParams[$name] = $value;
			} else {
				$type = self::getPdoType($value);
				$this->_pendingParams[$name] = array($value, $type);
			}
		}
		return $this;
	}


	/**
	 * @param $query
	 * @param array $params
	 * @return array|null
	 */
	public function getList($query, $params = array())
	{
		if (!$this->query($query, $params))
			return null;

		return $this->_stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @param $query
	 * @param array $params
	 * @return mixed|null
	 */
	public function one($query, $params = array())
	{
		if (!$this->query($query . ' LIMIT 1', $params))
			return null;
		return $this->_stmt->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * @param $query
	 * @param array $params
	 * @return mixed|null
	 */
	public function scalar($query, $params = array())
	{
		if (!$this->query($query . ' LIMIT 1', $params))
			return null;
		return $this->_stmt->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * @param $query
	 * @param array $params
	 * @return mixed|null
	 */
	public function column($query, $params = array())
	{
		if (!$this->query($query, $params))
			return null;
		return $this->_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	/**
	 * key => value
	 * `
	 * SELECT name, id FROM users
	 * // ['John' => '1', 'Mike' => '2','Mary' => '4']
	 * `
	 * @param $query
	 * @param array $params
	 * @return array|null
	 */
	public function indexedColumn($query, $params = array())
	{
		if (!$this->query($query, $params))
			return null;
		return $this->_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	/**
	 * `SELECT * FROM users`
	 * // ['John' => [ 'sex' => 'male', 'car' => 'Toyota','id'=>12], ...]
	 *
	 *
	 * @param $query
	 * @param array $params
	 * @return array|null
	 */
	public function indexedList($query, $params = array())
	{
		if (!$this->query($query, $params))
			return null;
		return $this->_stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
	}


	/**
	 * `SELECT sex, name, car FROM users`
	 * // [
	 *        'male' => ['name' => 'John', 'car' => 'Toyota'],
	 *    'female' => [..]
	 * ]
	 *
	 * @param $query
	 * @param array $params
	 * @return array|null
	 */
	public function getListGroup($query, $params = array())
	{
		if (!$this->query($query, $params))
			return null;
		return $this->_stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
	}

	/**
	 * @param $data
	 * @return int
	 */
	public static function getPdoType($data)
	{
		static $typeMap = array(
			'boolean' => PDO::PARAM_BOOL,
			'integer' => PDO::PARAM_INT,
			'string' => PDO::PARAM_STR,
			'resource' => PDO::PARAM_LOB,
			'NULL' => PDO::PARAM_NULL,
		);
		$type = gettype($data);

		return isset($typeMap[$type]) ? $typeMap[$type] : PDO::PARAM_STR;
	}

	public static function quoteSimpleTableName($name)
	{
		if (strpos($name, '`') !== false)
			return $name;

		$alias = '';
		if (strpos($name, ' ') !== false) {
			list($name, $alias) = explode(' ', $name);
			$alias = ' ' . $alias;
		}

		return '`' . $name . '`' . $alias;
	}

	public static function quoteSimpleColumnName($name)
	{
		if (strpos($name, '`') !== false)
			return $name;

		$table = '';
		if (strpos($name, '.') !== false) {
			list($table, $name) = explode('.', $name);
			$table = '`' . $table . '`.';
		} else {
			$name = '`' . $name . '`';
		}

		return $table . $name;
	}

	/**
	 * Returns the raw SQL
	 * @return string
	 */
	protected function getRawSql()
	{
		if (empty($this->_pendingParams)) {
			return $this->_sql;
		}
		$params = array();
		foreach ($this->_pendingParams as $name => $value) {
			if (is_string($name) && strncmp(':', $name, 1)) {
				$name = ':' . $name;
			}
			if (is_string($value)) {
				$params[$name] = $this->quoteValue($value);
			} elseif (is_bool($value)) {
				$params[$name] = ($value ? 'TRUE' : 'FALSE');
			} elseif ($value === null) {
				$params[$name] = 'NULL';
			} elseif (is_array($value)) {
				$params[$name] = $this->quoteValue($value[0]);
			} elseif (!is_object($value) && !is_resource($value)) {
				$params[$name] = $value;
			}
		}

		if (!isset($params[1])) {
			return strtr($this->_sql, $params);
		}
		$sql = '';
		foreach (explode('?', $this->_sql) as $i => $part) {
			$sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
		}
		return $sql;
	}

	public function quoteValue($str)
	{
		if (!is_string($str)) {
			return $str;
		}

		if (($value = $this->_dbh->quote($str)) !== false) {
			return $value;
		} else {
			return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
		}
	}

	/**
	 * Возвращает SQL последнего запроса
	 * @return mixed
	 */
	public function getLastSql()
	{
		return $this->_lastSql;
	}

	/**
	 * Последний вставленный Id
	 *
	 * @return int|null
	 */
	public function getLastInsetId()
	{
		$id = $this->_dbh->lastInsertId();
		return is_numeric($id) && $id > 0 ? (int)$id : null;
	}

	/**
	 * Возвращает все названия таблиц
	 * @return mixed|null
	 */
	public function getTables()
	{
		return $this->column('SHOW TABLES');
	}

	/**
	 * @return int
	 */
	public function getRowCount()
	{
		return $this->_stmt->rowCount();
	}
}