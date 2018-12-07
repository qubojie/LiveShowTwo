<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/14
 * Time: 下午5:34
 */
namespace app\common\controller;

use app\common\model\MstTableReserveDate;
use app\common\model\SysAdminUser;
use app\services\YlyPrint;
use think\Controller;
use think\Db;
use think\Env;
use think\Exception;

class BaseController extends Controller
{
    /*
    * 公共返回
    * */
    public function com_return($result = false,$message,$data = null,$code = 200)
    {
        if ($code == 500) $code = 520500;
        if ($code == 403) $code = 520403;
        if ($code == 200) $code = 520200;
        return [
            "result"  => $result,
            "message" => $message,
            "data"    => $data,
            "code"    => $code
        ];
    }

    /**
     * 记录禁止登陆解禁登陆 单据操作等日志
     * @param string $uid           '被操作用户id'
     * @param string $gid           '被操作的的商品id'
     * @param string $oid           '相关单据id'
     * @param string $action        '操作内容'
     * @param string $reason        '操作原因描述'
     * @param string $action_user   '操作管理员id'
     * @param string $action_time   '操作时间'
     */
    public function addSysAdminLog($uid = '',$gid = '',$oid = '',$action = 'empty',$reason = '',$action_user = '',$action_time = '')
    {
        $params  = [
            'uid'         => $uid,
            'gid'         => $gid,
            'oid'         => $oid,
            'action'      => $action,
            'reason'      => $reason,
            'action_user' => $action_user,
            'action_time' => $action_time,
        ];

        Db::name('sys_adminaction_log')
            ->insert($params);
    }

    /**
     * 记录系统操作日志
     * @param $log_time     '记录时间'
     * @param $action_user  '操作管理员名'
     * @param $log_info     '操作描述'
     * @param $ip_address   '操作登录的地址'
     * @return void
     */
    public function addSysLog($log_time,$action_user,$log_info,$ip_address)
    {
        if (empty($log_time)){
            $log_time = time();
        }

        if (empty($log_info)){
            $log_info =  '未记录到';
        }

        if (empty($action_user)){
            $action_user =  0;
        }

        if (empty($ip_address)){
            $ip_address =  '0.0.0.0';
        }

        $params = [
            'log_time'   => $log_time,
            'action_user'=> $action_user,
            'log_info'   => $log_info,
            'ip_address' => $ip_address
        ];

        $res = Db::name("sys_log")
            ->insert($params);
    }

    /**
     * 获取系统设置相关key值,返回value
     * @param $key
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSysSettingInfo($key)
    {
        $res = Db::name("sys_setting")
            ->where('key',$key)
            ->field('value')
            ->find();

        $res = json_decode(json_encode($res),true);

        $value = $res['value'];
        return $value;
    }

    /**
     * 获取openid,根据code
     * @param $code
     * @return mixed
     */
    public function getOpenId($code)
    {
        $Appid  = Env::get("WECHAT_XCX_APPID");
        $Secret = Env::get("WECHAT_XCX_APPSECRET");
        $url    = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$Appid.'&secret='.$Secret.'&js_code=' . $code . '&grant_type=authorization_code';
        $info   = $this->vget($url);
        $info   = json_decode($info,true);//对json数据解码
        return $info;
    }

