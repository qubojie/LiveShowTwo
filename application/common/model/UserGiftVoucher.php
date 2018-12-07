<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午12:05
 */

namespace app\common\model;


use think\Model;

class UserGiftVoucher extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_user_gift_voucher';

    protected $primaryKey = 'gift_vou_code';

    public $timestamps = false;
}