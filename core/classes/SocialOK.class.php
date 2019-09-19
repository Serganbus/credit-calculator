<?
require_once('Social.class.php');
class SocialOK extends Social {

    
    function getRedirectUrl($params=null){
        if($this->redirectUrl){
            return $this->redirectUrl.($params?"&$params":'');
        }
        return $this->getProtocol().'://'.$_SERVER['HTTP_HOST'].'/?name=ok'.($params?"&$params":'');
    }
    protected function request($url, $dataArray = array()) {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        if ($dataArray) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, urldecode(http_build_query($dataArray)));
        }
        $response = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if (curl_errno($handle)) {
            echo curl_error($handle);
            die();
        }
        $array = json_decode($response, true);
        $array['code'] = $code;
        return $array;
    }
    function getUser($code) {

        $dataArray = array(
            "client_id" => $this->applicationID,
            "client_secret" => $this->secretKey,
            "code" => $code,
            "grant_type" => 'authorization_code',
            "redirect_uri" => $this->getRedirect(),
        );
        $response = $this->request('http://api.odnoklassniki.ru/oauth/token.do', $dataArray);
        if(!empty($response['access_token'])){
        	$sign = md5('application_key=' . $this->publicKey . md5($response['access_token'] . $this->secretKey));
	
	        # get access api
	        $dataArray = array(
	        	"access_token" => $response['access_token'],
	            "application_key" => $this->publicKey,
	            "sig" => $sign,
	        );
	        $userMass = $this->request('http://api.odnoklassniki.ru/api/users/getCurrentUser', $dataArray);
	        
	        $bMass = explode('-', $userMass['birthday']);
	        $birthday = $bMass[2] . '.' . $bMass[1] . '.' . $bMass[0];
	
	        $out['uid'] = $userMass['uid'];
	        $out['first_name'] = $userMass['first_name'];
	        $out['last_name'] = $userMass['last_name'];
	        $out['bdate'] = $birthday;
	        $out['network'] = 'ok';
	        $out['pic_2'] = $userMass['pic_2'];
	        $out['access_token'] = $response['access_token'];
	        return $out;
        }
    }

    function getHref($params=null) {
        return "http://www.odnoklassniki.ru/oauth/authorize?client_id={$this->applicationID}&redirect_uri=" . urlencode($this->getRedirectUrl($params)) . "&response_type=code";
    }

    function getMainPhoto($uid) {
        return file_get_contents($_SESSION['user']['pic_2']);
    }

    function getScoring($uid, $access_token) {
        #get user data
        $dataArray = array(
            "access_token" => $access_token,
            "application_key" => $this->publicKey,
            "sig" => md5('application_key=' . $this->publicKey . md5($access_token . $this->secretKey))
        );
        $response = $this->request("http://api.odnoklassniki.ru/api/users/getCurrentUser", $dataArray);
        $userData = $response;
		$userData['network']='ok';

        #get groups list
        $dataArray = array(
            "access_token" => $access_token,
            "application_key" => $this->publicKey,
            "count" => '10',
            "sig" => md5('application_key=' . $this->publicKey . 'count=10' . md5($access_token . $this->secretKey))
        );
        $response = $this->request("http://api.odnoklassniki.ru/api/group/getUserGroupsV2", $dataArray);
        $groupsListArray = $response['groups'];


        $uids = array();
        if (!empty($groupsListArray)) {
            foreach ($groupsListArray as $dataArray) {
                $uids[] = $dataArray['groupId'];
            }
            $uids = implode(',', $uids);
            $dataArray = array(
                "access_token" => $access_token,
                "application_key" => $this->publicKey,
                "fields" => 'uid,name,description,shortname',
                "uids" => $uids,
                "sig" => md5('application_key=' . $this->publicKey . 'fields=uid,name,description,shortnameuids=' . $uids . md5($access_token . $this->secretKey))
            );
            $response = $this->request("http://api.odnoklassniki.ru/api/group/getInfo", $dataArray);
            unset($response['code']);
//            $uids = preg_replace('/,$/', '', $uids);
            $userData['groups'] = $response;
        }

        #compile sex to SQL format
        $userData['sex'] = 'unknow';
        switch ($userData['gender']) {
            case 'female' : $userData['sex'] = 'female';
                break;
            case 'male' : $userData['sex'] = 'male';
                break;
        }

        #compile birthday
        $userData['bdate']=$userData['birthday'];
        if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $userData['birthday'])) {
            $userData['bdate'] = 'NULL';
        }
		

        $userData['city_name'] = '';
        $userData['country_name'] = '';
        $userData['relation'] = '';
        $userData['friends_count'] = '';
        
        #compile phone
        $userData['phone'] = '';

        #compile university_name
        $userData['university_name'] = '';

        #compile faculty_name
        $userData['faculty_name'] = '';

        #compile graduation
        $userData['graduation'] = '0';

        $userData['education_form'] = '';

        return $userData;
    }
}
?>