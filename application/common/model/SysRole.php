<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午6:32
 */
namespace app\common\model;

use think\Model;

class SysRole extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_SYS_ROLE';

    protected $primaryKey = 'role_id';

    public $timestamps = false;
}