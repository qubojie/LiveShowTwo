<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/28
 * Time: 下午3:49
 */

namespace app\common\model;


use think\Model;

class MstPayMethod extends Model
{
    /**
     * 关联到模型的数据表
     *
     * 充值金额设置表
     *
     * @var string
     */
    protected $table = 'EL_MST_PAY_METHOD';

    protected $primaryKey = 'pay_method_id';

    public $timestamps = false;
}