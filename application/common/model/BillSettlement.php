<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:27
 */

namespace app\common\model;


use think\Model;

class BillSettlement extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'el_bill_settlement';

    public $timestamps = false;
}