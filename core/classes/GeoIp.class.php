<?

class GeoIp {

    const URL = "http://ipgeobase.ru:7020/geo";

    static function getCity($ip = '') {
        $data = self::get($ip);
        return isset($data['city']) ? $data['city'] : '';
    }

    static function get($ip = '') {

        if (preg_match('/^(10|192)\./', $ip)) {
            return array();
        }
        if ($ip == '127.0.0.1') {
            $ip = '87.224.214.72';
        }
        $url = GeoIp::URL;
        
        $result=  file_get_contents($url . "?ip=$ip");
        $result=  iconv('cp1251', 'utf-8', $result);
        $data = array();
        if (preg_match_all('|<([a-z]+)>(.+)</\1>|', $result, $res)) {
            foreach ($res[1] as $n => $v) {
                $data[$v] = $res[2][$n];
            }
        }
        return $data;
    }

}

?>