<?php

/* #
  # 	Требует подключеного класса MySql
  #
  #	Файлы шаблонов должны располагаться в папке /tpl/ и иметь суффикс ".tpl.php"
  #	файлы метаданных располагаются в папке /dat/
  #
  #
 */

class template {

    public $error = '';                     //вывод ошибок текущих действий
    public $bufferMeta = '';                //буффер метаданных (doctype и прочие метаданные, из папки "dat")
    public $js = array();                   //массив для js
    public $bufferTpl = '';              //буффер для шаблонов
    public $buffer = '';                 //общий буффер, который складывается из всех вышестоящих буфферов
    public $regions = array();
    public $mod = array();                //массив с загруженными модулями текущей страницы ( [id имя модуля] => array( region => регион модуля ), array( name => русское имя модуля ), array( manual => контент мануала модуля ), array( settings => контент настройки модуля ), array( orname => оригинальное имя модуля)
    public $manualMod = array();          //массив содержит мануалы  всех модулей проекта ( [оригинальное имя модуля] => array( manual => контент мануала ), array( name => русское имя модуля  )   )
    public $allModNames = array();
    private $root = '';
    private $pageId;
    protected $path = '';
    private $ownPageJs = array();
    private $data = array();

    function addData($data) {
        $this->data+=$data;
    }

    public function __construct($aPageId = 0) {
        $this->pageId = $aPageId;
        $this->initialize();
    }

    /* возвращает буффер */

    public function getBuffer() {
        return $this->buffer;
    }

    /* очищает весь буффер */

    public function clearBuffer() {
        $this->buffer = '';
    }

    /* очищает весь буффер tpl */

    public function clearBufferTpl() {
        $this->bufferTpl = '';
    }

    /* устанавливает свойство "path" на переданное в качестве аргумента */

    public function setPath($value) {
        $this->path = $value;
    }

    protected function initialize() {
        $this->root = $_SERVER['DOCUMENT_ROOT'];
    }

    protected function getMeta() {
        $this->js = array_unique($this->js);
        $this->bufferMeta.="\n";
        foreach ($this->js as $v)
            $this->bufferMeta.="\n" . $v;
        $this->bufferMeta.= "</head>\n";
        return $this->bufferMeta;
    }

    /* прогружает помещая в буффер DOCTYPE скрипты и css */

    public function buildHeader() {
        $this->loadMeta('doctype');
        $this->bufferMeta.="<head>\n";
        $this->loadMeta('meta');
        $this->setPath('');
        $this->getJs();
    }

    protected function loadMeta($tplFName) {
        $this->setPath('/admin/dat/');
        $fname = $this->root . $this->path . $tplFName . '.dat.php';
        if (file_exists($fname)) {
            $this->bufferMeta.= file_get_contents($fname) . "\n";
        }
    }

