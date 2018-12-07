<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午3:13
 */
namespace app\common\model;

use think\Model;

class PageBanner extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_PAGE_BANNER';

    protected $primaryKey = 'banner_id';

    public $timestamps = false;

    public $column = [

    ];
}