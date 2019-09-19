<?php

class kassamng {

    static function correct($division_id, $type, $sum, $userid, $comment = '') {
        $data = array(
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'division_id' => $division_id,
            'type' => $type,
            'comment' => $comment,
            'user_id' => $userid,
            'sum' => floatval($sum),
        );
        $error = "";
        $cond = " AND sum>0";
        if (in_array($type, array(20, 2))) {
            $rs = DB::select("SELECT SUM(sum) AS s FROM kassa WHERE division_id={$division_id}");
            if ($rs->next()) {
                if ($rs->getFloat('s') < floatval($sum)) {
                    $error = 'Суммы изьятия больше чем в кассе';
                }
            }
            $data['sum'] = -$data['sum'];
            $cond = " AND sum<0";
        }
        if (!$error) {
            $rs = DB::select("SELECT MAX(num) AS num FROM kassa WHERE division_id=$division_id AND date>='" . date('Y') . "' $cond");
            $num = 0;
            if ($rs->next()) {
                $num = $rs->getInt('num') + 1;
            }
            $data['num'] = $num;
            DB::insert('kassa', $data);
        }
        return $error;
    }

    static function correctds($division_id_from, $division_id_to, $type, $sum, $userid, $comment = '') {
        $data = array(
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'division_id_from' => $division_id_from,
            'division_id_to' => $division_id_to,
            'type' => $type,
            'comment' => $comment,
            'user_id' => $userid,
            'sum' => floatval($sum),
        );
        $error = "";
        $cond = " AND sum>0";
//        if (in_array($type,array(20,2))) {
//            $rs = DB::select("SELECT SUM(sum) AS s FROM cashflows WHERE division_id_from={$division_id_from}");
//            if ($rs->next()) {
//                if ($rs->getFloat('s') < floatval($sum)) {
//                    $error = 'Суммы изьятия больше чем в кассе';
//                }
//            }
//            $data['sum'] = -$data['sum'];
//            $cond=" AND sum<0";
//        }
        if (!$error) {
            $rs = DB::select("SELECT MAX(num) AS num FROM cashflows WHERE division_id_from=$division_id_from AND date>='" . date('Y') . "' $cond");
            $num = 0;
            if ($rs->next()) {
                $num = $rs->getInt('num') + 1;
            }
            $data['num'] = $num;
            DB::insert('cashflows', $data);
        }
        return $error;
    }

    static function insert($division_id, $type, $sum, $userid, $orderid, $comment = '') {
        $data = array(
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'division_id' => $division_id,
            'type' => $type,
            'comment' => $comment,
            'user_id' => $userid,
            'order_id' => $orderid,
            'sum' => floatval($sum),
        );
        $error = "";
        $cond = " AND sum>0";
        if (in_array($type, array(20, 2))) {
            $rs = DB::select("SELECT SUM(sum) AS s FROM kassa WHERE division_id={$division_id}");
            if ($rs->next()) {
                if ($rs->getFloat('s') < floatval($sum)) {
                    $error = 'Суммы изьятия больше чем в кассе';
                }
            }
            $data['sum'] = -$data['sum'];
            $cond = " AND sum<0";
        }
        if (in_array($type, array(2))) {//Для выдачи не возможно
            $rs = DB::select("SELECT * FROM kassa WHERE order_id=$orderid AND type=$type");
            if ($rs->next()) {
                //$error = "Для данного договора операция невозможна(повторная выдача)";
            }
        }
        if (!$error) {
            $rs = DB::select("SELECT MAX(num) AS num FROM kassa WHERE division_id=$division_id AND date>='" . date('Y') . "' $cond");
            $num = 0;
            if ($rs->next()) {
                $num = $rs->getInt('num') + 1;
            }
            $data['num'] = $num;
            DB::insert('kassa', $data);
        }
        return $error;
    }

    static function num($code, $num) {
        $temp = str_pad($code, 11, "0", STR_PAD_RIGHT);
        return str_pad($num, 11, $temp, STR_PAD_LEFT);
    }

    static function getByOrder($type, $orderid) {
        $rs = DB::select("SELECT k.*,d.code FROM kassa k, adm_users_division d WHERE d.id=k.division_id AND order_id=$orderid AND type=$type");
        if ($rs->next()) {
            $row = $rs->getRow();
            $row['num'] = self::num($row['code'], $row['num']);
            return $row;
        }
    }

}
