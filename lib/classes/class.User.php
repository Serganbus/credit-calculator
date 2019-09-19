<?php

/**
 * Class User
 *
 * @property int id
 * @property string fullName    // Иванов Иван Иваныч
 * @property string shortName    // Иванов И.И.
 * @property string $name
 * @property string $second_name
 * @property string $surname
 */
class User extends Model {

    public static function tableName() {
        return 'users u';
    }

    public function getFullName() {
        return $this->name . ' ' . $this->surname . ' ' . $this->second_name;
    }

    public function getShortName() {
        return $this->surname . ' ' . mb_strtoupper(mb_substr($this->name, 0, 1)) . '.' . mb_strtoupper(mb_substr($this->second_name, 0, 1)) . '.';
    }

    /**
     * Получение всех заказов пользователя
     *
     * @return array|null
     */
    public function getOrders() {
        return Orders::getOrdersByUserId($this->id);
    }

    public function getLastOrder() {
        return Orders::one(
                        array(
                    'user_id = :userId',
                    array('AND', 'date > :date')
                        ), array(
                    ":userId" => $this->id,
                    ":date" => '2015-09-01'
                        ), null, 'date DESC, time DESC'
        );
    }

    /**
     * Вопросы Правды для Скористы (ответы)
     * @return array|mixed
     */
    public function getScoristaTruthQuestions() {
        $truthQuestions = $this->getAttribute('scorista_truthQuestions');
        return !empty($truthQuestions) ? json_decode($truthQuestions, 1) : array();
    }

    /**
     * Возвращает пол пользователя на основе ФИО
     * @return int
     */
    public function genderDetect() {
        require_once ROOT . '/admin/plugins/results/back/scr/Library/NCLNameCaseRu.php';
        $nc = new NCLNameCaseRu();
        return $nc->genderDetect($this->getFullName());
    }

}
