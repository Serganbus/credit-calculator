<?php

/**
 * Description of ScoristaReport
 *
 * @author    efureev<efureev@yandex.ru>
 */
class ScoristaReport
{
	/** @var null|DecisionScoristaResponse */
	private $_response = null;

	/** @var string */
	private $_reportTemplatePath = "/tpl/answer.tpl";

	/** @var string */
	private $_reportTemplate = '';

	/** @var array Рублевое значение */
	private static $_typeValueRub = array(
		'overdue', 'overdue30', 'outstanding', 'closedSum30'
	);

	private static $_typeValueProc = array(
		'shareOfOverdueLoans30','shareOfOverdueLoans'
	);

	/** @var array Булево значение */
	private static $_typeValueYesNo = array(
		'cellularInCH', 'homePhoneInCH', 'workPhoneInCH'
	);

	private static $_typeValueDays = array(
		'timeCellular'
	);

	public function __construct(DecisionScoristaResponse $response)
	{
		$this->_response = $response;

		$fn = __DIR__ . $this->_reportTemplatePath;
		if (!file_exists($fn))
			throw new Exception('Нет файла-шаблона для ответа Скористы');

		$this->_reportTemplate = file_get_contents($fn);

		$this->summary();
	}

	/**
	 * Возвращает сгенерированный html
	 * @return string
	 */
	public function getHtml()
	{
		return $this->_reportTemplate;
	}

	private function summary()
	{
		$this->setStatusUser();
		$this->setDecision();
		$this->setCreditHistoryContent();
		$this->setTrustRatingContent();
		$this->setRiskEvaluationContent();
		$this->setPaymentContent();
		$this->setReliabilityLoanReceivingMethodContent();
		$this->setFraudFactorsContent();
		$this->setStopFactorsContent();
		$this->setTruthContent();
		$this->setFsspContent();
	}

	private function setDecision()
	{
		$cls = 'danger';
		$icls = 'fa-frown-o';
		$value = 'Отказ';

		if ($this->_response->getDecision('decisionBinnar')) {
			$cls = 'success';
			$value = 'Одобрено';
			$icls = 'fa-smile-o';
		}

		$html = '<div class="alert h4 alert-' . $cls . '"> <i class="fa ' . $icls . '"></i>&nbsp;' . $value . '</div>';
		$this->_reportTemplate = str_replace('<%DECISION_PART%>', $html, $this->_reportTemplate);
	}

	private function setStatusUser()
	{
		$request = $this->_response->getRequest();

		$orderId = $request['form']['info']['loan']['loanID'];
		$userId = $request['form']['persona']['personalInfo']['personaID'];

		$countLoansRepaid = db()->scalar('SELECT count(*) FROM orders WHERE id != :id AND user_id = :uid AND `status` =:status',
			array(
				':id' => $orderId,
				':uid' => $userId,
				':status' => 'О'
			)
		);

		$html = '<div>' . ($countLoansRepaid > 0 ? 'Вторичный' : 'Первичный') . ' заемщик</div><small>' . morph($countLoansRepaid, 'закрыт', 'закрыто', 'закрыто') . ' ' . $countLoansRepaid . ' ' . morph($countLoansRepaid, 'займ', 'займа', 'займов') . '</small>';
		$this->_reportTemplate = str_replace('<%USER_STATUS_PART%>', $html, $this->_reportTemplate);
	}

