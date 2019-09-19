<link rel="stylesheet" type="text/css" href="/admin/assets/templates/scorista_answer/sc_files/css.css">
<div class="col-sm-12">
	<div class="portlet">
		<div class="portlet-title">
			<div class="caption"><i class="fa fa-check-square"></i> Кредитное решение</div>
		</div>
		<div class="portlet-body">
			<div class="row">
				<div class="col-md-12">
					<div class="alert h4 alert-info"><%USER_STATUS_PART%></div>
				</div>
				<div class="col-sm-12">
					<%DECISION_PART%>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
					<div class="col-sm-3">
						<div class="note note-default">
							Расшифровка:
						</div>
					</div>
					<div class="col-sm-3">
						<div class="note note-success">
							<i class="fa fa-check-square-o"></i>
							Прошел проверку
						</div>
					</div>
					<div class="col-sm-3">
						<div class="note note-danger">
							<i class="fa fa-exclamation-triangle"></i>
							Причина отказа
						</div>
					</div>
					<div class="col-sm-3">
						<div class="note note-warning">
							<i class="fa fa-minus"></i> Не проверялся
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12"><%CREDIT_HISTORY_CONTENT%></div>

				<div class="col-md-12"><%TRUST_RATING_CONTENT%></div>

				<div class="col-md-12"><%RISK_EVALUATION_CONTENT%></div>

				<div class="col-md-12"><%PAYMENTS_CONTENT%></div>

				<div class="col-md-12"><%RELIABILITY_LOAN_RECEIVING_METHOD_CONTENT%></div>

				<div class="col-md-12"><%FRAUD_FACTORS_CONTENT%></div>

				<div class="col-md-12"><%STOP_FACTORS_CONTENT%></div>

				<div class="col-md-12"><%TRUTH_QA_CONTENT%></div>

				<div class="col-md-12"><%FSSP_CONTENT%></div>
			</div>
		</div>
	</div>
</div>