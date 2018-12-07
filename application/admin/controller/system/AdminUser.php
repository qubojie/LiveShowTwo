<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:45
 */
namespace app\admin\controller\system;

use app\common\controller\AdminAuthAction;
use app\common\model\SysAdminUser;
use app\common\model\SysRole;
use think\Db;
use think\Exception;
use think\Validate;

class AdminUser extends AdminAuthAction
{
    /**
     * 管理员列表
     * @return array
     */
    public function index()
    {
        $pagesize   = $this->request->param("pagesize",config('PAGESIZE'));

        $result     = array();

        $orderBy    = $this->request->param("orderBy","");

        $sort       = $this->request->param("sort","asc");

        if (!empty($orderBy)){
            $result['filter']['orderBy'] = $orderBy;
        }else{
            $result['filter']['orderBy'] = "id";
        }

        if (!empty($sort)){
            $result['filter']['sort'] = $sort;
        }else{
            $result['filter']['sort'] = "asc";
        }

        try {
            $sysAdminUserModel = new SysAdminUser();

            $nowPage = $this->request->has("nowPage") ? $this->request->param("nowPage") : "1"; //当前页

            $config = [
                "page" => $nowPage
            ];

            $column = $sysAdminUserModel->column;
            foreach ($column as $key => $val) {
                $column[$key] = "sdu.".$val;
            }

            $result = $sysAdminUserModel
                ->alias("sdu")
                ->join("sys_role sr","sr.role_id = sdu.role_id")
                ->where('is_delete','0')
                ->order($result['filter']['orderBy'],$result['filter']['sort'])
                ->field($column)
                ->field("sr.role_id,sr.role_name,sr.role_describe")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$result);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 登陆管理员详情
     * @return array
     */
    public function detail()
    {
        $authorization = $this->request->header("Authorization","");

        try {
            $res = self::tokenGetAdminLoginInfo($authorization);
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 添加管理员
     * @return array
     */
    public function create()
    {
        $user_name              = $this->request->param("user_name","");            //用户名
        $phone                  = $this->request->param("phone","");                //电话号码
        $email                  = $this->request->param("email","");                //邮箱
        $password               = $this->request->param("password","");             //密码
        $password_confirmation  = $this->request->param("password_confirmation",""); //确认密码
        $role_id                = $this->request->param("role_id","");              //角色id
        $user_sn                = $this->request->param("user_sn","");              //工号

        $rule = [
            "user_name|账号"               => "require|unique:sys_admin_user",
            "password|密码"                => "require",
            "password_confirmation|确认密码"=> "require",
            "role_id|角色分配"              => "require",
            "user_sn|工号"                 => "unique:sys_admin_user",
            "phone|电话"                   => "regex:1[0-9]{1}[0-9]{9}|unique:sys_admin_user",
            "email|邮箱"                   => "email|unique:sys_admin_user",
        ];

        $request_res = [
            "user_name"              => $user_name,
            "phone"                  => $phone,
            "email"                  => $email,
            "password"               => $password,
            "password_confirmation"  => $password_confirmation,
            "role_id"                => $role_id,
            "user_sn"                => $user_sn,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($password !== $password_confirmation){
            return $this->com_return(false,config("params.PASSWORD_DIF"),null);
        }

        try {
            $sysRoleModel = new SysRole();

            $action_list_res =  $sysRoleModel
                ->where('role_id',$role_id)
                ->field('action_list')
                ->find();
            $action_list_res = json_decode($action_list_res,true);
            $action_list = $action_list_res['action_list'];

            $sysAdminUserModel = new SysAdminUser();

            $insert_data = [
                "user_name"              => $user_name,
                "phone"                  => $phone,
                "email"                  => $email,
                "password"               => sha1($password),
                "role_id"                => $role_id,
                "user_sn"                => $user_sn,
                "action_list"            => $action_list,
                "nav_list"               => "all",
                "lang_type"              => "E",
                "is_delete"              => 0,
                "created_at"             => time(),
                "updated_at"             => time()
            ];

            $id = $sysAdminUserModel
                ->insertGetId($insert_data);

            if (!$id) {
                return $this->com_return(false,config("params.FAIL"));
            }

            $column = $sysAdminUserModel->column;

            $res = $sysAdminUserModel
                ->alias('sum')
                ->join('sys_role sr','sr.role_id = sum.role_id')
                ->where("id",$id)
                ->field($column)
                ->field('sr.role_name,sr.role_describe')
                ->find();

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 管理员编辑提交
     * @return array
     */
    public function edit()
    {
        $id         = $this->request->param("id","");
        $user_name  = $this->request->param("user_name","");
        $role_id    = $this->request->param("role_id","");
        $user_sn    = $this->request->param("user_sn","");
        $email      = $this->request->param("email","");
        $phone      = $this->request->param("phone","");

        $rule = [
            "id"              => "require",
            "user_name|用户名" => "require|unique:sys_admin_user",
            "role_id|角色分配" => "require",
            "user_sn|工号"    => "unique:sys_admin_user",
            "email|邮箱"      => "email|unique:sys_admin_user",
            "phone|电话号码"   => "regex:1[0-9]{1}[0-9]{9}|unique:sys_admin_user",
        ];

        $request_res = [
            "id"        => $id,
            "user_name" => $user_name,
            "role_id"   => $role_id,
            "user_sn"   => $user_sn,
            "email"     => $email,
            "phone"     => $phone,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            $sysAdminUserModel = new SysAdminUser();

            $update_data = [
                "user_name"  => $user_name,
                "role_id"    => $role_id,
                "user_sn"    => $user_sn,
                "email"      => $email,
                "phone"      => $phone,
                "updated_at" => time()
            ];
            $res = $sysAdminUserModel
                ->where("id",$id)
                ->update($update_data);

            if ($res === false){
                return $this->com_return(false,config("params.FAIL"));
            }

            return $this->com_return(true,config("params.SUCCESS"));
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 管理员删除
     * @return array
     */
    public function delete()
    {
        $ids = $this->request->param("id","");

        $rule = [
            "id"  => "require",
        ];

        $request_res = [
            "id" => $ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }
        try {
            $id_array = explode(",",$ids);

            //查看当前登录管理员是否有删除权限
            $authorization = $this->request->header("Authorization",'');

            $sysAdminModel = new SysAdminUser();

            $admin_info = $sysAdminModel
                ->where("token",$authorization)
                ->field("is_sys")
                ->find();

            if (empty($admin_info)) {
                return $this->com_return(false,config("params.FAIL"));
            }

            $is_sys = $admin_info["is_sys"];

            if (!$is_sys){
                return $this->com_return(false,config("params.PURVIEW_SHORT"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }

        $update_data = [
            "is_delete" => "1"
        ];

        Db::startTrans();
        try{
            foreach ($id_array as $id_l){
                $is_ok = $sysAdminModel
                    ->where("id",$id_l)
                    ->update($update_data);
                if ($is_ok === false){
                    return $this->com_return(false,config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true,config("params.SUCCESS"));

        }catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 变更密码
     * @return array
     */
    public function changeManagerPass()
    {
        try {
            $loginUser = self::tokenGetAdminLoginInfo($this->request->header("Authorization"));

            $action_user = $loginUser['user_name'];
            $role_id     = $loginUser['role_id'];

            if ($role_id != 1){
                return $this->com_return(false,config("params.PURVIEW_SHORT"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }

        $id                    = $this->request->param('id',"");//被操作管理员id
        $password              = $this->request->param("password","");
        $password_confirmation = $this->request->param("password_confirmation","");

        $rule = [
            "id"                            => "require",
            "password|密码"                  => "require|alphaNum|length:6,25",
            "password_confirmation|确认密码"  => "require",
        ];

        $request_res = [
            "id"                    => $id,
            "password"              => $password,
            "password_confirmation" => $password_confirmation,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($password !== $password_confirmation){
            return $this->com_return(false,config("params.PASSWORD_DIF"));
        }

        $params = [
            'password'   => jmPassword($password),
            'token'      => jmToken($password.time()),
            'updated_at' => time()
        ];

        try {
            $sysAdminModel = new SysAdminUser();

            $is_ok = $sysAdminModel
                ->where('id',$id)
                ->update($params);

            if ($is_ok === false){
                return $this->com_return(true,config("params.FAIL"));
            }

            return $this->com_return(true,config("params.SUCCESS"));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 更改自身信息
     * @return array
     */
    public function changeManagerInfo()
    {
        $authorization = $this->request->header("Authorization",'');

        $avatar     = $this->request->param('avatar','');//头像
        $user_name  = $this->request->param('user_name','');//用户名
        $phone      = $this->request->param('phone','');//电话号码
        $email      = $this->request->param('email','');//邮箱
        $password   = $this->request->param('password','');//密码

        $new_password          = $this->request->param('new_password','');//新密码
        $password_confirmation = $this->request->param('password_confirmation','');//确认密码


        $rule = [
            'user_name|用户名' => 'require|alphaNum',
            'avatar|头像'      => 'url',
            'phone|电话'       => 'regex:1[0-9]{1}[0-9]{9}',
            'email|邮箱'       => 'email',
        ];

        $check_data = [
            'user_name' => $user_name,
            'avatar'    => $avatar,
            'phone'     => $phone,
            'email'     => $email,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError(),null);
        }
        try {
            $sysAdminModel = new SysAdminUser();
            if (empty($password)){
                //如果登陆密码为空,则只更新基本信息
                $update_params = [
                    'user_name'  => $user_name,
                    'avatar'     => $avatar,
                    'phone'      => $phone,
                    'email'      => $email,
                    'updated_at' => time()
                ];
            }else{
                //否则,全部更新
                //首先判断当前输入密码是否正确
                $is_can_change = $sysAdminModel
                    ->where('token',$authorization)
                    ->where('password',jmPassword($password))
                    ->count();
                if ($is_can_change != 1){
                    return $this->com_return('false',config('params.PURVIEW_SHORT'));
                }

                /*验证新密码有效性 begin*/
                $rule = [
                    'new_password|密码'                   =>  'require|length:6,16|alphaNum',
                    'password_confirmation|确认密码'       =>  'require|length:6,16|alphaNum|confirm:new_password',
                ];

                $check_data = [
                    'new_password'              => $new_password,
                    'password_confirmation'     => $password_confirmation
                ];

                $validate = new Validate($rule);

                if (!$validate->check($check_data)){
                    return $this->com_return(false,$validate->getError());
                }
                /*验证密码有效性 off*/

                $update_params = [
                    'password'   => $new_password,
                    'token'      => jmToken($new_password.$password.time()),
                    'user_name'  => $user_name,
                    'avatar'     => $avatar,
                    'phone'      => $phone,
                    'email'      => $email,
                    'updated_at' => time()
                ];
            }

            $is_ok = $sysAdminModel
                ->where('token',$authorization)
                ->update($update_params);

            if ($is_ok === false){
                return $this->com_return(true,config('params.FAIL'));
            }

            return $this->com_return(true,config('params.SUCCESS'));

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}