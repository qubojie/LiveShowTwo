<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午10:35
 */

namespace app\common\model;


use think\Model;

class MstTableCard extends Model
{
    /**
     * 关联到模型的数据表
     *
     * 台位区域与卡的关联信息
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_CARD';

    public $timestamps = false;
}