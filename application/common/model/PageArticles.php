<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午2:41
 */
namespace app\common\model;

use think\Model;

class PageArticles extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'EL_PAGE_ARTICLE';

    protected $primaryKey = 'article_id';

    public $timestamps = false;

    public $column = [

    ];
}