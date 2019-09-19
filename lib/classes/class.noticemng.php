<?php

class noticemng {

    static function getMsg($name, $data = array()) {
        $out = '';
        $cond = " snd_sms=1 ";//смс
        if (is_int($name)) {
            $cond .= " AND id=$name";
        } else {
            $cond .= " AND name='$name'";
        }

        $rs = DB::select("SELECT * FROM sms_template WHERE $cond");
        if ($rs->next()) {
            $out = $rs->get('template');
            foreach ($data as $k => $v) {
                $out = str_replace(strtoupper("%{$k}%"), $v, $out);
            }
        }
        return $out;
    }
    static function getMsgMail($name, $data = array()) {
        $out = '';
        $cond = " snd_mail=1 ";//email
        if (is_int($name)) {
            $cond .= " AND id=$name";
        } else {
            $cond .= " AND name='$name'";
        }

        $rs = DB::select("SELECT * FROM sms_template WHERE $cond");
        if ($rs->next()) {
            $out = $rs->get('template_mail');
            foreach ($data as $k => $v) {
                $out = str_replace(strtoupper("%{$k}%"), $v, $out);
            }
        }
        return $out;
    }
    static function sendMail($email,$template,$data=array()){
        global $settings;
        $data['site'] = Cfg::get('site_href');
        $data['site_phone'] = Cfg::get('site_phone');
        $data['site_name'] = Cfg::get('site_name');
        $text_mail=self::getMsgMail($template,$data);
        if($text_mail){
            XMail::send($email, $settings['adminPanel']['siteNameTransliteration'], 'simple', array('text' => $text_mail));
        }
        
    }
    static function send($phone,$email,$template,$data=array()){
        $data['site'] = Cfg::get('site_href');
        $data['site_phone'] = Cfg::get('site_phone');
        $data['site_name'] = Cfg::get('site_name');
        if($email){
            self::sendMail($email,$template,$data=array());
        }
        if($phone){
            // @todo send phone
        }
    }

    const ORDER_STATUS_P_TPL='status_p';//Предварительное одобрение
    const ORDER_STATUS_N_TPL='status_n';//Не одобрен
    const ORDER_STATUS_O_TPL='status_o';//Одобрен
    const ORDER_STATUS_Z_TPL='status_z';//Запрет
    const ORDER_STATUS_K_TPL='status_k';//Клиент отказался
}
