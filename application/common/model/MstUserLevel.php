<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:26
 */
namespace app\common\model;

use think\Model;

class MstUserLevel extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_USER_LEVEL';

    protected $primaryKey = 'level_id';

    public $timestamps = false;
}