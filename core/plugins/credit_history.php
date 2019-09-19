<?php
class credit_history extends PLUGIN{
	
    function newCreditHistory($order_id){
		$result = array('result'=> false, 'desc'=>'');
		
			$row = $this->db->GetRow("SELECT * FROM kbki_credit_history WHERE order_id=%s", array($order_id));
			if(count($row)==0){
				$this->db->Run("INSERT INTO kbki_credit_history (date, order_id) VALUES(%s, %s)", array(date('Y-m-d H:i:s'), $order_id));
				$result['result'] = true;
			}
			else{
				$result['desc'] = 'Запрос на получение КИ уже был сформирован';
			}
		
		return $result;
    }

	function getStatus($order_id){
		$status = 0;
		
		$row = $this->db->GetRow("SELECT `check` FROM kbki_credit_history WHERE order_id=%s", array($order_id));
		
		if(count($row)==0){
			$status = 0;
		}
		elseif($row['check']=='0'){
			$status = 1;
		}
		else{
			$status = 2;
		}
		return $status;
	}

	function getResult($order_id){
		$result = array('result' => true, 'desc' => '');

		$row = $this->db->GetRow("SELECT `xml` FROM kbki_credit_history WHERE order_id=%s", array($order_id));
		
		$ch = json_decode(json_encode(simplexml_load_string(iconv('UTF-8', 'windows-1251', $row['xml']))), true);
		
		// cred_overdue_line
		if(count($ch['switch_response']['base_part']['credit'])===0){
			$result['desc'] = 'Нет кредитной истории.';
		}
		else{
			foreach($ch['switch_response']['base_part']['credit'] as $credit){
				$overdue_fact_max = 0;
				$overdue_line = $credit['cred_overdue_line'];
				for($i=0; $i<strlen($overdue_line); $i++){
					$array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
					if(!in_array($overdue_line[$i], $array_exc)){
						$overdue_srok = $overdue_line[$i];
					}
					if($overdue_srok > $overdue_fact_max){
						$overdue_fact_max = $overdue_srok;
					}
				}
				if($credit['cred_active']==0){
					$overdue_requirement_max = 5;	//	< 120 days
					$desc = 'Просрочка по закрытому кредиту больше 120 дней.';
				}
				else{
					$overdue_requirement_max = 2;	//	< 30 days
					$desc = 'Просрочка по открытому кредиту больше 30 дней.';
				}
				
				// отказ
				if($overdue_fact_max > $overdue_requirement_max){
					$result = array('result' => false, 'desc' => $desc);
					break;
				}
			}
		}
		return $result;
	}
	
	function showCreditHistory($order_id){
		$result = '';
		
		$row = $this->db->GetRow("SELECT `check`, `xml` FROM kbki_credit_history WHERE order_id=%s", array($order_id));
		
		$cred_type = array(
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
		);
		
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
		);
		
		$ch = json_decode(json_encode(simplexml_load_string(iconv('UTF-8', 'windows-1251', $row['xml']), null, LIBXML_NOCDATA)), true);
		
		$fio = $ch['switch_response']['title_part']['private']['second_name'].' '.$ch['switch_response']['title_part']['private']['firstname'].' '.$ch['switch_response']['title_part']['private']['middlename'];
		$birthday = $ch['switch_response']['title_part']['private']['birthday'];
		$birthplace = $ch['switch_response']['title_part']['private']['birthplace'];
		
		$result .= '<h4>'.$fio.'</h4>';
		$result .= 'Дата рождения: '.$birthday.'<br/>';
		$result .= 'Место рождения: '.$birthplace.'<br/><br/>';
		
		$result .= '
			<div class="panel panel-default">
				<div class="panel-heading"><h3 class="panel-title">Счета</h3></div>
				<table class="table table-bordered">
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
		
