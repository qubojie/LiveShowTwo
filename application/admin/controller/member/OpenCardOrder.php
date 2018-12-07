<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午12:34
 */

namespace app\admin\controller\member;


use app\common\controller\AdminAuthAction;
use app\common\controller\OrderCommon;
use app\common\model\BillCardFees;
use think\Db;
use think\Env;
use think\Exception;
use think\Request;
use think\Validate;

class OpenCardOrder extends AdminAuthAction
{
    /**
     * 开卡订单分组
     * @return array
     */
    public function orderType()
    {
        $typeList = config("order.open_card_type");

        $res = [];

        try {
            $billCardFeesModel = new BillCardFees();
            foreach ($typeList as $key => $val){
                if ($val["key"] == config("order.open_card_status")['pending_payment']['key']){
                    $count = $billCardFeesModel
                        ->where("sale_status",config("order.open_card_status")['pending_payment']['key'])
                        ->count();//未付款总记录数
                    $val["count"] = $count;
                }else{
                    $val["count"] = 0;
                }
                $res[] = $val;
            }
            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 开卡礼寄送分组
     */
    public function giftShipType()
    {
        $typeList = config("order.gift_ship_type");
        $res = [];
        try {
            $billCardModel = new BillCardFees();
            foreach ($typeList as $key => $val){

                if ($val["key"] == config("order.open_card_status")['pending_ship']['key']){
                    $count = $billCardModel
                        ->where("sale_status",config("order.open_card_status")['pending_ship']['key'])
                        ->count();//待发货

                    $val["count"] = $count;
                }else{
                    $val["count"] = 0;
                }

                $res[] = $val;
            }
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 开卡订单列表
     * @return array
     */
    public function index()
    {
        $status     = $this->request->param('status','');
        $keyword    = $this->request->param("keyword","");
        $pay_type   = $this->request->param("pay_type","");//支付方式
        $card_type  = $this->request->param("card_type","");//卡的类型
        $begin_time = $this->request->param('begin_time',"");//开始时间
        $end_time   = $this->request->param('end_time',"");//结束时间
        $gift_ship  = $this->request->param('gift_ship',"");//是否是发货管理请求

        $pagesize   = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $this->request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $gift_ship_where = [];
        if ($gift_ship == "gift_ship"){
            $gift_ship_where['delivery_name'] = ['neq',""];
        }

        $time_where = [];
        if (!empty($begin_time) && empty($end_time)){
            $time_where['bc.created_at'] = ['EGT',$begin_time];//大于
        }

        if (empty($begin_time) && !empty($end_time)){
            $time_where['bc.created_at'] = ['ELT',$end_time];//小于等于
        }

        if (!empty($begin_time) && !empty($end_time)){
            $time_where['bc.created_at'] = ['BETWEEN',"$begin_time,$end_time"];//时间区间
        }

        $card_type_where = [];

        if (!empty($card_type)){
            $card_type_where['bcf.card_type'] = ['eq',$card_type];
        }

        $pay_type_where = [];
        if (!empty($pay_type)){
            $pay_type_where['bc.pay_type'] = ['eq',$pay_type];
        }

        $config = [
            "page" => $nowPage,
        ];

        $where = [];
        if (!empty($keyword)){
            $where['bc.vid|u.name|u.nickname|bcf.card_name'] = ["like","%$keyword%"];
        }

        $status_where = [];
        if ($status != NULL){
            if ($status == config("order.open_card_type")['completed']['key']){
                $status_where['bc.sale_status'] = ["IN",$status];
            }else{
                $status_where['bc.sale_status'] = ["eq",$status];

            }
        }

        try {
            $billCardModel = new BillCardFees();

            $get_column       = $billCardModel->get_column;
            $card_gift_column = $billCardModel->card_gift_column;
            $user_column      = $billCardModel->user_column;

            $list = $billCardModel
                ->alias('bc')
                ->join('user u','u.uid = bc.uid')
                ->join('bill_card_fees_detail bcf','bcf.vid = bc.vid')
                ->where($where)
                ->where($status_where)
                ->where($pay_type_where)
                ->where($card_type_where)
                ->where($time_where)
                ->where($gift_ship_where)
                ->field($get_column)
                ->field($user_column)
                ->field($card_gift_column)
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);

            //获取付款方式
            $pay_type_arr = config("order.pay_method");
            //获取卡类型
            $card_type_arr = config("card.type");
            //获取订单状态
            $sale_status_arr = config("order.open_card_status");
            //获取发货类型
            $send_type_arr = config("order.send_type");

            for ($i = 0; $i<count($list['data']); $i++){
                /*名字电话编辑 on*/
                $name  = $list['data'][$i]['name'];
                $phone = $list['data'][$i]['phone'];
                if (!empty($name)){
                    $list['data'][$i]['phone_name'] = $name . " " . $phone;
                }else{
                    $list['data'][$i]['phone_name'] = $phone;
                }
                /*名字电话编辑 off*/

                /*默认头像 begin*/
                $avatar = $list['data'][$i]['avatar'];
                if (empty($avatar)){
                    $list['data'][$i]['avatar'] = Env::get("DEFAULT_AVATAR_URL")."avatar.jpg";
                }
                /*默认头像 off*/

                /*支付类型翻译 begin*/
                $pay_type = $list['data'][$i]['pay_type'];
                foreach ($pay_type_arr as $key => $value){

                    if ($pay_type == $key){
                        $list['data'][$i]['pay_type_name'] = $value['name'];
                    }
                }
                /*支付类型翻译 off*/

                /*卡种翻译 begin*/
                $card_type_s = $list['data'][$i]['card_type'];

                foreach ($card_type_arr as $key => $value){
                    if ($card_type_s == $value["key"]){
                        $list['data'][$i]['card_type_name'] = $value["name"];
                    }
                }
                /*卡种翻译 off*/

                /*状态翻译 begin*/
                $sale_status_s = $list['data'][$i]['sale_status'];
                foreach ($sale_status_arr as $key => $value){
                    if ($sale_status_s == $value['key']){
                        $list['data'][$i]['sale_status_name'] = $value['name'];
                    }
                }
                /*状态翻译 off*/

                /*发货类型翻译 begin*/
                $send_type_s = $list['data'][$i]['send_type'];
                foreach ($send_type_arr as $key => $value){
                    if ($send_type_s == $value['key']){
                        $list['data'][$i]['send_type_name'] = $value['name'];
                    }
                }
                /*发货类型翻译 off*/

                /*用户操作日志 begin*/
                $vid = $list['data'][$i]['vid'];
                $log_info = Db::name('sys_adminaction_log')
                    ->where('oid',$vid)
                    ->select();

                $useraction = config("useraction");

                for ($m = 0; $m < count($log_info); $m++){
                    $action = $log_info[$m]['action'];
                    foreach ($useraction as $key => $val){
                        if ($action == $key){
                            $log_info[$m]['action'] = $val['name'];
                        }
                    }
                }
                $list['data'][$i]['log_info'] = $log_info;
                /*用户操作日志 off*/
            }

            $list['filter'] = [
                "status"     => $status,
                "keyword"    => $keyword,
                "pay_type"   => $pay_type,
                "card_type"  => $card_type,
                "begin_time" => $begin_time,
                "end_time"   => $end_time,
            ];
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 发货操作
     * @return array
     */
    public function ship()
    {
        $vid                = $this->request->param('vid','');
        $send_type          = $this->request->param("send_type",'');//赠品发货类型   ‘express’ 快递 ‘salesman’销售代收
        $express_company    = $this->request->param('express_company','');//收货人物流公司
        $express_code       = $this->request->param('express_code','');//物流公司编码
        $express_number     = $this->request->param('express_number','');//物流单号
        $delivery_name      = $this->request->param('delivery_name','');//收货人姓名
        $delivery_phone     = $this->request->param('delivery_phone','');//收货人电话
        $delivery_area      = $this->request->param('delivery_area','');//收货人区域
        $delivery_address   = $this->request->param('delivery_address','');//收货人详细地址
        $express_name       = $this->request->param('express_name','');//代收货人姓名

        $pay_rule = [
            'vid|订单'       => 'require',
            'send_type|发货类型' => 'require'
        ];
        $check_pay_params = [
            'vid'       => $vid,
            'send_type' => $send_type
        ];
        //支付的回单号验证
        $pay_validate = new Validate($pay_rule);
        if (!$pay_validate->check($check_pay_params)){
            return $this->com_return(false,$pay_validate->getError(),null);
        }

        try {
            //获取自动收货时间
            $card_auto_delivery_day = $this->getSysSettingInfo("card_auto_delivery_day");
            $auto_finish_time       = time() + $card_auto_delivery_day * 24 * 60 * 60;

            if ($send_type == config("order.send_type")['express']['key']){
                //快递发货
                $rule = [
                    "express_company|收货人物流公司"    => "require|max:50",
                    "express_code|物流公司编码"         => "require",
                    "express_number|物流单号"          => "require|unique:bill_card_fees|max:50",
                    "delivery_name|收货人姓名"         => "require",
                    "delivery_phone|收货人电话"        => "require|regex:1[0-9]{1}[0-9]{9}",
                    "delivery_area|收货人区域"         => "require",
                    "delivery_address|收货人详细地址"   => "require|max:200",
                ];

                $check_params = [
                    "express_company"   => $express_company,
                    "express_code"      => $express_code,
                    "express_number"    => $express_number,
                    "delivery_name"     => $delivery_name,
                    "delivery_phone"    => $delivery_phone,
                    "delivery_area"     => $delivery_area,
                    "delivery_address"  => $delivery_address,
                ];

                $validate = new Validate($rule);

                if (!$validate->check($check_params)){
                    return $this->com_return(false,$validate->getError(),null);
                }

                $updated_params = [
                    "delivery_time"     => time(),
                    "auto_finish_time"  => $auto_finish_time,
                    "send_type"         => config("order.send_type")['express']['key'],
                    "sale_status"       => config("order.open_card_status")['pending_receipt']['key'],//改为待收货状态
                    "delivery_name"     => $delivery_name,
                    "delivery_phone"    => $delivery_phone,
                    "delivery_area"     => $delivery_area,
                    "delivery_address"  => $delivery_address,
                    "express_company"   => $express_company,
                    "express_code"      => $express_code,
                    "express_number"    => $express_number,
                    "updated_at"        => time()
                ];

            }else{
                //销售代收
                if (empty($express_name)){
                    return $this->com_return(false,config("params.FAIL"));
                }

                $updated_params = [
                    "express_name"      => $express_name,
                    "auto_finish_time"  => $auto_finish_time,
                    "sale_status"       => config("order.open_card_status")['pending_receipt']['key'],//改为待收货状态
                    "delivery_time"     => time(),
                    "send_type"         => config("order.send_type")['salesman']['key'],
                    "updated_at"        => time()
                ];
            }

            $billCardFeesModel = new BillCardFees();

            $res = $billCardFeesModel
                ->where('vid',$vid)
                ->where('sale_status',config('order.open_card_status')['pending_ship']['key'])
                ->update($updated_params);

            if ($res !== false) {
                /*记日志 on*/
                //获取当前登录管理员id
                $authorization   = $this->request->header('Authorization');
                $action_user_res = self::tokenGetAdminLoginInfo($authorization);
                $action_user     = $action_user_res['user_name'];
                $action = config("useraction.ship")['key'];
                $reason = config("useraction.ship")['name'];

                $this->addSysAdminLog("","","$vid","$action","$reason","$action_user",time());
                /*记日志 Off*/

                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 收款操作
     * @return array
     */
    public function adminPay()
    {
        if ($this->request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }

        $Authorization    = $this->request->header("Authorization");

        $notifyType       = $this->request->param('notifyType','adminCallback');//后台支付回调类型参数

        $vid              = $this->request->param('vid','');

        $payable_amount   = $this->request->param('payable_amount','');//线上应付且未付金额

        $pay_type         = $this->request->param('pay_type','');//支付方式 微信‘wxpay’ 支付宝 ‘alipay’ 线下银行转账 ‘bank’ 现金‘cash’

        $pay_no           = $this->request->param('pay_no','');//支付回单号

        $pay_name         = $this->request->param('pay_name','');//付款人或公司名称
        $pay_bank         = $this->request->param('pay_bank','');//付款方开户行
        $pay_account      = $this->request->param('pay_account','');//付款方账号
        $pay_bank_time    = $this->request->param('pay_bank_time','');//银行转账付款时间或现金支付时间
        $receipt_name     = $this->request->param('receipt_name','');//收款账户或收款人
        $receipt_bank     = $this->request->param('receipt_bank','');//收款银行
        $receipt_account  = $this->request->param('receipt_account','');//收款账号

        $delivery_name    = $this->request->param('delivery_name','');//收货人姓名
        $delivery_phone   = $this->request->param('delivery_phone','');//收货人电话
        $delivery_area    = $this->request->param('delivery_area','');//收货人区域
        $delivery_address = $this->request->param('delivery_address','');//收货人详细地址

        $reason           = $this->request->param('reason','');//操作原因

        if (empty($pay_bank_time)) $pay_bank_time = time();

        $public_rule = [
            'vid|订单号'                      => 'require',
            'payable_amount|付款金额'         => 'require',
            'pay_type|支付方式'                => 'require',
        ];

        $check_public_params = [
            "vid"               => $vid,
            "payable_amount"    => $payable_amount,
            "pay_type"          => $pay_type,
        ];

        $validate = new Validate($public_rule);

        if (!$validate->check($check_public_params)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            $payable_amount = $payable_amount * 100;//(以分为单位)

            $billCardFeesModel = new BillCardFees();
            $orderCommonObj    = new OrderCommon();

            $orderInfo = $orderCommonObj->vidGetOrderInfo($vid);

            if ($orderInfo['sale_status'] == config("order.open_card_status")['pending_ship']['key'] || $orderInfo['sale_status'] == config("order.open_card_status")['pending_receipt']['key'] || $orderInfo['sale_status'] == config("order.open_card_status")['completed']['key'] ){
                return $this->com_return(false,config("params.ORDER")['completed']);
            }

            $adminInfo   = self::tokenGetAdminLoginInfo($Authorization);
            $review_user = $adminInfo['user_name'];//收款审核人
            $review_desc = $reason;//审核备注

            //如果是微信支付或者阿里支付
            if ($pay_type == config('order.pay_method')['wxpay']['key'] || $pay_type == config('order.pay_method')['alipay']['key']){
                $pay_rule = [
                    'pay_no|支付回单号' => 'require|unique:bill_card_fees'
                ];
                $check_pay_params = [
                    'pay_no' => $pay_no
                ];
                //支付的回单号验证
                $pay_validate = new Validate($pay_rule);
                if (!$pay_validate->check($check_pay_params)){
                    return $this->com_return(false,$pay_validate->getError(),null);
                }
                //如果支付成功
                //更改订单支付信息
                $orderParams = [
                    'pay_type'         => $pay_type,
                    'pay_no'           => $pay_no,
                    'review_user'      => $review_user,
                    'review_time'      => time(),
                    'review_desc'      => $review_desc,
                    'updated_at'       => time()
                ];
            }elseif ($pay_type == config('order.pay_method')['bank']['key']){
                //如果是线下银行支付
                //更改订单支付信息
                $orderParams = [
                    'pay_type'        => $pay_type,
                    'pay_no'          => $pay_no,
                    'pay_name'        => $pay_name,
                    'pay_bank'        => $pay_bank,
                    'pay_account'     => $pay_account,
                    'pay_bank_time'   => $pay_bank_time,
                    'receipt_name'    => $receipt_name,
                    'receipt_bank'    => $receipt_bank,
                    'receipt_account' => $receipt_account,
                    'review_user'     => $review_user,
                    'review_time'     => time(),
                    'review_desc'     => $review_desc,
                    'updated_at'      => time()
                ];
            }elseif ($pay_type == config('order.pay_method')['cash']['key']){
                //更改订单支付信息
                $orderParams = [
                    'pay_type'         => $pay_type,
                    'pay_name'         => $pay_name,
                    'pay_bank_time'    => $pay_bank_time,
                    'receipt_name'     => $receipt_name,
                    'delivery_name'    => $delivery_name,
                    'delivery_phone'   => $delivery_phone,
                    'delivery_area'    => $delivery_area,
                    'delivery_address' => $delivery_address,
                    'review_user'      => $review_user,
                    'review_time'      => time(),
                    'review_desc'      => $review_desc,
                    'updated_at'       => time()
                ];
            }else{
                return $this->com_return(false,config('params.FAIL'));
            }


            $res = $this->callBackPay("$Authorization","$notifyType","$vid","$payable_amount","$payable_amount","$reason","$pay_no");

            $res = json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            if ($res['return_code'] != "SUCCESS"){
                return $this->com_return(false,$res['return_msg']);
            }

            $is_ok = $billCardFeesModel
                ->where('vid',$vid)
                ->update($orderParams);

            if ($is_ok !== false){
                Db::commit();
                //记日志
                $action = config("useraction.deal_pay")['key'];
                $this->addSysAdminLog("","","$vid","$action","$reason","$review_user",time());

                return $this->com_return(true,$res['return_msg']);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 获取快递公司列表
     * @return array
     */
    public function getLogisticsCompany()
    {
        try {
            $list = Db::name("mst_express")
                ->where("is_enable",1)
                ->where("is_delete",0)
                ->field("express_id,express_code,express_name")
                ->select();
            $list = json_decode(json_encode($list),true);
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }


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
    protected function callBackPay($Authorization,$notifyType,$vid,$total_fee,$cash_fee,$reason,$transaction_id= '')
    {
        $attach = config("order.pay_scene")['open_card']['key'];//开卡订单支付场景

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