    public function vget($url)
    {
        $info=curl_init();
        curl_setopt($info,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($info,CURLOPT_HEADER,0);
        curl_setopt($info,CURLOPT_NOBODY,0);
        curl_setopt($info,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($info,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($info,CURLOPT_URL,$url);
        $output= curl_exec($info);
        curl_close($info);
        return $output;
    }


    /**
     * 获取登陆管理人员信息
     * @param $token
     * @return array
     */
    public function getLoginAdminId($token)
    {
        try {
            $id_res = SysAdminUser::get(['token' => $token],false)->toArray();
            return $id_res;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 根据uid获取用户所办卡 消费,充值,办卡时返还的钱
     * @param $uid
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function uidGetCardReturnMoney($uid)
    {
        try {
            $column = [
                "cv.card_point",            //开卡赠送积分
                "cv.card_cash_gift",        //开卡赠送礼金
                "cv.card_job_cash_gif",     //开卡推荐人返礼金
                "cv.card_job_commission",   //开卡推荐人返佣金
                "cv.refill_job_cash_gift",  //充值推荐人返礼金
                "cv.refill_job_commission", //充值推荐人返佣金
                "cv.consume_cash_gift",     //消费持卡人返礼金
                "cv.consume_commission",    //消费持卡人返佣金
                "cv.consume_job_cash_gift", //消费推荐人返礼金
                "cv.consume_job_commission",//消费推荐人返佣金
            ];

            $info = Db::name("user")
                ->alias("u")
                ->join("user_card uc","uc.uid = u.uid","LEFT")
                ->join("mst_card_vip cv","cv.card_id = uc.card_id")
                ->where("u.uid",$uid)
                ->field($column)
                ->find();

            $info = json_decode(json_encode($info),true);
            if (!empty($info)){
                return $info;
            }else{
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取系统自然日
     * @param $dateTime '否则是时间区间'
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSysTimeLong($dateTime)
    {
        $nowTime = time();
        $sys_account_day_time = getSysSetting("sys_account_day_time");//获取系统设置自然日
        $now_h                = date("H",$nowTime);

        if ($now_h >= $sys_account_day_time){
            //大于,新的一天
            $nowDateTime = strtotime(date("Ymd",$nowTime));
        }else{
            //小于,还是昨天
            $nowDateTime = strtotime(date("Ymd",$nowTime - 24 * 60 * 60));
        }

        $six_s       = 60 * 60 * $sys_account_day_time;
        $nowDateTime = $nowDateTime + $six_s;

        if ($dateTime == 1){
            //今天
            $beginTime = date("YmdHis",$nowDateTime);
            $endTime   = date("YmdHis",$nowDateTime + 24 * 60 * 60 - 1);

        }elseif ($dateTime == 2){
            //昨天
            $beginTime = date("YmdHis",$nowDateTime - 24 * 60 * 60);
            $endTime   = date("YmdHis",$nowDateTime - 1);

        }else{
            $dateTimeArr = explode(",",$dateTime);
            $beginTime   = $dateTimeArr[0] + $six_s;
            $beginTime   = date("YmdHis",$beginTime);
            $endTime     = $dateTimeArr[1] + $six_s;
            $endTime     = date("YmdHis",$endTime);
        }
        $res = [
            "beginTime" => $beginTime,
            "endTime"   => $endTime
        ];
        return $res;
    }

    /**
     * 调用打印机打印订单(消费)
     * @param $pid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function openTableToPrintYly($pid)
    {
        /*调用打印机 处理落单 on*/
        $YlyPrintObj = new YlyPrint();

        $printToken  = $YlyPrintObj->getToken();

        $message     = $printToken['message'];

        if ($printToken['result'] == false){
            //获取token失败
            return $this->com_return(false,$message);
        }

        $data          = $printToken['data'];

        $access_token  = $data['access_token'];

        $refresh_token = $data['refresh_token'];


        /*for ($i = 0; $i <count($pids); $i ++){
            $pid = $pids[$i]['pid'];

            $printRes = $YlyPrintObj->printDish($access_token,$pid);

            if ($printRes['error'] != "0"){
                //落单失败
                return $this->com_return(false,$printRes['error_description'],$pid);
            }
        }*/

        $printRes = $YlyPrintObj->printDish($access_token,$pid);

        return $this->com_return(true,config("params.SUCCESS"));
        /*调用打印机 处理落单 off*/
    }

    /**
     * 获取预约日期的截止时间
     * @param $date
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getReservationDateRefundTime($date)
    {
        $tableReserveDateModel = new MstTableReserveDate();

        $res = $tableReserveDateModel
            ->where("appointment",$date)
            ->where("is_enable",1)
            ->field("refund_end_time")
            ->find();

        $res = json_decode(json_encode($res),true);
        if (!empty($res)) {
            $refund_end_time = $res['refund_end_time'];
        }else{
            $refund_end_time = null;
        }
        return $refund_end_time;
    }

}