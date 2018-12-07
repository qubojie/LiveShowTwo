<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/26
 * Time: 下午3:39
 */

namespace app\common\model;


use think\Model;

class MstCardType extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_CARD_TYPE';

    protected $primaryKey = 'type_id';

    public $timestamps = false;
}