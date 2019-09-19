<?
/**
 * Рос фин мониторинг
 */
class RFM {

    private function __construct() {
    }

    private static $instance = array();

    /**
     * $instance
     *
     * @return RFM
     */
    static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    

    private $url="http://fedsfm.ru/documents/terrorists-catalog-portal-act";
    function getUrl() {
        return $this->url;
    }
    function getData(){
        return file_get_contents($this->getUrl());
    }
    function getParsData(){
        $str=$this->getData();
   		ini_set('pcre.backtrack_limit', '5000000');
        $data=array();
        if(preg_match('|<div id="textRussianFL" .*>(.+)</div>|Usm', $str,$res)){
            if(preg_match_all('|<p>(.+)</p>|U', $res[1],$res)){
                foreach ($res[1] as $row){
                    if(preg_match('|(\d+)\.(.+)\*?,(.*)(,(.*))?;|U',$row,$d)){
                    	$data[]=array(
                    		'num'=>$d[1],
                    		'name'=>trim($d[2]),
                    		'bdate'=>date('Y-m-d',strtotime(preg_replace('|(\S)\s.*|','\1',trim($d[3])))),
                    		'place'=>!empty($d[5])?trim($d[5]):'',
                    	);
                    }else{
                    	echo $row."\n";
                    }
                    
                }
            }
        }
        return $data;
    }

    function update(){
    	$last_update_path=ROOT.'/log/rfm_last_update.txt';
    	if(!file_exists($last_update_path) || file_get_contents($last_update_path)!=date('Y-m-d')){
    		if($data=$this->getParsData()){
	    		DB::execute("TRUNCATE TABLE ref_rfm");
	    		foreach ($data as $row) {
	    		 	DB::insert('ref_rfm',$row);
	    		} 
	    	}
	    	file_put_contents($last_update_path,date('Y-m-d'));
    	}
	    	
    }
    
    static function search($name,$bdate){
    	//self::getInstance()->update();
    	$rs=DB::select("SELECT * FROM ref_rfm WHERE bdate='$bdate' AND name='".mb_strtoupper($name)."'");
    	if($rs->next()){
    		return $rs->getRow();
    	}
    }
}

?>