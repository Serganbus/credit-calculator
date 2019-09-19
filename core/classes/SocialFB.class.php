<?
require_once('Social.class.php');
class SocialFB extends Social {

    function getRedirectUrl($params=null){
        if($this->redirectUrl){
            return $this->redirectUrl.($params?"&$params":'');
        }
        return $this->getProtocol().'://'.$_SERVER['HTTP_HOST'].'/?name=fb'.($params?"&$params":'');
    }
	private function request2($url, $dataArray=array() ){
		$handle=curl_init();
		curl_setopt($handle, CURLOPT_URL, $url);;
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_POSTFIELDS,  urldecode(http_build_query($dataArray)));
		$response=curl_exec($handle);
		$code=curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if (curl_errno($handle)) {
            echo curl_error($handle);
            die();
        }
		$returnMass = array();
		$responseMass = explode('&',$response);
		for($i=0; $i<count($responseMass); $i++){
			$key_value = explode('=',$responseMass[$i]);
			$returnMass[$key_value[0]] = $key_value[1];
		}
		$returnMass['code'] = $code;
		return $returnMass;
	}
	
    function getUser($code) {
        
        $dataArray = array(
            "client_id" => $this->applicationID,
            "client_secret" => $this->secretKey,
            "code" => $code,
            "redirect_uri" => $this->getRedirect(),
        );
        $response = $this->request2('https://graph.facebook.com/oauth/access_token', $dataArray);

        # get access api
        $dataArray = array(
//            "access_token" => $response['access_token'],
        );
        $userMass = $this->request('https://graph.facebook.com/me?access_token='.$response['access_token'], $dataArray);
        $out['uid'] = $userMass['id'];
        $out['first_name'] = $userMass['first_name'];
        $out['last_name'] = $userMass['last_name'];
        $out['bdate'] = $userMass['bdate'];
        $out['network'] = 'fb';
        $out['access_token'] = $response['access_token'];
        return $out;
    }

    function getHref($params=null) {
        return "https://www.facebook.com/dialog/oauth?client_id={$this->applicationID}&redirect_uri=" . urlencode($this->getRedirectUrl($params)) . "&response_type=code";
    }

    function getMainPhoto($uid) {
        return file_get_contents('http://graph.facebook.com/' . $uid . '/picture?type=large');
    }

    function getScoring($uid, $access_token) {

        #get user data
        $dataArray = array(
            "access_token" => $access_token
        );
        $response = json_decode(file_get_contents('https://graph.facebook.com/me' . '?' . urldecode(http_build_query($dataArray))), true);
        $userData = $response;
        $userData['network'] = 'fb';
        $userData['uid'] = $uid;
        
        #compile dirthday for SQL format
        $bdate = (isset($userData['birthday']) ? $userData['birthday'] : '00/00/00');
        $tmpArr = explode("/", $bdate);
        $userData['bdate'] = $tmpArr[2] . '-' . $tmpArr[1] . '-' . $tmpArr[0];

        #compile sex to SQL format
        switch ($userData['gender']) {
            case 'female' : $userData['sex'] = 'female';
                break;
            case 'male' : $userData['sex'] = 'male';
                break;
            default : $userData['sex'] = 'unknow';
                break;
        }

        #compile city
        $locationArray = ( isset($userData['hometown']['name']) ) ? explode(",", $userData['hometown']['name']) : array();
        $userData['city'] = (isset($locationArray[0])) ? trim($locationArray[0]) : '';
        $userData['country'] = (isset($locationArray[2])) ? trim($locationArray[2]) : '';

        #compile phone
        $phone = ( isset($userData['phone']) ) ? $userData['phone'] : '';
        $phone = preg_replace('/(^(8||7||\\+7))/', '', $phone);
        $userData['phone'] = preg_replace('/[\(\)\s-]/', '', $phone);

        #compile education form
        $education['school'] = (isset($userData['education']['0']['school']['name'])) ? $userData['education']['0']['school']['name'] : '';
        $education['year'] = (isset($userData['education']['0']['year']['name'])) ? $userData['education']['0']['year']['name'] : '';
        $education['type'] = (isset($userData['education']['0']['type'])) ? $userData['education']['0']['type'] : '';

        $userData['university_name'] = $education['school'];
        $userData['faculty_name'] = '';
        $userData['graduation'] = $education['year'];
        $userData['education_form'] = '';
        /* @TODO Группы */
        $userData['groups']=array();
        
//        $dataArray = array(
//            "access_token" => $access_token,
//        );
//        $response = json_decode(file_get_contents('https://graph.facebook.com/me/groups' . '?' . urldecode(http_build_query($dataArray))), true);
        return $userData;
    }

}

?>