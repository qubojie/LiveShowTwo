<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:09
 */

namespace app\admin\controller\dishes;


use app\common\controller\AdminAuthAction;
use app\common\controller\DishesCommon;
use app\common\model\DishesCategory;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class DishClassify extends AdminAuthAction
{
    /**
     * 获取菜品分类无分页
     * @return array
     */
    public function dishType()
    {
        try {
            $dishCateGoryModel = new DishesCategory();

            $list = $dishCateGoryModel
                ->where("is_delete","0")
                ->order("sort")
                ->select();
            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品分类列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $pagesize   = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        $dishCateGoryModel = new DishesCategory();

        try {
            $list = $dishCateGoryModel
                ->where("is_delete","0")
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品分类添加
     * @param Request $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add(Request $request)
    {
        $cat_name  = $request->param("cat_name","");//分类名称
        $cat_img   = $request->param("cat_img","");//分类图片
        $sort      = $request->param("sort","");//排序
        $is_enable = $request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "cat_name|分类名称"  => "require|max:50|unique_delete:dishes_category",
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

        try {
            if (empty($cat_img)){
                $cat_img = getSysSetting("sys_logo");
            }

            $params = [
                "cat_name"   => $cat_name,
                "cat_img"   => $cat_img,
                "sort"       => $sort,
                "is_enable"  => $is_enable,
                "created_at" => time(),
                "updated_at" => time()
            ];
            $dishCateGoryModel = new DishesCategory();
            $is_ok = $dishCateGoryModel
                ->insert($params);
            if ($is_ok){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品分类编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $cat_id    = $request->param("cat_id","");//分类id
        $cat_name  = $request->param("cat_name","");//分类名称
        $cat_img   = $request->param("cat_img","");//分类图片
        $sort      = $request->param("sort","");//排序
        $is_enable = $request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "cat_id|属性id"    => "require",
            "cat_name|分类名称" => "require|max:50|unique_me:dishes_category,cat_id",
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

        try {
            if (empty($cat_img)){
                $cat_img = getSysSetting("sys_logo");
            }

            $params = [
                "cat_name"   => $cat_name,
                "cat_img"    => $cat_img,
                "sort"       => $sort,
                "is_enable"  => $is_enable,
                "updated_at" => time()
            ];

            $dishCateGoryModel = new DishesCategory();
            $is_ok = $dishCateGoryModel
                ->where("cat_id",$cat_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品分类删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $cat_ids = $request->param("cat_id","");//分类id

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
        try{
            $dishCateGoryModel = new DishesCategory();
            $dishesCommonObj = new DishesCommon();
            foreach ($id_array as $cat_id){
                //查看当前分类下是否存在菜品
                $is_exist_dishes = $dishesCommonObj->classifyHaveDish($cat_id);
                if ($is_exist_dishes){
                    return $this->com_return(false,config("params.DISHES")['CLASS_EXIST_DISHES']);
                }

                $params = [
                    "is_delete"  => 1,
                    "updated_at" => time()
                ];
                $is_ok = $dishCateGoryModel
                    ->where("cat_id",$cat_id)
                    ->update($params);

                if ($is_ok === false){
                    return $this->com_return(false,config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));
        }catch (Exception $e){
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 菜品分类排序
     * @param Request $request
     * @return array
     */
    public function sortEdit(Request $request)
    {
        $cat_id = $request->param("cat_id","");//分类id
        $sort   = $request->param("sort","");//排序

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
            $dishCateGoryModel = new DishesCategory();

            $is_ok = $dishCateGoryModel
                ->where("cat_id",$cat_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 菜品分类是否启用
     * @param Request $request
     * @return array
     */
    public function enable(Request $request)
    {
        $cat_id    = $request->param("cat_id","");//分类id
        $is_enable = $request->param("is_enable","");//是否启用

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
            $dishCateGoryModel = new DishesCategory();

            $is_ok = $dishCateGoryModel
                ->where("cat_id",$cat_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


}