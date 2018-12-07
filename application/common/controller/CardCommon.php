<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午6:43
 */

namespace app\common\controller;


use app\common\model\ManageSalesman;
use app\common\model\MstCardType;
use app\common\model\MstCardVip;
use app\common\model\MstCardVipGiftRelation;
use app\common\model\MstCardVipVoucherRelation;
use app\common\model\MstGiftVoucher;
use app\common\model\User;
use app\common\model\UserCard;
use app\common\model\UserCardHistory;
use think\Controller;
use think\Db;
use think\Exception;

class CardCommon extends BaseController
{
    /**
     * 获取卡种列表
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCardType()
    {
        $cardTypeModel = new MstCardType();
        $res = $cardTypeModel
            ->where("is_enable",1)
            ->where("is_delete",0)
            ->order("updated_at DESC")
            ->field("type_id `key`,type_name name")
            ->select();
        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 卡种下是否存在未删除的有效卡片
     * @param $type_id
     * @return bool
     */
    public function cardTypeHaveCard($type_id)
    {
        $cardVipModel = new MstCardVip();

        $res = $cardVipModel
            ->where("card_type_id",$type_id)
            ->where("is_delete",0)
            ->count();

        if ($res > 0){
            //存在返回 true
            return true;
        }else{
            return false;
        }
    }

    /**
     * 删除卡
     * @param $card_id
     * @return MstCardVip
     */
    public function delete_card($card_id)
    {
        $cardVipModel = new MstCardVip();

        $time = time();

        $params['updated_at'] = $time;
        $params['is_delete']  = 1;

        $is_ok = $cardVipModel
            ->where("card_id",$card_id)
            ->update($params);

        return $is_ok;
    }

    /**
     * 写入 mst_card_vip
     * 写入 vip卡表中
     * @param $params
     * @return int|string 'card_id'
     */
    public function insert_card_vip($params)
    {
        $cardVipModel = new MstCardVip();
        $card_id = $cardVipModel
            ->insertGetId($params);
        return $card_id;
    }

