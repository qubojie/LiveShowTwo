<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午6:16
 */

namespace app\xcx_member\controller\main;


use app\common\controller\BaseController;
use app\common\controller\CardCommon;
use app\common\controller\UserCommon;
use app\common\model\ManageSalesman;
use app\common\model\MstCardType;
use app\common\model\MstCardVip;
use app\common\model\User;
use app\services\Sms;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Auth extends BaseController
{
    /**
     * 发送验证码
     * @param Request $request
     * @return array
     */
    public function sendVerifyCode(Request $request)
    {
        $phone = $request->param("phone");
        $scene = $request->param("scene");//managePointList 发送验证码场景
        $sms   = new Sms();
        $res   = $sms ->sendVerifyCode($phone,$scene);
        return $res;
    }

    /**
     * 验证验证码
     * @param $phone
     * @param $code
     * @return array
     */
    public function checkVerifyCode($phone,$code)
    {
        $sms = new Sms();
        $res = $sms->checkVerifyCode($phone,$code);
        return $res;
    }

    /**
     * 手机号码+验证码绑定(授权信息绑定+注册)
     * @param Request $request
     * @return array
     */
    public function phoneRegister(Request $request)
    {
        $phone        = $request->param("phone","");
        $code         = $request->param("code","");
        $register_way = $request->param("register_way","");
        $openid       = $request->param('openid',"");     //openid
        $nickname     = $request->param('nickname',"");   //昵称
        $headimgurl   = $request->param('headimgurl',""); //头像
        $sex          = $request->param('sex',"1");        //性别
        $province     = $request->param('province',"");   //省份
        $city         = $request->param('city',"");       //城市
        $country      = $request->param('country',"");    //国家
        $rule = [
            "openid"               => "require",
            "register_way|注册途径" => "require",
        ];
        $check_data = [
            "openid"       => $openid,
            "register_way" => $register_way,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        if ($sex == 1){
            $sex = "先生";
        }else{
            $sex = "女士";
        }

        try {
            if (empty($headimgurl)) $headimgurl = getSysSetting("sys_default_avatar");
            if ($code != "989898" || $register_way != "h5"){
                $is_pp = $this->checkVerifyCode($phone,$code);
                //验证验证码
                if (!$is_pp['result']) return $is_pp;
            }

            $manageSalesmanModel = new ManageSalesman();
            //查看当前用户是否是内部人员
            $is_salesman = $manageSalesmanModel
                ->where('phone',$phone)
                ->count();
            if ($is_salesman){
                //是内部人员
                $return_msg = "内部人员";
            }else{
                //不是内部人员
                $return_msg = config("ADD_SUCCESS");
            }

            $userModel = new User();
            //查询当前手机号码绑定用户信息
            $phone_bind_info = $userModel
                ->where('phone',$phone)
                ->find();
            $phone_bind_info = json_decode(json_encode($phone_bind_info),true);

            //2.查询此wxid绑定用户信息
            $wxid_bind_info = $userModel
                ->where('wxid',$openid)
                ->find();
            $wxid_bind_info = json_decode(json_encode($wxid_bind_info),true);

            if (empty($phone_bind_info) && empty($wxid_bind_info)){
                //直接将信息绑定,创建新的用户信息
                //未注册用户
                $uid            = generateReadableUUID("U");
                $remember_token = jmToken($uid.time());

                //不存在,则写入
                $insert_data = [
                    'uid'            => $uid,
                    'phone'          => $phone,
                    'name'           => config("default_name"),
                    'password'       => sha1(config("DEFAULT_PASSWORD")),
                    'register_way'   => $register_way,
                    'wxid'           => $openid,
                    'nickname'       => $nickname,
                    'avatar'         => $headimgurl,
                    'sex'            => $sex,
                    'province'       => $province,
                    'city'           => $city,
                    'country'        => $country,
                    'user_status'    => config("user.user_status")['0']['key'],
                    'info_status'    => config("user.user_info")['referrer']['key'],
                    'lastlogin_time' => time(),
                    'token_lastime'  => time(),
                    'remember_token' => $remember_token,
                    'created_at'     => time(),
                    'updated_at'     => time()
                ];

                $insertUserRes = $userModel
                    ->insert($insert_data);
                if ($insertUserRes) {
                    $user_info = getUserInfo("$uid");
                    return $this->com_return(true, $return_msg,$user_info);
                }else{
                    return $this->com_return(false, config("params.FAIL"));
                }

            }else{
                if (!empty($phone_bind_info)){
                    $bind_wxid      = $phone_bind_info['wxid'];
                    $bind_nickname  = $phone_bind_info['nickname'];

                    $userCommon = new UserCommon();

                    if (!empty($wxid_bind_info)){
                        //wxid不为空
                        if ($bind_wxid == $openid) {
                            //如果一致,则更新数据
                            $update_params = [
                                'nickname'       => $nickname,
                                'avatar'         => $headimgurl,
                                'sex'            => $sex,
                                'province'       => $province,
                                'city'           => $city,
                                'country'        => $country,
                                'lastlogin_time' => time(),
                                'remember_token' => jmToken(generateReadableUUID("QBJ").time()),
                                'token_lastime'  => time(),
                                'updated_at'     => time()
                            ];

                            $userModel->where('phone',$phone)->update($update_params);


                            return $userCommon->check_user_status($phone);

                        }else{
                            $wxid_bind_phone = $wxid_bind_info['phone'];

                            $wxid_bind_phone = jmTel($wxid_bind_phone);

                            $message = config('params.USER')['WXID_USED'];
                            $message = str_replace("%key%",$wxid_bind_phone,$message);
                            return $this->com_return(true,$message,true);
                        }

                    }else{
                        //wxid绑定信息为空
                        if (empty($bind_wxid)) {
                            //直接绑定微信和手机号码
                            //如果未绑定过微信 则直接更新绑定信息
                            $update_params = [
                                'wxid'           => $openid,
                                'nickname'       => $nickname,
                                'avatar'         => $headimgurl,
                                'sex'            => $sex,
                                'province'       => $province,
                                'city'           => $city,
                                'country'        => $country,
                                'lastlogin_time' => time(),
                                'remember_token' => jmToken(generateReadableUUID("QBJ").time()),
                                'token_lastime'  => time(),
                                'updated_at'     => time()
                            ];

                            $userModel->where('phone',$phone)->update($update_params);

                            return $userCommon->check_user_status($phone);

                        }else{
                            //如果不为空,则表示wxid不一致
                            $message = config('params.USER')['PHONE_USED'];
                            $message = str_replace("%key%",$bind_nickname,$message);
                            return $this->com_return(true,$message,false);
                        }
                    }

                } else if (!empty($wxid_bind_info)){
                    $wxid_bind_phone = $wxid_bind_info['phone'];
                    $wxid_bind_phone = jmTel($wxid_bind_phone);
                    //wx信息不为空,但是手机号信息为空
                    $message = config('params.USER')['WXID_USED'];
                    $message = str_replace("%key%",$wxid_bind_phone,$message);
                    return $this->com_return(true,$message,true);
                }
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认更改信息
     */
    public function confirmChangeInfo()
    {
        $type      = $this->request->param('type','');
        $openid    = $this->request->param('openid','');
        $phone     = $this->request->param('phone','');
        $nickname  = $this->request->param('nickname','');
        $avatar    = $this->request->param('avatar','');
        $sex       = $this->request->param('sex','');

        try {
            $userCommonObj = new UserCommon();
            if ($type == 1){
                //更改电话
                $res = $userCommonObj->confirmChangePhone($openid,$phone,$nickname,$avatar,$sex);
                return $res;
            }elseif ($type == 2){
                //更改微信
                $res = $userCommonObj->confirmChangeWx($openid,$phone,$nickname,$avatar,$sex);
                return $res;
            }else{
                return $this->com_return(false,config('params.PARAM_NOT_EMPTY'));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 推荐人信息录入
     * @param Request $request
     * @return array
     */
    public function referrerUser(Request $request)
    {
        $phone          = $request->param('phone',"");
        $referrer_phone = $request->param("referrer_phone","");

        $rule = [
            "phone|推荐人" => "require",
        ];
        $check_data = [
            "phone" => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        try {
            $userModel = new User();
            //排除推荐人是8888的情况
            if ($referrer_phone != '8888'){

                $manageSalesmanModel = new ManageSalesman();
                //根据电话号码获取平台推荐人sid
                $sid_res = $manageSalesmanModel
                    ->alias('ms')
                    ->join('mst_salesman_type mst','mst.stype_id = ms.stype_id')
                    ->where('ms.phone',$referrer_phone)
                    ->where('ms.statue',config("salesman.salesman_status")['working']['key'])
                    ->field('ms.sid,mst.stype_key')
                    ->find();

                if(!empty($sid_res)){
                    if ($sid_res['stype_key'] == 'service' || $sid_res == 'reserve'){
                        return $this->com_return(false,"请输入正确的营销手机号码");
                    }

                    //销售推荐时,推荐人可以是自己
                    $referrer_id = $sid_res['sid'];
                    $referrer_type = $sid_res['stype_key'];//vip sales

                }else{
                    //如果是用户推荐,则推荐人不能是自己
                    if ($phone == $referrer_phone){
                        return $this->com_return(false,'推荐人不能是自己');
                    }

                    //获取推荐用户的uid
                    $uid_res = $userModel
                        ->where('phone',$referrer_phone)
                        ->field('uid')
                        ->find();
                    if (!empty($uid_res)){
                        $referrer_id = $uid_res['uid'];
                        $referrer_type = 'user';//用户推荐
                    }else{
                        return $this->com_return(false,'请输入正确的推荐人号码');
                    }
                }
            }else{
                //平台默认推荐人信息
                $referrer_id    = config("user.platform_recommend")['referrer_id']['name'];
                $referrer_type  = config("user.platform_recommend")['referrer_type']['name'];
            }

            $params = [
                'referrer_type' => $referrer_type,
                'referrer_id'   => $referrer_id,
                'info_status'   => config("user.user_info")['interest']['key']//更改用户状态为 待填写兴趣标签
            ];

            $res = $userModel
                ->where('phone',$phone)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 手机号+验证码登陆
     * @param Request $request
     * @return array
     */
    public function phoneVerifyLogin(Request $request)
    {
        $phone = $request->param("phone","");
        $code  = $request->param("code","");

        $rule = [
            "phone|电话号码" => "require",
            "code|验证码"  => "require",
        ];
        $check_data = [
            "phone" => $phone,
            "code"  => $code,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $is_pp = $this->checkVerifyCode($phone,$code);

            if (!$is_pp['result']) return $is_pp;

            //查询当前手机号码是否已经绑定用户
            $userModel = new User();

            $column = $userModel->column;

            $user_info = $userModel
                ->where('phone',$phone)
                ->field($column)
                ->find();

            if (!empty($user_info)){
                return $this->com_return(true,config("params.SUCCESS"),$user_info);
            }else{
                return $this->com_return(false,config("params.SUCCESS"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取卡种列表
     * @return array
     */
    public function getCardType()
    {
        try {
            $cardTypeModel = new MstCardType();
            $res = $cardTypeModel
                ->where("is_delete",0)
                ->where("is_enable",1)
                ->order("updated_at DESC")
                ->field("type_id,type_name")
                ->select();
            return $this->com_return(true,config('params.SUCCESS'),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Vip卡列表
     * @param Request $request
     * @return array
     */
    public function cardList(Request $request)
    {
        $uid          = $request->param('uid',"");
        $card_type_id = $request->param('card_type_id',"");
        $rule = [
            "uid" => "require",
        ];
        $check_data = [
            "uid" => $uid,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $card_type_where = [];
            if (!empty($card_type_id)) {
                $card_type_where['cv.card_type_id'] = ['eq',$card_type_id];
            }
            $userModel    = new User();
            $user_info = $userModel
                ->where('uid',$uid)
                ->field('referrer_id,referrer_type')
                ->find();
            $user_info     = json_decode(json_encode($user_info),true);
            $referrer_type = $user_info['referrer_type'];

            $where['cv.salesman'] = ["like","%$referrer_type%"];

            $cardVipModel = new MstCardVip();

            $card_list = $cardVipModel
                ->alias("cv")
                ->join("mst_card_type ct","ct.type_id = cv.card_type_id")
                ->where($where)
                ->where($card_type_where)
                ->where('cv.is_delete','0')
                ->where('cv.is_enable','1')
                ->select();
            $card_list = json_decode(json_encode($card_list),true);

            return $this->com_return(true,config("params.SUCCESS"),$card_list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 变更手机号码
     * @param Request $request
     * @return array
     */
    public function changePhone(Request $request)
    {
        $phone = $request->param('phone','');
        $code  = $request->param("code","");
        $type  = $request->param("type","");

        if (empty($phone) || empty($code) || empty($type)) return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));

        $remember_token =  $request->header('Token','');

        $authObj = new Auth();
        $is_pp = $authObj->checkVerifyCode($phone,$code);

        //验证验证码
        if (!$is_pp['result']) return $is_pp;

        $userCommonObj = new UserCommon();
        if ($type == "user"){
            $is_ok = $userCommonObj->userChangePhone($remember_token,$phone);
        }else{
            $is_ok = $userCommonObj->serverChangePhone($remember_token,$phone);
        }
        return $is_ok;
    }

}