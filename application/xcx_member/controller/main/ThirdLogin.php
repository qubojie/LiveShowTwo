<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午5:28
 */

namespace app\xcx_member\controller\main;


use app\common\controller\BaseController;
use app\common\model\User;
use think\Exception;
use think\Request;

class ThirdLogin extends BaseController
{
    /**
     * 微信小程序三方授权,判断用户是否是注册用户
     * @param Request $request
     * @return array
     */
    public function wechatLogin(Request $request)
    {
        $code       = $request->param('code','');
        $nickname   = $request->param('nickname','');
        $headimgurl = $request->param('headimgurl','');

        try {
            $userInfo   = $this->getOpenId($code);

            if (isset($userInfo['errcode']) && $userInfo['errcode'] != 0) {
                return $this->com_return(false,$userInfo['errmsg']);
            }

            $openid     = $userInfo['openid'];
            $userModel  = new User();

            $is_exist = $userModel
                ->where('wxid',$openid)
                ->find();
            if (empty($is_exist)){
                //如果不存在,新增
                return $this->com_return(false,'请注册登陆',$userInfo);
            }

            $token = jmToken(generateReadableUUID("token"));

            //如果存在,更新
            $params = [
                'nickname'       => $nickname,
                'avatar'         => $headimgurl,
                'lastlogin_time' => time(),
                'updated_at'     => time(),
                'remember_token' => $token,
                'token_lastime'  => time()
            ];

            $userModel
                ->where('wxid',$openid)
                ->update($params);

            $user_info = $userModel
                ->where('wxid',$openid)
                ->find();

            return $this->com_return(true,config('SUCCESS'),$user_info);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }

    }
}