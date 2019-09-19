<?php

class Xml2Array
{
	public $namespaces, $arr;

	public function __construct($xmlstring, $namespaces = null)
	{
		$xml = new simpleXmlIterator($xmlstring, null);
		$this->namespaces = is_null($namespaces) ? null : $xml->getNamespaces(true);
		$this->arr = $this->xmlToArray($xml, $namespaces);

	}

	/**
	 * @param simpleXmlIterator $xml
	 * @param null|array $namespaces
	 * @return array
	 */
	public function xmlToArray($xml, $namespaces = null)
	{
		$a = array();
		$xml->rewind();
		while ($xml->valid()) {
			$key = $xml->key();
			if (!isset($a[$key])) {
				$a[$key] = array();
				$i = 0;
			} else {
				$i = count($a[$key]);
			}
			$simple = true;
			foreach ($xml->current()->attributes() as $k => $v) {
				$a[$key][$i][$k] = (string)$v;
				$simple = false;
			}

			if ($this->namespaces) {
				foreach ($this->namespaces as $nid => $name) {
					foreach ($xml->current()->attributes($name) as $k => $v) {
						$a[$key][$i][$nid . ':' . $k] = ( string )$v;
						$simple = false;
					}
				}
			}
			if ($xml->hasChildren()) {
				if ($simple) $a[$key][$i] = $this->xmlToArray($xml->current(), $this->namespaces);
				else $a[$key][$i]['content'] = $this->xmlToArray($xml->current(), $this->namespaces);
			} else {
				if ($simple) {
					$a[$key][$i] = strval($xml->current());
				} else {
					$a[$key][$i]['content'] = strval($xml->current());
				}
			}
			$i++;
			$xml->next();
		}
		return $a;
	}
}
