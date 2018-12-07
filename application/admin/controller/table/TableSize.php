<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:59
 */

namespace app\admin\controller\table;


use app\common\controller\AdminAuthAction;
use app\common\model\MstTableSize;
use think\Exception;
use think\Request;
use think\Validate;

class TableSize extends AdminAuthAction
{
    /**
     * 容量列表
     * @return array
     */
    public function index()
    {

        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $tableSizeModel = new MstTableSize();

            $list = $tableSizeModel
                ->where('is_delete',0)
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 容量添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $size_title = $request->param("size_title","");//容量标题
        $size_desc  = $request->param("size_desc","");//容量描述
        $sort       = $request->param("sort",100);

        $rule = [
            "size_title|容量标题"  => "require|max:30|unique:mst_table_size",
            "size_desc|容量描述"   => "max:200",
            "sort|排序"           => "number",
        ];
        $check_data = [
            "size_title" => $size_title,
            "size_desc"  => $size_desc,
            "sort"       => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $insert_data = [
            "size_title" => $size_title,
            "size_desc"  => $size_desc,
            "sort"       => $sort,
            "created_at" => time(),
            "updated_at" => time()
        ];

        try {
            $tableSizeModel = new MstTableSize();
            $is_ok = $tableSizeModel
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
     * 容量编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $size_id    = $request->param("size_id","");//容量id
        $size_title = $request->param("size_title","");//容量标题
        $size_desc  = $request->param("size_desc","");//容量描述
        $sort       = $request->param("sort",100);

        $rule = [
            "size_id|容量id"      => "require",
            "size_title|容量标题"  => "require|max:30|unique:mst_table_size",
            "size_desc|容量描述"   => "max:200",
            "sort|排序"           => "number",
        ];
        $check_data = [
            "size_id"    => $size_id,
            "size_title" => $size_title,
            "size_desc"  => $size_desc,
            "sort"       => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "size_title" => $size_title,
            "size_desc"  => $size_desc,
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $tableSizeModel = new MstTableSize();

            $is_ok = $tableSizeModel
                ->where('size_id',$size_id)
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
     * 容量删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        $size_id    = $request->param("size_id","");//容量id

        $rule = [
            "size_id|容量id"      => "require",
        ];
        $check_data = [
            "size_id"    => $size_id,
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
            $tableSizeModel = new MstTableSize();

            $is_ok = $tableSizeModel
                ->where('size_id',$size_id)
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
        $size_id    = $request->param("size_id","");//容量id
        $sort       = $request->param("sort","");

        $rule = [
            "size_id|容量id"  => "require",
            "sort|排序"       => "require|number",
        ];
        $check_data = [
            "size_id" => $size_id,
            "sort"    => $sort,
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
            $tableSizeModel = new MstTableSize();
            $is_ok = $tableSizeModel
                ->where('size_id',$size_id)
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