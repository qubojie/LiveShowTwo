<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午5:52
 */

namespace app\admin\controller\structure;


use app\common\controller\AdminAuthAction;
use app\common\model\ManageDepartment;
use app\common\model\ManageSalesman;
use think\Exception;
use think\Validate;

class Department extends AdminAuthAction
{
    /**
     * 列表
     * @return array
     */
    public function index()
    {
        $keyword = $this->request->param("keyword","");

        $where = [];
        if (!empty($keyword)){
            $where['department_title|department_manager|phone'] = ["like","%$keyword%"];
        }

        try {
            $manageDepartmentModel = new ManageDepartment();

            $list = $manageDepartmentModel
                ->where($where)
                ->select();

            $list = json_decode(json_encode($list),true);

            //将数据转换成树状结构
            $res = make_tree($list,'department_id','parent_id');

            $manageModel = new ManageSalesman();

            for ($i = 0; $i < count($res); $i ++){
                $department_id = $res[$i]['department_id'];
                $res[$i]['department_user_num'] = $manageModel
                    ->where("department_id",$department_id)
                    ->where("statue","neq",config("salesman.salesman_status")['resignation']['key'])
                    ->count();
            }

            $department_list['data'] = $res;

            return $this->com_return(true,config("params.SUCCESS"),$department_list);

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
        $parent_id          = $this->request->param("parent_id",0);//父id
        $department_title   = $this->request->param("department_title","");//部门名称
        $department_manager = $this->request->param("department_manager","");//部门负责人
        $phone              = $this->request->param("phone","");//联系电话

        $rule = [
            "department_title|部门名称"     => "require|max:50|unique:manage_department",
            "phone|联系电话"                => "number|regex:1[0-9]{1}[0-9]{9}",
        ];

        $request_res = [
            "department_title"   => $department_title,
            "phone"              => $phone,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $insert_data = [
            "parent_id"          => $parent_id,
            "department_title"   => $department_title,
            "department_manager" => $department_manager,
            "phone"              => $phone,
            "created_at"         => time(),
            "updated_at"         => time()
        ];
        try {
            $manageDepartmentModel = new ManageDepartment();
            $res = $manageDepartmentModel
                ->insert($insert_data);
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
     * 编辑
     * @return array
     */
    public function edit()
    {

        $department_id      = $this->request->param("department_id",""); //部门id
        $parent_id          = $this->request->param("parent_id",0);//父类id
        $department_title   = $this->request->param("department_title","");//部门名称
        $department_manager = $this->request->param("department_manager","");//部门负责人
        $phone              = $this->request->param("phone","");//联系电话

        $rule = [
            "department_id|部门id"         => "require",
            "parent_id|父类id"             => "require",
            "department_title|部门名称"     => "require|max:50|unique:manage_department",
            "phone|联系电话"                => "number|regex:1[0-9]{1}[0-9]{9}",
        ];

        $request_res = [
            "department_id"      => $department_id,
            "parent_id"          => $parent_id,
            "department_title"   => $department_title,
            "phone"              => $phone,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $time = time();

        $update_data = [
            "parent_id"          => $parent_id,
            "department_title"   => $department_title,
            "department_manager" => $department_manager,
            "phone"              => $phone,
            "updated_at"         => $time
        ];
        try {
            $manageDepartmentModel = new ManageDepartment();

            $res = $manageDepartmentModel
                ->where('department_id', $department_id)
                ->update($update_data);
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
        $department_id = $this->request->param("department_id",""); //部门id

        $rule = [
            "department_id|部门" => "require",
        ];
        $request_res = [
            "department_id" => $department_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            $manageDepartmentModel = new ManageDepartment();
            //查询表中是否存在以此部门为父类的子类
            $is_exist = $manageDepartmentModel
                ->where("parent_id",$department_id)
                ->count();
            if ($is_exist > 0) {
                return $this->com_return(false,config('params.EXIST_SUBCLASS'));
            }

            //查询此部门下是否存在人员
            $manageSalesmanModel = new ManageSalesman();
            $exist_user = $manageSalesmanModel
                ->where("department_id",$department_id)
                ->where("statue","neq","9")
                ->count();
            if ($exist_user > 0) {
                return $this->com_return(false,config('params.EXIST_MANAGE'));
            }

            $res = $manageDepartmentModel
                ->where("department_id",$department_id)
                ->delete();
            if ($res !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}