<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:05
 */

namespace app\admin\controller\business;


use app\common\controller\AdminAuthAction;
use app\common\model\MstMerchant;
use app\common\model\MstMerchantCategory;
use think\Db;
use think\Exception;
use think\Validate;

class MerchantCategory extends AdminAuthAction
{
    /**
     * 获取联盟商家分类无分页
     * @return array
     */
    public function merchantType()
    {
        try {
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->where("is_delete","0")
                ->order("sort")
                ->select();

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家分类列表
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
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->where("is_delete","0")
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家分类添加
     * @return array
     */
    public function add()
    {
        $cat_name  = $this->request->param("cat_name","");//分类名称
        $sort      = $this->request->param("sort","");//排序
        $is_enable = $this->request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "cat_name|分类名称"  => "require|max:50|unique_delete:mst_merchant_category",
            "sort|排序"         => "number",
        ];

        $check_res = [
            "cat_name" => $cat_name,
            "sort"     => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "cat_name"   => $cat_name,
            "sort"       => $sort,
            "is_enable"  => $is_enable,
            "created_at" => time(),
            "updated_at" => time()
        ];
        try {
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->insert($params);

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
     * 联盟商家分类编辑
     * @return array
     */
    public function edit()
    {
        $cat_id    = $this->request->param("cat_id","");//分类id
        $cat_name  = $this->request->param("cat_name","");//分类名称
        $sort      = $this->request->param("sort","");//排序
        $is_enable = $this->request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "cat_id|属性id"    => "require",
            "cat_name|分类名称" => "require|max:50|unique_me:mst_merchant_category,cat_id",
            "sort|排序"        => "number",
        ];

        $check_res = [
            "cat_id"   => $cat_id,
            "cat_name" => $cat_name,
            "sort"     => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "cat_name"   => $cat_name,
            "sort"       => $sort,
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];

        try {
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->where("cat_id",$cat_id)
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
     * 联盟商家分类删除
     * @return array
     */
    public function delete()
    {
        $cat_ids = $this->request->param("cat_id","");//分类id

        $rule = [
            "cat_id|分类id"      => "require",
        ];

        $check_res = [
            "cat_id" => $cat_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $id_array = explode(",",$cat_ids);

        Db::startTrans();
        try {
            $merchantCategoryModel = new MstMerchantCategory();
            foreach ($id_array as $cat_id){
                //查看当前分类下是否存在菜品
                $is_exist_merchant = $this->categoryHaveMerchant($cat_id);

                if ($is_exist_merchant){
                    return $this->com_return(false,config("params.MERCHANT")['CLASS_EXIST_MERCHANT']);
                }

                $params = [
                    "is_delete"  => 1,
                    "updated_at" => time()
                ];

                $res = $merchantCategoryModel
                    ->where("cat_id",$cat_id)
                    ->update($params);

                if ($res === false) {
                    return $this->com_return(false,config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家分类排序
     * @return array
     */
    public function sortEdit()
    {
        $cat_id = $this->request->param("cat_id","");//分类id
        $sort   = $this->request->param("sort","");//排序

        $rule = [
            "cat_id|分类id"  => "require",
            "sort|排序"      => "require|number",
        ];

        $check_res = [
            "cat_id"  => $cat_id,
            "sort"    => $sort,
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
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->where("cat_id",$cat_id)
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
     * 联盟商家分类是否启用
     * @return array
     */
    public function enable()
    {
        $cat_id    = $this->request->param("cat_id","");//分类id
        $is_enable = $this->request->param("is_enable","");//是否启用

        $rule = [
            "cat_id|分类id"     => "require",
            "is_enable|是否启用" => "require|number",
        ];

        $check_res = [
            "cat_id"    => $cat_id,
            "is_enable" => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        $params = [
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];
        try {
            $merchantCategoryModel = new MstMerchantCategory();
            $res = $merchantCategoryModel
                ->where("cat_id",$cat_id)
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
     * 分类列表 ———— 键值对
     * @return array
     */
    public function cateList()
    {
        try {
            $merchantCategoryModel = new MstMerchantCategory();

            $res = $merchantCategoryModel
                ->where('is_delete',0)
                ->field("cat_id,cat_name")
                ->select();

            $res = json_decode(json_encode($res),true);

            $list = [];
            foreach ($res as $key => $val){

                foreach ($val as $k => $v){

                    if ($k == "cat_id"){
                        $k = "key";
                    }else{
                        $k = "name";
                    }

                    $list[$key][$k] = $v;
                }
            }

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }


    /**
     * 查看当前分类下是否存在联盟商家
     * @param $cat_id
     * @return bool
     */
    protected function categoryHaveMerchant($cat_id)
    {
        $merchantModel = new MstMerchant();

        $is_have_merchant = $merchantModel
            ->where("cat_id",$cat_id)
            ->count();

        if ($is_have_merchant > 0){
            return true;
        }else{
            return false;
        }
    }

}