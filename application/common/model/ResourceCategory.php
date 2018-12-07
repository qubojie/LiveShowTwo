<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:07
 */

namespace app\common\model;


use think\Model;

class ResourceCategory extends Model
{
    /**
     * 关联到模型的数据表
     * 素材分类
     *
     * @var string
     */
    protected $table = 'EL_RESOURCE_CATEGORY';

    protected $primaryKey = 'cat_id';

    public $timestamps = false;
}