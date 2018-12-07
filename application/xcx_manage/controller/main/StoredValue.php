<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/4
 * Time: 下午5:32
 * App: LiveShowTwo
 */

namespace app\xcx_manage\controller\main;


use app\common\controller\ManageAuthAction;
use app\common\controller\RechargeCommon;
use app\common\controller\UserCommon;
use think\Exception;
use think\Validate;

class StoredValue extends ManageAuthAction
{
    /**
     * 确认充值
     */
    public function confirmRecharge()
    {
        $phone           = $this->request->param("phone","");//用户电话
        $sales_phone     = $this->request->param("sales_phone","");//营销电话
        $recharge_amount = $this->request->param("recharge_amount","");//储值金额
        $cash_amount     = $this->request->param("cash_amount","");//赠送礼金
        $review_desc     = $this->request->param("review_desc","");//备注
        $pay_type        = $this->request->param("pay_type","");//支付方式

        $rule = [
            "phone|用户电话"              => "require|regex:1[0-9]{1}[0-9]{9}",
            "sales_phone|营销电话"     => "regex:1[0-9]{1}[0-9]{9}",
            "recharge_amount|储值金额" => "require|number|egt:0",
            "cash_amount|赠送礼金"     => "require|number|egt:0",
            "pay_type|支付方式"        => "require",
        ];
        $request_res = [
            "phone"           => $phone,
            "sales_phone"     => $sales_phone,
            "recharge_amount" => $recharge_amount,
            "cash_amount"     => $cash_amount,
            "pay_type"        => $pay_type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $token       = $this->request->header("Token");
            $manageInfo  = $this->tokenGetManageInfo($token);
            $action_user = $manageInfo['sales_name'];

            //获取用户信息
            $userCommonObj = new UserCommon();
            $userInfo = $userCommonObj->uidOrPhoneGetUserInfo("$phone");
            if (empty($userInfo)){
                return $this->com_return(false,config("params.USER")['USER_NOT_EXIST']);
            }

            $rechargeCommonObj = new RechargeCommon();
            $uid           = $userInfo['uid'];
            $pay_line_type = 1;
            $res           = $rechargeCommonObj->rechargePublicAction("$uid","$recharge_amount","$cash_amount","$pay_type","$pay_line_type","$action_user","$review_desc","$sales_phone");
            /*if (!isset($res['result']) || !$res['result']) {
                return $this->com_return(false,config("params.FAIL"));
            }*/
            return $res;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}