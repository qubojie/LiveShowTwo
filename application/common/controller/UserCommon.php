<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午5:12
 */
namespace app\common\controller;

use app\common\model\JobAccount;
use app\common\model\JobUser;
use app\common\model\ManageSalesman;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\model\UserAccountCashGift;
use app\common\model\UserAccountDeposit;
use app\common\model\UserAccountPoint;
use app\common\model\UserCard;
use app\common\model\UserGiftVoucher;
use think\Db;
use think\Exception;

class UserCommon extends BaseController
{
    /**
     * 插入新的用户
     * @param $params
     * @return bool
     */
    public function insertNewUser($params)
    {
        try {
            $userModel = new User();
            $res = $userModel->insert($params);

            if ($res !== false) {
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

    }
    /**
     * 根据uid获取用户信息
     * @param $uid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserInfo($uid)
    {
        $userModel = new User();
        $column = $userModel->column;
        $res = $userModel
            ->where('uid',$uid)
            ->field($column)
            ->find();
        $res  =json_decode(json_encode($res),true);
        return $res;
    }

    /**
     * 根据sid获取销售人员信息
     * @param $sid
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSalesManInfo($sid)
    {
        $salesModel = new ManageSalesman();
        $admin_column = $salesModel->admin_column;
        $salesmanInfo = $salesModel
            ->alias("ms")
            ->where('sid',$sid)
            ->field($admin_column)
            ->find();
        return $salesmanInfo;
    }

    /**
     * 用户操作日志信息获取s
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserLog($uid)
    {
        $sysLogObj = new SysLog();

        $res = $sysLogObj->log_list("uid","$uid");

        for ($i = 0; $i < count($res); $i++){
            $action = $res[$i]['action'];
            $action_des = config("useraction.$action")['name'];

            $res[$i]['action'] = $action_des;
        }
        return $res;
    }

    /**
     * 根据uid或者phone获取用户指定信息
     * @param $keyword
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function uidOrPhoneGetUserInfo($keyword)
    {
        $userModel = new User();

        $u_column = $userModel->u_column;

        $res = $userModel
            ->alias("u")
            ->join("user_card uc","uc.uid = u.uid","LEFT")
            ->join("mst_card_vip cv","cv.card_id = uc.card_id","LEFT")
            ->join("el_mst_card_type ct","ct.type_id = cv.card_type_id","LEFT")
            ->where("u.uid=$keyword OR u.phone=$keyword")
            ->field($u_column)
            ->field("cv.card_id,cv.card_name,ct.type_name")
            ->find();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 已经是会员,判断用户状态,返回值
     * @param $phone
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check_user_status($phone)
    {
        $userModel = new User();
        $column = $userModel->column;
        $user_info = $userModel
            ->where('phone',$phone)
            ->field($column)
            ->find();

        //用户信息状态
        $referrer_id = $user_info['referrer_id'];

        if (empty($referrer_id)){
            //如果推荐人为空,即为待写推荐人,提示跳转至填写推荐人页面
            return $this->com_return(true,config("user.user_info")['referrer']['name'],$user_info);
        }

        //用户注册状态
        $user_status = $user_info['user_status'];

        if ($user_status == config("user.user_register_status")['register']['key']){

            //仅注册
            return $this->com_return(true,config("user.user_register_status")['register']['name'],$user_info);

        }elseif ($user_status == config("user.user_register_status")['post_order']['key']){

            //提交订单
            $cardCommonObj = new CardCommon();
            $referrer_info = $cardCommonObj->getUserCardInfo($user_info['uid'],'0');
            return $this->com_return(true,config("user.user_register_status")['post_order']['name'],$referrer_info);

        }elseif ($user_status == config("user.user_register_status")['open_card']['key']){

            return $this->com_return(true,config("user.user_register_status")['open_card']['name'],$user_info);

        }
    }

    /**
     * 确认更改电话
     * @param $openid
     * @param $phone
     * @param $nickname
     * @param $avatar
     * @param $sex
     * @return array
     */
    public function confirmChangePhone($openid,$phone,$nickname,$avatar,$sex)
    {
        //变更手机号码 将此openid绑定的微信信息获取到,绑定到新phone的手机上
        $userModel = new User();

        Db::startTrans();
        try{
            //更新旧的wxid的数据为空
            $old_params = [
                "wxid"           => "",
                "wx_number"      => "",
                "nickname"       => "",
                "avatar"         => "",
                "sex"            => "",
                "token_lastime"  => time(),
                "remember_token" => jmToken(generateReadableUUID("QBJ").time()),
                "updated_at"     => time()
            ];

            $updateOldInfo = $userModel
                ->where('wxid',$openid)
                ->update($old_params);

            if ($updateOldInfo === false){
                return $this->com_return(false,config('params.FAIL'));
            }

            //更新新的phone为新的微信信息
            $new_params = [
                "wxid"           => $openid,
                "nickname"       => $nickname,
                "avatar"         => $avatar,
                "sex"            => $sex,
                "token_lastime"  => time(),
                "remember_token" => jmToken(generateReadableUUID("QBJTY").time()),
                "updated_at"     => time()
            ];

            $updateOldInfo = $userModel
                ->where('phone',$phone)
                ->update($new_params);

            if ($updateOldInfo === false){
                return $this->com_return(false,config('params.FAIL'));
            }

            Db::commit();
            return $this->check_user_status($phone);
        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 确认更改微信信息
     * @param $openid
     * @param $phone
     * @param $nickname
     * @param $avatar
     * @param $sex
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function confirmChangeWx($openid,$phone,$nickname,$avatar,$sex)
    {
        //将新的wxid的信息直接绑定到此手机号上
        $params = [
            "wxid"           => $openid,
            "nickname"       => $nickname,
            "avatar"         => $avatar,
            "sex"            => $sex,
            "token_lastime"  => time(),
            "remember_token" => jmToken(generateReadableUUID("QBJ").time()),
            "updated_at"     => time()
        ];
        $userModel = new User();
        $phone_bind_info = $userModel
            ->where('phone',$phone)
            ->update($params);
        if ($phone_bind_info === false){
            return $this->com_return(false,config('params.FAIL'));
        }

        return $this->check_user_status($phone);
    }

    /**
     * 根据uid获取用户的账户信息
     * @param $uid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function uidGetUserMoney($uid)
    {
        try {
            $userModel = new User();
            $userMoneyInfo = $userModel
                ->where('uid',$uid)
                ->field('account_balance,account_deposit,account_cash_gift,account_point')
                ->find();
            $userMoneyInfo = json_decode(json_encode($userMoneyInfo),true);
            if (!empty($userMoneyInfo)) {
                return $userMoneyInfo;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 电话号码获取用户账户信息
     * @param $phone
     * @return bool
     */
    public function phoneGetUserMoney($phone)
    {
        try {
            $userModel = new User();
            $salesUserInfo = $userModel
                ->where("phone",$phone)
                ->field("uid,account_balance,account_point,account_cash_gift")
                ->find();
            $salesUserInfo = json_decode(json_encode($salesUserInfo),true);
            if (!empty($salesUserInfo)) {
                return $salesUserInfo;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取推荐人信息
     * @param $uid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getSalesmanId($uid)
    {
        try {
            $userModel = new User();
            $res = $userModel
                ->where('uid',$uid)
                ->field('referrer_id,referrer_type')
                ->find();
            $res = json_decode($res,true);
            if (!empty($res)) {
                return $res;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取用户指定字段值
     * @param $uid
     * @param $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getUserFieldValue($uid,$field)
    {
        try {
            $userModel = new User();
            $res = $userModel
                ->where('uid',$uid)
                ->field($field)
                ->find();
            $res = json_decode(json_encode($res),true);
            if (!empty($res)){
                $val = $res[$field];
            }else{
                $val = null;
            }
            return $val;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 用户礼金明细
     * @param array $params
     * @return bool
     */
    public function updateUserAccountCashGift($params = array())
    {
        try {
            $userAccountCashGiftModel = new UserAccountCashGift();
            $is_ok = $userAccountCashGiftModel
                ->insert($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新用户礼金账户余额
     * @param $uid
     * @param $num
     * @param $type 'inc 增加; dec 减少'
     * @return bool
     */
    public function updatedAccountCashGift($uid,$num,$type)
    {
        try {
            $userModel = new User();
            if ($type == "inc"){
                //增加
                $res = $userModel->where('uid',$uid)
                    ->inc("account_cash_gift",$num)
                    ->update();
            }else if ($type == "dec"){
                //减少
                $res = $userModel->where('uid',$uid)
                    ->dec("account_cash_gift",$num)
                    ->update();
            }else{
                $res = false;
            }
            if ($res !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 写入新的兼职会员
     * @param $params
     * @return bool
     */
    public function insertJobUser($params)
    {
        try {
            $jobUserModel = new JobUser();
            $res =$jobUserModel
                ->insert($params);
            if ($res !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新兼职会员信息
     * @param $params
     * @return bool
     */
    public function updateJobUserInfo($params,$uid)
    {
        try {
            $jobUserModel = new JobUser();
            $res =$jobUserModel
                ->where("uid",$uid)
                ->update($params);
            if ($res !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取兼职用户信息
     * @param $uid
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getJobUserInfo($uid)
    {
        try {
            $jobUserModel = new JobUser();
            $res =$jobUserModel
                ->where("uid",$uid)
                ->find();
            $res = json_decode(json_encode($res),true);
            return $res;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取用户指定字段值
     * @param $uid
     * @param $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getJobUserFieldValue($uid,$field)
    {
        try {
            $jobUserModel = new JobUser();
            $res = $jobUserModel
                ->where('uid',$uid)
                ->field($field)
                ->find();
            $res = json_decode(json_encode($res),true);
            if (!empty($res)){
                return $res;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新或新增兼职推荐用户佣金表
     * @param $uid
     * @param $job_balance
     * @return bool
     */
    public function updateJobUser($uid,$job_balance)
    {
        try {
            $jobUserModel = new JobUser();
            $userInfo = $jobUserModel
                ->where('uid',$uid)
                ->count();
            $userInfo = json_decode(json_encode($userInfo),true);

            $time = time();
            $params['updated_at'] = $time;
            if ($userInfo){
                //如果存在,更新
                $is_ok = $jobUserModel
                    ->where('uid',$uid)
                    ->inc("job_balance","$job_balance")
                    ->inc("referrer_num")
                    ->exp("updated_at","$time")
                    ->update();
            }else{
                //如果不存在,新增
                $params = [
                    "uid"           => $uid,
                    "job_balance"   => $job_balance,
                    "referrer_num"  => 1,
                    "created_at"    => $time,
                    "updated_at"    => $time
                ];
                $is_ok = $jobUserModel
                    ->insert($params);
            }

            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 新增兼职推荐用户佣金明细表
     * @param array $params
     * @return bool
     */
    public function insertJobAccount($params = array())
    {
        try {
            $jobAccountModel = new JobAccount();

            $is_ok = $jobAccountModel
                ->insert($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新用户信息
     * @param array $params
     * @param string $uid
     * @return bool
     */
    public function updateUserInfo($params = array(),$uid)
    {
        try {
            $userModel = new User();
            $is_ok = $userModel
                ->where('uid',$uid)
                ->update($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 添加用户余额账户明细账
     * @param array $params
     * @return bool
     */
    public function updateUserAccount($params = array())
    {
        try {
            $userAccountModel = new UserAccount();
            $is_ok = $userAccountModel
                ->insert($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 添加用户押金明细账
     * @param array $params
     * @return bool
     */
    public function updateUserAccountDeposit($params = array())
    {
        try {
            $userAccountDepositModel = new UserAccountDeposit();
            $is_ok = $userAccountDepositModel
                ->insert($params);

            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 添加用户积分明细账
     * @param array $params
     * @return bool
     */
    public function updateUserAccountPoint($params = array())
    {
        try {
            $userAccountPointModel = new UserAccountPoint();
            $is_ok = $userAccountPointModel
                ->insert($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 开卡下发赠送的券
     * @param array $params
     * @return bool
     */
    public function updateUserGiftVoucher($params = array())
    {
        try {
            $userGiftVoucherModel = new UserGiftVoucher();
            $is_ok = $userGiftVoucherModel
                ->insert($params);
            if ($is_ok !== false){
                return true;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 用户变更手机号码
     * @param $remember_token
     * @param $phone
     * @return array
     */
    public function userChangePhone($remember_token,$phone)
    {
        try {
            $userModel = new User();
            //查询此用户是否存在
            $userExist = $userModel
                ->where('remember_token',$remember_token)
                ->count();
            if ($userExist != 1){
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            //查询此手机号码是否已绑定其他账户
            $phoneExist = $userModel
                ->where('phone',$phone)
                ->count();
            if ($phoneExist > 0){
                return $this->com_return(false,config("params.PHONE_BIND_OTHER"));
            }
            $update_data = [
                'phone'      => $phone,
                'updated_at' => time()
            ];
            $is_ok = $userModel
                ->where('remember_token',$remember_token)
                ->update($update_data);
            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 服务人员变更手机号码
     * @param $remember_token
     * @param $phone
     * @return array
     */
    public function serverChangePhone($remember_token,$phone)
    {
        try {
            $salesmanModel = new ManageSalesman();
            $isExist = $salesmanModel
                ->where('remember_token',$remember_token)
                ->count();
            if ($isExist != 1){
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            //查询此手机号码是否已绑定其他账户
            $phoneExist = $salesmanModel
                ->where('phone',$phone)
                ->count();
            if ($phoneExist > 0){
                return $this->com_return(false,config("params.PHONE_BIND_OTHER"));
            }
            $update_data = [
                'phone'      => $phone,
                'updated_at' => time()
            ];
            $is_ok = $salesmanModel
                ->where('remember_token',$remember_token)
                ->update($update_data);
            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else {
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * uid获取礼金账户明细
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function uidGetAccountCashGift($uid)
    {
        $cashGiftModel  = new UserAccountCashGift();
        $cash_gift = $cashGiftModel
            ->where('uid',$uid)
            ->order("created_at DESC")
            ->select();
        return $cash_gift;
    }

    /**
     * uid获取账户明细
     * @param $uid
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function uidGetAccount($uid)
    {
        $userAccountModel = new UserAccount();
        $account = $userAccountModel
            ->where('uid',$uid)
            ->order("created_at DESC")
            ->select();
        return $account;
    }

    /**
     * 根据uid获取用户开卡信息
     * @param $uid
     * @return array|false|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function uidGetCardInfo($uid)
    {
        $userCardModel = new UserCard();
        $card_info = $userCardModel
            ->where('uid',$uid)
            ->where("is_valid",1)
            ->find();
        $card_info = json_decode(json_encode($card_info),true);
        if (!empty($card_info)){
            return $card_info;
        }else{
            return null;
        }
    }

    /**
     * 消费返佣金礼金数据
     * @param $uid
     * @param $referrer_id            '推荐人id'
     * @param $referrer_type          '推荐人类型'
     * @param $consume_cash_gift      '消费持卡人返礼金'
     * @param $consume_commission     '消费持卡人返佣金'
     * @param $consume_job_cash_gift  '消费推荐人返礼金'
     * @param $consume_job_commission '消费推荐人返佣金'
     * @param $consumption_money      '消费总额'
     * @return array
     */
    public function consumptionReturnMoney($uid,$referrer_id,$referrer_type,$consume_cash_gift,$consume_commission,$consume_job_cash_gift,$consume_job_commission,$consumption_money)
    {
        if ($consumption_money < 0){
            //如果余额消费和现金消费金额为0 则不返还
            return NULL;
        }

        $cash_gift_return_money  = intval($consumption_money * ($consume_cash_gift/100));//持卡人返还礼金数
        $commission_return_money = intval($consumption_money * ($consume_commission/100));//持卡人返还佣金数

        /*$referrer_type_vip   = config("salesman.salesman_type")['0']['key'];//会籍
        $referrer_type_sales = config("salesman.salesman_type")['1']['key'];//销售
        $referrer_type = config("salesman.salesman_type")['1']['key'];//销售*/

        if ($referrer_type == config("salesman.salesman_type")['2']['key']){
            //如果是用户推荐
            $job_cash_gift_return_money  = intval($consumption_money * ($consume_job_cash_gift/100));//消费推荐人返礼金

            $job_commission_return_money = intval($consumption_money * ($consume_job_commission/100));//消费推荐人返佣金
        }else{
            $job_cash_gift_return_money  = 0;
            $job_commission_return_money = 0;
        }

        $params = [
            "cash_gift_return_money"      => $cash_gift_return_money,
            "commission_return_money"     => $commission_return_money,
            "job_cash_gift_return_money"  => $job_cash_gift_return_money,
            "job_commission_return_money" => $job_commission_return_money
        ];

        return $params;

    }

    /**
     * 用户手机号码检索
     * @param $phone
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userPhoneRetrieval($phone)
    {
        $userModel = new User();

        $where["u.phone"] = ["like","%$phone%"];

        $res = $userModel
            ->alias("u")
            ->join("user_card uc","uc.uid = u.uid","LEFT")
            ->join("mst_user_level ul","ul.level_id = u.level_id","LEFT")
            ->where($where)
            ->field("u.uid,u.name,u.nickname,u.phone,u.account_balance,u.account_cash_gift")
            ->field("uc.card_name,uc.card_type")
            ->field("ul.level_name,u.credit_point")
            ->select();

        $res = json_decode(json_encode($res),true);

        return $res;
    }

    /**
     * 员工手机号码检索
     * @param $phone
     * @return false|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function salesPhoneRetrieval($phone)
    {
        $salesModel = new ManageSalesman();
        $where = [];
        if (!empty($phone)){
            $where["phone"] = ["like","%$phone%"];
        }
        $res = $salesModel
            ->alias("ms")
            ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
            ->where($where)
            ->where("mst.stype_key","IN","vip,sales,boss")
            ->field("ms.sid,ms.sales_name,ms.phone")
            ->select();
        $res = json_decode(json_encode($res),true);
        return $res;
    }

}