	private function setCreditHistoryContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['creditHistory']['result']);

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Оценка
							добросовестности
						</div>
						<h4 class="block">Качество кредитной истории</h4>

						<div class="row">
							<div class="col-md-4">
								<div class="note note-default"> <i class="fa fa-check-square-o"></i>
									Хорошая кредитная история <h3 style="text-align: center;">' . $data['creditHistory']['goodCreditHistory']['result'] . '%</h3>
								</div>

							</div>
							<div class="col-md-4">
								<div class="note note-default"> <i class="fa fa-check-square-o"></i>
									Неопределенная кредитная история <h3 style="text-align: center;">' . $data['creditHistory']['unknownCreditHistory']['result'] . '%</h3>
								</div>

							</div>
							<div class="col-md-4">
								<div class="note note-default"> <i class="fa fa-check-square-o"></i>
									Негативная кредитная история <h3 style="text-align: center;">' . $data['creditHistory']['negativeCreditHistory']['result'] . '%</h3>
								</div>
							</div>
							</div>
						<h4 class="block">Балл добросовестности</h4>

						<div class="row">
							<div class="col-md-4">
								<div style="text-align:center;" class="note note-default">
									Балл NPL15+
									<h3>' . (empty($data['additional']['creditHistory']['score']) ? '-' : $data['additional']['creditHistory']['score']) . '</h3>
								</div>
							</div>
							<div class="col-md-4">
								<div style="text-align:center;" class="note note-default">
									Балл NPL45+
									<h3>' . (empty($data['additional']['creditHistory']['score2']) ? '-' : $data['additional']['creditHistory']['score2']) . '</h3>
								</div>
							</div>
							<div class="col-md-4">
								<div style="text-align:center;" class="note note-default">
									Балл NPL90+
									<h3>' . (empty($data['additional']['creditHistory90']['creditHistory']['result']) ? '-' : $data['additional']['creditHistory90']['creditHistory']['result']) . '</h3>
								</div>
							</div>
						</div>';

		if (isset($data['additional']['creditHistory'])) {
			$i = 0;

			foreach ($data['additional']['creditHistory'] as $name => $value) {
				if (!is_array($value) || $name === 'phones')
					continue;

				$i++;
				$suffix = '';

				if ($i % 2 !== 0)
					$html .= '<div class="row">';

				if (in_array($name, self::$_typeValueRub)) {
					$suffix = ' руб.';
				} else if (in_array($name, self::$_typeValueProc)) {
					$suffix = ' %';
					$value['result'] = $value['result'] * 100;
				}

				$html .= '<div class="col-md-6"><div class="note note-info"><h4>' . $value['description'] . ': ' . formatNumberRus($value['result']) . $suffix . '</h4></div></div>';

				if ($i % 2 === 0)
					$html .= '</div>';
			}


			if (isset($data['additional']['creditHistory']['phones'])) {
				$html .= '<div class="row"><div class="col-md-12"><div class="col-md-12"><div class="note note-info"><h4>Телефоны из кредитной истории:</h4><div>';
				$html .= '<table class="table table-striped"><thead><tr><th>Телефон</th><th>Тип</th><th>Дата записи</th></tr></thead><tbody>';


				foreach ($data['additional']['creditHistory']['phones']['result'] as $phone) {
					$html .= '<tr><td>' . formatPhone($phone['number']) . '</td><td>' . $phone['phoneTypeText'] . '</td><td>' . formatDate($phone['fileSinceDt']) . '</td></tr>';
				}

				$html .= '</tbody></table>';
				$html .= '</div></div></div></div></div></div>';

			}
		}


		$this->_reportTemplate = str_replace('<%CREDIT_HISTORY_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setTrustRatingContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['trustRating']['result']);

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"><i class="fa ' . $cls['iconCls'] . '"></i> Оценка благонадежности</div>
					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default"> <i class="fa fa-check-square-o"></i>
								<h4 class="inline">Вероятность возврата</h4>
							</div>
						</div>
					<div class="col-md-6">
						<div style="text-align:center;" class="note note-default">
							<h3 style="text-align: center;">' . number_format($data['trustRating']['trustRating']['result'], 2) . '</h3>
						</div>
					</div>
				</div>
				<h4 class="block">Балл благонадежности</h4>
				<div class="row">
					<div class="col-md-4">
						<div style="text-align:center;" class="note note-default"> Балл NPL15+
							<h3>' . (isset($data['additional']['trustRating']['score']) ? $data['additional']['trustRating']['score'] : '-') . '</h3>
						</div>
					</div>
					<div class="col-md-4">
					<div style="text-align:center;" class="note note-default"> Балл NPL45+
						<h3>' . (isset($data['additional']['trustRating45']) ? $data['additional']['trustRating45']['trustRating']['result'] : '-') . '</h3>
					</div>
				</div>
				<div class="col-md-4">
					<div style="text-align:center;" class="note note-default"> Балл NPL90+
						<h3>' . (isset($data['additional']['trustRating90']) ? $data['additional']['trustRating90']['trustRating']['result'] : '-') . '</h3>
					</div>
				</div>
			</div>';

		if (isset($data['additional']['trustRating'])) {
			$i = 0;

			foreach ($data['additional']['trustRating'] as $name => $value) {
				if (!is_array($value))
					continue;

				$i++;
				$suffix = '';

				if ($i % 2 !== 0)
					$html .= '<div class="row">';

				if (in_array($name, self::$_typeValueYesNo)) {
					$value['result'] = $value['result'] == 1 ? 'Да' : 'Нет';
				} else if (in_array($name, self::$_typeValueDays)) {
					$suffix = ' дн.';
				}

				$html .= '<div class="col-md-6"><div class="note note-info"><h4>' . $value['description'] . ': ' . formatNumberRus($value['result']) . $suffix . '</h4></div></div>';

				if ($i % 2 === 0)
					$html .= '</div>';

			}
		}

		$this->_reportTemplate = str_replace('<%TRUST_RATING_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setReliabilityLoanReceivingMethodContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['reliabilityloanReceivingMethod']['result']);
		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Оценка способа получения займа</div>
					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								Уровень мошенничества по указанному БИКу, БИНу, платежной системе и пр.
							</div>
						</div>
					<div class="col-md-6">
						<div style="text-align:center;" class="note note-default">
							<h3>' . (float)$data['reliabilityloanReceivingMethod']['loanReceivingMethodDanger']['result'] . '%</h3>
						</div>
					</div>
				</div>';

		$this->_reportTemplate = str_replace('<%RELIABILITY_LOAN_RECEIVING_METHOD_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setStopFactorsContent()
	{
		$data = $this->_response->getData();
		$cls = isset($data['stopFactors']['result'])
			? self::getClassAccordingToResult($data['fraudFactors']['result'])
			: null;

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Стоп-факторы</div>';

		$i = 0;

		foreach ($data['stopFactors'] as $name => $value) {
			if (!is_array($value)) {
				continue;
			}

			$cls = self::getClassAccordingToResult($value['result']);

			$i++;

			if ($i % 2 !== 0)
				$html .= '<div class="row">';

			$html .= '<div class="col-md-6"><div class="note ' . $cls['noteCls'] . '"><i class="fa ' . $cls['iconCls'] . '"></i> ' . $value['description'] . '</div></div>';

			if ($i % 2 === 0)
				$html .= '</div>';
		}
		$this->_reportTemplate = str_replace('<%STOP_FACTORS_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setRiskEvaluationContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['riskEvaluation']['result']);
		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Оценка риска</div>
				<div class="row">
					<div class="col-md-6">
						<div style="text-align:center;" class="note note-default">
							<i class="fa fa-check-square-o"></i>
							<h4 class="inline">Уровень  первоначального невозврата</h4>
						</div>
					</div>
					<div class="col-md-6">
						<div style="text-align:center;" class="note note-default">
							<h3 style="text-align: center;">' . $data['riskEvaluation']['defaultRate']['result'] . '%</h3>
						</div>
					</div>
				</div>

				<div class="note note-default"><i class="fa fa-check-square-o"></i> Сегмент
					<ul>
						<li>Возраст - ' . $data['riskEvaluation']['segment']['result']['age'] . '</li>
						<li>Сумма - ' . $data['riskEvaluation']['segment']['result']['sum'] . '</li>
						<li>Срок - ' . $data['riskEvaluation']['segment']['result']['days'] . '</li>
						<li>Погашено - ' . $data['riskEvaluation']['segment']['result']['closed'] . '</li>
					</ul>
				</div>
            </div>';
		$this->_reportTemplate = str_replace('<%RISK_EVALUATION_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setPaymentContent()
	{
		$data = $this->_response->getData();

		$html = '<div class="alert h4 alert-solvency"><i class="fa fa-check-square-o"></i> Платежеспособность</div>
					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">Доход прогноз</div>
						</div>
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								<h3>' . (empty($data['additional']['solvency']['income']) ? '-' : formatNumberRus($data['additional']['solvency']['income']) . ' руб.') . '</h3>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">Расход прогноз</div>
						</div>
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								<h3>' . (empty($data['additional']['solvency']['expenses']) ? '-' : formatNumberRus($data['additional']['solvency']['expenses']) . ' руб.') . '</h3>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">Способность обслуживать как запрашиваемый займ, так и кредиты в других кредитных организациях</div>
						</div>
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								<h3>' . (empty($data['additional']['solvency']['solveK1']) ? '-' : $data['additional']['solvency']['solveK1']) . '</h3>
								<div>(хорошо менее -1)</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">Способность обслуживать запрашиваемый займ с учетом месячной задолженности</div>
						</div>
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								<h3>' . (empty($data['additional']['solvency']['solveK2']) ? '-' : $data['additional']['solvency']['solveK2']) . '</h3>
								<div>(хорошо менее -1)</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">Способность обслуживать запрашиваемый займ как таковой</div>
						</div>
						<div class="col-md-6">
							<div style="text-align:center;" class="note note-default">
								<h3>' . (empty($data['additional']['solvency']['solveK3']) ? '-' : $data['additional']['solvency']['solveK3']) . '</h3>
								<div>(хорошо менее -7)</div>
							</div>
						</div>
					</div>';
		$this->_reportTemplate = str_replace('<%PAYMENTS_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setFraudFactorsContent()
	{
		$data = $this->_response->getData();

		$cls = isset($data['fraudFactors']['result'])
			? self::getClassAccordingToResult($data['fraudFactors']['result'])
			: null;

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Оценка мошеннического фактора</div>';

		foreach ($data['fraudFactors'] as $name => $value) {
			if (!is_array($value)) {
				continue;
			}

			$html .= '<div class="row"><div class="col-md-6"><div style="text-align:center;" class="note note-default">' . $value['description'] . '</div></div>
						<div class="col-md-6"><div style="text-align:center;" class="note note-default"><h3>' . (!$value['result'] ? '-' : $value['result']) . '</h3></div></div></div>';
		}

		$this->_reportTemplate = str_replace('<%FRAUD_FACTORS_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setTruthContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['additional']['truthQuestions']['result']);

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i> Оценка мошеннического фактора</div>
                <div class="row">
                        <div class="col-md-6">
                             <div style="text-align:center;" class="note note-default">' . $data['additional']['truthQuestions']['truthQuestions']['description'] . '</div>
                        </div>
                        <div class="col-md-6">
                             <div style="text-align:center;" class="note note-default">
                                   <h3>' . $data['additional']['truthQuestions']['truthQuestions']['result'] . '</h3>
                                   <div>(хорошо менее 10)</div>
                             </div>
                        </div>
               	</div>
                <div class="row">
                  	<div class="col-md-12">
                  		<table class="table table-striped">
                  			<thead>
                  				<tr><th>Вопрос</th><th>Ответ</th><th>Расчётный ответ</th><th>Примечание</th></tr>
                  			</thead><tbody>';

		foreach ($data['additional']['truthQuestions']['truthQuestions']['answers'] as $q => $a) {
			$html .= '<tr><td>' . ScoristaTruthQuestions::getQuestionNameByKey($q) . '</td><td>' . $a . '</td><td>' . $data['additional']['truthQuestions']['truthQuestions']['trueAnswers'][$q] . '</td><td>' . (is_numeric($qd = $data['additional']['truthQuestions']['truthQuestions']['trueAnswerDetails'][$q]) ? formatNumberRus($qd) : $qd) . '</td></tr>';
		}

		$html .= '</tbody></table></div></div>';


		$this->_reportTemplate = str_replace('<%TRUTH_QA_CONTENT%>', $html, $this->_reportTemplate);
	}

	private function setFsspContent()
	{
		$data = $this->_response->getData();
		$cls = self::getClassAccordingToResult($data['additional']['fssp']['result']);

		$html = '<div class="alert h4 alert-' . $cls['cls'] . '"> <i class="fa ' . $cls['iconCls'] . '"></i>Иски и правонарушения</div>
					<div class="row">
						<div class="col-md-12">
							<div class="note note-danger">
								<h4>
									<span class="block">Исполнительное производство:</span>
									<span class="block">' . $data['additional']['fssp']['textResult'] . '</span>
								</h4>
								<h4>' . $data['additional']['fssp']['sum']['description'] . ': ' . formatNumberRus($data['additional']['fssp']['sum']['result']) . ' руб.</h4>
							</div>';

		foreach ($data['additional']['fssp']['fssp'] as $fssp) {
			$html .= '<div class="note note-default" style="margin-top: 10px;">' . $fssp['subject'] . '<br>Сумма иска: ' . (empty($fssp['subjectSum']) ? '<нет данных>' : formatNumberRus($fssp['subjectSum'])) . '<br>' . $fssp['osp'] . '<br>' . $fssp['ospAddress'] . '<br>Дата документа: ' . formatDate($fssp['docDate']) . '<br>Номер документа: ' . $fssp['docNo'] . '<br><br>Экспедитор: ' . $fssp['executor'] . '<br>Телефон экспедитора: ' . formatPhone($fssp['executorPhone']) . '</div>';
		}

		$html .= '</div></div>';

		$this->_reportTemplate = str_replace('<%FSSP_CONTENT%>', $html, $this->_reportTemplate);
	}

	private static function getClassAccordingToResult($result)
	{
		switch ($result) {
			case '0':
				$cls = "success";
				$noteCls = "note-success";
				$iconCls = "fa-check-square-o";
				break;
			case '1':
				$cls = "danger";
				$noteCls = "note-danger";
				$iconCls = "fa-exclamation-triangle";
				break;
			default:
				$cls = "warning";
				$noteCls = "note-warning";
				$iconCls = "fa-minus";
				break;
		}
		return array(
			'noteCls' => $noteCls,
			'iconCls' => $iconCls,
			'cls' => $cls
		);
	}
}
