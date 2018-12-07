<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午4:01
 */

namespace app\common\model;


use think\Model;

class SysMenu extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_SYS_MENU';

    public $timestamps = false;
}