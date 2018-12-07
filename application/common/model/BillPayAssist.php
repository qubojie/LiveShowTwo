<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午10:06
 */

namespace app\common\model;


use think\Model;

class BillPayAssist extends Model
{
    /**
     * 关联到模型的数据表s
     *
     * @var string
     */
    protected $table = 'el_bill_pay_assist';

    public $timestamps = false;

    public $r_column = [
        "pid",
        "uid",
        "card_name",
        "phone",
        "verification_code",
        "table_id",
        "table_no",
        "sid",
        "sname",
        "type",
        "sale_status",
        "check_user",
        "check_time",
        "check_reason",
        "account_balance",
        "account_cash_gift",
        "cash",
        "gift_vou_code",
        "re_account_balance",
        "re_account_cash_gift",
        "re_cash",
        "return_point",
        "return_own_commission",
        "return_own_cash_gift",
        "referrer_id",
        "return_cash_gift",
        "return_commission",
        "is_settlement",
        "settlement_user",
        "settlement_id",
        "updated_at",
        "created_at"
    ];
}