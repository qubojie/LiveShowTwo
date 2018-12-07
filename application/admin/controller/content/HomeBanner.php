<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午3:11
 */
namespace app\admin\controller\content;

use app\common\controller\AdminAuthAction;
use app\common\model\PageBanner;
use think\Db;
use think\Exception;
use think\Validate;

class HomeBanner extends AdminAuthAction
{
    /**
     * Banner列表
     * @return array
     */
    public function index()
    {
        $pagesize   = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $pageBannerModel = new PageBanner();

            $res = $pageBannerModel
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 添加
     * @return array
     */
    public function add()
    {
        $banner_title = $this->request->param("banner_title","");
        $banner_img   = $this->request->param("banner_img","");
        $link         = $this->request->param("link","");
        $sort         = $this->request->param("sort","100");
        $is_show      = $this->request->param("is_show","0");

        $rule = [
            "banner_title|banner标题"  => "require|max:100|unique:page_banner",
            "banner_img|banner图片"    => "require|max:300",
            "link|链接地址"             => "max:200",
            "sort|排序"                => "number",
            "is_show|是否展示"          => "number",
        ];

        $request_res = [
            "banner_title" => $banner_title,
            "banner_img"   => $banner_img,
            "link"         => $link,
            "sort"         => $sort,
            "is_show"      => $is_show,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "banner_title" => $banner_title,
            "banner_img"   => $banner_img,
            "link"         => $link,
            "sort"         => $sort,
            "is_show"      => $is_show,
            "created_at"   => time(),
            "updated_at"   => time(),
        ];

        try {
            $pageBannerModel = new PageBanner();

            $res = $pageBannerModel
                ->insert($params);

            if ($res !== false) {
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 编辑
     * @return array
     */
    public function edit()
    {
        $banner_id    = $this->request->param("banner_id","");
        $banner_title = $this->request->param("banner_title","");
        $banner_img   = $this->request->param("banner_img","");
        $link         = $this->request->param("link","");
        $sort         = $this->request->param("sort","100");
        $is_show      = $this->request->param("is_show","0");

        $rule = [
            "banner_id|id"            => "require",
            "banner_title|banner标题"  => "require|max:100|unique:page_banner",
            "banner_img|banner图片"    => "require|max:300",
            "link|链接地址"             => "max:200",
            "sort|排序"                => "number",
            "is_show|是否展示"          => "number",
        ];

        $request_res = [
            "banner_id"    => $banner_id,
            "banner_title" => $banner_title,
            "banner_img"   => $banner_img,
            "link"         => $link,
            "sort"         => $sort,
            "is_show"      => $is_show,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $params = [
            "banner_title" => $banner_title,
            "banner_img"   => $banner_img,
            "link"         => $link,
            "sort"         => $sort,
            "is_show"      => $is_show,
            "updated_at"   => time(),
        ];

        try {
            $pageBannerModel = new PageBanner();

            $res = $pageBannerModel
                ->where("banner_id",$banner_id)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 删除
     * @return array
     */
    public function delete()
    {
        $banner_ids  = $this->request->param("banner_id","");

        $rule = [
            "banner_id|id"            => "require",
        ];
        $request_res = [
            "banner_id"    => $banner_ids,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            $id_array = explode(",",$banner_ids);

            $pageBannerModel = new PageBanner();

            foreach ($id_array as $banner_id){
                $is_ok = $pageBannerModel
                    ->where("banner_id",$banner_id)
                    ->delete();
                if ($is_ok == false) {
                    return $this->com_return(false, config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 排序编辑s
     * @return array
     */
    public function sortEdit()
    {
        $banner_id = $this->request->param("banner_id","");//图id
        $sort      = $this->request->param("sort","");//排序

        $rule = [
            "banner_id|id"  => "require",
            "sort|排序"      => "require|number",
        ];
        $check_res = [
            "banner_id" => $banner_id,
            "sort"      => $sort,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $pageBannerModel = new PageBanner();

            $res = $pageBannerModel
                ->where("banner_id",$banner_id)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 是否显示
     * @return array
     */
    public function isShow()
    {
        $banner_id  = $this->request->param("banner_id","");
        $is_show    = (int)$this->request->param("is_show","");

        $rule = [
            "banner_id|id"    => "require",
            "is_show|是否显示" => "require|number",
        ];
        $check_res = [
            "banner_id" => $banner_id,
            "is_show"   => $is_show,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        $params = [
            "is_show"    => $is_show,
            "updated_at" => time()
        ];
        try {
            $pageBannerModel = new PageBanner();

            $res = $pageBannerModel
                ->where("banner_id",$banner_id)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}