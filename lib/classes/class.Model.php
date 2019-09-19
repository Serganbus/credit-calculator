<?php

class Model extends Object
{
	protected static $joinTypes = array('inner', 'left', 'right', 'full');
	protected static $_defaultConditionSplit = 'AND';

	/**
	 * Имя таблицы (возможно с алиалом)
	 * @return string
	 */
	public static function tableName()
	{
		return get_called_class();
	}

	/**
	 * Возвращает единичную строку
	 *
	 * `
	 * HttpResponse::one("`requestId` = :requestId", array(':requestId' => 12));
	 * `
	 * @param $condition
	 * @param array $params
	 * @param string|null $select
	 * @param string|null $orderBy
	 * @return Model
	 * @throws Exception
	 */
	public static function one($condition, $params = array(), $select = null, $orderBy = null)
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		if (is_numeric($condition)) {
			$params[':id'] = $condition;
			$condition = 'id = :id';
		}

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		if ($select === null)
			$select = '*';

		if ($orderBy !== null)
			$orderBy = ' ORDER BY ' . $orderBy;

		$table = PDOBase::quoteSimpleTableName(static::tableName());

		if (!$result = db()->one('SELECT ' . $select . ' FROM ' . $table . $condition . $orderBy, $params))
			return null;

		$class = get_called_class();

		return self::setModel($class, $result);
	}

	/**
	 * Возвращает массив выборки
	 *
	 * @param null $condition
	 * @param array $params
	 * @param null|string $select
	 * @param null|array|string $join
	 * @param null|string $orderBy
	 * @param boolean $asArray Возвращает массив или объект класса Order
	 * @param int $limit Лимит
	 * @return Order[]|array|null
	 * @throws Exception
	 */
	public static function all($condition = null, $params = array(), $select = null, $join = null, $orderBy = null, $limit = null, $asArray = false)
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		if (empty($select))
			$select = '*';

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		$join = self::joinToText($join);

		if (!empty($limit) && is_numeric($limit))
			$limit = ' LIMIT ' . $limit;

		if ($orderBy !== null)
			$orderBy = ' ORDER BY ' . $orderBy;

		$table = PDOBase::quoteSimpleTableName(static::tableName());

		if (!$result = db()->getList('SELECT ' . $select . ' FROM ' . $table . $join . $condition . $orderBy . $limit, $params))
			return array();

		if (!$asArray) {
			$class = get_called_class();

			foreach ($result as &$row) {
				$row = self::setModel($class, $row);
			}
		}

		return $result;
	}

	/**
	 * Возвращает одно значение
	 *
	 * @param null $select
	 * @param null $condition
	 * @param array $params
	 * @return mixed|null
	 * @throws Exception
	 */
	public static function scalar($select = null, $condition = null, $params = array())
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		if ($select === null)
			$select = '*';

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		$table = PDOBase::quoteSimpleTableName(static::tableName());
		return db()->scalar('SELECT ' . $select . ' FROM ' . $table . $condition, $params);
	}

	/**
	 * Возвращает массив из только одной колонки
	 *
	 * @param $column
	 * @param null $condition
	 * @param array $params
	 * @return array|null
	 * @throws Exception
	 */
	public static function column($column, $condition = null, $params = array())
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		$column = PDOBase::quoteSimpleColumnName($column);
		$table = PDOBase::quoteSimpleTableName(static::tableName());
		return db()->column('SELECT ' . $column . ' FROM ' . $table . $condition, $params);
	}

	public static function indexedColumn($key, $value, $condition = null, $params = array())
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		$key = PDOBase::quoteSimpleColumnName($key);
		$value = PDOBase::quoteSimpleColumnName($value);
		$table = PDOBase::quoteSimpleTableName(static::tableName());

