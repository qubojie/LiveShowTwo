<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:42
 */

namespace app\common\controller;


use think\Request;

class WechatCallCommon extends BaseController
{
    /**
     * 统一模拟支付,组装参数
     * @param $Authorization
     * @param $notifyType
     * @param $vid
     * @param $total_fee
     * @param $cash_fee
     * @param $reason
     * @param string $transaction_id
     * @return mixed
     */
    public function callBackPay($Authorization,$notifyType,$vid,$total_fee,$cash_fee,$reason,$transaction_id= '')
    {
        $attach = config("order.pay_scene")['recharge']['key'];//充值支付场景

        $values = [
            'attach'         => $attach,
            'notifyType'     => $notifyType,
            'total_fee'      => $total_fee,
            'cash_fee'       => $cash_fee,
            'out_trade_no'   => $vid,
            'transaction_id' => $transaction_id,
            'time_end'       => date("YmdHi",time()),
            'reason'         => $reason
        ];

        $res = $this->requestPost($Authorization,$values);

        return $res;

    }

    /**
     * 模拟post支付回调接口请求
     *
     * @param $Authorization
     * @param array $postParams
     * @return bool|mixed
     */
    protected function requestPost($Authorization,$postParams = array())
    {
        $request = Request::instance();

        $url = $request->domain()."/wechat/notify";

        if (empty($url) || empty($postParams)) {
            return false;
        }

        $o = "";
        foreach ( $postParams as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }

        $postParams = substr($o,0,-1);


        $postUrl = $url;
        $curlPost = $postParams;

        $header = array();
        $header[] = 'Authorization:'.$Authorization;

        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }
}