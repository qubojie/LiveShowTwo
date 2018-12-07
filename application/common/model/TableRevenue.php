<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午10:35
 */

namespace app\common\model;


use think\Model;

class TableRevenue extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌区域信息
     *
     * @var string
     */
    protected $table = 'EL_TABLE_REVENUE';

    protected $primaryKey = 'trid';

    public $timestamps = false;

    public $column = [
        'trid',                 //台位预定id  前缀T
        'status',               //订台状态   0待付定金或结算   1 预定成功   2已开台  3已清台   9取消预约
        'type',                 //预约类型  0无押金   1押金  2押金且取消不可退
        'uid',                  //用户id
        'sid',                  //服务人员id
        'sname',                //服务人员姓名
        'table_id',             //酒桌id
        'reserve_way',          //预定途径  client  客户预订（小程序）    service 服务端预定（内部小程序）   manage 管理端预定（pc管理端暂保留）
        'reserve_time',         //预约时间
        'reserve_people',       //预约人数
        'action_time',          //操作时间
        'action_user',          //操作人  预定途径为小程序时 “用户”
        'action_desc',          //操作备注
        'suid',                 //台位订金缴费单ID SU前缀
        'subscription',         //订金或订单金额
        'refund',               //实际退款金额
        'buid',                 //开台时的营业行为id  前缀D
        'created_at',           //数据创建时间
        'updated_at',           //最后更新时间
    ];

    public $revenue_column = [
        'tr.trid',                 //台位预定id  前缀T
        'tr.status',               //订台状态   0待付定金或结算   1 预定成功   2已开台  3已清台   9取消预约
        'tr.type',                 //预约类型  0无押金   1押金  2押金且取消不可退
        'tr.uid',                  //用户id
        'tr.sid',                  //服务人员id
        'tr.sname',                //服务人员姓名
        'tr.table_id',             //酒桌id
        'tr.reserve_way',          //预定途径  client  客户预订（小程序）    service 服务端预定（内部小程序）   manage 管理端预定（pc管理端暂保留）
        'tr.reserve_time',         //预约时间
        'tr.reserve_people',       //预约人数
        'tr.action_time',          //操作时间
        'tr.action_user',          //操作人  预定途径为小程序时 “用户”
        'tr.action_desc',          //操作备注
        'tr.suid',                 //台位订金缴费单ID SU前缀
        'tr.subscription',         //订金或订单金额
        'tr.refund',               //实际退款金额
        'tr.buid',                 //开台时的营业行为id  前缀D
    ];
}