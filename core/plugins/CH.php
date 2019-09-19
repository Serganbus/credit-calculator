<?php

interface iCH {

    public function getCH($order);

    public function newCH($order);

    public function getStatus($order);
}

class CH extends PLUGIN {

    /**
     * Приводит валютный код в нормальный вид
     * @param $currency
     * @return string
     */
    public static function normalizeCurrency($currency) {
        switch ($currency) {
            case 'RUR' :
            case 'RUB' : return 'RUB';
            case 'USD' : return 'USD';
        }
        new Exception('Не определенный тип валюты: ' . $currency);
    }

    public static function mergeCHDatas(array $creditHistorysData) {
        $mergedData = array();
        $mergedData['identification'] = array();
        $mergedData['address'] = array();
        $mergedData['phone'] = array();
        $mergedData['account'] = [];

        if (count($creditHistorysData) === 0) {
            throw new Exception('array $creditHistorysData contains 0 CH instances');
        }

        foreach ($creditHistorysData as $chData) {
            if (isset($chData['error']) && $chData['error'] == 1) {
                continue;
            }

            $commonInfo = array('name', 'surname', 'second_name', 'birth_day', 'birth_place');
            foreach ($commonInfo as $commonField) {
                if (empty($mergedData[$commonField]) && !empty($chData[$commonField])) {
                    $mergedData[$commonField] = $chData[$commonField];
                }
            }

            /**
             * Чтобы проверять одинаковые адреса, нужна эвристика, поскольку
             * разные бюро кредитных историй по-разному возвращают информацию.
             * Поэтому поступаем просто: чья база местопроживания человека полней - 
             * того и выводим. 
             */
            if (count($mergedData['address']) < count($chData['address'])) {
                $mergedData['address'] = $chData['address'];
            }

            if ($chData['identification'] != null && is_array($chData['identification'])) {
                foreach ($chData['identification'] as $identificationRow) {
                    $isRowAddedToMergedIdentification = false;
                    foreach ($mergedData['identification'] as $mergedIdentificationRow) {
                        if (trim($identificationRow['number']) == trim($mergedIdentificationRow['number'])) {
                            $isRowAddedToMergedIdentification = true;
                            break;
                        }
                    }
                    if (!$isRowAddedToMergedIdentification) {
                        $mergedData['identification'][] = $identificationRow;
                    }
                }
            }

            if ($chData['phone'] != null && is_array($chData['phone'])) {
                foreach ($chData['phone'] as $phoneRow) {
                    $isRowAddedToMergedPhone = false;
                    foreach ($mergedData['phone'] as $mergedPhoneRow) {
                        if (trim($phoneRow['number']) == trim($mergedPhoneRow['number'])) {
                            $isRowAddedToMergedPhone = true;
                            break;
                        }
                    }
                    if (!$isRowAddedToMergedPhone) {
                        $mergedData['phone'][] = $phoneRow;
                    }
                }
            }
            if (is_array($chData['account'])) {
                foreach ($chData['account'] as $currency => $accountCurrency) {
                    foreach ($accountCurrency as $accountRow) {
                        if (empty($mergedData['account'][$currency])) {
                            $mergedData['account'][$currency][] = $accountRow;
                            continue;
                        }

                        if (!self::isInMergeExist($accountRow, $mergedData['account'][$currency])) {
                            $mergedData['account'][$currency][] = $accountRow;
                        }
                    }
                }
            }
        }

        return $mergedData;
    }

    function select($type) {
        require_once ROOT . "/admin/core/plugins/CH_" . $type . ".php";
        $select = 'CH_' . $type;
        return new $select();
    }

