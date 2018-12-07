<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:53
 */

namespace app\admin\controller\whore;


use app\common\controller\BaseController;
use app\common\model\SysAdminUser;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Auth extends BaseController
{
    /**
     * 管理员用户密码登录
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        $user_name = $request->param("user_name","");
        $password  = $request->param("password","");

        $rule = [
            "user_name|账号" => "require",
            "password|密码"  => "require"
        ];

        $request_res = [
            "user_name" => $user_name,
            "password"  => $password
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }
        $ip = $request->ip();
        $password = jmPassword($password);//密码加密

        Db::startTrans();
        try {
            $sysAdminUserModel = new SysAdminUser();
            $is_exist =  $sysAdminUserModel
                ->where("user_name",$user_name)
                ->where("password",$password)
                ->where("is_delete","0")
                ->field("id,user_sn,user_name,ec_salt,avatar,phone,email,last_ip,action_list,nav_list,lang_type,role_id,token,is_delete,is_sys,created_at,updated_at")
                ->find();

            if (empty($is_exist)){
                return $this->com_return(false,config("params.ACCOUNT_PASSWORD_DIF"));
            }

            $user_id = $is_exist['id'];
            $token = jmToken($password.time());
            //更新token
            $save_data = [
                "token" => $token,
                "last_ip" => $ip
            ];
            $is_ok = $sysAdminUserModel
                ->where("id",$user_id)
                ->update($save_data);
            if ($is_ok !== false){
                Db::commit();
                $is_exist['token'] = $token;
                $is_exist['last_ip'] = $ip;
                return $this->com_return(true,config("params.SUCCESS"),$is_exist);
            }else{
                return $this->com_return(false,config("params.ABNORMAL_ACTION"),null);
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /*
     * 刷新token
     * @return 新token
     * */
    public function refresh_token()
    {
        $Authorization = Request::instance()->header("authorization","");

        if (empty($Authorization)){
            return $this->com_return(false,config("params.FAIL"));
        }

        $sysAdminUserModel = new SysAdminUser();
        $new_token = jmToken($Authorization.time());
        $update_date = [
            "token" => $new_token
        ];
        try {
            $is_exist = $sysAdminUserModel
                ->where("token",$Authorization)
                ->update($update_date);
            if ($is_exist){
                return $this->com_return(true,config("params.SUCCESS"),$new_token);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}