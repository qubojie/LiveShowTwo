<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午4:49
 */

namespace app\admin\controller\member;

use app\common\controller\AdminAuthAction;
use app\common\controller\AdminUserCommon;
use app\common\controller\UserCommon;
use app\common\model\JobUser;
use app\common\model\MstCardVip;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\model\UserAccountCashGift;
use app\common\model\UserAccountDeposit;
use app\common\model\UserAccountPoint;
use think\Exception;
use think\Validate;

class UserInfo extends AdminAuthAction
{
    /**
     * 会员状态类
     * @return array
     */
    public function userStatus()
    {
        $type = config('user.user_status');

        foreach ($type as $key => $val){
            if ($key == 1){
                unset($type[$key]);
            }
        }
        $type = array_values($type);
        rsort($type);

        return $this->com_return(true,config('params.SUCCESS'),$type);
    }

    /**
     * 获取卡种列表
     * @return array
     */
    public function cardType()
    {
        try {
            $cardModel = new MstCardVip();
            $card_list = $cardModel
                ->where('is_enable','1')
                ->where('is_delete','0')
                ->field('card_id,card_name')
                ->select();
            return $this->com_return(true,config('SUCCESS'),$card_list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员列表
     * @return array
     */
    public function index()
    {
        $pagesize    = $this->request->param("pagesize",config('PAGESIZE'));//显示个数,不传时为10
        $orderBy     = $this->request->param("orderBy","");//根据什么排序
        $sort        = $this->request->param("sort","asc");//正序or倒叙
        $keyword     = $this->request->param("keyword","");//搜索关键字
        $user_status = $this->request->param("user_status","");//会员状态 0为已注册 1为提交订单,2开卡成功
        $card_name   = $this->request->param('card_name',"");//卡种

        $nowPage     = $this->request->param("nowPage","1");

        if ($user_status == 2){
            $user_status_where['user_status'] = ["eq",2];
        }else{
            $user_status_where['user_status'] = ["neq",2];
        }

        $card_name_where = [];
        if (!empty($card_name)){
            $card_name_where['cfd.card_name'] = ["eq",$card_name];
        }

        if (empty($pagesize)){
            $pagesize = config('PAGESIZE');
        }

        if (empty($orderBy)){
            $orderBy = "created_at";
        }

        if ($orderBy == "card_type_name"){
            $orderBy = "ct.type_name";
        }else if ($orderBy == "register_way_name"){
            $orderBy = "u.register_way";
        }else{
            $orderBy = "u.".$orderBy;
        }

        if (empty($sort)){
            $sort = "asc";
        }

        if (!empty($keyword)){
            $where['u.uid|u.phone|u.email|u.nickname|u.sex|u.province|u.city|u.country|uc.card_name'] = ["like","%$keyword%"];
        }else{
            $where = [];
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $userModel = new User();
            $u_column = $userModel->u_column;
            $userCount = [];

            /*获取各种卡开卡数量 On*/
            $adminUserCommonObj = new AdminUserCommon();
            $openCardCount = $adminUserCommonObj->getOpenCardCount();
            /*获取各种卡开卡数量 Off*/


            $openCardNum    =  $userModel->where('user_status','2')->count();
            $notOpenCardNum = $userModel->where('user_status','neq 2')->count();

            $userCount['openCardNum']       = $openCardNum;
            $userCount['notOpenCardNum']    = $notOpenCardNum;
            $userCount['openCardTypeCount'] = $openCardCount;

            $user_list = $userModel
                ->alias('u')
                ->join('user_card uc','uc.uid = u.uid','LEFT')
                ->join('mst_card_vip cv','cv.card_id = uc.card_id','LEFT')
                ->join('mst_card_type ct','ct.type_id = cv.card_type_id','LEFT')
                ->where($where)
                ->where($card_name_where)
                ->where($user_status_where)
                ->field($u_column)
                ->field('cv.card_name,cv.card_type_id,uc.created_at open_card_time')
                ->field('ct.type_name card_type_name')
                ->order($orderBy,$sort)
                ->paginate($pagesize,false,$config);

            $user_list = json_decode(json_encode($user_list),true);

            $user_list['filter']['orderBy'] = $orderBy;
            $user_list['filter']['sort'] = $sort;
            $user_list['filter']['keyword'] = $keyword;

            $data = $user_list['data'];

            //获取注册途径配置文件
            $register_way_arr = config("user.register_way");

            //会员状态配置文件
            $user_status_arr = config("user.user_status");

            //获取卡类型
            $card_type_arr = config("card.type");

            for ($i=0;$i<count($data);$i++){
                $referrer_type = $data[$i]['referrer_type'];
                $referrer_id   = $data[$i]['referrer_id'];
                $avatar        = $data[$i]['avatar'];
                $uid           = $data[$i]['uid'];

                /*默认头像填充 begin*/
                if (empty($avatar)){
                    $data[$i]['avatar'] = getSysSetting("sys_default_avatar");
                }
                /*默认头像填充 off*/

                /*注册途径 begin*/
                $register_way_s = $data[$i]['register_way'];

                foreach ($register_way_arr as $key => $value){
                    if ($register_way_s == $value["key"]){
                        $data[$i]['register_way_name'] = $value["name"];
                    }
                }
                /*注册途径 off*/

                /*会员状态翻译 begin*/
                $user_status_s = $data[$i]['user_status'];
                foreach ($user_status_arr as $key => $value){
                    if ($user_status_s == $value["key"]){
                        $data[$i]['user_status_name'] = $value["name"];
                    }
                }
                /*会员状态翻译 off*/

                $userCommonObj = new UserCommon();
                if ($referrer_type == 'user'){
                    //去用户表查找推荐人信息
                    $user_info = $userCommonObj->getUserInfo($referrer_id);

                    $data[$i]['referrer_name'] = $user_info['nickname'];
                    $data[$i]['referrer_type'] = "用户";

                }elseif ($referrer_type == 'sales' || $referrer_type == 'vip'){
                    //去销售表查找推荐人信息
                    $sales_info = $userCommonObj->getSalesManInfo($referrer_id);
                    $sales_info = json_decode($sales_info,true);
                    $data[$i]['referrer_name'] = $sales_info['sales_name'];

                    if ($referrer_type == 'sales'){
                        $data[$i]['referrer_type'] = config('salesman.salesman_type')[1]['name'];
                    }else{
                        $data[$i]['referrer_type'] = config('salesman.salesman_type')[0]['name'];
                    }

                }else{
                    $data[$i]['referrer_name'] = "";
                    $data[$i]['referrer_type']= "";
                }

                /*获取用户是否有佣金账户余额 On*/
                $jobUserModel = new JobUser();

                $jobInfo = $jobUserModel
                    ->where('uid',$uid)
                    ->field('job_balance,job_freeze,job_cash,consume_amount,referrer_num')
                    ->find();
                $jobInfo = json_decode(json_encode($jobInfo),true);

                $data[$i]['job_user'] = $jobInfo;
                /*获取用户是否有佣金账户余额 Off*/

                //获取用户日志文件
                $res = $userCommonObj->getUserLog("$uid");
                $data[$i]['log_info'] = $res;
            }
            $user_list['userCount'] = $userCount;

            $user_list['data'] = $data;

            return $this->com_return(true,config("params.SUCCESS"),$user_list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 编辑会员
     * @return array
     */
    public function edit()
    {
        $uid = $this->request->param('uid','');

        $rule = [
            "uid|用户" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $userModel = new User();

            $params = $this->request->param();

            $params['updated_at'] = time();

            $is_ok = $userModel
                ->where('uid',$uid)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(false,config('EDIT_SUCCESS'));
            }else{
                return $this->com_return(false,config('EDIT_FAIL'));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 禁止登陆或解禁
     * @return array
     */
    public function noLogin()
    {
        $uid    = $this->request->param('uid','');
        $status = $this->request->param('status','0');

        $rule = [
            "uid|用户" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        if ($status == 0){
            $log_info = 'unban';
            $reason = "解禁用户";
        }else{
            $log_info = 'ban';
            $reason = "禁止此用户登陆";
        }

        $params = [
            'status' => $status
        ];

        $authorization = $this->request->header('Authorization');

        try {
            //获取当前登录管理员id
            $action_user_res = self::tokenGetAdminLoginInfo($authorization);

            $action_user = $action_user_res['user_name'];

            $userModel = new User();

            $res = $userModel
                ->where('uid',$uid)
                ->update($params);

            if ($res !== false){

                //操作日志记录禁止登陆和解禁操作 time(),$user_id,$log_info,$request->ip()
                $this->addSysAdminLog("$uid","","","$log_info","$reason","$action_user",time());

                return $this->com_return(true,config('SUCCESS'));
            }else{
                return $this->com_return(false,config('FAIL'));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 后台操作变更会员密码
     * @return array
     */
    public function changePass()
    {
        $uid                   = $this->request->param('uid','');
        $password              = $this->request->param('password','');
        $password_confirmation = $this->request->param('password_confirmation','');

        $rule = [
            "uid"                            => "require",
            "password|密码"                  => "require|alphaNum|length:6,25",
            "password_confirmation|确认密码"  => "require|confirm:password",
        ];

        $request_res = [
            "uid"                   => $uid,
            "password"              => $password,
            "password_confirmation" => $password_confirmation,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $params = [
            'token_lastime'  => time(),
            'remember_token' => jmToken($password.time()),
            'password'       => jmPassword($password),
            'updated_at'     => time()
        ];

        try {
            $userModel = new User();

            $is_ok = $userModel
                ->where('uid',$uid)
                ->update($params);
            if ($is_ok){
                //获取当前登录管理员
                $authorization = $this->request->header('Authorization');
                $action_user_res = self::tokenGetAdminLoginInfo($authorization);

                $action_user = $action_user_res['user_name'];

                //日志
                $this->addSysLog(time(),"$action_user",config("useraction.change_user_pass")['name']." -> $uid",$this->request->ip());

                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员钱包明细
     * @return array
     */
    public function accountInfo()
    {
        $uid      = $this->request->param('uid','');
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $rule = [
            "uid" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $config = [
            "page" => $nowPage,
        ];

        try {

            $userAccountModel = new UserAccount();

            $res = $userAccountModel
                ->where('uid',$uid)
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config('params.SUCCESS'),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员押金明细
     * @return array
     */
    public function depositInfo()
    {
        $uid      = $this->request->param('uid','');
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $rule = [
            "uid" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $config = [
            "page" => $nowPage,
        ];
        try {
            $userAccountDepositModel = new UserAccountDeposit();

            $res = $userAccountDepositModel
                ->where('uid',$uid)
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员礼金明细
     * @return array
     */
    public function cashGiftInfo()
    {
        $uid      = $this->request->param('uid','');
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $rule = [
            "uid" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $config = [
            "page" => $nowPage,
        ];

        try {

            $CashGiftModel = new UserAccountCashGift();

            $res = $CashGiftModel
                ->where('uid',$uid)
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config('params.SUCCESS'),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 会员积分明细
     * @return array
     */
    public function accountPointInfo()
    {
        $uid      = $this->request->param('uid','');
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $rule = [
            "uid" => "require",
        ];

        $request_res = [
            "uid" => $uid,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $accountPointModel = new UserAccountPoint();

            $list = $accountPointModel
                ->where('uid',$uid)
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config('params.SUCCESS'),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }
}