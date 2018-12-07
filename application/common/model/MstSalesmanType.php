<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午5:40
 */

namespace app\common\model;


use think\Model;

class MstSalesmanType extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_SALESMAN_TYPE';

    protected $primaryKey = 'stype_id';

    public $timestamps = false;

}