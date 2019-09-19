<?

require_once('Social.class.php');

class SocialVK extends Social {

    function getRedirectUrl($params = null) {
        if ($this->redirectUrl) {
//            return $this->redirectUrl;
            return $this->redirectUrl . ($params ? "&$params" : '');
        }
//        return $this->getProtocol().'://'.$_SERVER['HTTP_HOST'].'/?name=vk';
        return $this->getProtocol() . '://' . $_SERVER['HTTP_HOST'] . '/?name=vk' . ($params ? "&$params" : '');
    }

    function getUser($code) {
        $dataArray = array(
            "client_id" => $this->applicationID,
            "client_secret" => $this->secretKey,
            "code" => $code,
            "redirect_uri" => $this->getRedirect(), //$this->getRedirectUrl()
        );
        $response = $this->request('https://oauth.vk.com/access_token', $dataArray);

        $access_token = $this->access_token = $response['access_token'];

        $dataArray = array(
            "user_ids" => $response['user_id'],
            "fields" => 'bdate,photo_big',
        );
        $userMass = $this->request('https://api.vk.com/method/users.get', $dataArray);
        $out = $userMass["response"][0];
        $out['network'] = 'vk';
        $out['access_token'] = $access_token;
        return $out;
    }

    function getHref($params = null) {
        return "https://oauth.vk.com/authorize?client_id={$this->applicationID}&redirect_uri=" . urlencode($this->getRedirectUrl($params)) . "&response_type=code";
    }

    function getMainPhoto($uid) {
        return file_get_contents($_SESSION['user']['photo_big']);
    }

    function getScoring($uid, $access_token) {
        #get user data
        $dataArray = array(
            "access_token" => $access_token,
            "uids" => $uid,
            "fields" => 'nickname, screen_name, sex, bdate, city, country, timezone, photo, photo_medium, photo_big, has_mobile, contacts, education, universities, online, counters,relation, personal,last_seen,status, can_write_private_message , can_see_all_posts, can_post, universities ',
            "name_case" => 'nom'
        );
        $response = $this->request('https://api.vk.com/method/users.get', $dataArray);
        $userData = $response['response'][0];
        $userData['network'] = 'vk';

        #get city name by city ID
        $dataArray = array(
            "access_token" => $access_token,
            "cids" => $userData['city']
        );
        $response = $this->request('https://api.vk.com/method/places.getCityById', $dataArray);
        $userData['city_name'] = $response['response'][0]['name'];

        #get country name by country ID
        $dataArray = array(
            "access_token" => $access_token,
            "cids" => $userData['country']
        );
        $response = $this->request('https://api.vk.com/method/places.getCountryById', $dataArray);
        $userData['country_name'] = $response['response'][0]['name'];

        #get groups list
        $dataArray = array(
            "access_token" => $access_token,
            "uid" => $uid,
            "extended" => '1',
            "fields" => 'description,wiki_page,status',
            "count" => '10'
        );

        $response = $this->request('https://api.vk.com/method/groups.get', $dataArray);
        $userData['groups'] = $response['response'];
        unset($userData['groups'][0]);

        #compile birthday date to SQL format
        $bdate = (isset($userData['bdate']) ? $userData['bdate'] : '00.00.00');
        $tmpArr = explode(".", $bdate);
        foreach ($tmpArr as &$value) {
            if (strlen($value) == 1) {
                $value = '0' . $value;
            }
        }
        $userData['bdate'] = $tmpArr[2] . '-' . $tmpArr[1] . '-' . $tmpArr[0];

        #compile sex to SQL format
        switch ($userData['sex']) {
            case '0' : $userData['sex'] = 'unknow';
                break;
            case '1' : $userData['sex'] = 'female';
                break;
            case '2' : $userData['sex'] = 'male';
                break;
        }

        #compile phone
        $userData['phone'] = '';
        if ($userData['has_mobile'] == '1' && isset($userData['mobile_phone'])) {
            $phone = preg_replace('/(^(8||7||\\+7))/', '', $userData['mobile_phone']);
            $userData['phone'] = preg_replace('/[\(\)\s-]/', '', $phone);
        }

//			#compile education form
        if (isset($userData['education_form'])) {
            $education_form = mb_strtolower($userData['education_form'], 'UTF-8');
            $education_form = preg_replace('/(отделение)/', '', $education_form);
            $userData['education_form'] = preg_replace('/[\s]/', '', $education_form);
        } else {
            $userData['education_form'] = '';
        }
        $userData['reg_date'] = $this->getRegDate($uid);
        return $userData;
    }

    //Дата регистрации
    private function getRegDate($uid) {
        $out = null;
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout' => 3
            )
                )
        );
        $xml = file_get_contents("http://vk.com/foaf.php?id=$uid", 0, $ctx);
        //<ya:created dc:date="2012-07-31T14:15:48+03:00"/>
        if (preg_match('|<ya:created dc:date="(.*)"/>|U', $xml, $res)) {
//            print_r($res);
            $out = date('Y-m-d', strtotime($res[1]));
        }
        return $out;
    }

}

?>