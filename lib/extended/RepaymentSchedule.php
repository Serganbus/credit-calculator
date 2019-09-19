<?php

/**
 * Предоставляет доступ к таблице `repayment_schedules`.
 * Описывает доступные графики погашения кредита без привязки
 * к конкретным параметрам(дата получения кредита, сумма и т.д.)
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RepaymentSchedule extends Model {
    
    public static function tableName() {
		return 'repayments_schedules';
	}
    
    public function getDurationInDays() {
        return $this->duration * $this->count;
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'duration' => $this->duration,
            'count' => $this->count,
            'in_days' => $this->getDurationInDays()
        ];
    }
    
    public function __toString() {
        $duration_str = $this->duration.' '.morph($this->duration, 'день', 'дня', 'дней');
        $count_str = $this->count.' '.morph($this->count, 'погашение', 'погашения', 'погашений');
        return $count_str.'; '.'Длительность периода: '.$duration_str;
    }
    
}

/*TESTS...*//*
function __autoload($className) {
	$fname = $_SERVER['DOCUMENT_ROOT'] . '/admin/lib/classes/class.' . $className . '.php';

	if (file_exists($fname))
		include_once($fname);

	else if (file_exists($fname = $_SERVER['DOCUMENT_ROOT'] . '/admin/lib/extended/' . $className . '.php')) {
		include_once($fname);
	}
}
include $_SERVER['DOCUMENT_ROOT'] . "/admin/core/admin_common.php";
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

$ld1c1 = new RepaymentSchedule();
$ld1c1->duration = 1;
$ld1c1->count = 1;
var_dump($ld1c1->save());

$ld14c1 = new RepaymentSchedule();
$ld14c1->duration = 1;
$ld14c1->count = 1;
var_dump($ld14c1->save());
$ld14c1->duration = 14;
var_dump($ld14c1->save());

$ld7c3 = new RepaymentSchedule();
$ld7c3->duration = 7;
$ld7c3->count = 3;
var_dump($ld7c3->save());

die('basic tests completed successfully!');
*//*...TESTS*/