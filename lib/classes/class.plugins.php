<?php
class plugins {

	private $root = '';
	public $plugins = array();
	public $allPlugNames = array();			//содержит имена всех плагинов
	public $manualLinks = '';
	public $settingsLinks = '';
	public $js = array();
	public $css = array();
	
	public function __construct() {
		$this->initialize();
	}
	
	protected function initialize() {
		$this->root = $_SERVER['DOCUMENT_ROOT'];
	}
	
	public function preloadCss( $pluginName ) {
		if( !file_exists( $this->root.'/admin/plugins/'.$pluginName.'/back/css' ) ) return;
		$arr = glob( $this->root.'/admin/plugins/'.$pluginName.'/back/css/*');
		foreach ($arr as $k => $v) {
			if(  preg_match( '/^[^-](.+)(\.css$)/', basename($v) ) )
				$this->css[] = '<link rel="stylesheet" type="text/css" href="/admin/plugins/'.$pluginName.'/back/css/'.basename($v).'" />';
		}
        unset($v);
	}
	
	public function preloadJs( $pluginName ) {
		if( !file_exists( $this->root.'/admin/plugins/'.$pluginName.'/back/js' ) ) return;
		$arr = glob( $this->root.'/admin/plugins/'.$pluginName.'/back/js/*');
		foreach ($arr as $k => $v) {
			if(  preg_match( '/^[^-](.+)(\.js$)/', basename($v) ) )
				$this->js[] = '<script type="text/javascript"  src="/admin/plugins/'.$pluginName.'/back/js/'.basename($v).'" ></script>'."\n";
		}
        unset($v);
	}	

	public function loadAllPlugNames() {
		$arr = glob( $this->root.'/admin/plugins/*');
		foreach ($arr as $k => $v) {
			$this->preloadJs( basename($v) );
			$this->preloadCss( basename($v) );
			ob_start();
			if( file_exists( $v.'/back/settings/'.basename($v).'.php' ) ) {
				include $v.'/back/settings/'.basename($v).'.php';
				$this->allPlugNames[ basename($v) ]['name'] = $Myname;
			}
			ob_get_clean();
		}
	}
	
	public function loadPlugins() {
		if( empty( $this->allPlugNames ) ) $this->loadAllPlugNames();
		foreach( $this->allPlugNames as $k => $v ) {
			if( file_exists( $_SERVER['DOCUMENT_ROOT'].'/admin/plugins/'.$k.'/back/manual/'.$k.'.php' ) )  $this->manualLinks[].= '<a class="plug_manual" href="!#" plugName="'.$k.'" >'.$v['name'].'</a>';
			if( file_exists( $_SERVER['DOCUMENT_ROOT'].'/admin/plugins/'.$k.'/back/settings/'.$k.'.php' ) )  $this->settingsLinks[].= '<a class="plug_settings" href="!#" plugName="'.$k.'" >'.$v['name'].'</a>';
		}
	}
}
?>