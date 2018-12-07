<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:16
 */

namespace app\common\controller;


use app\common\model\PageArticles;
use app\common\model\PageBanner;
use think\Env;
use think\Exception;

class HomeCommon extends BaseController
{
    /**
     * 获取Banner列表
     * @return array
     */
    public function getBannerList()
    {
        try {
            $bannerModel = new PageBanner();

            $list = $bannerModel
                ->where('is_show',1)
                ->order("sort")
                ->field("is_show,created_at,updated_at",true)
                ->select();
            $list = json_decode(json_encode($list),true);
            /*图片样式 On*/
            $imageView = Env::get('QINIU_IMAGEVIEW_HOMEBANNER');
            for ($i = 0; $i < count($list); $i ++){
                $banner_img = $list[$i]['banner_img'];
                $list[$i]['banner_img'] = $banner_img."?$imageView";
            }
            /*图片样式 Off*/
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 文章列表
     * @return array
     */
    public function getArticle($pagesize,$nowPage)
    {
        if (empty($pagesize)) $pagesize = config('page_size');
        $config = [
            "page" => $nowPage,
        ];
        try {
            $articleModel = new PageArticles();
            $articleInfo = $articleModel
                ->where('is_show',1)
                ->order('is_top DESC,sort,created_at DESC')
                ->paginate($pagesize,false,$config);
            $articleInfo = json_decode(json_encode($articleInfo),true);
            if (!empty($articleInfo)){
                $imageView = Env::get('QINIU_IMAGEVIEW_HOMEARTICLE');
                for ($i = 0; $i < count($articleInfo['data']); $i ++){
                    /*图片样式 On*/
                    $article_image = $articleInfo['data'][$i]['article_image'];
                    $articleInfo['data'][$i]['article_image'] = $article_image."?$imageView";
                    /*图片样式 Off*/
                }
            }
            return $this->com_return(true,config('params.SUCCESS'),$articleInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}