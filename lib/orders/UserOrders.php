<?php

namespace admin\lib\orders;

/**
 * Description of UserOrders
 *
 * @author HP
 */
class UserOrders {
    
    private $userId;
    private $db;
    
    public function __construct($aUser_id) {
        $aUser_id = (int)$aUser_id;
        if (!isset($aUser_id)) {
            throw new Exception('Не передан обязательный параметр $aUser_id!');
        }
        if (!is_int($aUser_id)) {
            throw new Exception('$aUser_id не является целым числом! $aUser_id='.$aUser_id);
        }
        if ($aUser_id <= 0) {
            throw new Exception('$aUser_id меньше или равен нулю! $aUser_id='.$aUser_id);
        }
        $this->userId = $aUser_id;
        
        $this->db = new \MySql();
    }
    
    public function isSomeOrderTaken() {
        $result = array('result' => false, 'desc' => '');
        $lIsBanned_Arr_str = $this->db->getRow("SELECT `banned` FROM `users` WHERE `id`=%s", array($this->userId));
        $lIsBanned_bl = (bool)$lIsBanned_Arr_str['banned'];
        if (!$lIsBanned_bl) {
            $lastorder = $this->db->GetRow("
                SELECT 
                    o.status,
                    o.take_loan,
                    o.is_paid,
                    o.`date`,
                    o.`time`
                FROM 
                    orders o
                WHERE
                    o.user_id = %s 
                ORDER BY
                    `date` DESC, `time` DESC
                LIMIT 1
            ", array($this->userId));
            
            if (count($lastorder) > 0) {
                if ($lastorder['is_paid'] == '1') {
                    $result['result'] = true;
                } elseif ($lastorder['take_loan'] == '0') {
                    if ($lastorder['status'] == 'Т') {
                        $result['desc'] = 'Ваша заявка уже находится на рассмотрении.';
                    } elseif ($lastorder['status'] == 'О') {
                        $result['desc'] = 'Ваша заявка уже одобрена. Для получения денежных средств укажите платежные реквизиты и ждите перечисления.';
                    } elseif ($lastorder['status'] == 'Н') {
                        // если Н(не одобр.), то КД 1 месяц
                        $date_plus_3_mons = new DateTime($lastorder['date']);
                        if (!$m = (int) Cfg::get('next_order_period_if_deny')) {
                            $m = 1;
                        }

                        $date_plus_3_mons->modify("+{$m} month");
                        if ($date_plus_3_mons->format('Y-m-d') > date('Y-m-d')) {
                            $result['desc'] = 'С момента подачи Вашей прошлой заявки пока не прошел месяц.';
                        } else {
                            $result['result'] = true;
                        }
                    } elseif ($lastorder['status'] == 'З') {
                        $result['desc'] = 'Вам больше недоступна функция "Новая заявка". Более подробную информация Вы можете получить по телефону ' . Cfg::get('site_phone') . ' (звонок по России бесплатный).';
                    } else {
                        /*Дополнительная проверка: ищем договора, которые когда-либо не были закрыты клиентом*/
                        $openedOrders = $this->db->getRow("
                            SELECT 
                                count(`id`) as count
                            FROM 
                                orders
                            WHERE
                                `user_id`=%s
                                AND `take_loan`='1'
                                AND `is_paid`='0'
                        ", array($this->userId));
                        if ($openedOrders['count'] > 0) {
                            $result['desc'] = 'У вас есть незакрытые договора';
                        } else {
                            $result['result'] = true;
                        }
                    }
                } else {
                    $result['desc'] = 'Вы не сможете подать новую заявку до тех пор, пока не погасите займ по последнему кредиту.';
                }
            } else {
                $result['result'] = true;
            }
        } else {
            $result['desc'] = 'В связи с большой просрочкой займа в нашей компании Вы потеряли доверие и больше не можете пользоваться нашими услугами.';
        }
        return $result;
    }
    
    public function newOrder() {
        return "method is TBA";
    }
    
    public function getLastOrderInfo() {
        $lastorder = $this->db->GetRow("
		SELECT 
                    o.*,
                    IFNULL(`p`.quantity, 1) as quantity,
                    adddate(o.`date`,(IFNULL(p.quantity, 1)) * o.days) AS back_date
		FROM 
                    orders AS o
                    LEFT OUTER JOIN prolongation p ON (o.id = p.order_id)
		WHERE
                    `o`.`user_id`=%s
		ORDER BY
                    `date` DESC, `time` DESC
		LIMIT 1
	", array(
            $this->userId
        ));
        $lastorder['give_date'] = '';
        if ($lastorder['id'] != '') {
            $orderdate = $this->db->GetRow("
                    SELECT 
                        send_time
                    FROM 
                        sent_sms
                    WHERE
                        order_id = '%s'
		", array(
                $lastorder['id']
            ));
            if ($orderdate['send_time'] != '') {
                $give_date_array = explode(' ', $orderdate['send_time']);
                $lastorder['give_date'] = $give_date_array[0];
            }
        }
        return $lastorder;
    }
    
    public function getLastOrderId() {
        $lastorder = $this->db->GetRow("
		SELECT 
			o.id
		FROM 
			all_orders AS o
		WHERE
			`o`.`user_id`=%s
		ORDER BY
			`date` DESC, `time` DESC
		LIMIT 1
	", array(
            $this->userId
        ));
        return $lastorder['id'];
    }
    
    public function getOrdersInfo() {
        $orders = $this->db->GetTable("
            SELECT 
                `o`.`id`,
                `o`.`order_num`,
                `o`.`status`,
                `o`.`take_loan`,
                `o`.`date`,
                `o`.`time`,
                `o`.`sum_request`,
                `o`.`days`,
                `o`.`back_sum`,
                `o`.`is_paid`
            FROM 
                `all_orders` AS `o`
            WHERE
                `o`.`user_id`=%s 
            ORDER BY
                `date`DESC, `time`DESC
	", array(
            $this->userId
        ));
        return $orders;
    }
    
    public function getOrderInfo($o_id) {
        $order = new UserOrder($o_id);
        return $order->getInfo();
    }
    
    public function getOrder($o_id) {
        $order = new UserOrder($o_id);
        return $order;
    }
}
