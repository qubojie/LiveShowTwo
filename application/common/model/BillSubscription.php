<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午10:17
 */

namespace app\common\model;


use think\Model;

class BillSubscription extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_bill_subscription';

    protected $primaryKey = 'suid';

    public $timestamps = false;

    public $alias = "bs";

    public  $column = [
        "suid",
        "trid",
        "uid",
        "status",
        "subscription",
        "pay_time",
        "cancel_user",
        "cancel_time",
        "auto_cancel",
        "auto_cancel_time",
        "cancel_reason",
        "is_refund",
        "refund_amount",
        "pay_type",
        "pay_no",
        "created_at",
        "updated_at"
    ];
}