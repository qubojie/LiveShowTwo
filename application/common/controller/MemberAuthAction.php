<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午4:27
 */

namespace app\common\controller;


use app\common\model\User;
use think\Exception;
use think\Request;

class MemberAuthAction extends BaseController
{
    /**
     * 初始化方法,其余控制器继承此方法，进行判断登录
     * @return array|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
   {
       parent::_initialize();
       $method = Request::instance()->method();

       if ( $method == "OPTIONS"){
           return json($this->com_return(true,config('params.SUCCESS')))->send();
       }
       $Token = Request::instance()->header("Token","");

       if (empty($Token)) {
           return json($this->com_return(false, '登陆失效' , null, 403))->send();
       }
       $userModel = new User();
       $is_exist = $userModel
           ->where("remember_token",$Token)
           ->field('token_lastime')
           ->find();
       $is_exist = json_decode(json_encode($is_exist),true);

       if (empty($is_exist)) {
           return json($this->com_return(false, '登陆失效' , null, 403))->send();
       }

       $time          = time();//当前时间
       $token_lastime = $is_exist['token_lastime'];//上次刷新token时间
       $over_time     = $token_lastime + (24 * 60 * 60);   //过期时间

       if ($time > $over_time){
           return json($this->com_return(false, '登陆失效' , null, 403))->send();
       }
   }

    /**
     * 根据token获取用户信息
     * @param $token
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function tokenGetUserInfo($token)
    {
        try {
            $userModel = new User();
            $column = $userModel->column;
            $user_info = $userModel
                ->where('remember_token', $token)
                ->field($column)
                ->find();
            if (!empty($user_info)) {
                $user_info = $user_info->toArray();
                return $user_info;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}