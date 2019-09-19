<?php

function getSMSTextWhenMoneySendedToClient($aOrderId) {
    global $db;
    
    $orderRow = $db->GetRow("
        SELECT *
        FROM
            `orders`
        WHERE 
            `id` = %s
        LIMIT 1	
    ", array($aOrderId));
    
    $userRow = $db->GetRow("
        SELECT *
        FROM
            `users`
        WHERE 
            `id` = %s
        LIMIT 1	
    ", array($orderRow['user_id']));
    
    $cardRow = $db->GetRow("
        SELECT *
        FROM
            `cards`
        WHERE 
            `id` = %s
        LIMIT 1
    ", array($orderRow['take_type']));
    
    $date = date('Y-m-d',  strtotime("+{$orderRow['days']} day"));
    
    $dateArr = explode('-', $date);
    $monthes = array(
        '01' => 'января',
        '02' => 'февраля',
        '03' => 'марта',
        '04' => 'апреля',
        '05' => 'мая',
        '06' => 'июня',
        '07' => 'июля',
        '08' => 'августа',
        '09' => 'сентября',
        '10' => 'октября',
        '11' => 'ноября',
        '12' => 'декабря'
    );
    
    if ($orderRow['paytype'] == '1' || $orderRow['paytype'] == '2') {
        $cardType = 'банковскую карту';
        if ($cardRow['is_ym_ewallet']) {
            $cardType = 'яндекс-кошелек';
        }

        $text = 'Вам отправлен займ №' . $orderRow['order_num'] . ' на '
                . $cardType . ' №' . $cardRow['number'] . '. '
                . 'Сумма '.$orderRow['sum_request'].' руб. '
                . 'Погасить до ' . $dateArr[2] . ' ' . $monthes[$dateArr[1]] . ' '.$dateArr[0].'г. включительно.';
    } elseif ($orderRow['paytype'] == '4') {
        $text = 'Вам отправлен займ №' . $orderRow['order_num'] . ' через платежную систему БЕСТ. '
                . 'Сумма '.$orderRow['sum_request'].' руб. '
                . 'Погасить до ' . $dateArr[2] . ' ' . $monthes[$dateArr[1]] . ' '.$dateArr[0].'г. включительно.';
    }
    return $text;
}

function sendSMSIfMoneySended($aOrderId, $aPhone, $aOptEmail = null) {
    global $db;
    $get_sms_controller_order_type = $db->GetRow("SELECT order_type FROM sms_collector WHERE order_id = ".$aOrderId." AND order_type > 0");              
    if($get_sms_controller_order_type==NULL){
        $db->Run("UPDATE `orders` SET `date`=%s,`time`=%s WHERE `id`=%s LIMIT 1", array(Date('Y-m-d'),Date('H:i:s'), $aOrderId)); 
    }
    $_POST['order_id'] = $aOrderId;
    $_POST['phone'] = $aPhone;
    $_POST['text'] = getSMSTextWhenMoneySendedToClient($aOrderId);
    if (!is_null($aOptEmail)) {
        $_POST['email'] = $aOptEmail;
    }
    $_POST['action'] = 'sendTakenLoan';
	
    include $_SERVER["DOCUMENT_ROOT"].'/admin/plugins/results/back/scr/sms.scr.php';
}