    public static function isInMergeExist($account, $merge) {
        foreach ($merge as $mergedAccountRow) {
            if (self::equvalAccounts($account, $mergedAccountRow)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Эквивалентность счетов
     * @param $accountOne
     * @param $accountTwo
     * @return bool
     */
    public static function equvalAccounts($accountOne, $accountTwo) {
        return
                (int) $accountOne['cred_sum'] == (int) $accountTwo['cred_sum'] &&
                trim($accountOne['cred_date']) == trim($accountTwo['cred_date']);
    }

    public function getResult($data) {
        $result = array('result' => true, 'desc' => '');

        // cred_overdue_line
        if (count($data['account']) === 0) {
            $result['desc'] = 'Нет кредитной истории.';
        } else {
            foreach ($data['account'] as $currency => $accountCurrency) {

                foreach ($accountCurrency as $credit) {
                    $overdue_fact_max = 0;
                    $overdue_line = $credit['payline'];

                    for ($i = 0; $i < strlen($overdue_line); $i++) {
                        $array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
                        if (!in_array($overdue_line[$i], $array_exc)) {
                            $overdue_srok = $overdue_line[$i];
                        }
                        if ($overdue_srok > $overdue_fact_max) {
                            $overdue_fact_max = $overdue_srok;
                        }
                    }
                    if ($credit['status'] == 0) {
                        $overdue_requirement_max = 5; //	< 120 days
                        $desc = 'Просрочка по закрытому кредиту больше 120 дней.';
                    } else {
                        $overdue_requirement_max = 2; //	< 30 days
                        $desc = 'Просрочка по открытому кредиту больше 30 дней.';
                    }

                    // отказ
                    if ($overdue_fact_max > $overdue_requirement_max) {
                        $result = array('result' => false, 'desc' => $desc);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    public function showCH($data) {
        /* $cred_type = array(
          '00' => 'Неизвестный тип кредита',
          '01' => 'Кредит на автомобиль',
          '02' => 'Лизинг',
          '03' => 'Ипотека',
          '04' => 'Кредитная карта',
          '05' => 'Потребительский кредит',
          '06' => 'Кредит на развитие бизнеса',
          '07' => 'Кредит на пополнение оборотных средств',
          '08' => 'Кредит на покупку оборудования',
          '09' => 'Кредит на строительство недвижимости',
          '10' => 'Кредит на покупку акций (маржинальное кредитование)',
          '11' => 'Межбанковский кредит',
          '12' => 'Кредит мобильного оператора',
          '13' => 'Кредит на обучение',
          '14' => 'Дебетовая карта с овердрафтом',
          '15' => 'Ипотека (первичный рынок)',
          '16' => 'Ипотека (вторичный рынок)',
          '17' => 'Ипотека (ломбардный кредит)',
          '18' => 'Кредит наличными (нецелевой)',
          '19' => 'Микрозайм',
          '99' => 'Другой тип кредита',
          );

          $cred_active = array(
          'закрыт',
          'активен',
          'продан',
          'списан с баланса',
          'реструктурирован/ рефинансирован',
          'передан коллекторам',
          'заблокирован',
          'отменен',
          ); */

        $mons = array(
            'Я', 'Ф', 'М', 'А', 'М', 'И', 'И', 'А', 'С', 'О', 'Н', 'Д'
        );

        $color_array = array(
            '0' => '8f8',
            '1' => 'afa',
            '2' => 'fdd',
            '3' => 'fcc',
            '4' => 'fbb',
            '5' => 'faa',
            '6' => 'f99',
            '7' => 'f99',
            '8' => 'f88',
            '9' => 'f77',
            'B' => 'f66',
            'C' => '8f8',
            'S' => 'f66',
            'R' => 'f66',
            'W' => 'f66',
            'N' => 'f66',
            '-' => 'ccc',
        );

        $itog = $this->getResult($data);
        if ($itog['result']) {
            $result = '<p class="text-success">Одобрено</p>';
        } else {
            $result = '<p class="text-danger">' . $itog['desc'] . '</p>';
        }
        $result .= '<h4>' . $data['surname'] . ' ' . $data['name'] . ' ' . $data['second_name'] . '</h4>';
        $result .= 'Дата рождения: ' . $data['birth_day'] . '<br/>';
        $result .= 'Место рождения: ' . $data['birth_place'] . '<br/><br/>';

        //****************************************************
        $totalCreditsCount = 0;
        $openedCreditsCount = 0;
        $negativeCreditsCount = 0;

        $currentBalanceTxt = '';
        $creditLimitTxt = '';
        $paysPerMonthTxt = '';
        $zadolshennostTxt = '';
        $creditSumOverdueTxt = '';

        $creditOpenedDates = [];


        foreach ($data['account'] as $currency => $accountCurrency) {
            $currentBalance = 0;
            $creditLimit = 0;
            $paysPerMonth = 0;
            $zadolshennost = 0;
            $creditSumOverdue = 0;

            foreach ($accountCurrency as $credit) {
                $totalCreditsCount++;
                $overdue_line = $credit['payline'];
                if (strlen($overdue_line) > 0) {
                    $strlen1 = 0;
                    $strlen2 = 1;
                    while ($strlen1 != $strlen2) {
                        $strlen1 = strlen($overdue_line);
                        $overdue_line = str_replace('CC', 'C', $overdue_line);
                        $strlen2 = strlen($overdue_line);
                    }
                }

                $overdue_fact_max = 0;
                for ($i = 0; $i < strlen($overdue_line); $i++) {
                    $array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
                    if (!in_array($overdue_line[$i], $array_exc)) {
                        $overdue_srok = $overdue_line[$i];
                    }
                    if ($overdue_srok > $overdue_fact_max) {
                        $overdue_fact_max = $overdue_srok;
                    }
                }

                if ($credit['status'] == 0) {
                    $overdue_requirement_max = 5; //	< 120 days
                } else {
                    $overdue_requirement_max = 2; //	< 30 days
                    $openedCreditsCount++;

                    $paysPerMonth += $credit['cred_sum_debt'];
                    $currentBalance += $credit ['cred_sum'];

                    $creditLimit += $credit ['cred_sum'];

                    // текущая задолженность
                    $zadolshennost += $credit['cred_max_overdue'];


                    // задолженность на конец кредита
                    $creditSumOverdue += $credit['cred_sum_overdue'];
                }

                // отказ
                if ($overdue_fact_max > $overdue_requirement_max) {
                    $negativeCreditsCount++;
                }
                /*
                  if ($credit['cred_sum'] > 0) {
                  $creditLimit += $credit ['cred_sum'];
                  }

                  if ($credit['cred_sum_debt'] > 0) {
                  $zadolshennost += $credit['cred_sum_debt'];
                  }
                 */

                $creditOpenedDates[] = Date($credit['cred_date']);
            }
//die;
            $currentBalanceTxt .= $currentBalance > 0 ? ('<div>' . $currency . ': ' . formatNumberRus($currentBalance) . '</div>') : '';
            $creditLimitTxt .= $creditLimit > 0 ? ('<div>' . $currency . ': ' . formatNumberRus($creditLimit) . '</div>') : '';
            $paysPerMonthTxt .= $paysPerMonth > 0 ? ('<div>' . $currency . ': ' . formatNumberRus($paysPerMonth) . '</div>') : '';
            $zadolshennostTxt .= $zadolshennost > 0 ? ('<div>' . $currency . ': ' . formatNumberRus($zadolshennost) . '</div>') : '';
            $creditSumOverdueTxt .= $creditSumOverdue > 0 ? ('<div>' . $currency . ': ' . formatNumberRus($creditSumOverdue) . '</div>') : '';
        }

        /* 	foreach ($data['account'] as $credit) {
          $overdue_line = $credit['payline'];
          if (strlen($overdue_line) > 0) {
          $strlen1 = 0;
          $strlen2 = 1;
          while ($strlen1 != $strlen2) {
          $strlen1 = strlen($overdue_line);
          $overdue_line = str_replace('CC', 'C', $overdue_line);
          $strlen2 = strlen($overdue_line);
          }
          }

          $overdue_fact_max = 0;
          for ($i = 0; $i < strlen($overdue_line); $i++) {
          $array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
          if (!in_array($overdue_line[$i], $array_exc)) {
          $overdue_srok = $overdue_line[$i];
          }
          if ($overdue_srok > $overdue_fact_max) {
          $overdue_fact_max = $overdue_srok;
          }
          }
          $status = '';
          if ($credit['status'] == 0) {
          $overdue_requirement_max = 5; //	< 120 days
          } else {
          $overdue_requirement_max = 2; //	< 30 days
          $openedCreditsCount++;

          $paysPerMonth += $credit['cred_sum_debt'];
          $currentBalance += $credit ['cred_sum'];
          }

          // отказ
          if ($overdue_fact_max > $overdue_requirement_max) {
          $negativeCreditsCount++;
          }

          $creditLimit += $credit ['cred_sum'];

          // текущая задолженность
          $zadolshennost += $credit['cred_max_overdue'];


          // задолженность на конец кредита
          $creditSumOverdue += $credit['cred_sum_overdue'];

          $creditOpenedDates[] = Date($credit['cred_date']);
          } */

        usort($creditOpenedDates, function ( $a, $b ) {
            return strtotime($a) - strtotime($b);
        });

        $firstCreditOpenedDate = reset($creditOpenedDates);
        $lastCreditOpenedDate = end($creditOpenedDates);

        $result .= '
			<div class="panel panel-default">
				<div class="panel-heading">
                    <div style="float:right; cursor:pointer;" class="togglePanelUp">показать</div>
                    <h3 class="panel-title">Сводка</h3>
                </div>
				<table class="table table-bordered togglePanel" style="display:none; width:100% !important;">
				<thead>
					<tr>
						<th>Счета</th>
						<th>Договоры</th>
						<th>Баланс</th>
						<th>Открыт</th>
					</tr>
				</thead>
				<tbody>
                    <tr>
                        <td>Всего: ' . $totalCreditsCount . '<br/>Негативных: ' . $negativeCreditsCount . '<br/>Открытых: ' . $openedCreditsCount . '</td>
                        <td>Сумма кредитов: ' . $creditLimitTxt . '<br/>Ежемес. плат: ' . $paysPerMonthTxt . '</td>
                        <td>Текущий баланс: ' . $currentBalanceTxt . 'Задолженность: ' . $zadolshennostTxt . '<br/>Просрочено: ' . $creditSumOverdueTxt . '</td>
                        <td>Первый: ' . $firstCreditOpenedDate . '<br/>Последний: ' . $lastCreditOpenedDate . '</td>
                    </tr>
                </tbody>
                </table>
            </div>
                
		';
        //****************************************************

        $result .= '
			<div class="panel panel-default">
				<div class="panel-heading">
                    <div style="float:right; cursor:pointer;" class="togglePanelUp">показать</div>
                    <h3 class="panel-title">Счета</h3>
                </div>
				<table class="table table-bordered togglePanel" style="display:none; width:100% !important;">
				<thead>
					<tr>
						<th>Номер</th>
						<th>Сумма</th>
						<th>Статус</th>
						<th>Подробнее</th>
					</tr>
				</thead>
				<tbody>
		';

        if (count($data['account']) === 0) {
            $result .= '<tr><td colspan="4">Нет кредитной истории.</td></td>';
        } else {
            foreach ($data['account'] as $currency => $accountCurrency) {
                foreach ($accountCurrency as $credit) {

                    // CCCCC => C
                    $overdue_line = $credit['payline'];
                    if (strlen($overdue_line) > 0) {
                        $strlen1 = 0;
                        $strlen2 = 1;
                        while ($strlen1 != $strlen2) {
                            $strlen1 = strlen($overdue_line);
                            $overdue_line = str_replace('CC', 'C', $overdue_line);
                            $strlen2 = strlen($overdue_line);
                        }
                    }

                    $overdue_fact_max = 0;
                    $cred_overdue_line_block = '<ul class="list-inline">';

                    $cred_date_array = explode('.', $credit['cred_date']);
                    $start_mon_int = intval($cred_date_array[1]) - 1;
                    $start_year_int = intval($cred_date_array[2]) - 2000;
                    $final_mon_int = $start_mon_int + (strlen($overdue_line) - 1);
                    $final_year_int = $start_year_int;

                    while ($final_mon_int > 11) {
                        $final_mon_int -= 12;
                        $final_year_int++;
                    }


                    for ($i = 0; $i < strlen($overdue_line); $i++) {
                        $cred_overdue_line = '<span style="display:block; width:15px; text-align:center; margin:0px 1px; background:#' . $color_array[$overdue_line[$i]] . '">' . $overdue_line[$i] . '</span>';

                        $mon_number = $final_mon_int - $i;
                        $final_year = $final_year_int;
                        while ($mon_number < 0) {
                            $mon_number += 12;
                            $final_year--;
                        }
                        if ($mon_number == 0) {
                            $overdue_line_mons = '<span style="display:block; width:15px; text-align:center; margin:0px 1px; color:#00b">' . $final_year . '</span>';
                        } else {
                            $overdue_line_mons = '<span style="display:block; width:15px; text-align:center; margin:0px 1px;">' . $mons[$mon_number] . '</span>';
                        }

                        $cred_overdue_line_block .= '<li>' . $cred_overdue_line . $overdue_line_mons . '</li>';


                        $array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
                        if (!in_array($overdue_line[$i], $array_exc)) {
                            $overdue_srok = $overdue_line[$i];
                        }
                        if ($overdue_srok > $overdue_fact_max) {
                            $overdue_fact_max = $overdue_srok;
                        }
                    }

                    $cred_overdue_line_block .= '</ul>';

                    if ($credit['status'] == 0) {
                        $overdue_requirement_max = 5; //	< 120 days
                        $status = 'закрыт';
                        if ($overdue_fact_max > $overdue_requirement_max) {
                            $color = 'class="nact_yes_proc"';
                        } else {
                            $color = 'class="noact_no_proc"';
                        }
                    } else {
                        $status = 'открыт';
                        $overdue_requirement_max = 2; //	< 30 days
                        if ($overdue_fact_max > $overdue_requirement_max) {
                            $color = 'class="act_yes_proc"';
                        } else {
                            $color = 'class="act_no_proc"';
                        }
                    }

                    $result .= '
					<tr ' . $color . '>
						<td>' . $credit['cred_id'] . '</td>
						<td>' . $currency . ': ' . formatNumberRus($credit['cred_sum']) . '</td>
						<td>' . $status . '</td>
						<td><div class="showCreditInfo" style="cursor:pointer;" data-credit-id="' . $credit['cred_id'] . '">показать</div></td>
					</tr>
					<tr ' . $color . ' id="' . $credit['cred_id'] . '" style="display:none; font-size:11px;">
						<td colspan="4">
							<table class="table table-bordered" style="width:100% !important;">
								<tr>
									<td width="50%" colspan="2">
										Вид: ' . $credit['cred_type_text'] . '<br/>
										Статус: ' . $credit['cred_status_text'] /* . ' от ' . $credit['cred_first_load'] */ . '<br/>
										Последнее изменение: ' . $credit['cred_update'] . '
									</td>
									<td width="50%" colspan="2">
										<table style="width:170px !important">
											<tr>
												<td width="20" align="center">-></td>
												<td width="70" align="center">' . $credit['cred_sum'] . '</td>
												<td width="80" align="center">' . $credit['cred_date'] . '</td>
											</tr>
											<tr>
												<td align="center"><-</td>
												<td align="center">' . $credit['cred_max_overdue'] . '</td>
												<td align="center">' . $credit['cred_enddate'] . '</td>
											</tr>
										</table>
										Следующий платеж: ' . @$credit['cred_sum_debt'] . '
									</td>
								</tr>
								<tr>
									<td width="25%">
										От 31 до 60 дней: ' . $credit['delay60'] . '<br/>
										От 61 до 90 дней: ' . $credit['delay90'] . '<br/>
										Более 90 дней: ' . $credit['delay_more'] . '
									</td>
									<td width="75%" colspan="3">
										' . $cred_overdue_line_block . '
									</td>
								</tr>
							</table>
						</td>
					</tr>
				';
                }
            }
        }

        $result .= '	</tbody>';
        $result .= '	</table>';
        $result .= '</div>';

        $result .= '	
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" class="togglePanelUp">показать</div><h3 class="panel-title">Идентификация</h3></div>
				<table class="table table-bordered togglePanel" id="block_identification" style="display:none; width:100% !important;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Номер</th>
						<th>Подробнее</th>
					</tr>
				</thead>
				<tbody>';
        for ($i = 0; $i < count($data['identification']); $i++) {
            $result .= '
				<tr>
					<td>' . $data['identification'][$i]['type'] . '</td>
					<td>' . $data['identification'][$i]['number'] . '</td>
					<td>' . $data['identification'][$i]['desc'] . '</td>
				</tr>
			';
        }
        $result .= '
				</tbody>
				</table>
			</div>
		';

        $result .= '
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" class="togglePanelUp">показать</div><h3 class="panel-title">Адреса</h3></div>
				<table class="table table-bordered togglePanel" id="block_address" style="display:none; width:100% !important;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Адрес</th>
						<th>Актуальность</th>
					</tr>
				</thead>
				<tbody>';
        if (count($data['address']) > 0) {
            foreach ($data['address'] as $address) {
                $result .= '
					<tr>
						<td>' . $address['type'] . '</td>
						<td>' . $address['address'] . '</td>
						<td>' . $address['date'] . '</td>
					</tr>
				';
            }
        } else {
            $result .= '<tr><td colspan="2"><div class="empty_result">Адреса не указаны</div></td></tr>';
        }
        $result .= '
				</tbody>
				</table>
			</div>
		';

        $result .= '
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" class="togglePanelUp">показать</div><h3 class="panel-title">Телефоны</h3></div>
				<table class="table table-bordered togglePanel" id="block_phones" style="display:none; width:100% !important;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Номер</th>
						<th>Актуальность</th>
					</tr>
				</thead>
				<tbody>';
        if (count($data['phone']) > 0) {
            foreach ($data['phone'] as $phone) {
                $result .= '
					<tr>
						<td>' . $phone['type'] . '</td>
						<td>' . $phone['number'] . '</td>
						<td>' . $phone['date'] . '</td>
					</tr>
				';
            }
        } else {
            $result .= '<tr><td colspan="2"><div class="empty_result">Телефоны не указаны</div></td></tr>';
        }
        $result .= '
				</tbody>
				</table>
			</div>
		';

        $result .= '
			<script>
			$(".showCreditInfo").click(function(){
				var credit_id = $(this).attr("data-credit-id");
				if($("#"+credit_id).css("display")=="none"){
					$("#"+credit_id).show(300);
					$(this).text("скрыть");
				}
				else{
					$("#"+credit_id).hide(300);
					$(this).text("показать");
				}
			});
			$(".togglePanelUp").click(function(){
            var lPb = $("table", $(this).parent().parent());
				if(lPb.css("display")=="none"){
					lPb.show(300);
					$(this).text("скрыть");
				}
				else{
					lPb.hide(300);
					$(this).text("показать");
				}
			});
			</script>';
        return $result;
    }

}

?>