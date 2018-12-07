<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 上午10:03
 */

namespace app\xcx_member\controller\main;
header('Content-Type:text/html;charset=utf-8');/*设置php编码为utf-8*/
header('Access-Control-Allow-Origin:*');

use app\common\controller\BaseController;
use app\common\controller\NotifyCommon;
use app\common\controller\OrderCommon;
use think\Env;
use think\Exception;
use think\Validate;
use wxpay\JsapiPay;
use wxpay\NativePay;
use wxpay\WapPay;

class WechatPay extends BaseController
{
    /**
     * 扫码支付
     * @return array|string
     * @throws \WxPayException
     */
    public function scavengingPay()
    {
        $pid = $this->request->param("vid","");

        $rule = [
            "vid" => "require"
        ];
        $request_res = [
            "vid" => $pid
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $orderCommonObj = new OrderCommon();
            //获取点单支付金额
            $payable_amount = $orderCommonObj->getBillPayAmount($pid);

            $payable_amount = $payable_amount * 100;

            $params = [
                "body"         => "LiveShow",
                "out_trade_no" => $pid,
                "total_fee"    => $payable_amount,
                "product_id"   => $pid
            ];

            return  NativePay::getPayImage($params);//这里返回 code_url
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * H5支付
     * @return array|string
     */
    public function wappay()
    {
        $vid = $this->request->param("vid","");
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
            $orderCommonObj = new OrderCommon();
            //获取订单金额
            $payable_amount = $orderCommonObj->getOrderPayableAmount($vid);

            if ($payable_amount === false){
                return $this->com_return(false,'订单有误');
            }

            $params = [
                'body'          => Env::get("PAY_BODY"),
                'out_trade_no'  => $vid,
                'total_fee'     => $payable_amount * 100
            ];

            $redirect_url = Env::get("WEB_DOMAIN_NAME").'page/orderspay.html';

            $result = WapPay::getPayUrl($params,$redirect_url);

            return $result;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 公众号支付
     * @return array|\ 'json数据，可直接填入js函数作为参数'
     */
    public function jspay()
    {
        $vid = $this->request->param("vid","");

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
            $orderCommonObj = new OrderCommon();
            //获取订单金额
            $payable_amount = $orderCommonObj->getOrderPayableAmount($vid);

            if ($payable_amount == false){
                return $this->com_return(false,'订单有误');
            }

            $params = [
                'body'          => Env::get("PAY_BODY"),
                'out_trade_no'  => $vid,
                'total_fee'     => $payable_amount * 100
            ];

            if (isset($_GET['code'])){
                $code = $_GET['code'];
            }else{
                $code = '';
            }

            $result = JsapiPay::getPayParams($params,$code);
            return $result;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 客户端小程序支付
     * @return array|\json数据，可直接填入js函数作为参数
     */
    public function smallapp()
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
            $orderCommonObj = new OrderCommon();
            $payable_amount = false;

            if ($scene == config("order.pay_scene")['open_card']['key']){
                //获取开卡订单金额
                $payable_amount = $orderCommonObj->getOrderPayableAmount($vid);
            }

            if ($scene == config("order.pay_scene")['reserve']['key']){
                //这里去处理预约定金回调逻辑
                //获取订台金额
                $payable_amount = $orderCommonObj->getSubscriptionPayableAmount($vid);
            }

            if ($scene == config("order.pay_scene")['recharge']['key']){
                //获取充值金额
                $payable_amount = $orderCommonObj->getBillRefillAmount($vid);
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

            $result = JsapiPay::getParams2($params,$openId,$scene);
            return $result;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 微信退款
     * @return array
     */
    public function reFund()
    {
        if ($this->request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }
        $vid           = $this->request->param('vid','');
        $total_fee     = $this->request->param('total_fee','');
        $refund_fee    = $this->request->param('refund_fee','');
        $out_refund_no = $this->request->param('out_refund_no','');

        try {
            $total_fee  = $total_fee * 100;
            $refund_fee = $refund_fee * 100;

            $params = [
                "out_trade_no"  => $vid,
                "total_fee"     => $total_fee,
                "refund_fee"    => $refund_fee,
                "out_refund_no" => $out_refund_no,
            ];

            $result = \wxpay\Refund::exec($params);

            //如果退款成功返回
            /*array(18) {
                  ["appid"] => string(18) "wx946331ee6f54ddf8"
                  ["cash_fee"] => string(3) "200"
                  ["cash_refund_fee"] => string(3) "200"
                  ["coupon_refund_count"] => string(1) "0"
                  ["coupon_refund_fee"] => string(1) "0"
                  ["mch_id"] => string(10) "1507786841"
                  ["nonce_str"] => string(16) "EDnxDxfNVZTNmv7B"
                  ["out_refund_no"] => string(28) "4200000137201807252214503179"
                  ["out_trade_no"] => string(20) "V18072515142485277C6"
                  ["refund_channel"] => array(0) {
                  }
                  ["refund_fee"] => string(3) "200"
                  ["refund_id"] => string(29) "50000307492018072605818207474"
                  ["result_code"] => string(7) "SUCCESS"
                  ["return_code"] => string(7) "SUCCESS"
                  ["return_msg"] => string(2) "OK"
                  ["sign"] => string(32) "A0D0D23A4C82780E47C0BABFB2752EEE"
                  ["total_fee"] => string(3) "200"
                  ["transaction_id"] => string(28) "4200000137201807252214503179"
            }*/

            if (isset($result['return_code']) && $result['return_msg'] == "OK"){
                $cash_fee        = $result['cash_fee'];
                $cash_refund_fee = $result['cash_refund_fee'];
                $refund_fee      = $result['refund_fee'];
                $refund_id       = $result['refund_id'];
                $transaction_id  = $result['transaction_id'];
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                $result = json_decode(json_encode($result),true);
                return $this->com_return(false,$result["return_msg"]);
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 订单查询
     * @return array
     * @throws \WxPayException
     */
    public function query()
    {
        try {
            if ($this->request->isOptions()){
                return $this->com_return(true,'预请求');
            }
            $vid = $this->request->param('vid','');
            $result = \wxpay\Query::exec($vid);
            return $result;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 下载对账单
     * @return array
     * @throws \WxPayException
     */
    public function download()
    {
        try {
            $date = $this->request->param('date',date("Ymd"));//格式为 20080808,当天的不可查询
            if ($date == date("Ymd")){
                return $this->com_return(false,'当天账单不可查');
            }
            $result = \wxpay\DownloadBill::exec($date);
            return $this->com_return(true,config("params.SUCCESS"),$result);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 支付回调
     */
    public function notify()
    {
//        $notify = new Notify();
//        $notify->Handle();
        $notifyType = $this->request->param('notifyType',"");

        if ($notifyType == 'adminCallback'){
            $values = $this->request->param();
        }else{
            $xml = file_get_contents("php://input");
            libxml_disable_entity_loader(true);
            $values= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        }

        $attach   = $values['attach'];//获取回调支付包名

        $notifyCommonObj = new NotifyCommon();
        if ($attach == config("order.pay_scene")['open_card']['key']){
            //这里去处理开卡回调逻辑
            $res = $notifyCommonObj->openCardNotify($values,$notifyType);
            echo $res;die;
        }

        if ($attach == config("order.pay_scene")['reserve']['key']){
            //这里去处理预约定金回调逻辑
            //获取订台金额
            $res = $notifyCommonObj->payDeposit($values,$notifyType);
            echo $res;die;
        }


        if ($attach == config("order.pay_scene")['recharge']['key']){
            //这里去处理充值回调逻辑
            //获取订台金额
            $res = $notifyCommonObj->recharge($values,$notifyType);
            echo $res;die;
        }

        if ($attach == config("order.pay_scene")['point_list']['key']){
            //这里去处理订单缴费回调逻辑
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            //TODO '点单缴费需要完善'
            $res = $notifyCommonObj->pointListNotify($values,$notifyType);
            echo $res;die;
        }
    }
}