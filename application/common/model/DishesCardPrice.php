<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午12:13
 */

namespace app\common\model;


use think\Model;

class DishesCardPrice extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_DISHES_CARD_PRICE';

    public $timestamps = false;
}