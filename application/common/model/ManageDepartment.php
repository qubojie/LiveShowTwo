<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午5:54
 */

namespace app\common\model;


use think\Model;

class ManageDepartment extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MANAGE_DEPARTMENT';

    protected $primaryKey = 'department_id';

    public $timestamps = false;
}