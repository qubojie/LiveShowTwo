<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 上午11:33
 */
namespace app\common\model;

use think\Model;

class SysSetting extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'el_sys_setting';

    protected $primaryKey = 'key';

    public $timestamps = false;

    public $column = [
        "key",
        "ktype",
        "key_title",
        "key_des",
        "vtype",
        "select_cont",
        "value",
        "default_value",
        "is_sys"
    ];
}