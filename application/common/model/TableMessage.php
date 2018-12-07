<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:06
 */

namespace app\common\model;


use think\Model;

class TableMessage extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'el_table_message';

    protected $primaryKey = 'message_id';

    public $timestamps = false;
}