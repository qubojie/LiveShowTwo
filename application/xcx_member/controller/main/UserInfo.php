<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午2:08
 */

namespace app\xcx_member\controller\main;


use app\common\controller\AgeConstellation;
use app\common\controller\BaseController;
use app\common\controller\UserCommon;
use app\common\model\BcUserInfo;
use app\common\model\SysSetting;
use app\common\model\User;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class UserInfo extends BaseController
{
    /**
     * 获取标签信息
     * @return array
     */
    public function tagList()
    {
        try {
            $sysSettingModel = new SysSetting();
            $key_arr = $sysSettingModel
                ->where('ktype','user')
                ->field('key')
                ->order("sort",'asc')
                ->select();
            $key_arr = json_decode(json_encode($key_arr),true);
            $value = array();
            for ($i=0;$i<count($key_arr);$i++) {
                $key = $key_arr[$i]['key'];
                $value[$key] = $sysSettingModel
                    ->where('key',$key)
                    ->field('value')
                    ->find();
            }
            return $this->com_return(true,config("SUCCESS"),$value);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /*
     * 完善用户信息
     * */
    public function postInfo(Request $request)
    {
        $phone        = $request->param("phone","");//电话号码
        $sex          = $request->param("sex","先生");//性别,前台直接传的文字
        $birthday     = $request->param("birthday","");//生日
        $blood        = $request->param("blood","");//血型
        $nation       = $request->param("nation","");//民族
        $native_place = $request->param("native_place","");//籍贯
        $stature      = $request->param("stature","");//身高 cm
        $weight       = $request->param("weight","");//体重 kg
        $car_no       = $request->param("car_no","");//车牌号
        $profession   = $request->param("profession","");//职业
        $interest     = $request->param("interest","");//兴趣
        $skill        = $request->param("skill","");//技能
        $character    = $request->param("character","");//性格
        $need         = $request->param("need","");//希望资源

        $params = $request->param();

        $rule = [
            "phone|电话号码"    => "require",
        ];
        $request_res = [
            "phone"     => $phone,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        Db::startTrans();
        try {
            $userCommonObj = new UserCommon();
            $uid_res = $userCommonObj->uidOrPhoneGetUserInfo($phone);
            $uid = $uid_res['uid'];
            $params["uid"] = $uid;

            if (empty($uid_res)){
                return $this->com_return(false,config('FAIL'));
            }

            //更新用户性别
            $sexParams = [
                "sex" => $sex
            ];
            $one = $userCommonObj->updateUserInfo($sexParams,$uid);

            if ($one == false){
                return $this->com_return(false,config('FAIL'));
            }

            //移除参数 phone,sex
            if(isset($params['phone'])){
                $params = bykey_reitem($params,"phone");
            }
            if (isset($params['sex'])){
                $params = bykey_reitem($params,"sex");
            }

            //如果生日不为空,获取年龄,星座,属相
            if (!empty($birthday)){
                $constellationObj = new AgeConstellation();
                $nxs = $constellationObj->getInfo($birthday);
                $constellation = $nxs['constellation'];
                $params['astro'] = $constellation;
            }

            if (!empty($interest)){
                //如果用户填写了兴趣等标签,则更新用户信息状态为已完善
                $userInfoParams = [
                    "info_status" => config("user.user_info")['complete']['key']
                ];
                $two = $userCommonObj->updateUserInfo($userInfoParams,$uid);
                if ($two == false){
                    return $this->com_return(false,config('FAIL'));
                }
            }

            Db::commit();
            return $this->insertUserInfo($params);
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /*
    * 将补全信息资料写入数据库
    * */
    public function insertUserInfo($params=array())
    {
        $userInfoModel = new BcUserInfo();
        $uid = $params["uid"];
        //查看当前是否存在当前用户的信息
        $is_exist = $userInfoModel
            ->where("uid",$uid)
            ->count();
        $time = time();
        if ($is_exist){
            //存在则更新
            $params["updated_at"] = $time;
            $is_ok = $userInfoModel->where('uid',$uid)->update($params);
        }else{
            //不存在则新增
            $params["created_at"] = $time;
            $params["updated_at"] = $time;
            $is_ok = $userInfoModel->insert($params);
        }

        if ($is_ok){
            return $this->com_return(true,config("params.SUCCESS"),$uid);
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    /**
     * 获取用户列表排序
     * @return array
     */
    public function getUserList()
    {
        try {
            $userModel = new User();
            $user_list = $userModel
                ->select();
            $user_list = json_decode(json_encode($user_list),true);
            $user_name = [];
            for ($i = 0; $i <count($user_list); $i++){
                $user_name[$i] = $user_list[$i]['name'];
            }
            $charArray=array();
            foreach ($user_name as $name ){
                $char = getFirstChar($name);
                $nameArray = array();
                if(isset($charArray[$char])){
                    $nameArray = $charArray[$char];
                }
                array_push($nameArray,$name);
                $charArray[$char] = $nameArray;
            }
            ksort($charArray);
            return $this->com_return(true,config('params.SUCCESS'),$charArray);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}