<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 上午10:00
 */

namespace app\common\model;


use think\Model;

class MstCardVipVoucherRelation extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_CARD_VIP_VOUCHER_RELATION';

    public $timestamps = false;
}