<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:38
 */
namespace app\common\controller;

use app\common\model\SysAdminUser;
use think\exception\HttpException;
use think\Request;

class AdminAuthAction extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        $method = Request::instance()->method();

        if ( $method == "OPTIONS"){
            return json($this->com_return(true,'options'))->send();
        }

        $authorization = Request::instance()->header("authorization","");

        if (empty($authorization)) {
            return json($this->com_return(false, '登陆失效' , null, 403))->send();
        }

        $sysAdminModel = new SysAdminUser();

        $is_exist = $sysAdminModel
            ->where("token",$authorization)
            ->count();

        if ($is_exist == 0) {
            return json($this->com_return(false, '登陆失效' , null, 403))->send();
        }
    }

    /**
     * 根据token获取登陆管理员信息
     * @param $token
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function tokenGetAdminLoginInfo($token)
    {
        $sysAdminUserModel = new SysAdminUser();
        $column = $sysAdminUserModel->column;

        $res = $sysAdminUserModel
            ->where("token",$token)
            ->field($column)
            ->find();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 检测角色下是否存在管理员
     * @param $role_id
     * @return bool 有 true; 无 false
     */
    public static function checkRoleHaveAdmin($role_id)
    {
        $sysAdminUserModel = new SysAdminUser();
        $role_id_exist = $sysAdminUserModel
            ->where('role_id',$role_id)
            ->count();
        if ($role_id_exist > 0) {
            return true;
        }else{
            return false;
        }
    }
}