<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 上午10:01
 */
namespace app\common\model;

use think\Model;

class MstGiftVoucher extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_GIFT_VOUCHER';

    protected $primaryKey = 'gift_vou_id';

    public $timestamps = false;
}