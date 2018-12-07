<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午11:51
 */

namespace app\reception\controller\card;


use app\common\controller\CardCommon;
use app\common\controller\ReceptionAuthAction;
use app\common\controller\UserCommon;
use app\common\model\BillCardFees;
use app\common\model\ManageSalesman;
use app\common\model\User;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class OpenCard extends ReceptionAuthAction
{
    /**
     * 获取所有的有效卡种
     * @return array
     */
    public function getAllCardInfo()
    {
        try {
            $cardCommonObj = new CardCommon();
            $list = $cardCommonObj->getEnableCardList();
            $cardInfo = [];
            foreach ($list as $key => $val){
                foreach ($val as $k => $v){
                    if ($k == "card_id"){
                        $k = "key";
                    }else{
                        $k = "name";
                    }
                    $cardInfo[$key][$k] = $v;
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 开卡订单列表
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $dateTime = $request->param("dateTime","");//时间
        $payType  = $request->param("payType","");//付款方式
        $pagesize = $request->param("pagesize","");
        $nowPage  = $request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config("page_size");
        if (empty($nowPage))  $nowPage = 1;
        $rule = [
            "dateTime|时间"    => "require",
        ];
        $request_res = [
            "dateTime"    => $dateTime,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $dateTimeRes = $this->getSysTimeLong($dateTime);
            $beginTime = $dateTimeRes['beginTime'];
            $endTime    = $dateTimeRes['endTime'];

            $date_where['bcf.created_at'] = ["between time",["$beginTime","$endTime"]];
            $pay_type_where = [];
            if (!empty($payType)){
                if ($payType == "all"){
                    $pay_type_where = [];
                }else{
                    $pay_type_where["pay_type"] = ["eq",$payType];
                }
            }

            $billCardFeesModel = new BillCardFees();

            $completed       = config("order.open_card_status")['completed']['key'];
            $pending_ship    = config("order.open_card_status")['pending_ship']['key'];
            $pending_receipt = config("order.open_card_status")['pending_receipt']['key'];

            $sale_status_str = "$completed,$pending_ship,$pending_receipt";

            $list = $billCardFeesModel
                ->alias("bcf")
                ->join("bill_card_fees_detail bcfd","bcfd.vid = bcf.vid")
                ->join("mst_card_vip mcv","mcv.card_id = bcfd.card_id")
                ->join("mst_card_type mct","mct.type_id = mcv.card_type_id","LEFT")
                ->join("user u","u.uid = bcf.uid","LEFT")
                ->where("bcf.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->where($pay_type_where)
                ->order("bcf.created_at DESC")
                ->field("u.name,u.phone")
                ->field("mct.type_name card_type")
                ->field("mcv.card_name,mcv.card_type_id")
                ->field("bcf.created_at,bcf.pay_type,bcf.order_amount,bcf.deal_price,bcf.review_user")
                ->field("bcfd.card_cash_gift")
                ->paginate($pagesize,false,$config);

            $list = json_decode(json_encode($list),true);

            /*开卡金额统计 On*/
            $money_sum = $billCardFeesModel
                ->alias("bcf")
                ->where("bcf.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->where($pay_type_where)
                ->sum("bcf.deal_price");
            $list['money_sum'] = $money_sum;
            /*开卡金额统计 Off*/

            /*开卡赠送礼金金额 On*/
            $card_cash_gift_sum = $billCardFeesModel
                ->alias("bcf")
                ->join("bill_card_fees_detail bcfd","bcfd.vid = bcf.vid")
                ->where("bcf.sale_status","IN",$sale_status_str)
                ->where($date_where)
                ->where($pay_type_where)
                ->sum("bcfd.card_cash_gift");
            /*开卡赠送礼金金额 Off*/

            $list['card_cash_gift_sum'] = $card_cash_gift_sum;
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认开卡
     * @param Request $request
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirmOpenCard(Request $request)
    {
        $referrer_phone = $request->param("referrer_phone","");//推荐人电话
        $user_phone     = $request->param("user_phone","");//用户电话
        $user_name      = $request->param("user_name","");//用户姓名
        $user_sex       = $request->param("user_sex","");//用户性别
        $card_id        = $request->param("card_id","");//卡id
        $review_desc    = $request->param("review_desc","");//备注
        $pay_type       = $request->param("pay_type","");//支付方式
        $is_giving      = $request->param("is_giving","");//是否赠送 1:是
        $rule = [
            "user_phone|客户电话"    => "require|regex:1[0-9]{1}[0-9]{9}",
            "user_name|客户姓名"     => "require",
            "card_id|卡种"          => "require",
            "pay_type|支付方式"      => "require",
        ];
        $request_res = [
            "user_phone" => $user_phone,
            "user_name"  => $user_name,
            "card_id"    => $card_id,
            "pay_type"   => $pay_type,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $token       = $request->header("Token");
        $manageInfo  = $this->receptionTokenGetManageInfo($token);
        $review_user = $manageInfo["sales_name"];
        Db::startTrans();
        try {
            $userModel = new User();
            /*处理推荐人信息 On*/
            if ($referrer_phone == "8888"){
                $referrer_type = config("salesman.salesman_type")['3']['name'];
                $referrer_id = config("salesman.salesman_type")['3']['key'];
            }else{
                //查询是否是营销
                $vip_type    = config("salesman.salesman_type")['0']['key'];
                $sales_type  = config("salesman.salesman_type")['1']['key'];
                $boss_type   = config("salesman.salesman_type")['4']['key'];

                $stype_key_str = "$vip_type,$sales_type,$boss_type";
                $manageModel = new ManageSalesman();
                $referrerInfo = $manageModel
                    ->alias("ms")
                    ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
                    ->where("ms.phone",$referrer_phone)
                    ->where("mst.stype_key","IN",$stype_key_str)
                    ->field("ms.sid,mst.stype_key")
                    ->find();

                $referrerInfo = json_decode(json_encode($referrerInfo),true);

                if (!empty($referrerInfo)){
                    //内部人员推荐
                    $referrer_id = $referrerInfo['sid'];
                    $referrer_type = $referrerInfo['stype_key'];
                }else{
                    //查询是否是用户推荐
                    $userReferrerInfo = $userModel
                        ->where("phone",$referrer_phone)
                        ->field("uid")
                        ->find();
                    $userReferrerInfo = json_decode(json_encode($userReferrerInfo),true);
                    if (!empty($userReferrerInfo)){
                        //用户推荐
                        $referrer_id   = $userReferrerInfo["uid"];
                        $referrer_type = config("salesman.salesman_type")['2']['key'];
                    }else{
                        //推荐人不存在,返回false
                        return $this->com_return(false,config("params.SALESMAN_NOT_EXIST"));
                    }
                }

            }
            /*处理推荐人信息 Off*/
            //查看会员信息
            $userInfo = $userModel
                ->where("phone",$user_phone)
                ->field("uid,user_status,referrer_type,referrer_id")
                ->find();
            $userInfo = json_decode(json_encode($userInfo),true);

            $userCommonObj = new UserCommon();
            if (!empty($userInfo)){
                $uid         = $userInfo["uid"];
                $user_status = $userInfo['user_status'];
                if ($user_status == config("user.user_register_status")['open_card']['key']){
                    //用户已开卡,请勿重复开卡
                    return $this->com_return(false,config("params.USER")['USER_OPENED_CARD']);
                }
                //更新用户推荐人信息
                $updateUserReferrerParams = [
                    "referrer_type" => $referrer_type,
                    "referrer_id"   => $referrer_id,
                    "updated_at"    => time()
                ];
                $updateUserReferrerReturn = $userCommonObj->updateUserInfo($updateUserReferrerParams,"$uid");
                if ($updateUserReferrerReturn == false){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 002");
                }
            }else{
                //新用户,首先注册用户信息
                $uid = generateReadableUUID("U");
                $newUserParams = [
                    "uid"           => $uid,
                    "phone"         => $user_phone,
                    "password"      => sha1(config("DEFAULT_PASSWORD")),
                    "name"          => $user_name,
                    "sex"           => $user_sex,
                    "register_way"  => config("user.register_way")['web']['key'],
                    "user_status"   => config("user.user_status")['0']['key'],
                    "info_status"   => config("user.user_info")['interest']['key'],
                    "referrer_type" => $referrer_type,
                    "referrer_id"   => $referrer_id,
                    "created_at"    => time(),
                    "updated_at"    => time()
                ];

                $insertNewUserReturn = $userCommonObj->insertNewUser($newUserParams);
                if ($insertNewUserReturn == false){
                    return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
                }
            }

            /*开卡 on*/
            $cardCommonObj = new CardCommon();
            $res = $cardCommonObj->createdOpenCardOrder($card_id,$uid,$referrer_type,$referrer_id,$review_user,$review_desc,$pay_type,$is_giving);
            if (isset($res['result']) && $res['result']){
                Db::commit();
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $res;
            }
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


}