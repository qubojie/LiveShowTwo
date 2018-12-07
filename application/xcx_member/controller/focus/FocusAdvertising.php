<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/20
 * Time: 下午3:51
 */
namespace app\xcx_member\controller\focus;



use app\admin\controller\table\WxQrcode;
use app\common\controller\BaseController;
use app\common\controller\CardCommon;
use app\common\model\User;
use app\xcx_member\controller\main\Auth;
use think\Db;
use think\Env;
use think\Exception;
use think\Request;
use think\Validate;

class FocusAdvertising extends BaseController
{
    /**
     * 获取分众卡信息
     * @return array
     */
    public function getCardInfo()
    {
        $card_id = Env::get("FENZHONG_CARD_ID");
        //获取卡信息
        $cardCommonObj = new CardCommon();
        $cardInfo = $cardCommonObj->cardIdGetCardInfo($card_id);
        if ($cardInfo === false){
            return $this->com_return(false,config('params.FAIL'));
        }
        return $this->com_return(true, config("params.SUCCESS"), $cardInfo);
    }

    /**
     * 检查用户
     * @param Request $request
     * @return array
     */
    public function toPay(Request $request)
    {
        $name       = $request->param("name", "");
        $phone      = $request->param("phone", "");
        $code       = $request->param("code", "");
        $wxid       = $request->param("wxid","");
        $nickname   = $request->param("nickname","");
        $avatar     = $request->param("avatar","");
        $sex        = $request->param("sex","");
        $rule = [
            "name|姓名"       => "require",
            "phone|电话号码"   => "require|regex:1[0-9]{1}[0-9]{9}",
            "code|验证码"     => "require",
            "wxid"           => "require",
            "nickname|昵称"   => "require",
            "avatar|头像"     => "require",
            "sex|性别"        => "require",
        ];
        $request_res = [
            "name"      => $name,
            "phone"     => $phone,
            "code"      => $code,
            "wxid"      => $wxid,
            "nickname"  => $nickname,
            "avatar"    => $avatar,
            "sex"       => $sex,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)) {
            return $this->com_return(false, $validate->getError());
        }

        Db::startTrans();
        try {
            /*验证验证码 On*/
            $authObj = new Auth();
            $is_pp   = $authObj->checkVerifyCode($phone,$code);
            if (!$is_pp['result']) return $is_pp;
            /*验证验证码 Off*/

            if ($sex == 1){
                $sex = "先生";
            }else{
                $sex = "女士";
            }
            /*判断用户是否已注册并返回用户信息 On*/
            $checkRes = $this->phoneCheckUser($phone, $name , $wxid , $nickname , $avatar , $sex);
            Db::commit();
            return $checkRes;
            /*判断用户是否已注册并返回用户信息 Off*/
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 手机号码检测用户是否注册
     * @param $phone
     * @param $name
     * @param $wxid
     * @param $nickname
     * @param $avatar
     * @param $sex
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function phoneCheckUser($phone, $name , $wxid , $nickname , $avatar , $sex)
    {
        $userModel = new User();
        $column = $userModel->column;
        $userInfo = $userModel
            ->where('phone', $phone)
            ->field($column)
            ->find();
        $userInfo = json_decode(json_encode($userInfo), true);
        if (empty($userInfo)) {
            //没有,则新建
            $uid     = $this->createdNewUser($phone, $name , $wxid , $nickname , $avatar , $sex);
            $message = "新用户";
        } else {
            //有
            $user_status = $userInfo['user_status'];
            $uid         = $userInfo['uid'];
            if ($user_status == config("user.user_register_status")['open_card']['key']) {
                //已开卡
                $message = "会员";
            } else {
                //未开卡
                $message = "未开卡";
            }
            $this->makeLogin($uid);
        }
        $userNewInfo = $userModel
            ->where('phone', $phone)
            ->field($column)
            ->find();
        $userNewInfo = json_decode(json_encode($userNewInfo), true);
        return $this->com_return(true,$message,$userNewInfo);
    }

    /**
     * 新建用户
     * @param $phone
     * @param $name
     * @param $wxid
     * @param $nickname
     * @param $avatar
     * @param $sex
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function createdNewUser($phone, $name , $wxid , $nickname , $avatar , $sex)
    {
        $uid = generateReadableUUID("U");
        $user_params = [
            "uid"               => $uid,
            "phone"             => $phone,
            "wxid"              => $wxid,
            "name"              => $name,
            "nickname"          => $nickname,
            "avatar"            => $avatar,
            "sex"               => $sex,
            "avatar"            => getSysSetting("sys_default_avatar"),
            "password"          => jmPassword(config("DEFAULT_PASSWORD")),
            "register_way"      => config("user.register_way")['fz']['key'],
            "lastlogin_time"    => time(),
            "user_status"       => config("user.user_register_status")['register']['key'],
            "info_status"       => config("user.user_info")['interest']['key'],
            "referrer_type"     => config('salesman.salesman_type')['3']['name'],
            "referrer_id"       => config('salesman.salesman_type')['3']['key'],
            "token_lastime"     => time(),
            "remember_token"    => jmToken($wxid.time()),
            "created_at"        => time(),
            "updated_at"        => time()
        ];

        $userModel = new User();
        //插入新的用户信息
        $userModel->insert($user_params);

        return $uid;
    }

    /**
     * 刷新token
     * @param $uid
     * @return bool
     */
    protected function makeLogin($uid)
    {
        $params = [
            "token_lastime"     => time(),
            "remember_token"    => jmToken($uid.time()),
            "updated_at"        => time()
        ];
        $userModel = new User();
        $res = $userModel
            ->where("uid",$uid)
            ->update($params);
        if ($res !== false){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取二维码
     * @return array
     */
    public function getSmallAppQrCode()
    {
        $src = $this->created();
        $zipName = __DIR__."/../../../public/WXQRCODE/fz001.zip";

        $zip     = new \ZipArchive();//使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释

        if ($zip->open($zipName, \ZIPARCHIVE::OVERWRITE || \ZIPARCHIVE::CREATE) !== true){
            return $this->com_return(false,"无法打开文件，或者文件创建失败");
        }

        $vals = __DIR__."/../../../public".$src;

        if (file_exists($vals)){
            $zip->addFile($vals);
        }

        $zip->close();

        if(!file_exists($zipName)){
            return $this->com_return(false,"无法找到文件");//即使创建，仍有可能失败
        }


        //如果不要下载，下面这段删掉即可，如需返回压缩包下载链接，只需 return $zipName;
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename='.basename($zipName)); //文件名
        header("Content-Type: application/zip"); //zip格式的
        header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
        header('Content-Length: '. filesize($zipName)); //告诉浏览器，文件大小
        @readfile($zipName);
    }

    public function created()
    {
        $postParams = [
            "scene"      => "fz",
//            "scene"      => "pid=P18110212185299CB",
            "page"       => "pages/card_2000/main",
//            "page"       => "pages/index/main",
            "width"      => "1280",
            "auto_color" => false,
            "is_hyaline" => config("qrcode.is_hyaline")['key']
        ];

        $postParams = json_encode($postParams);

        $wxQrcodeObj = new WxQrcode();

        $ACCESS_TOKEN = $wxQrcodeObj->getAccessToken();

        $res = $wxQrcodeObj->requestPost($ACCESS_TOKEN,$postParams);

        //  设置文件路径和文件前缀名称
        $path = __DIR__."/../../../public/WXQRCODE/";

        is_dir($path) OR @mkdir($path,0777,true);

        file_put_contents($path.'fz001'.'.png',$res);

        $src = "/WXQRCODE/".'fz001'.'.png';

        return $src;
    }
}