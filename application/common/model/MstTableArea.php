<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:36
 */

namespace app\common\model;


use think\Model;

class MstTableArea extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌区域信息
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_AREA';

    protected $primaryKey = 'area_id';

    public $timestamps = false;

    public $column = [
        'area_id',
        'area_title',
        'area_desc',
        'turnover_limit_l1',
        'turnover_limit_l2',
        'turnover_limit_l3',
        'order_rules',
        'sort',
        'is_enable',
        'is_delete',
        'created_at',
        'updated_at',
    ];
}