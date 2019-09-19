<?php

/**
 * Class CreditPolicy
 *
 * Кредитная политика
 */
class CreditPolicy extends Object
{
	/** @var int Амнистия в днях (сколько можно не платить до наступления штрафа) */
	protected $amnesty;

	/** @var int Единоразовый Штраф в рублях */
	protected $fineSinglePay;

	/** @var int просрочка процентов в день */
	protected $finePerDay;

	/** @var int просрочка процентов в год */
	protected $finePerYear;

	/** @var  DateTime|string Дата, на которую вызывается кредитная политика */
	public $date;

	public function init()
	{
		parent::init();

		if ($creditPolicy = $this->_getCustomCreditPolicy()) {
			if (!$this->date)
				$this->date = new DateTime();
			elseif (is_string($this->date))
				$this->date = new DateTime($this->date);


			$this->_findCurrentCreditPolicy($creditPolicy);
		} else {
			$this->finePerDay = 0.001;
			$this->finePerYear = 0.2;
			$this->fineSinglePay = 2000;
			$this->amnesty = 3;
		}
	}

	protected function _getCustomCreditPolicy()
	{
		return Cfg::get('credit_policy');
	}

	protected function _findCurrentCreditPolicy($creditPolicy)
	{
		krsort($creditPolicy);
		foreach ($creditPolicy as $d => $policy) {
			if ($this->date->format('Y-m-d') >= $d) {
				$this->_applyCurrentCreditPolicy($policy);
				break;
			}
		}
	}

	protected function _applyCurrentCreditPolicy($creditPolicy)
	{
		if (isset($creditPolicy['amnesty'])) $this->amnesty = $creditPolicy['amnesty'];
		if (isset($creditPolicy['fine']['single_pay'])) $this->fineSinglePay = $creditPolicy['fine']['single_pay'];
		if (isset($creditPolicy['fine']['pday'])) $this->finePerDay = $creditPolicy['fine']['pday'];
		if (isset($creditPolicy['fine']['pyear'])) $this->finePerYear = $creditPolicy['fine']['pyear'];
	}

	public function getFinePerYear()
	{
		return $this->finePerYear;
	}

	public function getFinePerDay()
	{
		return $this->finePerDay;
	}

	public function getFineSinglePay()
	{
		return $this->fineSinglePay;
	}

	public function getAmnesty()
	{
		return $this->amnesty;
	}
}
