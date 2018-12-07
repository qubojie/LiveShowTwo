<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:53
 */

namespace app\common\model;


use think\Model;

class MstCardVip extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_CARD_VIP';

    protected $primaryKey = 'card_id';

    public $timestamps = false;

    public $column = [
        'card_id',
        'card_type_id',
        'card_name',
        'card_level',
        'card_image',
        'card_no_prefix',
        'card_desc',
        'card_equities',
        'card_amount',
        'card_pay_amount',
        'card_validity_time',
        'card_deposit',
        'card_point',
        'card_cash_gift',
        'is_giving',
        'salesman',
        'sort',
        'created_at',
        'updated_at',
    ];
}