    /*
     * 关联mst_card_vip_gift_relation表
     *
     *@params $card_id:VIP卡id
     *@params $gift_id:关联的礼品id
     *@params $qty:赠送数量
     *
     * */
    public function insert_card_vip_gift_relation($card_id,$gift_id)
    {
        $cardVipGiftRelationModel = new MstCardVipGiftRelation();

        $data = [
            "card_id" => $card_id,
            "gift_id" => $gift_id,
        ];

        //去表里查询是否存在,如果存在更新,如果不存在则新建
        $is_exist = $cardVipGiftRelationModel
            ->where('card_id',$card_id)
            ->where('gift_id',$gift_id)
            ->count();

        if ($is_exist){
            $is_ok = $cardVipGiftRelationModel
                ->update($data);
        }else{
            $is_ok = $cardVipGiftRelationModel
                ->insert($data);
        }

        if ($is_ok){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 关联mst_card_vip_voucher_relation表
     *
     * VIP卡赠送消费券关系表
     *
     * @params $card_id VIP卡id
     * @params $gift_vou_id 关联的礼品券id
     * @params $gift_vou_type 赠券类型  ‘once’单次    ‘multiple’多次   ‘limitless’ 无限制
     * @params $qty 赠送数量
     * @params $use_qty 使用数量
     * @param $card_id
     * @param $gift_vou_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function insert_card_vip_voucher_relation($card_id,$gift_vou_id)
    {
        $cardVipVoucherRelationModel = new MstCardVipVoucherRelation();

        $giftVoucherModel = new MstGiftVoucher();

        $gift_vou_type_res = $giftVoucherModel
            ->where('gift_vou_id',$gift_vou_id)
            ->field('gift_vou_type')
            ->find();

        $gift_vou_type = $gift_vou_type_res['gift_vou_type'];

        $data = [
            "card_id"       => $card_id,
            "gift_vou_id"   => $gift_vou_id,
            "gift_vou_type" => $gift_vou_type,
        ];

        //去表里查询是否存在,如果存在更新,如果不存在则新建
        $is_exist = $cardVipVoucherRelationModel
            ->where('card_id',$card_id)
            ->where('gift_vou_id',$gift_vou_id)
            ->count();

        if ($is_exist){
            $is_ok = $cardVipVoucherRelationModel
                ->update($data);
        }else{
            $is_ok = $cardVipVoucherRelationModel
                ->insert($data);
        }
        if ($is_ok){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新会员卡信息
     * @param $card_id
     * @param $params
     * @return bool
     */
    public function updateMstCardVip($card_id,$params)
    {
        $cardVipModel = new MstCardVip();
        $res = $cardVipModel
            ->where('card_id',$card_id)
            ->update($params);
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 删除会员卡
     * @param $card_id
     * @return bool
     */
    public function deleteMstCardVip($card_id)
    {
        $cardVipModel = new MstCardVip();

        $params['updated_at'] = time();
        $params['is_delete']  = 1;

        $res = $cardVipModel
            ->where("card_id",$card_id)
            ->update($params);

        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 删除礼品关系表中关于此卡的信息
     * @param $card_id
     * @return bool
     */
    public function deleteCardVipGiftRelation($card_id)
    {
        $cardVipGiftRelationModel = new MstCardVipGiftRelation();
        $res = $cardVipGiftRelationModel
            ->where('card_id',$card_id)
            ->delete();
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 删除礼券关系表中关于此卡的信息
     * @param $card_id
     * @return bool
     */
    public function deleteCardVipVoucherRelation($card_id)
    {
        $cardVipVoucherRelationModel = new MstCardVipVoucherRelation();
        $res = $cardVipVoucherRelationModel
            ->where('card_id',$card_id)
            ->delete();
        if ($res !== false) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检测卡是否绑定指定礼品
     * @param $card_id
     * @param $gift_id
     * @return bool
     */
    public function checkCardVipGiftRelation($card_id,$gift_id)
    {
        $tableModel = new MstCardVipGiftRelation();

        $res = $tableModel
            ->where('card_id',$card_id)
            ->where('gift_id',$gift_id)
            ->count();
        if ($res > 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检测卡是否绑定指定礼券
     * @param $card_id
     * @param $gift_vou_id
     * @param $gift_vou_type
     * @return bool
     */
    public function checkCardVipVoucherRelation($card_id,$gift_vou_id,$gift_vou_type)
    {
        $tableModel = new MstCardVipVoucherRelation();

        $res = $tableModel
            ->where('card_id',$card_id)
            ->where('gift_vou_id',$gift_vou_id)
            ->where('gift_vou_type',$gift_vou_type)
            ->count();
        if ($res > 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取卡列表
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCardList()
    {
        $cardModel = new MstCardVip();

        $cardList = $cardModel
            ->alias("cv")
            ->join("mst_card_type ct","ct.type_id = cv.card_type_id")
            ->where("ct.is_enable",1)
            ->where("ct.is_delete",0)
            ->where("cv.is_delete",0)
            ->field("cv.card_id,cv.card_name,ct.type_name,ct.type_id card_type_id")
            ->select();

        $cardList = json_decode(json_encode($cardList),true);

        return $cardList;
    }

    /**
     * 获取卡列表
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getEnableCardList()
    {
        $cardModel = new MstCardVip();

        $cardList = $cardModel
            ->where('is_enable',1)
            ->where("is_delete",0)
            ->order('sort')
            ->field('card_id,card_name,card_amount,card_pay_amount,is_giving')
            ->select();

        $cardList = json_decode(json_encode($cardList),true);

        return $cardList;
    }

    /**
     * 获取办理指定卡的用户id和电话
     * @param $card_id
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCardUserList($card_id)
    {
        $userCardModel = new UserCard();

        $res = $userCardModel
            ->alias("uc")
            ->join("user u","u.uid = uc.uid","LEFT")
            ->where("uc.card_id","IN",$card_id)
            ->field("uc.uid,u.phone")
            ->select();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 获取用户开卡推荐人信息
     * @param $uid
     * @param $sale_status
     * @return array|false|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserCardInfo($uid,$sale_status)
    {
        $column = [
            'vid',
            'uid',
            'referrer_id',
            'referrer_type',
            'payable_amount',
            'delivery_name',
            'delivery_phone',
            'delivery_area',
            'delivery_address',
            'created_at'
        ];

        $bill = Db::name('bill_card_fees')
            ->where('uid',$uid)
            ->where('sale_status',$sale_status)
            ->field($column)
            ->find();

        if (!empty($bill)){
            $vid = $bill['vid'];
            $bill_detail = Db::name('bill_card_fees_detail')
                ->where('vid',$vid)
                ->find();
            $bill['card_gift'] = $bill_detail;
        }else{
            return null;
        }
        //获取推荐人电话
        $referrer_id_res = Db::name('user')
            ->where('uid',$uid)
            ->field('referrer_type,referrer_id')
            ->find();

        if (!empty($referrer_id_res)){

            $referrer_type = $referrer_id_res['referrer_type'];
            $referrer_id   = $referrer_id_res['referrer_id'];

            if ($referrer_type == 'empty'){

                $referrer_info = array();
            }else{
                if ($referrer_type == 'user'){

                    $dbName = 'user';
                    $id = 'uid';

                }else{
                    $dbName = 'manage_salesman';
                    $id = 'sid';
                }

                $referrer_info  = Db::name($dbName)
                    ->where($id,$referrer_id)
                    ->field('phone')
                    ->find();
            }
        }else{
            $referrer_info = array();
        }

        $bill['referrer_info'] = $referrer_info;
        return $bill;
    }

    /**
     * 添加或更新开卡信息
     * @param array $params
     * @return bool
     */
    public function updateCardInfo($params = array())
    {
        try {
            $userCardModel        = new UserCard();
            $userCardHistoryModel = new UserCardHistory();

            //查看当前用户是否已经办过卡
            $is_exist = $userCardModel
                ->where('uid',$params['uid'])
                ->find();
            $is_exist = json_decode($is_exist,true);
            if (!empty($is_exist)){
                //将旧数据写入user_card_history历史表
                $is_exist["record_time"] = time();
                $userCardHistoryModel->insert($is_exist);
                //存在则更新
                $is_ok = $userCardModel
                    ->where('uid',$params['uid'])
                    ->update($params);
            }else{
                //不存在则新增
                $params['created_at'] = $params['updated_at'];
                $is_ok = $userCardModel
                    ->insert($params);
            }

            if ($is_ok !== false) {
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * card_id获取卡信息
     * @param $card_id
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function cardIdGetCardInfo($card_id)
    {
        try {
            $cardVipModel = new MstCardVip();

            $column = $cardVipModel->column;

            $cardInfo = $cardVipModel
                ->where('card_id', $card_id)
                ->field($column)
                ->find();
            $cardInfo = json_decode($cardInfo, true);
            if (!empty($cardInfo)) {
                return $cardInfo;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 开卡
     * @param $card_id
     * @param $uid
     * @param $referrer_type
     * @param $referrer_id
     * @param $review_user
     * @param $review_desc
     * @param $pay_type
     * @param $is_giving
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function createdOpenCardOrder($card_id,$uid,$referrer_type,$referrer_id,$review_user,$review_desc,$pay_type,$is_giving)
    {
        $card_info  = $this->cardIdGetCardInfo($card_id);
        if ($card_info === false ){
            return $this->com_return(false,config("params.USER")['CARD_VALID_NO']);
        }
        $cardInfo_amount  = $card_info["card_amount"];//充值金额
        $card_pay_amount  = $card_info["card_pay_amount"];//支付金额

        //如果赠送,则支付金额为 0
        if ($is_giving == 1) $card_pay_amount = 0;

        $vid = generateReadableUUID("V");//充值缴费单 V前缀
        //获取自动取消分钟数
        $cardAutoCancelMinutes = getSysSetting("card_auto_cancel_time");
        //将分钟数转换为秒
        $cardAutoCancelTime = $cardAutoCancelMinutes * 60;
        $commission_ratio = 0;
        $discount = 0;//折扣金额
        //③生成缴费订单,创建发货订单
        $billCardFeesParams = [
            'vid'             => $vid,
            'uid'             => $uid,//用户id
            'referrer_type'   => $referrer_type,//推荐人类型
            'referrer_id'     => $referrer_id,//推荐人id
            'sale_status'     => config("order.open_card_status")['completed']['key'],//单据状态
            'deal_time'       => time(),//成交时间
            'pay_time'        => time(),//付款时间
            'finish_time'     => time(),//完成时间
            'auto_cancel_time'=> time()+$cardAutoCancelTime,//单据自动取消的时间
            'order_amount'    => $card_pay_amount,//订单金额
            'discount'        => $discount,//折扣,暂且为0
            'payable_amount'  => 0,//线上应付且未付金额
            'deal_price'      => $card_pay_amount - $discount,
            'pay_type'        => $pay_type,
            'is_settlement'   => $referrer_type == "empty" ? 1 : 0 ,//是否结算佣金
            'commission_ratio'=> $commission_ratio,//下单时的佣金比例   百分比整数     没有推荐人的自动为0
            'commission'      => ($card_pay_amount - $discount) * $commission_ratio / 100,
            'review_time'     => time(),
            'review_user'     => $review_user,
            'review_desc'     => $review_desc,
            'created_at'      => time(),//创建时间
            'updated_at'      => time(),//更新时间
        ];

        //返回订单id
        $orderCommonObj = new OrderCommon();
        $billCardFeesReturn = $orderCommonObj->insertBillCardFees($billCardFeesParams);
        if ($billCardFeesReturn == false){
            return $this->com_return(false,config("params.ORDER")['CREATE_CARD_ORDER_FAIL']);
        }
        //获取开卡赠送礼金数(百分比)
        $card_cash_gift     = $card_info['card_cash_gift'];
        //获取开卡赠送积分
        $card_point         = $card_info['card_point'];
        //获取开卡赠送推荐用户礼金(百分比)
        $card_job_cash_gif  = $card_info['card_job_cash_gif'];
        //获取开卡赠送推荐用户佣金(百分比)
        $card_job_commission= $card_info['card_job_commission'];

        /*创建 bill_card_fees_details On*/
        $billCardFeesDetailParams = [
            'vid'           => $billCardFeesReturn,
            'card_id'       => $card_id,
            'card_type'     => $card_info["card_type"],//卡片类型   ‘vip’会籍卡      ‘value’ 储值卡
            'card_name'     => $card_info["card_name"],//VIP卡名称
            'card_level'    => $card_info["card_level"],//vip卡级别名称
            'card_image'    => $card_info["card_image"],//VIP卡背景图
            'card_no_prefix'=> $card_info["card_no_prefix"],//卡号前缀（两位数字）
            'card_desc'     => $card_info["card_desc"],//VIP卡使用说明及其他描述
            'card_equities' => $card_info["card_equities"],//卡片享受权益详情
            'card_deposit'  => $card_info["card_deposit"],//卡片权益保证金额

            'card_amount'         => $cardInfo_amount,//充值金额
            'card_pay_amount'     => $card_pay_amount,//支付金额
            'card_point'          => $card_point,//开卡赠送积分
            'card_cash_gift'      => intval(($card_cash_gift / 100) * $cardInfo_amount),//开卡赠送礼金数
            'card_job_cash_gif'   => intval(($card_job_cash_gif / 100) * $cardInfo_amount),//推荐人返佣礼金
            'card_job_commission' => intval(($card_job_commission / 100) * $cardInfo_amount),//推荐人返佣金
        ];

        $billCardFeesDetailReturn = $orderCommonObj->billCardFeesDetail($billCardFeesDetailParams);

        if ($billCardFeesDetailReturn == false){
            return $this->com_return(false,config("params.ORDER")['CREATE_CARD_ORDER_FAIL']);
        }
        /*创建 bill_card_fees_details Off*/

        /*给用户写入开卡信息 on*/
        $userCardParams = [
            "uid"               => $uid,
            "card_no"           => generateReadableUUID($card_info["card_no_prefix"]),
            "card_id"           => $card_id,
            "card_type"         => $card_info['card_type'],
            "card_name"         => $card_info['card_name'],
            "card_image"        => $card_info['card_image'],
            "card_o_pay_amount" => $card_info['card_pay_amount'],//应支付金额
            "card_pay_amount"   => $card_pay_amount,//实际支付金额
            "card_amount"       => $cardInfo_amount,//充值金额
            "card_deposit"      => $card_info['card_deposit'],
            "card_desc"         => $card_info['card_desc'],
            "card_equities"     => $card_info['card_equities'],
            "is_valid"          => 1,
            "valid_time"        => $card_info['card_validity_time'],
            "created_at"        => time(),
            "updated_at"        => time()
        ];
        $userCardInfoReturn = $this->updateCardInfo($userCardParams);
        if ($userCardInfoReturn === false) {
            return $this->com_return(false,config("params.FAIL"));
        }
        /*给用户写入开卡信息 off*/

        $userOldMoneyInfo = Db::name('user')
            ->where('uid',$uid)
            ->field('account_balance,account_deposit,account_cash_gift,account_point')
            ->find();
        //用户钱包可用余额
        $account_balance   = $userOldMoneyInfo['account_balance'];
        //用户钱包押金余额
        $account_deposit   = $userOldMoneyInfo['account_deposit'];
        //用户礼金余额
        $account_cash_gift = $userOldMoneyInfo['account_cash_gift'];
        //用户积分可用余额
        $account_point     = $userOldMoneyInfo['account_point'];

        $userCommonObj = new UserCommon();

        if ($referrer_id != config("salesman.salesman_type")['3']['key']){
            //如果推荐人不是平台推荐
            if ($referrer_type != 'user'){
                //如果是内部人员推荐,给人员用户端账号返还礼金,佣金
                $manageSalesModel = new ManageSalesman();
                $salesInfo = $manageSalesModel
                    ->where("sid",$referrer_id)
                    ->field("phone")
                    ->find();
                $salesInfo = json_decode(json_encode($salesInfo),true);

                if (empty($salesInfo)){
                    //推荐人不存在
                    return $this->com_return(false,config("params.SALESMAN_NOT_EXIST"));
                }

                $sales_phone = $salesInfo['phone'];
                $userModel = new User();
                $salesUserInfo = $userModel
                    ->where("phone",$sales_phone)
                    ->field("uid,account_balance,account_point,account_cash_gift")
                    ->find();
                $salesUserInfo = json_decode(json_encode($salesUserInfo),true);

                if (empty($salesUserInfo)){
                    $referrer_id = "";
                }else{
                    $referrer_id = $salesUserInfo['uid'];
                }
            }
            //TODO 如果推荐人未注册用户端,则不返还
            if (!empty($referrer_id)){
                //如果推荐人是用户,给推荐人用户更新礼金信息
                //账户可用礼金变动  正加 负减  直接取整,舍弃小数
                $cash_gift = intval(($card_job_cash_gif / 100) * $cardInfo_amount);
                if ($cash_gift > 0){
                    //如果赠送礼金大于0
                    //首先获取推荐人的礼金余额
                    $referrer_user_gift_cash_old = $userCommonObj->getUserFieldValue("$referrer_id","account_cash_gift");
                    //变动后的礼金总余额
                    $last_cash_gift = $cash_gift + $referrer_user_gift_cash_old;
                    $userAccountCashGiftParams = [
                        'uid'            => $referrer_id,
                        'cash_gift'      => $cash_gift,
                        'last_cash_gift' => $last_cash_gift,
                        'change_type'    => '2',
                        'action_user'    => 'sys',
                        'action_type'    => config('user.gift_cash')['recommend_reward']['key'],
                        'action_desc'    => config('user.gift_cash')['recommend_reward']['name'],
                        'oid'            => $vid,
                        'created_at'     => time(),
                        'updated_at'     => time()
                    ];

                    //给推荐用户添加礼金明细
                    $userAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($userAccountCashGiftParams);
                    if ($userAccountCashGiftReturn == false){
                        return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 001");
                    }

                    //给推荐用户添加礼金余额
                    $updatedAccountCashGiftReturn = $userCommonObj->updatedAccountCashGift("$referrer_id","$cash_gift","inc");
                    if ($updatedAccountCashGiftReturn == false){
                        return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 002");
                    }
                }
                /*给推荐用户添加佣金*/
                if ($card_job_commission > 0){
                    //首先获取推荐人的佣金余额
                    $old_last_balance_res = Db::name("job_user")
                        ->where('uid',$referrer_id)
                        ->field('job_balance')
                        ->find();
                    $old_last_balance_res = json_decode(json_encode($old_last_balance_res),true);

                    if (!empty($old_last_balance_res)){
                        $job_balance = $old_last_balance_res['job_balance'];
                    }else{
                        $job_balance = 0;
                    }
                    $plus_card_job_commission = intval(($card_job_commission / 100) * $cardInfo_amount);

                    //添加或更新推荐用户佣金表
                    $jobUserReturn = $userCommonObj->updateJobUser($referrer_id,$plus_card_job_commission);

                    if ($jobUserReturn == false){
                        return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 003");
                    }

                    //添加推荐用户佣金明细表
                    $jobAccountParams = [
                        "uid"          => $referrer_id,
                        "balance"      => $plus_card_job_commission,
                        "last_balance" => $job_balance + $plus_card_job_commission,
                        "change_type"  => 2,
                        "action_user"  => 'sys',
                        "action_type"  => config('user.job_account')['recommend_reward']['key'],
                        "oid"          => $vid,
                        "deal_amount"  => $cardInfo_amount,
                        "action_desc"  => config('user.job_account')['recommend_reward']['name'],
                        "created_at"   => time(),
                        "updated_at"   => time()
                    ];

                    $jobAccountReturn = $userCommonObj->insertJobAccount($jobAccountParams);

                    if ($jobAccountReturn == false){
                        return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 004");
                    }
                }
            }
        }

        //获取当前用户旧的礼金余额
        $user_gift_cash_old = $userCommonObj->getUserFieldValue("$uid","account_cash_gift");
        $card_cash_gift_money = intval(($card_cash_gift / 100) * $cardInfo_amount);

        if ($card_cash_gift_money > 0){
            //如果赠送开卡用户礼金大于0
            $user_gift_cash_new   = $user_gift_cash_old + $card_cash_gift_money;
            $updatedOpenCardCashGiftReturn = $userCommonObj->updatedAccountCashGift("$uid","$card_cash_gift_money","inc");

            if ($updatedOpenCardCashGiftReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 005");
            }

            //更新用户礼金明细
            $updatedUserCashGiftParams = [
                'uid'            => $uid,
                'cash_gift'      => $card_cash_gift_money,
                'last_cash_gift' => $user_gift_cash_new,
                'change_type'    => '2',
                'action_user'    => 'sys',
                'action_type'    => config("user.gift_cash")['open_card_reward']['key'],
                'action_desc'    => config("user.gift_cash")['open_card_reward']['name'],
                'oid'            => $vid,
                'created_at'     => time(),
                'updated_at'     => time()
            ];

            //增加开卡用户礼金明细
            $openCardUserAccountCashGiftReturn = $userCommonObj->updateUserAccountCashGift($updatedUserCashGiftParams);

            if ($openCardUserAccountCashGiftReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 006");
            }
        }

        /*如果充值金额大于 0  On*/
        if ($cardInfo_amount > 0) {
            //更新用户余额账户以及余额明细
            //获取用户旧的余额
            //用户余额参数
            $userCardParams = [
                "uid"               => $uid,
                "account_balance"   => $cardInfo_amount + $account_balance,
                "user_status"       => config("user.user_status")['2']['key'],
                "updated_at"        => time()
            ];
            $userUpdateReturn = $userCommonObj->updateUserInfo($userCardParams,$uid);

            if ($userUpdateReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 010");
            }

            //余额明细参数
            $userAccountParams = [
                "uid"          => $uid,
                "balance"      => $cardInfo_amount,
                "last_balance" => $cardInfo_amount + $account_balance,
                "change_type"  => '2',
                "action_user"  => 'sys',
                "action_type"  => config('user.account')['card_recharge']['key'],
                "oid"          => $vid,
                "deal_amount"  => $cardInfo_amount,
                "action_desc"  => config('user.account')['card_recharge']['name'],
                "created_at"   => time(),
                "updated_at"   => time()
            ];
            $userInsertReturn = $userCommonObj->updateUserAccount($userAccountParams);

            if ($userInsertReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 011");
            }

        }
        /*如果充值金额大于 0  Off*/

        //⑩更新用户积分账户以及积分明细
        //$account_point用户积分可用余额
        //$card_point 开卡赠送积分
        if ($card_point > 0){
            //如果赠送积分大于0 则更新
            $new_account_point = $account_point + $card_point;
            //获取用户新的等级id
            $level_id = getUserNewLevelId($new_account_point);
            //1.更新用户积分余额
            $updateUserPointParams = [
                'level_id'      => $level_id,
                'account_point' => $new_account_point,
                'updated_at'    => time()
            ];
            $userUserPointReturn = $userCommonObj->updateUserInfo($updateUserPointParams,$uid);

            if ($userUserPointReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 012");
            }
            //2.更新用户积分明细
            $updateAccountPointParams = [
                'uid'         => $uid,
                'point'       => $card_point,
                'last_point'  => $new_account_point,
                'change_type' => 2,
                'action_user' => 'sys',
                'action_type' => config("user.point")['open_card_reward']['key'],
                'action_desc' => config("user.point")['open_card_reward']['name'],
                'oid'         => $vid,
                'created_at'  => time(),
                'updated_at'  => time()
            ];
            $userAccountPointReturn = $userCommonObj->updateUserAccountPoint($updateAccountPointParams);

            if ($userAccountPointReturn == false){
                return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 013");
            }
        }

       /* $voucherCommonObj = new VoucherCommon();
        //下发赠送的券
        $giftVouReturn = $voucherCommonObj->putVoucher("$card_id","$uid");
        if ($giftVouReturn == false){
            return $this->com_return(false,config("params.ABNORMAL_ACTION")." - 014");
        }*/

        $action  = config("useraction.open_card")['key'];
        addSysAdminLog("$uid","","$vid","$action","$review_desc","$review_user",time());

        return $this->com_return(true,config("params.SUCCESS"));
    }
}