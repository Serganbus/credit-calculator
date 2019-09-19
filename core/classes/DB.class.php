<?php
class DB{
	/**
	 * @var SQL
	 */
	private static $db;
	private function __construct(){
		$cfg=Cfg::get('DB');
		if(empty($cfg['driver']) || $cfg['driver']=='mysql'){
			require_once('SQL.class.php');
			self::$db=new SQL($cfg['host'],$cfg['login'],$cfg['password'],$cfg['base']);
		}elseif($cfg['driver']=='pgsql'){
			require_once('PgSQL.class.php');
			self::$db=new SQL($cfg['host'],$cfg['login'],$cfg['password'],$cfg['base']);
		}
	}
	/**
	 * @return SQL
	 */
	static function getInstance(){
		if(empty(self::$db)){
			new self;
		}
		return self::$db;
	}
	/**
	 * @param string $q
	 * @param array $vars
	 * @return SQLResult
	 */
	static function select($q,$vars=array()){
		return self::getInstance()->select($q,$vars);
	}
	static function insert($table_name,$vars,$returning='id'){
		return self::getInstance()->insert($table_name,$vars,$returning);
	}
	static function update($table_name,$values,$condition=''){
		return self::getInstance()->update($table_name,$values,$condition);
	}
	static function delete($table_name,$condition=''){
		return self::getInstance()->delete($table_name,$condition);
	}
	static function execute($q){
		return self::getInstance()->execute($q);
	}
}
?>