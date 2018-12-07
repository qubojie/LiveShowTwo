<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 上午11:02
 */
namespace app\admin\controller\system;

use app\common\controller\AdminAuthAction;
use app\common\model\SysRole;
use think\Validate;

class Roles extends AdminAuthAction
{
    /**
     * 角色列表
     * @return array
     * @throws \think\exception\DbException
     */
   public function index()
   {
       $result = array('roles' => array());
       $result['filter']['orderBy'] = $this->request->has('orderBy') ? $this->request->input('orderBy') : 'role_id';
       $result['filter']['sort']    = $this->request->has('sort') ? $this->request->input('sort') : 'asc';

       $nowPage = $this->request->has("nowPage") ? $this->request->param("nowPage") : "1"; //当前页
       $config = [
           "page" => $nowPage
       ];

       $sysRoleModel = new SysRole();

       $result = $sysRoleModel
           ->order($result['filter']['orderBy'], $result['filter']['sort'])
           ->paginate(config('PAGESIZE'),'',$config);

       return $this->com_return(true,config('params.SUCCESS'),$result);
   }

    /**
     * 角色添加
     * @return array
     */
   public function add()
   {
       $role_name       = $this->request->param("role_name","");
       $role_describe   = $this->request->param("role_describe","");
       $action_list     = $this->request->param("action_list","");

       $rule = [
           "role_name|角色名"       => "require|max:60|unique:sys_role",
           "role_describe|角色描述" => "require"
       ];

       $request_res = [
           "role_name"     => $role_name,
           "role_describe" => $role_describe
       ];

       $validate = new Validate($rule);

       if (!$validate->check($request_res)){
           return $this->com_return(false,$validate->getError());
       }

       //写入数据,返回id
       $insert_data = [
           "role_name"     => $role_name,
           "role_describe" => $role_describe,
           "action_list"   => $action_list
       ];

       $sysRoleModel = new SysRole();

       $res = $sysRoleModel
           ->insert($insert_data);

       if ($res !== false) {
           return $this->com_return(true,config('params.SUCCESS'));
       }else{
           return $this->com_return(false,config('params.FAIL'));
       }
   }

    /**
     * 角色编辑
     * @return array
     */
   public function edit()
   {
       $role_id        = $this->request->param("role_id","");
       $role_name      = $this->request->param("role_name","");
       $role_describe  = $this->request->param("role_describe","");
       $action_list    = $this->request->param("action_list","");

       $rule = [
           "role_id|角色id"        => "require",
           "role_name|角色名"      => "require|max:60|unique:sys_role",
           "role_describe|角色描述" => "require"
       ];

       $request_res = [
           "role_id"       => $role_id,
           "role_name"     => $role_name,
           "role_describe" => $role_describe
       ];

       $validate = new Validate($rule);

       if (!$validate->check($request_res)){
           return $this->com_return(false,$validate->getError());
       }

       //更新数据,返回
       $update_data = [
           "role_name"     => $role_name,
           "role_describe" => $role_describe,
           "action_list"   => $action_list
       ];

       $sysRoleModel = new SysRole();

       $is_ok = $sysRoleModel
           ->where("role_id",$role_id)
           ->update($update_data);

       if ($is_ok !== false){
           return $this->com_return(true,config('params.SUCCESS'));
       }else{
           return $this->com_return(false,config('params.FAIL'));
       }
   }

    /**
     * 角色删除
     * @return array
     */
    public function delete()
    {
        $role_id  =$this->request->param("role_id","");

        $rule = [
            "role_id|角色id"        => "require",
        ];

        $request_res = [
            "role_id"       => $role_id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        //查看当前角色是否有管理员
        $is_exist = self::checkRoleHaveAdmin($role_id);

        if ($is_exist) {
            //如果存在
            return $this->com_return(false,config('params.ROLE_HAVE_ADMIN'));
        }

        $sysRoleModel = new SysRole();

        $res = $sysRoleModel
            ->where("role_id",$role_id)
            ->delete();

        if ($res !== false){
            return $this->com_return(true,config('params.SUCCESS'));
        }else{
            return $this->com_return(false,config('params.FAIL'));
        }
    }


}