    public function getRegions($pageId) {
        global $db;
        $row = $db->GetRow("SELECT `template` FROM `parts` WHERE `id`=%s ", array($pageId));
        if ($row != NULL && $row > 0) {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admin/tpl/' . $row['template'] . '.tpl.php')) {
                ob_start();
                include $_SERVER['DOCUMENT_ROOT'] . '/admin/tpl/' . $row['template'] . '.tpl.php';
                $buff = ob_get_clean();
                preg_match_all('/%{region[-|_][0-9a-zA-Z]+}%/', $buff, $this->regions);
            }
        }
    }

    /* подгружает в буффер шаблон, имя которого	передано в качестве агрумента */

    public function loadTpl($tplFName, $data = array()) {
        $this->data+=$data;
        if ($this->path == '')
            $this->setPath('/admin/tpl/');
        $fname = $this->root . $this->path . $tplFName . '.tpl.php';
        if (file_exists($fname)) {
            ob_start();
            foreach ($this->data as $k => $v) {
                $$k = $v;
            }
            include $fname;
            $this->bufferTpl.=ob_get_clean();
        }
    }

    /* собирает весьбуффер и готовит его к выводу */

    public function getRenderTpl() {
        $this->buffer = $this->getMeta() . "\n<body>\n\n" . $this->bufferTpl;
        $this->buffer.= "\n</body>\n";
        $this->buffer.= $this->getOwnPageJs();
        $this->buffer.= "\n</html>";
        return $this->buffer;
    }

    /* выводит все загруженные в буффер шаблоны */

    public function renderTpl() {
        $this->buffer = $this->getMeta() . "\n<body>\n\n" . $this->buffer;
        $this->buffer.= "\n</body>\n";
        $this->buffer.= $this->getOwnPageJs();
        $this->buffer.= "\n</html>";
        echo $this->buffer;
    }

    protected function getJs() {
        global $settings;
		if ($settings['adminPanel']['voximplant']['enable'])
			$this->js[] = '<script type="text/javascript" src="//cdn.voximplant.com/voximplant.min.js"></script>
            	<script type="text/javascript">var VOXIMPLANT_CFG=' . json_encode($settings['adminPanel']['voximplant']) . ';</script>';

        if ($this->path == '')
            $this->setPath('/admin/js/');
        $arr = glob($this->root . $this->path . '*');
		
        foreach ($arr as $k => $v) {
            if (preg_match('/^[^-](.+)(\.js$)/', basename($v)))
                $this->js[] = '<script type="text/javascript"  src="' . $this->path . basename($v) . '" ></script>';
        }
        unset($v);
        $this->setPath('');
        $this->js[] = '<script type="text/javascript" src="/admin/assets/plugins/modernizr/modernizr-2.6.2-respond-1.1.0.min.js"></script>';

        $this->js[] = '<script type="text/javascript" src="/admin/plugins/results/back/js/jquery.blockUI.js"></script>';
        $this->js[] = '<script type="text/javascript" src="/admin/plugins/results/back/js/jquery.form.js"></script>';
        $this->js[] = '<script type="text/javascript" src="/admin/plugins/results/back/js/jquery.printPage.js"></script>';
		$this->js[] = '<script type="text/javascript" src="/admin/plugins/results/back/js/res.js"></script>';

        /* collector js code */
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.1.8.2.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/chilltip-packed.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery-ui-1.10.2.custom.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.animate-colors.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.browser.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.cookie.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.dmenu.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.easing.1.3.js" ></script>'; //

        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.filtertext.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.formstyler.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.imageLens.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.json-2.3.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.maskedinput.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.numberMask.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.placeholder.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.splash.2.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.splash.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.validate.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jqueryslidemenu.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/printThis.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/zIeWarning.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/anketa.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/authorize.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/edit.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/lib.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/registry.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/restore.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/cabinet.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/jquery.slider.min.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/zaem.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/payment.js" ></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/collector/js/main.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/flash/js/main.js"></script>'; //

        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/userhistory/back/js/userhistory.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/call_history/back/js/call_history.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/unload_bki/back/js/unload_bki.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/flash/js/unload_cbrf.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/flash/js/unload_inventory.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/flash/js/unload_settings.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/searchPeople/back/js/downnbki.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/admins_and_permissions/js/main.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/settings/js/pageContentManagementScripts.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/kassa/kassa.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/anketa/anketa.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/settings/settings.js"></script>'; //
        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/notice/notice.js"></script>'; //

        $this->js[] = '<script type="text/javascript"  src="/admin/plugins/article/article.js"></script>'; //
    }

    protected function getOwnPageJs() {
		global $settings;
        switch ($this->pageId) {
            case 1:
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/mandatoryJs.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/metrojs/metrojs.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/fullcalendar/moment.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/fullcalendar/fullcalendar.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/simple-weather/jquery.simpleWeather.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/google-maps/markerclusterer.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/bootstrap/bootstrap.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/datatables/dynamic/jquery.dataTables.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/datatables/dataTables.bootstrap.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/datatables/dataTables.tableTools.js"></script>';
				$this->ownPageJs[] = '<script src="/admin/assets/plugins/datetimepicker/jquery.datetimepicker.js"></script>';
				$this->ownPageJs[] = '<script type="text/javascript" src="/admin/plugins/results/back/js/jquery.form.js"></script>';

				if ($settings['showMap']) {
					$this->ownPageJs[] = '<script src="https://maps.google.com/maps/api/js?sensor=true"></script>';
					$this->ownPageJs[] = '<script src="/admin/assets/plugins/google-maps/gmaps.js"></script>';
				}
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jquery-validation/jquery.validate.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jquery-validation/src/localization/messages_ru.js"></script>';

                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-flot/jquery.flot.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-flot/jquery.flot.animator.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-flot/jquery.flot.resize.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-flot/jquery.flot.time.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-morris/raphael.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/charts-morris/morris.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jquery-ui-touch-punch/jquery.ui.touch-punch.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/summernote/summernote.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/parsley/parsley.min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/parsley/parsley.extend.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/parsley/i18n/ru.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/kladr/kladr.min.js"></script>';

                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jQRangeSliders/jQAllRangeSliders-min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jQRangeSliders/jQAllRangeSliders-withRuler-min.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/plugins/bootstrap-slider/bootstrap-slider.js"></script>';

                $this->ownPageJs[] = '<script src="/admin/assets/plugins/jcrop/jquery.Jcrop.min.js"></script>';

				$this->ownPageJs[] = '<script src="/admin/plugins/results/back/js/jquery.printPage.js"></script>';

                $this->ownPageJs[] = '<script src="/admin/assets/js/calendar.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/js/dashboard.js"></script>';
                $this->ownPageJs[] = '<script src="/admin/assets/js/application.js"></script>';

                break;
        }

        $t = '';
        $this->ownPageJs = array_unique($this->ownPageJs);
        foreach ($this->ownPageJs as $v)
            $t.=$v . "\n";
        return $t;
    }

    /* помещает в мета-буффер все скрипты и css найденые
      в соответствующих папках модуля, имя которого передано
      в качестве аргумента */

    public function preloadMod($modName) {
        $this->setPath('/mod/' . $modName . '/back/css/');
        $this->setPath('/mod/' . $modName . '/back/js/');
        $this->getJs();
    }

    public function loadModuls($pageId) {
        global $db;
        $res = $db->GetTable(" SELECT * FROM `moduls` WHERE `page_id`=%s ORDER BY `range` ", array($pageId));

        if ($res != NULL && $res > 0) {
            foreach ($res as $row) {
                $this->mod[$row['id']]['region'] = $row['region'];
                $this->mod[$row['id']]['range'] = $row['range'];
                $this->mod[$row['id']]['page_id'] = $row['page_id'];
                $this->mod[$row['id']]['id'] = $row['id'];  //id модуля
                $this->preloadMod($row['mod_name']);
                $this->loadMod($row['id'], $row['mod_name']);
            }
        }
    }

    public function loadMod($modId, $modName) {
        global $page;
        ob_start();

        $myId = $this->mod[$modId]['id'];   //отдаем в модуль переменную id модуля
        $myRegion = $this->mod[$modId]['region']; //отдаем в модуль переменную region модуля
        $myPageId = $this->mod[$modId]['page_id']; //отдаем в модуль переменную page id модуля	

        include $this->root . '/mod/' . $modName . '/back/settings/' . $modName . '.php';
        $this->mod[$modId]['name'] = $Myname;
        $this->mod[$modId]['orname'] = $modName;
        $this->mod[$modId]['settings'] = ob_get_clean();

        ob_start();
        include $this->root . '/mod/' . $modName . '/back/manual/' . $modName . '.php';
        $this->mod[$modId]['manual'] = ob_get_clean();
    }

    public function loadAllModNames() {
        $arr = glob($this->root . '/mod/*');
        foreach ($arr as $k => $v) {
            if (!file_exists($v . '/back/settings/' . basename($v) . '.php'))
                continue;

            ob_start();
            include $v . '/back/settings/' . basename($v) . '.php';
            if (isset($Myname))
                $this->allModNames[basename($v)]['name'] = $Myname;
            ob_get_clean();
        }
    }
}