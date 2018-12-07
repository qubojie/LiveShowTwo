<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午10:29
 */

namespace app\common\model;


use think\Model;

class MstTableLocation extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌位置信息
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_LOCATION';

    protected $primaryKey = 'location_id';

    public $timestamps = false;
}