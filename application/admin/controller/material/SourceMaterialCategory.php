<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:05
 */
namespace app\admin\controller\material;


use app\common\controller\AdminAuthAction;
use app\common\model\ResourceCategory;
use app\common\model\ResourceFile;
use think\Exception;
use think\Request;
use think\Validate;

class SourceMaterialCategory extends AdminAuthAction
{
    /**
     * 素材分类列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $type = $request->param("type","");//分类类型  0图片   1视频

        $rule = [
            "type|分类类型"  => "require",
        ];
        $check_data = [
            "type"  => $type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $type_where['type']    = ['eq',$type];
        try {

            $resourceCategoryModel = new ResourceCategory();
            $catList = $resourceCategoryModel
                ->where($type_where)
                ->order('sort')
                ->select();

            $catList = json_decode(json_encode($catList),true);
            if (!empty($catList)){
                $resourceFileModel = new ResourceFile();
                for ($i = 0; $i < count($catList); $i ++){
                    $cat_id  = $catList[$i]['cat_id'];
                    $cat_num = $resourceFileModel
                        ->where("cat_id",$cat_id)
                        ->where($type_where)
                        ->count();
                    $catList[$i]['cat_num'] = $cat_num;
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$catList);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 素材分类添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $type = $request->param("type","");
        $name = $request->param('name', '');
        $sort = $request->param('sort', '500');

        //验证
        $rule = [
            "type|分类类型" => "require",
            "name|分类名"  => "require|unique:resource_category",
            "sort|排序"    => "number",
        ];
        $check_data = [
            "name"  => $name,
            "sort"  => $sort,
            "type"  => $type
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        if (empty($sort)){
            $sort = '500';
        }

        $params = [
            "type"       => $type,
            "name"       => $name,
            "sort"       => $sort,
            "created_at" => time(),
            "updated_at" => time()
        ];

        try {
            $resourceCategoryModel = new ResourceCategory();

            $res = $resourceCategoryModel
                ->insert($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 素材分类删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $cat_id = $request->param("cat_id","");//分类id

        $rule = [
            "cat_id|分类id"  => "require",
        ];
        $check_data = [
            "cat_id"  => $cat_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            /*检测当前分类下是否存在未删除的素材 On*/
            $is_exist = $this->checkCategoryHaveSource($cat_id);
            if ($is_exist){
                return $this->com_return(false,config("params.TEMP")['SC_DELETE_NO']);
            }
            /*检测当前分类下是否存在未删除的素材 Off*/

            $resourceCategoryModel = new ResourceCategory();

            $is_delete = $resourceCategoryModel
                ->where("cat_id",$cat_id)
                ->delete();

            if ($is_delete !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 素材分类编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $cat_id = $request->param("cat_id","");//分类id
        $name   = $request->param("name","");//名称
        $sort   = $request->param("sort","");//排序

        $rule = [
            "cat_id|分类id" => "require",
            "name|名称"     => "require",
            "sort|排序"     => "require|number",
        ];
        $check_data = [
            "cat_id" => $cat_id,
            "name"   => $name,
            "sort"   => $sort
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        $params = [
            "name"       => $name,
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $resourceCategoryModel = new ResourceCategory();

            $is_edit = $resourceCategoryModel
                ->where("cat_id",$cat_id)
                ->update($params);

            if ($is_edit !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 检测当前素材分类下是否有素材
     * @param $cat_id
     * @return bool
     */
    public function checkCategoryHaveSource($cat_id)
    {
        try {
            $resourceFileModel = new ResourceFile();

            $is_exist = $resourceFileModel
                ->where("cat_id",$cat_id)
                ->count();

            if ($is_exist > 0){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}