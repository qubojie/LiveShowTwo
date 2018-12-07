<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:53
 */

namespace app\admin\controller\table;


use app\common\controller\AdminAuthAction;
use app\common\model\MstTableAppearance;
use think\Exception;
use think\Request;
use think\Validate;

class TableAppearance extends AdminAuthAction
{
    /**
     * 品项列表
     * @return array
     */
    public function index()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $tableAppearanceModel = new MstTableAppearance();

            $list = $tableAppearanceModel
                ->where('is_delete',0)
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }

    }

    /**
     * 品项添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $appearance_title = $request->param("appearance_title","");//品项标题
        $appearance_desc  = $request->param("appearance_desc","");//品项描述
        $sort             = $request->param("sort","");//排序

        $rule = [
            "appearance_title|品项标题"  => "require|max:30|unique:mst_table_appearance",
            "appearance_desc|品项描述"   => "require|max:200",
            "sort|排序"                 => "number",
        ];
        $check_data = [
            "appearance_title" => $appearance_title,
            "appearance_desc"  => $appearance_desc,
            "sort"             => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $insert_data = [
            "appearance_title" => $appearance_title,
            "appearance_desc"  => $appearance_desc,
            "sort"             => $sort,
            "created_at"       => time(),
            "updated_at"       => time()
        ];

        try {
            $tableAppearanceModel = new MstTableAppearance();

            $is_ok = $tableAppearanceModel
                ->insert($insert_data);

            if ($is_ok){
                return $this->com_return(true,config("SUCCESS"));
            }else{
                return $this->com_return(false,config("FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 品项编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $appearance_id    = $request->param("appearance_id","");//品项id
        $appearance_title = $request->param("appearance_title","");//品项标题
        $appearance_desc  = $request->param("appearance_desc","");//品项描述
        $sort             = $request->param("sort","");//排序

        $rule = [
            "appearance_id|品项id"      => "require",
            "appearance_title|品项标题"  => "require|max:30|unique:mst_table_appearance",
            "appearance_desc|品项描述"   => "require|max:200",
            "sort|排序"                 => "number",
        ];
        $check_data = [
            "appearance_id"    => $appearance_id,
            "appearance_title" => $appearance_title,
            "appearance_desc"  => $appearance_desc,
            "sort"             => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "appearance_title" => $appearance_title,
            "appearance_desc"  => $appearance_desc,
            "sort"             => $sort,
            "updated_at"       => time()
        ];

        try {
            $tableAppearanceModel = new MstTableAppearance();
            $is_ok = $tableAppearanceModel
                ->where('appearance_id',$appearance_id)
                ->update($update_data);

            if ($is_ok !== false){
                return $this->com_return(true,config("SUCCESS"));
            }else{
                return $this->com_return(false,config("FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 品项删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $appearance_id    = $request->param("appearance_id","");//品项id

        $rule = [
            "appearance_id|品项id"      => "require",
        ];
        $check_data = [
            "appearance_id"    => $appearance_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $delete_date = [
            "is_delete" => 1,
            "updated_at" => time()
        ];

        try {
            $tableAppearanceModel = new MstTableAppearance();
            $is_ok = $tableAppearanceModel
                ->where('appearance_id',$appearance_id)
                ->update($delete_date);

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
     * 排序编辑
     * @param Request $request
     * @return array
     */
    public function sortEdit(Request $request)
    {
        $appearance_id = $request->param("appearance_id","");//品项id
        $sort          = $request->param("sort","");

        $rule = [
            "appearance_id|品项id" => "require",
            "sort|排序"            => "require|number",
        ];
        $check_data = [
            "appearance_id" => $appearance_id,
            "sort"          => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "sort" => $sort,
            "updated_at" => time()
        ];
        try {
            $tableAppearanceModel = new MstTableAppearance();
            $is_ok = $tableAppearanceModel
                ->where('appearance_id',$appearance_id)
                ->update($update_data);

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