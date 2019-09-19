<?php

/**
 * Представляет доступ к таблице `credit_proposals`.
 * Описывает имеющиеся в системе кредитные предложения,
 * предлагаемые пользователям.
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class CreditProposals extends Model {
    
    public static function tableName() {
		return 'credit_proposals';
	}
    
    /*Хранит экземпляры объекта RepaymentSchedule*/
    public $repayment_schedules = [];
    
    public function toArray() {
        $repaymentsSchedules = [];
        foreach ($this->repayment_schedules as $rs) {
            $repaymentsSchedules[] = $rs->toArray();
        }
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'percent' => $this->percent,
            'sum_min' => $this->sum_min,
            'sum_max' => $this->sum_max,
            'rs' => $repaymentsSchedules
        ];
    }
    
    public function save() {
        $this->validateFields();
        
        $result = parent::save();
        if ($result !== false) {
            $this->updateRs2CpLinks();
        }
        
        return $result;
    }
    
    /**
	 * @param array|null $result
	 */
	protected function afterFind(array $result = null)
	{
        parent::afterFind($result);
        
        $rsIds = db()->column('SELECT rs_id FROM rs2cp WHERE cp_id=:cp_id', [':cp_id' => $this->id]);
        if ($rsIds) {
            foreach ($rsIds as $rsId) {
                $rs_inst = RepaymentSchedule::one($rsId);
                $this->repayment_schedules[] = $rs_inst;
            }
        }
	}
    
    private function validateFields() {
        if (strlen($this->title) > 64) {
            $this->title = substr($this->title, 0, 64);
        }
        $dateFrom = DateTime::createFromFormat('Y-m-d', $this->date_from);
        if (!$dateFrom) {
            $this->date_from = date('Y-m-d');
        }
        $dateTo = DateTime::createFromFormat('Y-m-d', $this->date_to);
        if (!$dateTo) {
            $this->date_to = date('Y-m-d');
        }
        $this->percent = (float)$this->percent;
        $this->sum_min = (int)$this->sum_min;
        $this->sum_max = (int)$this->sum_max;
    }
    
    private function updateRs2CpLinks() {
        db()->query("DELETE FROM rs2cp WHERE cp_id=:cp_id", [':cp_id' => $this->id]);
        foreach ($this->repayment_schedules as $schedule) {
            $schedule->save();
            db()->query('INSERT INTO rs2cp (cp_id, rs_id) VALUES (:cp_id, :rs_id)', [
                ':cp_id' => $this->id,
                ':rs_id' => $schedule->id
            ]);
        }
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

$ld1c1 = new RepaymentSchedule([
    'duration' => 1,
    'count' => 1
]);

$ld2c1 = new RepaymentSchedule([
    'duration' => 2,
    'count' => 1
]);

$ld3c1 = new RepaymentSchedule([
    'duration' => 5,
    'count' => 1
]);

$cp1 = new CreditProposals([
    'title' => 'Новая КП',
    'date_from' => '2016-01-01',
    'date_to' => '2016-03-01',
    'percent' => '0.05',
    'sum_max' => 30000
]);

$cp1->repayment_schedules[] = $ld1c1;
$cp1->repayment_schedules[] = $ld2c1;
$cp1->repayment_schedules[] = $ld3c1;
var_dump($cp1->save());

die('basic tests completed successfully!');
*//*...TESTS*/