<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:16
 */

namespace app\common\model;


use think\Model;

class ManageSalesman extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MANAGE_SALESMAN';

    protected $primaryKey = 'sid';

    public $timestamps = false;

    public $admin_column = [
        'ms.sid',
        'ms.department_id',
        'ms.stype_id',
        'ms.is_governor',
        'ms.sales_name',
        'ms.statue',
        'ms.id_no',
        'ms.id_img1',
        'ms.id_img2',
        'ms.phone',
        'ms.wxid',
        'ms.nickname',
        'ms.avatar',
        'ms.sex',
        'ms.province',
        'ms.city',
        'ms.country',
        'ms.entry_time',
        'ms.dimission_time',
        'ms.lastlogin_time',
        'ms.sell_num',
        'ms.sell_amount',
        'ms.created_at',
        'ms.updated_at'
    ];

    public $manage_column = [
        'ms.sid',
        'ms.department_id',
        'ms.stype_id',
        'ms.is_governor',
        'ms.sales_name',
        'ms.statue',
        'ms.id_no',
        'ms.phone',
        'ms.wxid',
        'ms.nickname',
        'ms.avatar',
        'ms.sex',
        'ms.province',
        'ms.city',
        'ms.country',
        'ms.entry_time',
        'ms.sell_num',
        'ms.sell_amount',
        'ms.remember_token',
        'ms.token_lastime',
        'ms.reception_token',
        'ms.reception_token_lastime',
        'ms.updated_at',
        'ms.created_at',
    ];
}