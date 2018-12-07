<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午5:13
 */

namespace app\xcx_member\controller\personal;


use app\common\controller\MemberAuthAction;
use app\common\controller\RechargeCommon;
use app\common\model\MstRefillAmount;
use think\Exception;
use think\Validate;

class Recharge extends MemberAuthAction
{
    /**
     * 充值金额列表获取
     * @return array
     */
    public function rechargeList()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");
        try {
            $rechargeCommonObj = new RechargeCommon();
            $res = $rechargeCommonObj->rechargeList($pagesize,$nowPage);
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 充值确认
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rechargeConfirm()
    {
        //生成充值订单
        $token          = $this->request->header("Token","");
        $amount         = $this->request->param("amount","");//充值金额
        $cash_gift      = $this->request->param("cash_gift","");//赠送礼金数
        $referrer_phone = $this->request->param("referrer_phone","");//营销电话
        $rule = [
            "amount|充值金额"      => "require|number|max:20|gt:0",
            "cash_gift|赠送礼金数" => "require|number|max:20|egt:0",
        ];
        $request_res = [
            "amount"    => $amount,
            "cash_gift" => $cash_gift,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $refill_lower_limit = getSysSetting("user_refill_lower_limit");
        if (empty($refill_lower_limit)){
            $refill_lower_limit = 200;
        }

        if ($amount < $refill_lower_limit){
            return $this->com_return(false,"充值金额不能低于".$refill_lower_limit."元");
        }

        $userInfo = $this->tokenGetUserInfo($token);
        if ($userInfo === false){
            return $this->com_return(false,config("params.FAIL"));
        }
        $uid = $userInfo['uid'];

        $pay_type = config("order.pay_method")['wxpay']['key'];

        $pay_line_type = 0;
        $rechargeCommonObj = new RechargeCommon();
        $res = $rechargeCommonObj->rechargePublicAction("$uid","$amount","$cash_gift","$pay_type","$pay_line_type","user","","$referrer_phone");
        return $res;
    }

}