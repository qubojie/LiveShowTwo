<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:53
 */

namespace app\common\model;


use think\Model;

class MstTableAppearance extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌区域信息
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_APPEARANCE';

    protected $primaryKey = 'appearance_id';

    public $timestamps = false;
}