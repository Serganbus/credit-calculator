<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RanksDivision
 *
 * @author Sergey Ivanov <sivanovkz@gmail.com>
 */
class RanksDivision extends Model {
    
    public static function tableName() {
		return 'settings_ranks_division';
	}
    
    /*Хранит экземпляры объекта CreditProposals*/
    public $credit_proposals;
    
    /*Код подразделения в буквенном виде*/
    public $division_code;
    
    public function __construct($config = []) {
        if (
                !isset($config['credit_proposals'])
                || (
                        isset($config['credit_proposals']) 
                        && !($config['credit_proposals'] instanceof CreditProposals)
                    )
            ) {
            $config['credit_proposals'] = new CreditProposals();
        }
        parent::__construct($config);
    }
    
    public function save() {
        $result = false;
        
        if ($this->credit_proposals->save() !== false) {
            $this->credit_proposal_id = $this->credit_proposals->id;
            $result = parent::save();
        }
        return $result;
    }
    
    /**
	 * @param array|null $result
	 */
	protected function afterFind(array $result = null)
	{
        parent::afterFind($result);
        
        $this->credit_proposals = CreditProposals::one($this->credit_proposal_id);
        $this->division_code = db()->scalar("SELECT code FROM adm_users_division WHERE id=:id", [':id' => $this->division_id]);
	}
    
}