		if(count($ch['switch_response']['base_part']['credit'])===0){
			$result .= '<tr><td colspan="4">Нет кредитной истории.</td></td>';
		}
		else{
			foreach($ch['switch_response']['base_part']['credit'] as $credit){
				
				// CCCCC => C
				$overdue_line = $credit['cred_overdue_line'];
				if(strlen($overdue_line) > 0){
					$strlen1 = 0;
					$strlen2 = 1;
					while($strlen1 != $strlen2){
						$strlen1 = strlen($overdue_line);
						$overdue_line = str_replace('CC', 'C', $overdue_line);
						$strlen2 = strlen($overdue_line);
					}
				}
				
				$color = '';
				$overdue_fact_max = 0;
				$cred_overdue_line = '';
				for($i=0; $i<strlen($overdue_line); $i++){
					$cred_overdue_line .= '<span style="display:inline-block; width:15px; text-align:center; margin:0px 1px; background:#'.$color_array[$overdue_line[$i]].'">'.$overdue_line[$i].'</span>';
					$array_exc = array('B', 'C', 'S', 'R', 'W', 'N', '-');
					if(!in_array($overdue_line[$i], $array_exc)){
						$overdue_srok = $overdue_line[$i];
					}
					if($overdue_srok > $overdue_fact_max){
						$overdue_fact_max = $overdue_srok;
					}
				}
				$status = '';
				if($credit['cred_active']==0){
					$overdue_requirement_max = 5;	//	< 120 days
					$color = 'class="success"';
					$status = 'закрыт';
				}
				else{
					$overdue_requirement_max = 2;	//	< 30 days
					$status = 'открыт';
				}
				
				// отказ
				if($overdue_fact_max > $overdue_requirement_max){
					$color = 'class="danger"';
				}
				
				$cred_date_array = explode('.', $credit['cred_date']);
				$start_mon_int = intval($cred_date_array[1]) - 1;
				$start_year_int = intval($cred_date_array[2]) - 2000;
				$final_mon_int = $start_mon_int + (strlen($overdue_line) - 1);
				$final_year_int = $start_year_int;
				while($final_mon_int > 11){
					$final_mon_int -= 12;
					$final_year_int ++;
				}
					
				$overdue_line_mons = '';
				for($i=0; $i<strlen($overdue_line); $i++){
					
					$mon_number = $final_mon_int - $i;
					$final_year = $final_year_int;
					while($mon_number < 0){
						$mon_number += 12;
						$final_year --;
					}
					if($mon_number == 0){
						$overdue_line_mons .= '<span style="display:inline-block; width:15px; text-align:center; margin:0px 1px; color:#00b">'.$final_year.'</span>';
					}
					else{
						$overdue_line_mons .= '<span style="display:inline-block; width:15px; text-align:center; margin:0px 1px;">'.$mons[$mon_number].'</span>';
					}
				}
				
				$result .= '
					<tr '.$color.'>
						<td>'.$credit['cred_id'].'</td>
						<td>'.$credit['cred_sum'].'</td>
						<td>'.$status.'</td>
						<td><a href="#" data-credit-id="'.$credit['cred_id'].'" class="showCreditInfo">показать</a></td>
					</tr>
					<tr '.$color.' id="'.$credit['cred_id'].'" style="display:none; font-size:11px;">
						<td colspan="4">
							<table class="table table-bordered">
								<tr>
									<td width="25%">Вид: '.$cred_type[$credit['cred_type']].'</td>
									<td width="25%">
										Размер: '.$credit['cred_sum'].'<br/>
										Финал.: '.$credit['cred_enddate'].'<br/>
										Период.: '.$credit['cred_sum_debt'].'
									</td>
									<td width="25%">
										Статус: '.$cred_active[$credit['cred_active']].'<br/>
										Дата: '.$credit['cred_first_load'].'<br/>
										Открыт: '.$credit['cred_date'].'<br/>
										Обнов.: '.$credit['cred_update'].'
									</td>
									<td width="25%">
										Задолж: '.$credit['cred_sum_overdue'].'<br/>
										Проср.дней: '.$credit['cred_day_overdue'].'<br/>
										Проср.: '.$credit['cred_max_overdue'].'
									</td>
								</tr>
								<tr>
									<td>Кол-во закрытых просрочек</td>
									<td colspan="3">Платежная дисциплина</td>
								</tr>
								<tr>
									<td>
										Менее 6 дней: '.$credit['delay5'].'<br/>
										От 6 до 30 дней: '.$credit['delay30'].'<br/>
										От 31 до 60 дней: '.$credit['delay60'].'<br/>
										От 61 до 90 дней: '.$credit['delay90'].'<br/>
										Более 90 дней: '.$credit['delay_more'].'
									</td>
									<td colspan="3">
										'.$cred_overdue_line.'<br/>
										'.$overdue_line_mons.'
									</td>
								</tr>
							</table>
						</td>
					</tr>
				';
			}
		}
		
		$result .= '	</tbody>';
		$result .= '	</table>';
		$result .= '</div>';
		