//		dump('SELECT ' . $key . ', ' . $value . ' FROM ' . $table . $condition);
		return db()->indexedColumn('SELECT ' . $key . ', ' . $value . ' FROM ' . $table . $condition, $params);
	}

	public static function indexedList($key, array $columns = [], $condition = null, $params = array(), $join = null, $orderBy = null,$limit = null)
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		if (!empty($columns)) {
			$columns = array_map(function ($col) {
				return PDOBase::quoteSimpleColumnName($col);
			}, $columns);
			$columns = implode(',', $columns);
		} else
			$columns = static::getAlias() . '.*';

		if (!empty($limit) && is_numeric($limit))
			$limit = ' LIMIT ' . $limit;

		if ($orderBy !== null)
			$orderBy = ' ORDER BY ' . $orderBy;

		$join = self::joinToText($join);

		$key = PDOBase::quoteSimpleColumnName($key);
		$table = PDOBase::quoteSimpleTableName(static::tableName());

		if (!$result = db()->indexedList('SELECT ' . $key . ', ' . $columns . ' FROM ' . $table . $join . $condition . $orderBy . $limit, $params))
			return array();

		$class = get_called_class();

		foreach ($result as &$row) {
			$row = self::setModel($class, $row);
		}

		return $result;
	}

	public static function listGroup($key, array $columns = [], $condition = null, $params = array(), $orderBy = null, $asArray = true)
	{
		if (static::tableName() === null)
			throw new Exception('Не указана таблица');

		$condition = self::conditionToText($condition);

		if (!empty($condition))
			$condition = ' WHERE ' . $condition;

		if (count($columns)) {
			$columns = array_map(function ($col) {
				return PDOBase::quoteSimpleColumnName($col);
			}, $columns);
			$columns = implode(',', $columns);
		} else
			$columns = static::getAlias() . '.*';

		if ($orderBy !== null)
			$orderBy = ' ORDER BY ' . $orderBy;

		$key = PDOBase::quoteSimpleColumnName($key);
		$table = PDOBase::quoteSimpleTableName(static::tableName());

		if (!$result = db()->getListGroup('SELECT ' . $key . ', ' . $columns . ' FROM ' . $table . $condition . $orderBy, $params))
			return [];

		if (!$asArray) {
			$class = get_called_class();

			foreach ($result as &$group) {
				foreach ($group as &$row) {
					$row = self::setModel($class, $row);
				}
			}
		}
		return $result;
	}

	public static function delete($id)
	{
		$idColumn = PDOBase::quoteSimpleColumnName('id');
		$table = PDOBase::quoteSimpleTableName(static::tableName());

		return db()->query("DELETE FROM {$table} WHERE {$idColumn}=:id", [':id' => $id]);
	}


	/*protected static function getAlias()
	{
		$table = static::tableName();

		if (strpos($table, ' ') !== false) {
			list(, $alias) = explode(' ', $table);
			return $alias;
		}
		return null;
	}*/

	/**
	 * Возвращает только имя таблицы без алиаса
	 * @return null|string
	 */
	protected static function tableNameClear()
	{
		return self::_getListOfTableName('table');
	}

	/**
	 * Возвращает алиас таблицы
	 * @return null|string
	 */
	protected static function getAlias()
	{
		return self::_getListOfTableName('alias');
	}

	/**
	 * @param $type
	 * @return null|string
	 */
	private static function _getListOfTableName($type)
	{

		$t = static::tableName();

		if (strpos($t, ' ') !== false) {
			list($table, $alias) = explode(' ', $t);
			return $$type;
		} elseif ($type==='table')
			return $t;
		return null;
	}

	/**
	 * @param string|array $condition
	 * @return mixed
	 */
	private static function conditionToText($condition)
	{
		if (is_array($condition)) {
			$cond = '';
			foreach ($condition as $key => $value) {
				if (is_array($value)) {
					$cond .= ' ' . $value[0] . ' (' . $value[1] . ') ';
				} else {
					$cond .= ($key ? ' ' . static::$_defaultConditionSplit : false) . ' (' . $value . ') ';
				}
			}
			$condition = preg_replace('/(\s+)/', ' ', $cond);
		}

		return $condition;
	}

	/**
	 * @param array $join
	 * @return string
	 */
	private static function joinToText($join)
	{
		$result = '';

		if (!empty($join)) {
			if (is_array($join)) {
				if (is_array($join[0])) {
					foreach ($join as $j) {
						$result .= self::joinToText($j);
					}
				}

				if (in_array($join[0], self::$joinTypes)) {
					$joinTbl = PDOBase::quoteSimpleTableName($join[1]);
					$result .= $join[0] . ' join  ' . $joinTbl . ' ON ' . $join[2];
				}
			}

		}
		$result = ' ' . $result;
		return $result;
	}

	/**
	 * @param $class
	 * @param array $attributes
	 * @return Model
	 */
	private static function setModel($class, array $attributes)
	{
		/** @var Model $model */
		$model = new $class();
		$model->afterFind($attributes);
		return $model;
	}

	private $_attributes = [];

	public function __construct($config = [])
	{
		$table = PDOBase::quoteSimpleTableName(static::tableNameClear());
              
		$tableFields = db()->column('DESCRIBE ' . $table);

		$this->setAttributes($tableFields);

		parent::__construct($config);
	}

	public function __get($name)
	{
		if (isset($this->_attributes[$name]) || array_key_exists($name, $this->_attributes)) {
			return $this->_attributes[$name];
		} elseif ($this->hasAttribute($name)) {
			return null;
		} else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value)
	{
		if ($this->hasAttribute($name)) {
			$this->_attributes[$name] = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	/**
	 * @return mixed
	 */
	public function save()
	{
        if ($this->getAttribute('id')) {
            return $this->update();
        } else {
            return $this->insert();
        }
	}

	/**
	 * Обновляет запись
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function update()
	{
		if (!$this->hasAttribute('id'))
			throw new Exception('Not found ID');

		$table = PDOBase::quoteSimpleTableName(static::tableNameClear());
		$fieldsList = $values_arr = [];

		foreach ($this->_attributes as $field => $value) {
            if ($field === 'id') {
                continue;
            }
			$fld = PDOBase::quoteSimpleColumnName($field);
			$fieldsList[] = $fld . '=:' . $field;
			$values_arr[':' . $field] = $value;
		}

		$idFld = PDOBase::quoteSimpleColumnName('id');
        $values_arr[':id'] = $this->id;
		$fieldsList = implode(', ',$fieldsList);
		$query_str = "UPDATE {$table} SET {$fieldsList} WHERE {$idFld}=:id";
		return db()->query($query_str, $values_arr);
	}

	/**
	 * Вставляет запись
	 * @return int|false primary key вставленной записи
	 */
	public function insert()
	{
		$table = PDOBase::quoteSimpleTableName(static::tableNameClear());
		$fieldsList = $values_arr = $fieldsValuesList = [];

		foreach ($this->_attributes as $field => $value) {
			$fld = PDOBase::quoteSimpleColumnName($field);
			$fieldsList[] = $fld;
			$fieldsValuesList[] = ':' . $field;
			$values_arr[':' . $field] = $value;
		}

		$fieldsList = implode(', ',$fieldsList);
		$fieldsValuesList = implode(', ',$fieldsValuesList);

		$query_str = "INSERT INTO {$table} ({$fieldsList}) VALUES ({$fieldsValuesList})";

		if (!$result = db()->query($query_str, $values_arr))
			return false;

		return $this->id = db()->getLastInsetId();
	}

	public function hasAttribute($name)
	{
		return array_key_exists($name, $this->_attributes) || in_array($name, $this->attributes());
	}

	/**
	 * @return array
	 */
	public function attributes()
	{
		$class = new ReflectionClass($this);
		$names = array();
		foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if (!$property->isStatic()) {
				$names[] = $property->getName();
			}
		}

		return $names;
	}

	/**
	 * @param $name
	 * @return null
	 */
	public function getAttribute($name)
	{
		return isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
	}

	/**
	 * @param array|null $result
	 */
	protected function afterFind(array $result = null)
	{
		if ($result === null)
			return;

		$this->setAttributes(array_keys($result));

		foreach ($result as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * @param array $columns
	 */
	private function setAttributes(array $columns)
	{
		foreach ($columns as $name) {
			$this->_attributes[$name] = null;
		}
	}

}