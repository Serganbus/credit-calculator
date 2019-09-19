<?php

namespace admin\lib\orders;

class UserOrder {

    private $userOrderAlgoritm = null;

    function __call($name, $arguments) {
        return call_user_func_array(array($this->userOrderAlgoritm, $name), $arguments);
    }
    /**
     * 
     * @param mixed $params
     * @return UserOrderStandart
     */
    static function getPrototype($params){
    	return new self($params);
    }

	/**
	 * @param $params
	 */
    public function __construct($params) {
        $date = null;
        if (!is_array($params)) {
           $q = "
                SELECT 
                    o.*,
                    adddate(o.`date`, o.days) AS back_date,
                    adddate(o.`date`, o.days) AS back_date_without_prolong,
                    IFNULL(`p`.quantity, 1) as quantity,
                    adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days) AS back_date_with_prolong,
                    p.date AS prolongation_date
                FROM 
                    `orders` AS `o`
                    LEFT OUTER JOIN prolongation p ON (o.id = p.order_id)
                WHERE
                    `o`.`id`=$params
            ";
            $rs = \DB::select($q);
            if ($rs->next()) {
                $params = $rs->getRow();
            }
        }

        if (!empty($params['date'])) {
            $date = $params['date'];
        }
        $cp = self::getCreditPolicy($date);
        
        $class_name = "admin\lib\orders\UserOrderStandard";
        if (!empty($cp['algoritm']) && $cp['algoritm']!='Standart') {
            $algoritm = ucfirst($cp['algoritm']);
            $class_name = "custom\orders\UserOrder{$algoritm}";
        }
        $this->userOrderAlgoritm = new $class_name($params);
    }

    static function getCreditPolicy($date = null) {
        $current_policy = array(
            'fine' => array(
                'pday' => 0.1 / 100,
                'pyear' => 0.2,
            )
        );
        if (!$date) {
            $date = date('Y-m-d');
        }
        $credit_policy = \Cfg::get('credit_policy');
        if ($credit_policy) {
            foreach (array_reverse($credit_policy) as $d => $policy) {
                if ($date >= $d) {
                    $current_policy = $policy;
                    break;
                }
            }
        }
        return $current_policy;
    }

}
