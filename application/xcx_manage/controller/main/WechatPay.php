<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午10:03
 */

namespace app\xcx_manage\controller\main;


use app\common\controller\BaseController;
use app\common\controller\OrderCommon;
use think\Env;
use think\Exception;
use think\Log;
use think\Validate;
use wxpay\JsapiPay;

class WechatPay extends BaseController
{
    /**
     * 服务端小程序支付
     * @return array
     */
    public function manageSmallApp()
    {
        $vid    = $this->request->param("vid","");
        $openId = $this->request->param('openid','');
        $scene  = $this->request->param("scene","");//支付场景

        $rule = [
            "vid" => "require"
        ];
        $request_res = [
            "vid" => $vid
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $payable_amount = false;
            $orderCommonObj = new OrderCommon();

            if ($scene == config("order.pay_scene")['reserve']['key']){
                //这里去处理预约定金回调逻辑
                //获取订台金额
                $payable_amount = $orderCommonObj->getSubscriptionPayableAmount($vid);
            }

            if ($scene == config("order.pay_scene")['point_list']['key']){
                //获取点单支付金额
                $payable_amount = $orderCommonObj->getBillPayAmount($vid);
            }

            if ($payable_amount == false){
                return $this->com_return(false,'订单有误');
            }

            $params = [
                'body'         => Env::get("PAY_BODY"),
                'out_trade_no' => $vid,
                'total_fee'    => $payable_amount * 100,
            ];
            $result = JsapiPay::getParamsManage($params,$openId,$scene);
            return $this->com_return(true,config("params.SUCCESS"),$result);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}