<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午12:01
 */

namespace app\common\model;


use think\Model;

class MstTableSize extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌容量
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_SIZE';

    protected $primaryKey = 'size_id';

    public $timestamps = false;
}