<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/27
 * Time: 下午2:54
 */

namespace app\common\model;


use think\Model;

class DishesAttribute extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_DISHES_ATTRIBUTE';

    protected $primaryKey = 'att_id';

    public $timestamps = false;

}