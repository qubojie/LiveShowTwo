<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:15
 */
namespace app\xcx_member\controller\home;

use app\common\controller\BaseController;
use app\common\controller\HomeCommon;

class Banner extends BaseController
{
    /**
     * 首页banner列表
     * @return array
     */
    public function index()
    {
        $homeCommonObj = new HomeCommon();
        return $homeCommonObj->getBannerList();
    }
}