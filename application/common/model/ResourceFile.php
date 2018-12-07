<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:07
 */

namespace app\common\model;


use think\Model;

class ResourceFile extends Model
{
    /**
     * 关联到模型的数据表
     * 素材分类
     *
     * @var string
     */
    protected $table = 'EL_RESOURCE_FILE';

    protected $primaryKey = 'id';

    public $timestamps = false;
}