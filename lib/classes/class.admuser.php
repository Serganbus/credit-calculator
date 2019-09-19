<?php

class admuser {

    static $linkList = array(
        LINK_REMOVE_LOAN_REQUEST => 'Снять заявку',
        LINK_CHANGE_POINTS_AND_STATUS => 'Изменить баллы и статусы',
        LINK_SKORISTA_SCORRING_REUQEST => 'Запрос Скористы',
        LINK_SKORISTA_SCORRING_VIEW => 'Просмотр Скористы',
        LINK_GET_BANK_INFO => 'Просмотр информации банка',
        LINK_GET_LOAN_INFO => 'Просмотр информации займа',
        LINK_GET_CREDIT_HISTORY => 'Просмотр кредитной истории',
        LINK_REQUEST_NBKI => 'Запросить у НБКИ',
        LINK_REQUEST_EKVIFAKS => 'Запросить у ЭКВИФАКС',
        LINK_GET_CRONOS_HISTORY => 'КРОНОС',
        LINK_GET_FINKARTA_HISTORY => 'Финкарта',
        LINK_GET_ANKETA_DATA => 'Просмотр анкеты',
        LINK_MODIFY_ANKETA_DATA => 'Модификация анкеты',
        LINK_VIEW_COMMENTS => 'Просмотр комментариев',
        LINK_ADD_NEW_COMMENT => 'Добавление комментариев',
        LINK_PRINT_DOCS => 'Печать документов',
        LINK_PRINT_ANKETA => 'Печать анкеты',
        LINK_PRINT_TREB => 'Печать требований',
        LINK_CHANGE_PAYMENT_STATUS => 'Модификация статуса платежа',
        LINK_CORRECTION_BUTTONS => 'Кнопки корректировок',
        LINK_SCR_BUTTONS => 'Кнопки скр',
        LINK_BACK_MONEY_BUTTON => 'Кнопка списать деньги',
        LINK_TAKE_MONEY_BUTTON => 'Кнопка вернуть деньги',
        LINK_PROLONGATE_ORDER => 'Реструктуризация займа',
        LINK_PAY_ORDER => 'Оплата займа',
        LINK_SIMPLE_SEARCH => 'Упрощенный поиск',
    );

    //БЛОК ПРОВЕРКИ ПРАВ ДОСТУПА К КОНКРЕТНЫМ ССЫЛКАМ...
    static function getLinksPermissions($section_id = SECTION_FIRST_LOAN) {
        global $db;
        $linksPermissions = array();
        foreach (self::$linkList as $id => $title) {
            $linksPermissions[$id] = false;
        }
        $authRules = $db->GetTable("SELECT `link_id`, `permission` FROM `adm_authorization_rules_for_section_links` WHERE `adm_id`=" . get_user_id() . " AND `section_id`=" . SECTION_FIRST_LOAN);
        if ($authRules != NULL) {
            foreach ($authRules as $authRule) {
                $authLink = $authRule['link_id'];
                $LinkPermission = $authRule['permission'];
                $linksPermissions[$authLink] = (bool) $LinkPermission;
            }
        }
        //...КОНЕЦ
        return $linksPermissions;
    }

    static function getRoles() {
        return get_user_roles();
    }

    static function getRoleSettings($role_id) {
        $rs = DB::select("SELECT * FROM adm_users_role WHERE id=$role_id");
        if ($rs->next()) {
            if ($c = json_decode($rs->get('cfg'),true)) {
                return $c;
            }
        }
        return array();
    }

    /**
     * Доступные стадии LC заявки
     * @return type
     */
    private static $lc = null;

    static function getLC() {
        if (self::$lc !== null) {
            return self::$lc;
        }
        $lc = array();
        if (($roles = self::getRoles()) && $division = get_user('division_id')) {//Если есть роль у пользователя
            $rs = DB::select("SELECT * FROM adm_simple_lc WHERE division_id=$division AND role_id IN(" . implode(',', $roles) . ")");
            while ($rs->next()) {
                $lc[$rs->getInt('role_id')] = $rs->getInt('pos') - 1;
            }
        }
        return self::$lc = $lc;
    }

    static function getRoleByLC($lc_id) {
        $lc = self::getLC();
        return array_search((int) $lc_id, $lc);
    }

    static function getRoleLinksPermissions($role_id, $section_id = SECTION_FIRST_LOAN) {
        $names = array(
            SECTION_FIRST_LOAN => 'first_loan_params',
            SECTION_FIRST_LOAN => 'first_loan_params',
            SECTION_FIRST_LOAN => 'first_loan_params',
        );
        if ($cfg = self::getRoleSettings($role_id)) {
            if (!empty($cfg[$names[$section_id]])) {
                return $cfg[$names[$section_id]];
            }
        }
    }

    static function updateRoleLinksPermissions($linksPermissions, $role_id, $section_id) {
        if ($cfg = self::getRoleLinksPermissions($role_id, $section_id)) {
            foreach ($cfg as $linkId) {
                $linksPermissions[$linkId] = true;
            }
        }
        return $linksPermissions;
    }

}

?>