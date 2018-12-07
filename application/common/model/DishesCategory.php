<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:10
 */

namespace app\common\model;


use think\Model;

class DishesCategory extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_DISHES_CATEGORY';

    protected $primaryKey = 'cat_id';

    public $timestamps = false;
}