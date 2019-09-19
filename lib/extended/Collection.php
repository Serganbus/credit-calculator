<?php


/**
 * Class Collection
 */
class Collection extends Object implements IteratorAggregate, Countable
{

	protected $_data = array();

	public function getIterator()
	{
		return new ArrayIterator($this->_data);
	}

	public function count()
	{
		return $this->getCount();
	}

	public function getCount()
	{
		return count($this->_data);
	}

	public function get($id)
	{
		if (isset($this->_data[$id])) {
			return $this->_data[$id];
		}
		return null;
	}

	public function set($id, array $value)
	{
		$this->_data[$id] = $value;

		return $this;
	}


	public function has($id)
	{
		return isset($this->_data[$id]);
	}


	public function remove($id)
	{
		if (isset($this->_data[$id])) {
			$value = $this->_data[$id];
			unset($this->_data[$id]);
			return $value;
		}
		return null;
	}

	public function removeAll()
	{
		$this->_data = array();
	}


	public function toArray()
	{
		return $this->_data;
	}

	public function fromArray(array $array)
	{
		$this->_data = $array;
	}
}