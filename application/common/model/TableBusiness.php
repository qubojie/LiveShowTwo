<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/11/30
 * Time: 下午2:31
 * App: LiveShowTwo
 */

namespace app\common\model;


use think\Model;

class TableBusiness extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌区域信息
     *
     * @var string
     */
    protected $table = 'EL_TABLE_BUSINESS';

    protected $primaryKey = 'buid';

    public $timestamps = false;

    public $column = [
        "buid",
        "uid",
        "table_id",
        "table_no",
        "status",
        "turnover_limit",
        "ssid",
        "ssname",
        "sid",
        "sname",
        "turnover_num",
        "turnover",
        "is_refund",
        "refund_num",
        "refund_amount",
        "cancel_user",
        "cancel_time",
        "cancel_reason",
        "open_time",
        "clean_time",
        "created_at",
        "updated_at"
    ];
}