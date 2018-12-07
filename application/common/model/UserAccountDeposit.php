<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:45
 */
namespace app\common\model;

use think\Model;

class UserAccountDeposit extends  Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_account_deposit';

    protected $primaryKey = 'deposit_id';

    public $timestamps = false;
}