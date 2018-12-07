<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:06
 */
namespace app\common\model;

use think\Model;

class MstMerchantCategory extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_MST_MERCHANT_CATEGORY';

    protected $primaryKey = 'cat_id';

    public $timestamps = false;
}