<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午10:34
 */

namespace app\common\model;


use think\Model;

class MstTableImage extends Model
{
    /**
     * 关联到模型的数据表
     * 酒桌图片信息
     *
     * @var string
     */
    protected $table = 'EL_MST_TABLE_IMAGE';

    protected $primaryKey = 'table_id';

    public $timestamps = false;

    public $column = [
        'table_id',
        'type',
        'sort',
        'title',
        'image',
    ];
}