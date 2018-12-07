<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午7:02
 */

namespace app\xcx_manage\controller\main;


use app\common\controller\BaseController;
use app\common\model\ManageSalesman;
use app\common\model\MstRefillAmount;
use app\xcx_member\controller\main\Auth;
use think\Exception;
use think\Request;
use think\Validate;

class ManageAuth extends BaseController
{
    /**
     * 工作人员登陆
     * @param Request $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login(Request $request)
    {
        $phone    = $request->param("phone","");
        $password = $request->param("password","");
        $rule = [
            "phone|用户电话"  => "require",
            "password|密码"   => "require",
        ];
        $request_res = [
            "phone"    => $phone,
            "password" => $password,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $password = jmPassword($password);
        $manageSalesmanModel = new ManageSalesman();
        $manage_column = $manageSalesmanModel->manage_column;
        $manageInfo = $manageSalesmanModel
            ->where('phone',$phone)
            ->where('password',$password)
            ->find();
        $manageInfo = json_decode(json_encode($manageInfo),true);

        if (!empty($manageInfo)){
            $quitStatue = config("salesman.salesman_status")['resignation']['key'];
            $statue = $manageInfo['statue'];
            if ($statue == $quitStatue){
                return $this->com_return(false,"离职员工,不可登陆");
            }

            $remember_token = jmToken($password.time());
            $time = time();
            $update_params = [
                "remember_token" => $remember_token,
                "token_lastime"  => $time,
                "updated_at"     => $time
            ];
            $is_ok = $manageSalesmanModel
                ->where('phone',$phone)
                ->where('password',$password)
                ->update($update_params);
            if ($is_ok !== false){
                $manageInfo = $manageSalesmanModel
                    ->alias("ms")
                    ->join("manage_department md","md.department_id = ms.department_id")
                    ->join("mst_salesman_type st","st.stype_id = ms.stype_id")
                    ->where('ms.phone',$phone)
                    ->where('ms.password',$password)
                    ->field("md.department_title")
                    ->field("st.stype_key,st.stype_name")
                    ->field($manage_column)
                    ->find();
                return $this->com_return(true,config("params.SUCCESS"),$manageInfo);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        }else{
            return $this->com_return(false,config("params.ACCOUNT_PASSWORD_DIF"));
        }
    }

    /**
     * 小程序微信授权信息绑定
     * @param Request $request
     * @return array
     */
    public function phoneBindWechat(Request $request)
    {
        $phone      = $request->param('phone','');
        $code       = $request->param('code','');
        $openid     = $request->param('openid',"");     //openid
        $nickname   = $request->param('nickname',"");   //昵称
        $headimgurl = $request->param('headimgurl',""); //头像

        if (empty($openid)) {
            return $this->com_return(false,config("login.status_no_login"));
        }

        $rule = [
            "phone|电话"      => "require",
            "code|验证码"     => "require",
            "openid|openid"  => "require",
            "nickname|昵称"   => "require",
            "headimgurl|头像"  => "require",
        ];

        $request_res = [
            "phone"        => $phone,
            "code"         => $code,
            "openid"       => $openid,
            "nickname"     => $nickname,
            "headimgurl"   => $headimgurl,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $salesmanModel = new ManageSalesman();
            /*判断当前手机号码是否在管理员表中存在 On*/
            $is_exist = $salesmanModel
                ->where("phone",$phone)
                ->count();
            if ($is_exist <= 0) {
                return $this->com_return(false,config("login.only_in_user_use"));
            }
            /*判断当前手机号码是否在管理员表中存在 Off*/

            /*验证验证码  On*/
            $authObj = new Auth();
            $is_pp = $authObj->checkVerifyCode($phone,$code);
            if (!$is_pp['result']) return $is_pp;
            /*验证验证码  Off*/

            $params = [
                'wxid'           => $openid,
                'avatar'         => $headimgurl,
                'nickname'       => $nickname,
                'token_lastime'  => time(),
                'remember_token' => jmToken(generateReadableUUID('QBJ').time()),
                'updated_at'     => time()
            ];

            $is_ok = $salesmanModel
                ->where('phone',$phone)
                ->update($params);
            if ($is_ok !== false){
                $res = $salesmanModel
                    ->where("phone",$phone)
                    ->find();
                return $this->com_return(true,config("params.SUCCESS"),$res);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 储值金额获取返回礼金数
     * @return array
     */
    public function rechargeAmountGetCashAmount()
    {
        $recharge_amount = $this->request->param("recharge_amount","");//储值金额
        try {
            $refillAmountModel = new MstRefillAmount();
            $res = $refillAmountModel
                ->where('is_enable',1)
                ->where('amount',"<=",$recharge_amount)
                ->order("amount DESC")
                ->find();
            $res = json_decode(json_encode($res),true);
            if (empty($res)) {
                $cash_gift = 0;
            }else{
                $cash_gift = $res['cash_gift'];
            }
            return $this->com_return(false, config("params.SUCCESS"),$cash_gift);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}