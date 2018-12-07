<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午2:35
 */
namespace app\admin\controller\finance;

use app\common\controller\AdminAuthAction;
use app\common\controller\RechargeCommon;
use app\common\controller\UserCommon;
use app\common\controller\WechatCallCommon;
use app\common\model\BillRefill;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Recharge extends AdminAuthAction
{
    /**
     * 充值订单状态组
     * @return array
     */
    public function orderStatus()
    {
        try {
            $res = config("order.recharge_status");
            $statusGroup = [];
            $billRefillModel = new BillRefill();
            foreach ($res as $key => $val){
                if ($val["key"] == config("order.recharge_status")['pending_payment']['key']){
                    $count = $billRefillModel
                        ->where("status",config("order.recharge_status")['pending_payment']['key'])
                        ->count();//未付款总记录数

                    $val["count"] = $count;
                }else{
                    $val["count"] = 0;
                }
                $statusGroup[] = $val;
            }
            return $this->com_return(true,config("params.SUCCESS"),$statusGroup);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 充值订单列表
     * @param Request $request
     * @return array
     */
    public function order(Request $request)
    {
        $status     = $request->param("status","");
        $keyword    = $request->param("keyword","");
        $pay_type   = $request->param("pay_type","");//支付方式
        $begin_time = $request->param('begin_time',"");//开始时间
        $end_time   = $request->param('end_time',"");//结束时间
        $pagesize   = $request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $time_where = [];
        if (!empty($begin_time) && empty($end_time)){
            $time_where['br.created_at'] = ['EGT',$begin_time];//大于
        }

        if (empty($begin_time) && !empty($end_time)){
            $time_where['br.created_at'] = ['ELT',$end_time];//小于等于
        }

        if (!empty($begin_time) && !empty($end_time)){
            $time_where['br.created_at'] = ['BETWEEN',"$begin_time,$end_time"];//时间区间
        }

        $pay_type_where = [];
        if (!empty($pay_type)){
            $pay_type_where['br.pay_type'] = ['eq',$pay_type];
        }

        $where = [];
        if (!empty($keyword)){
            $where['br.rfid|br.pay_name|br.pay_bank|br.pay_account|br.receipt_name|br.receipt_bank|br.pay_user|u.phone|u.name|u.nickname|ms.sales_name|ms.sales_phone'] = ["like","%$keyword%"];
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $billRefillModel = new BillRefill();
            $admin_column = $billRefillModel->admin_column;

            foreach ($admin_column as $key => $val){
                $admin_column[$key] = "br.".$val;
            }

            $list = $billRefillModel
                ->alias("br")
                ->join("user u","u.uid = br.uid","LEFT")
                ->join("manage_salesman ms","ms.sid = br.referrer_id","LEFT")
                ->where('br.status',$status)
                ->where($time_where)
                ->where($where)
                ->where($pay_type_where)
                ->field($admin_column)
                ->field("u.phone,u.name,u.nickname,u.avatar,u.sex")
                ->field("ms.sales_name,ms.phone sales_phone")
                ->order("created_at DESC")
                ->paginate($pagesize,false,$config);

            $list = json_decode(json_encode($list),true);

            $data = $list["data"];

            for ($i = 0; $i < count($data); $i++){
                $rfid = $data[$i]['rfid'];

                $log_info = Db::name('sys_adminaction_log')
                    ->where('oid',$rfid)
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
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 后台新增充值
     * @param Request $request
     * @return array
     */
    public function addRechargeOrder(Request $request)
    {
        $notifyType  = $request->param('notifyType','adminCallback');//后台支付回调类型参数
        $user_phone  = $request->param("user_phone","");//用户电话
        $sales_phone = $request->param("sales_phone","");//营销电话
        $pay_type    = $request->param("pay_type","");//支付方式
        $amount      = $request->param("amount","");//支付金额
        $cash_gift   = $request->param("cash_gift","");//赠送礼金数

        $pay_no          = $request->param("pay_no","");//支付回单号

        $pay_name        = $request->param("pay_name","");//付款人或公司名称
        $pay_bank        = $request->param("pay_bank","");//付款方开户行
        $pay_account     = $request->param("pay_account","");//付款方账号
        $pay_bank_time   = $request->param("pay_bank_time",time());//银行转账付款时间或现金支付时间
        $receipt_name    = $request->param("receipt_name","");//收款账户或收款人
        $receipt_bank    = $request->param("receipt_bank","");//收款银行
        $receipt_account = $request->param("receipt_account","");//收款账号

        $pay_user    = $request->param("pay_user","");//代收付款人       有代收人时填写

        $review_desc = $request->param("review_desc","");//审核备注         微信   “微信系统收款”


        $rule = [
            "user_phone|用户电话"  => "require",
            "pay_type|支付方式"    => "require",
            "amount|充值金额"      => "require|number|max:20|gt:0",
            "cash_gift|赠送礼金数" => "require|number|max:20|egt:0",
        ];

        $request_res = [
            "user_phone" => $user_phone,
            "pay_type"   => $pay_type,
            "amount"     => $amount,
            "cash_gift"  => $cash_gift,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($pay_type == config("order.pay_method")['wxpay']['key'] || $pay_type == config("order.pay_method")['alipay']['key']){
            //微信充值或阿里充值
            $pay_rule = [
                'pay_no|支付回单号' => 'require|unique:bill_refill'
            ];

            $check_pay_params = [
                'pay_no' => $pay_no
            ];

        }elseif ($pay_type == config("order.pay_method")['bank']['key']){
            //线下银行转账
            $pay_rule = [
                'pay_no|支付回单号' => 'require|unique:bill_refill'
            ];

            $check_pay_params = [
                'pay_no' => $pay_no
            ];


        }elseif ($pay_type == config("order.pay_method")['cash']['key']) {
            //现金支付
            $pay_rule = [];
            $check_pay_params = [];

        }else{
            //其他支付报错
            return $this->com_return(false,config("params.FAIL"));
        }

        //支付的回单号验证
        $pay_validate = new Validate($pay_rule);
        if (!$pay_validate->check($check_pay_params)){
            return $this->com_return(false,$pay_validate->getError(),null);
        }
        $Authorization = $request->header("Authorization");
        try {
            $adminInfo = self::tokenGetAdminLoginInfo($Authorization);
            $action_user = $adminInfo['user_name'];

            //根据用户电话获取用户信息
            $userCommon = new UserCommon();
            $userInfo = $userCommon->uidOrPhoneGetUserInfo($user_phone);

            if (empty($userInfo)){
                return $this->com_return(false,config("params.PHONE_NOT_EXIST"));
            }
            $amount = $amount * 100;

            $uid = $userInfo['uid'];

            $rechargeCommonObj = new RechargeCommon();
            $pay_line_type = 1;

            $res = $rechargeCommonObj->rechargePublicAction("$uid","$amount","$cash_gift","$pay_type","$pay_line_type","$action_user","$review_desc","$sales_phone");

            if (!$res['result']){
                return $this->com_return(false,config("params.FAIL"));
            }

            $rfid   =$res['data']['rfid'];
            $amount =$res['data']['amount'];

            $wechatCallCommonObj = new WechatCallCommon();
            $res = $wechatCallCommonObj->callBackPay("$Authorization","$notifyType","$rfid","$amount","$amount","$review_desc","$pay_no");
            $res= json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            $billRefillModel = new BillRefill();

            if ($res['return_code'] != "SUCCESS"){
                return $this->com_return(false,$res['return_msg']);
            }

            //如果支付成功
            //更改订单支付信息
            $orderParams = [
                'pay_type'         => $pay_type,
                'pay_name'         => $pay_name,
                'pay_bank'         => $pay_bank,
                'pay_account'      => $pay_account,
                'pay_bank_time'    => $pay_bank_time,
                'receipt_name'     => $receipt_name,
                'receipt_bank'     => $receipt_bank,
                'receipt_account'  => $receipt_account,
                'pay_user'         => $pay_user,
                'review_time'      => time(),
                'review_user'      => $action_user,
                'review_desc'      => $review_desc,
                'updated_at'       => time()
            ];

            $is_ok = $billRefillModel
                ->where('rfid',$rfid)
                ->update($orderParams);

            if ($is_ok !== false){
                //记录操作日志
                addSysAdminLog("$uid","","$rfid",config("useraction.recharge")["key"],config("useraction.recharge")["name"],"$action_user",time());
                return $this->com_return(true,$res['return_msg']);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 后台充值收款操作
     * @param Request $request
     * @return array
     */
    public function receipt(Request $request)
    {
        if ($request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }
        $notifyType       = $request->param('notifyType','adminCallback');//后台支付回调类型参数
        $rfid             = $request->param('rfid','');
        $payable_amount   = $request->param('payable_amount','');//线上应付且未付金额
        $pay_type         = $request->param('pay_type','');//支付方式 微信‘wxpay’ 支付宝 ‘alipay’ 线下银行转账 ‘bank’ 现金‘cash’
        $pay_no           = $request->param('pay_no','');//支付回单号
        $pay_name         = $request->param("pay_name","");//付款人或公司名称
        $pay_bank         = $request->param("pay_bank","");//付款方开户行
        $pay_account      = $request->param("pay_account","");//付款方账号
        $pay_bank_time    = $request->param("pay_bank_time",time());//银行转账付款时间或现金支付时间
        $receipt_name     = $request->param("receipt_name","");//收款账户或收款人
        $receipt_bank     = $request->param("receipt_bank","");//收款银行
        $receipt_account  = $request->param("receipt_account","");//收款账号
        $pay_user         = $request->param("pay_user","");//代收付款人       有代收人时填写
        $review_desc      = $request->param("review_desc","");//审核备注         微信   “微信系统收款”

        $public_rule = [
            'rfid|订单号'                      => 'require',
            'payable_amount|付款金额'         => 'require',
            'pay_type|支付方式'                => 'require',
        ];

        $check_public_params = [
            "rfid"              => $rfid,
            "payable_amount"    => $payable_amount,
            "pay_type"          => $pay_type,
        ];

        $validate = new Validate($public_rule);

        if (!$validate->check($check_public_params)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($pay_bank_time)) $pay_bank_time = time();
        if ($pay_type == config("order.pay_method")['wxpay']['key'] || $pay_type == config("order.pay_method")['alipay']['key']){
            //微信充值或阿里充值
            $pay_rule = [
                'pay_no|支付回单号' => 'require|unique:bill_refill'
            ];

            $check_pay_params = [
                'pay_no' => $pay_no
            ];

        }elseif ($pay_type == config("order.pay_method")['bank']['key']){
            //线下银行转账
            $pay_rule = [
                'pay_no|支付回单号' => 'require|unique:bill_refill'
            ];

            $check_pay_params = [
                'pay_no' => $pay_no
            ];


        }elseif ($pay_type == config("order.pay_method")['cash']['key']) {
            //现金支付
            $pay_rule = [];
            $check_pay_params = [];

        }else{
            //其他支付报错
            return $this->com_return(false,config("params.FAIL"));
        }
        //支付的回单号验证
        $pay_validate = new Validate($pay_rule);

        if (!$pay_validate->check($check_pay_params)){
            return $this->com_return(false,$pay_validate->getError(),null);
        }

        try {
            $payable_amount = $payable_amount * 100;//(以分为单位)
            $Authorization  = $request->header("Authorization");
            $wechatCallCommonObj = new WechatCallCommon();
            $res = $wechatCallCommonObj->callBackPay("$Authorization","$notifyType","$rfid","$payable_amount","$payable_amount","$review_desc","$pay_no");
            $res= json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            $adminInfo = self::tokenGetAdminLoginInfo($Authorization);
            $action_user = $adminInfo['user_name'];

            $billRefillModel = new BillRefill();

            if ($res['return_code'] != "SUCCESS"){
                return $this->com_return(false,$res['return_msg']);

            }
            //如果支付成功
            //更改订单支付信息
            $orderParams = [
                'pay_type'         => $pay_type,
                'pay_name'         => $pay_name,
                'pay_bank'         => $pay_bank,
                'pay_account'      => $pay_account,
                'pay_bank_time'    => $pay_bank_time,
                'receipt_name'     => $receipt_name,
                'receipt_bank'     => $receipt_bank,
                'receipt_account'  => $receipt_account,
                'pay_user'         => $pay_user,
                'review_time'      => time(),
                'review_user'      => $action_user,
                'review_desc'      => $review_desc,
                'updated_at'       => time()
            ];

            $is_ok = $billRefillModel
                ->where('rfid',$rfid)
                ->update($orderParams);

            if ($is_ok !== false){
                addSysAdminLog("","","$rfid",config("useraction.recharge")["key"],config("useraction.recharge")["name"],"$action_user",time());
                return $this->com_return(true,$res['return_msg']);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


}