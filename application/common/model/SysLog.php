<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午2:31
 */
namespace app\common\model;

use think\Model;

class SysLog extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'el_sys_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;
}