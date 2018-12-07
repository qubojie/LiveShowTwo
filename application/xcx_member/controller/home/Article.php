<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:20
 */

namespace app\xcx_member\controller\home;


use app\common\controller\BaseController;
use app\common\controller\HomeCommon;

class Article extends BaseController
{
    /**
     * 文章列表
     * @return array
     */
    public function getArticle()
    {
        $pagesize = $this->request->param("pagesize", config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage", "1");
        $homeCommonObj = new HomeCommon();
        return $homeCommonObj->getArticle($pagesize,$nowPage);
    }
}