<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午6:08
 */
namespace app\xcx_manage\controller\main;


use app\common\controller\BaseController;
use app\common\model\ManageSalesman;
use think\Env;
use think\Exception;

class ThirdLogin extends BaseController
{
    /**
     * 管理端小程序授权登陆,并判断用户是否绑定手机号码
     * @return mixed
     */
    public function getManageOpenId()
    {
        $code = $this->request->param('code','');
        $Appid  = Env::get("WECHAT_XCX_MANAGE_APPID");
        $Secret = Env::get("WECHAT_XCX_MANAGE_APPSECRET");
        $url    = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$Appid.'&secret='.$Secret.'&js_code=' . $code . '&grant_type=authorization_code';

        try {
            $info   = $this->vget($url);
            $info   = json_decode($info,true);//对json数据解码

            if (isset($info['errcode']) && $info['errcode'] != 0) {
                return $this->com_return(false,$info['errmsg']);
            }
            $openid     = $info['openid'];

            return $this->openIdCheckManageInfo($openid);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * openId检测用户的信息
     * @param $openid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function openIdCheckManageInfo($openid)
    {
        $manageSalesModel = new ManageSalesman();
        $res = $manageSalesModel
            ->where("wxid",$openid)
            ->find();
        $res = json_decode(json_encode($res),true);
        $data = [
            "openid" => $openid
        ];
        if (empty($res)){
            return $this->com_return(true,config("login.bind_phone"),$data);
        }
        if ($res['statue'] != config("salesman.salesman_status")['working']['key']) {
            return $this->com_return(false,config("login.status_no_login"),$data,config("code.LOGIN_ERROR"));
        }
        $params = [
            "token_lastime"  => time(),
            "remember_token" => jmToken(generateReadableUUID("QBJ").time()),
            "updated_at"     => time()
        ];

        $updateInfo = $manageSalesModel
            ->where("wxid",$openid)
            ->update($params);

        if ($updateInfo === false) {
            return $this->com_return(false,config("params.FAIL"));
        }

        $manageInfo = $manageSalesModel
            ->where("wxid",$openid)
            ->find();
        return $this->com_return(true,config("params.SUCCESS"),$manageInfo);
    }
}