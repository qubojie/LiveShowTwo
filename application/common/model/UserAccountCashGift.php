<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:48
 */
namespace app\common\model;

use think\Model;

class UserAccountCashGift extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_account_cash_gift';

    protected $primaryKey = 'cash_gift_id';

    public $timestamps = false;
}