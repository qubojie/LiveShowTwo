<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 上午9:58
 */

namespace app\common\model;


use think\Model;

class MstCardVipGiftRelation extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_CARD_VIP_GIFT_RELATION';

    public $timestamps = false;
}