		$docno = $ch['switch_response']['title_part']['private']['doc']['docno'];
		$docdate = $ch['switch_response']['title_part']['private']['doc']['docdate'];
		$docplace = $ch['switch_response']['title_part']['private']['doc']['docplace'];
		$inn = $ch['switch_response']['title_part']['private']['inn'];
		$pfno = $ch['switch_response']['title_part']['private']['pfno'];
		$driverno = $ch['switch_response']['title_part']['private']['driverno'];
		$medical = $ch['switch_response']['title_part']['private']['medical'];
		
		$result .= '	
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" id="showIdentification">показать</div><h3 class="panel-title">Идентификация</h3></div>
				<table class="table table-bordered" id="block_identification" style="display:none;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Номер</th>
						<th>Выдан</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Паспорт</td>
						<td>'.$docno.'</td>
						<td>'.$docdate.' '.$docplace.'</td>
					</tr>
		';
		
		if($inn!=''){
			$result .= '
					<tr>
						<td>ИНН</td>
						<td>'.$inn.'</td>
						<td></td>
					</tr>
			';
		}
		if($pfno!=''){
			$result .= '
					<tr>
						<td>ПФР</td>
						<td>'.$pfno.'</td>
						<td></td>
					</tr>
			';
		}
		if($driverno!=''){
			$result .= '
					<tr>
						<td>Водител.</td>
						<td>'.$driverno.'</td>
						<td></td>
					</tr>
			';
		}
		if($medical!=''){
			$result .= '
					<tr>
						<td>Мед.полис</td>
						<td>'.$medical.'</td>
						<td></td>
					</tr>
			';
		}
		$result .= '
				</tbody>
				</table>
			</div>
		';
		
		$addr_reg = $ch['switch_response']['base_part']['addr_reg'];
		$addr_fact = $ch['switch_response']['base_part']['addr_fact'];
		$history_addr_reg = $ch['switch_response']['base_part']['history_addr']['addr_reg'];
		$history_addr_fact = $ch['switch_response']['base_part']['history_addr']['addr_fact'];
		
		$result .= '
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" id="showAddress">показать</div><h3 class="panel-title">Адреса</h3></div>
				<table class="table table-bordered" id="block_address" style="display:none;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Адрес</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="test">Прописка</td>
						<td>'.$addr_reg.'</td>
					</tr>
					<tr>
						<td>Проживание</td>
						<td>'.$addr_fact.'</td>
					</tr>
					<tr>
						<td>Пред.прописка</td>
						<td>'.$history_addr_reg.'</td>
					</tr>
					<tr>
						<td>Пред.проживание</td>
						<td>'.$history_addr_fact.'</td>
					</tr>
				</tbody>
				</table>
			</div>
		';
		$credit_report = $ch['switch_response']['add_part']['credit_report'];
		$ch2 = json_decode(json_encode(simplexml_load_string(iconv('UTF-8', 'windows-1251', $credit_report))), true);
		
		$result .= '
			<div class="panel panel-default">
				<div class="panel-heading"><div style="float:right; cursor:pointer;" id="showPhones">показать</div><h3 class="panel-title">Телефоны</h3></div>
				<table class="table table-bordered" id="block_phones" style="display:none;">
				<thead>
					<tr>
						<th>Тип</th>
						<th>Номер</th>
					</tr>
				</thead>
				<tbody>';
		if(count($ch2['preply']['report']['PhoneReply'])>0){
			foreach($ch2['preply']['report']['PhoneReply'] as $PhoneReply){
				$result .= '<tr><td>'.$PhoneReply['phoneTypeText'].'</td><td>'.$PhoneReply['number'].'</td></tr>';
			}
		}
		else{
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
			})
			$("#showIdentification").click(function(){
				if($("#block_identification").css("display")=="none"){
					$("#block_identification").show(300);
					$(this).text("скрыть");
				}
				else{
					$("#block_identification").hide(300);
					$(this).text("показать");
				}
			})
			$("#showAddress").click(function(){
				if($("#block_address").css("display")=="none"){
					$("#block_address").show(300);
					$(this).text("скрыть");
				}
				else{
					$("#block_address").hide(300);
					$(this).text("показать");
				}
			})
			$("#showPhones").click(function(){
				if($("#block_phones").css("display")=="none"){
					$("#block_phones").show(300);
					$(this).text("скрыть");
				}
				else{
					$("#block_phones").hide(300);
					$(this).text("показать");
				}
			})
			</script>';
		
		$result_array = array('result' => false, 'desc' => $result);
		return $result_array;
	}

}
?>