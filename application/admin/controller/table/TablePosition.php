<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:32
 */

namespace app\admin\controller\table;


use app\common\controller\AdminAuthAction;
use app\common\model\MstTableArea;
use app\common\model\MstTableLocation;
use think\Exception;
use think\Validate;

class TablePosition extends AdminAuthAction
{
    /**
     * 位置列表
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
            $tableLocationModel = new MstTableLocation();
            $list = $tableLocationModel
                ->where('is_delete',0)
                ->order("sort")
                ->paginate($pagesize,false,$config);
            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 位置添加
     * @return array
     */
    public function add()
    {
        $location_title = $this->request->param("location_title","");
        $location_desc  = $this->request->param("location_desc","");
        $sort           = $this->request->param("sort","100");

        $rule = [
            "location_title|位置名称"  => "require|max:30|unique_delete:mst_table_location",
            "location_desc|位置描述"   => "max:200",
            "sort|排序"               => "number",
        ];
        $check_data = [
            "location_title" => $location_title,
            "location_desc"  => $location_desc,
            "sort"           => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $insert_data = [
            "location_title" => $location_title,
            "location_desc"  => $location_desc,
            "sort"           => $sort,
            "created_at"     => time(),
            "updated_at"     => time()
        ];

        try {
            $tableLocationModel = new MstTableLocation();

            $is_ok = $tableLocationModel
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
     * 位置编辑
     * @return array
     */
    public function edit()
    {
        $location_id    = $this->request->param("location_id","");
        $location_title = $this->request->param("location_title","");
        $location_desc  = $this->request->param("location_desc","");
        $sort           = $this->request->param("sort","100");

        $rule = [
            "location_id|位置id"      => "require",
            "location_title|位置名称"  => "require|max:30|unique_delete:mst_table_location,location_id",
            "location_desc|位置描述"   => "max:200",
            "sort|排序"               => "number",
        ];
        $check_data = [
            "location_id"    => $location_id,
            "location_title" => $location_title,
            "location_desc"  => $location_desc,
            "sort"           => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "location_title" => $location_title,
            "location_desc"  => $location_desc,
            "sort"           => $sort,
            "updated_at"     => time()
        ];

        try {
            $tableLocationModel = new MstTableLocation();
            $is_ok = $tableLocationModel
                ->where("location_id",$location_id)
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
     * 位置删除
     * @return array
     */
    public function delete()
    {
        $location_id    = $this->request->param("location_id","");
        $rule = [
            "location_id|位置id"      => "require",
        ];
        $check_data = [
            "location_id"    => $location_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $tableAreaModel = new MstTableArea();

        $is_exist = $tableAreaModel
            ->where("location_id",$location_id)
            ->where("is_delete",0)
            ->count();

        if ($is_exist > 0){
            return $this->com_return(false,config("params.TABLE")['AREA_EXIST']);
        }

        $delete_date = [
            "is_delete" => 1,
            "updated_at" => time()
        ];

        try {
            $tableLocationModel = new MstTableLocation();
            $is_ok = $tableLocationModel
                ->where('location_id',$location_id)
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
    public function sortEdit()
    {
        $location_id  = $this->request->param("location_id","");
        $sort         = $this->request->param("sort","");
        $rule = [
            "location_id|位置id"      => "require",
            "sort|排序"               => "require|number",
        ];
        $check_data = [
            "location_id"    => $location_id,
            "sort"           => $sort,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $tableLocationModel = new MstTableLocation();
            $is_ok = $tableLocationModel
                ->where('location_id',$location_id)
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