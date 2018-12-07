<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午6:09
 */

namespace app\admin\controller\structure;


use app\common\controller\AdminAuthAction;
use app\common\model\ManageSalesman;
use think\Db;
use think\Env;
use think\Exception;
use think\Validate;

class SalesUser extends AdminAuthAction
{
    /**
     * 人员状态分组
     * @return array
     */
    public function salesmanStatus()
    {
        try {
            $statusList = config("salesman.salesman_status");

            $res = [];

            unset($statusList['pending']);

            $salesmanModel = new ManageSalesman();

            foreach ($statusList as $key => $val){
                if ($val["key"] == config("salesman.salesman_status")['pending']['key']){
                    $count = $salesmanModel
                        ->where("statue",config("salesman.salesman_status")['pending']['key'])
                        ->count();//未审核总记录数
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
     * 人员列表
     * @return array
     */
    public function index()
    {
        $pagesize      = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage       = $this->request->param("nowPage","1");
        $keyword       = $this->request->param("keyword","");
        $status        = $this->request->param('status',"");//营销人员状态  0入职待审核  1销售中  2停职  9离职
        $department_id = $this->request->param('department_id','');//部门Id

        if (empty($pagesize)) $pagesize = config('page_size');

        $department_where = [];
        if (!empty($department_id)){
            $department_where['ms.department_id'] = ["eq","$department_id"];
        }

        if (empty($status)) $status = 0;

        $status_where['ms.statue'] = ["eq","$status"];

        $where = [];
        if (!empty($keyword)){
            $where['ms.sid|ms.sales_name|ms.phone|ms.id_no|ms.province|ms.city|ms.country|md.department_title|mst.stype_name'] = ["like","%$keyword%"];
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $manageSalesmanModel = new ManageSalesman();

            $admin_column = $manageSalesmanModel->admin_column;

            $salesman_list = $manageSalesmanModel
                ->alias("ms")
                ->where($where)
                ->where($department_where)
                ->where($status_where)
                ->join("manage_department md","md.department_id = ms.department_id","LEFT")
                ->join("mst_salesman_type mst","mst.stype_id = ms.stype_id")
                ->field($admin_column)
                ->field("md.department_title,md.department_manager")
                ->field("mst.stype_name,mst.stype_desc,mst.commission_ratio")
                ->paginate($pagesize,false,$config);

            $salesman_list = json_decode(json_encode($salesman_list),true);

            for ($i=0;$i<count($salesman_list['data']);$i++){

                /*处理身份中照片地址 On*/
                if (!empty($salesman_list['data'][$i]['id_img1'])){
                    $salesman_list['data'][$i]['id_img1'] = Env::get("IMG_YM_PATH")."/".$salesman_list['data'][$i]['id_img1'];
                    $salesman_list['data'][$i]['id_img2'] = Env::get("IMG_YM_PATH")."/".$salesman_list['data'][$i]['id_img2'];
                }
                /*处理身份中照片地址 Off*/

                /*处理默认头像 On*/
                if (empty($salesman_list['data'][$i]['avatar'])){
                    $salesman_list['data'][$i]['avatar'] = getSysSetting("sys_default_avatar");
                }
                /*处理默认头像 Off*/

                /*更改营销人员状态 On*/
                if ($salesman_list['data'][$i]['statue'] == config("salesman.salesman_status")['pending']['key']){
                    $salesman_list['data'][$i]['statue_name'] = config("salesman.salesman_status")['pending']['name'];
                }
                if ($salesman_list['data'][$i]['statue'] == config("salesman.salesman_status")['working']['key']){
                    $salesman_list['data'][$i]['statue_name'] = config("salesman.salesman_status")['working']['name'];
                }
                if ($salesman_list['data'][$i]['statue'] == config("salesman.salesman_status")['suspended']['key']){
                    $salesman_list['data'][$i]['statue_name'] = config("salesman.salesman_status")['suspended']['name'];
                }
                if ($salesman_list['data'][$i]['statue'] == config("salesman.salesman_status")['resignation']['key']){
                    $salesman_list['data'][$i]['statue_name'] = config("salesman.salesman_status")['resignation']['name'];
                }
                /*更改营销人员状态 Off*/
            }
            return $this->com_return(true,config("params.SUCCESS"),$salesman_list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 人员添加
     * @return array
     */
    public function add()
    {
        $department_id         = $this->request->param("department_id","");//部门id
        $stype_id              = $this->request->param("stype_id","");//销售员类型ID
        $is_governor           = $this->request->param("is_governor",0);//是否业务主管
        $sales_name            = $this->request->param("sales_name","");//姓名
        $statue                = $this->request->param("statue","1");//销售员状态 0入职待审核  1销售中  2停职  9离职
        $phone                 = $this->request->param("phone","");//电话号码 必须唯一 未来可用于登录 实名验证等使用
        $password              = $this->request->param("password",config("default_password"));//密码
        $password_confirmation = $this->request->param("password_confirmation",config("default_password"));//确认密码
        $wxid                  = $this->request->param("wxid","");//微信id(openId/unionId)
        $nickname              = $this->request->param("nickname","");//昵称
        $avatar                = $this->request->param("avatar","");//头像
        $sex                   = $this->request->param("sex",config('default_sex'));//性别
        $province              = $this->request->param("province","");//省份
        $city                  = $this->request->param("city","");//城市
        $country               = $this->request->param("country","中国");//国家
        $dimission_time        = $this->request->param("dimission_time","");//离职或停职时间
        $lastlogin_time        = $this->request->param("lastlogin_time","");//最后登录时间
        $sell_num              = $this->request->param("sell_num","0");//销售数量（定时统计）
        $sell_amount           = $this->request->param("sell_amount","0");//销售总金额 （定时统计）


        if (empty($password)) $password = $password_confirmation = config("default_password");
        if (empty($sex))  $sex = config('default_sex');

        $rule = [
            "department_id|部门"          => "require",
            "stype_id|销售员类型"          => "require",
            "sales_name|姓名"             => "require",
            "statue|销售员状态"            => "require",
            "phone|电话号码"               => "require|number|regex:1[0-9]{1}[0-9]{9}|unique:manage_salesman",
        ];

        $request_res = [
            "department_id"         => $department_id,
            "stype_id"              => $stype_id,
            "sales_name"            => $sales_name,
            "statue"                => $statue,
            "phone"                 => $phone,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        if ($password !== $password_confirmation) {
            return $this->com_return(false,config("params.PASSWORD_DIF"));
        }
        try {
            if (empty($avatar)) $avatar = getSysSetting("sys_default_avatar");

            $sid = generateReadableUUID("S");

            $insert_data = [
                "sid"            => $sid,
                "department_id"  => $department_id,
                "stype_id"       => $stype_id,
                "is_governor"    => $is_governor,
                "sales_name"     => $sales_name,
                "statue"         => $statue,
                "phone"          => $phone,
                "password"       => sha1($password),
                "wxid"           => $wxid,
                "nickname"       => $nickname,
                "avatar"         => $avatar,
                "sex"            => $sex,
                "province"       => $province,
                "city"           => $city,
                "country"        => $country,
                "dimission_time" => $dimission_time,
                "lastlogin_time" => $lastlogin_time,
                "sell_num"       => $sell_num,
                "sell_amount"    => $sell_amount,
                "created_at"     => time(),
                "updated_at"     => time(),
            ];

            $manageSalesmanModel = new ManageSalesman();
            $res = $manageSalesmanModel
                ->insert($insert_data);

            if ($res !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 人员编辑
     * @return array
     */
    public function edit()
    {
        $sid            = $this->request->param("sid",""); //销售人员id
        $department_id  = $this->request->param("department_id","");//部门id
        $stype_id       = $this->request->param("stype_id","");//销售员类型ID
        $is_governor    = $this->request->param("is_governor","");//是否业务主管 0否,1是
        $sales_name     = $this->request->param("sales_name","");//姓名
        $phone          = $this->request->param("phone","");//电话号码 必须唯一 未来可用于登录 实名验证等使用

        $rule = [
            "sid|销售人员"         => "require",
            "department_id|部门"  => "require",
            "stype_id|销售员类型"  => "require",
            "sales_name|姓名"     => "require",
            "phone|电话号码"       => "require|regex:1[0-9]{1}[0-9]{9}|number",
        ];

        $request_res = [
            "sid"           => $sid,
            "department_id" => $department_id,
            "stype_id"      => $stype_id,
            "sales_name"    => $sales_name,
            "phone"         => $phone,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $manageSalesmanModel = new ManageSalesman();
            //判断电话号码是否存在
            $is_exist = $manageSalesmanModel
                ->where('sid','neq',$sid)
                ->where('phone',$phone)
                ->count();

            if ($is_exist > 0){
                return $this->com_return(false,config("params.PHONE_EXIST"));
            }
            $param = $this->request->param();

            if (isset($param["password"]) && !empty($param["password"])) {
                $rule = [
                    "password|密码"        => "alphaNum|length:6,25",
                ];
                $request_res = [
                    "password"            => $param["password"],
                ];
                $validate = new Validate($rule);
                if (!$validate->check($request_res)){
                    return $this->com_return(false,$validate->getError(),null);
                }
                $param["password"] = sha1(jmPassword($param["password"]));
            }

            if (empty($param["password"])){
                //如果为空,移除
                $param = bykey_reitem($param,"password");
            }

            $param["updated_at"] = time();

            $res = $manageSalesmanModel
                ->where("sid",$sid)
                ->update($param);

            if ($res !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 人员状态操作 销售员状态 0入职待审核  1销售中  2停职  9离职
     * @return array
     */
    public function changeStatus()
    {
        $sids   = $this->request->param("sid","");
        $status = $this->request->param("status","");
        $reason = $this->request->param("reason","");//操作原因

        $rule = [
            "sid|销售人员"         => "require",
        ];
        $request_res = [
            "sid"           => $sids,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        if ($status == config("salesman.salesman_status")['working']['key']){
            $action = config("useraction.verify_passed")['name'];
        }elseif ($status == config("salesman.salesman_status")['suspended']['key']){
            $action = config("useraction.suspended")['name'];
        }elseif ($status == config("salesman.salesman_status")['resignation']['key']){
            $action = config("useraction.resignation")['name'];
        }else{
            $action = "empty";
        }
        $params = [
            'statue'     => $status,
            'updated_at' => time()
        ];
        Db::startTrans();
        try{
            $manageSalesmanModel = new ManageSalesman();
            $id_array = explode(",",$sids);
            foreach ($id_array as $sid){
                $res = $manageSalesmanModel
                    ->where("sid",$sid)
                    ->update($params);
                if ($res === false) {
                    return $this->com_return(false, config("params.FAIL"));
                }
                //获取当前登录管理员
                $Authorization = $this->request->header('Authorization');
                $adminInfo =self::tokenGetAdminLoginInfo($Authorization);
                $action_user =$adminInfo['user_name'];
                //记录操作日志
                $this->addSysAdminLog("$sid","","","$action","$reason","$action_user",time());
            }

            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));

        }catch (Exception $e){
            Db::rollback();
            return $this->com_return(false,$e->getMessage(),null);
        }
    }
}