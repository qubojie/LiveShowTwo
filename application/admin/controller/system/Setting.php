<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 上午11:32
 */
namespace app\admin\controller\system;

use app\common\controller\AdminAuthAction;
use app\common\model\SysAdminUser;
use app\common\model\SysSetting;
use think\Exception;
use think\Validate;

class Setting extends AdminAuthAction
{
    /**
     * 设置类型列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lists()
    {
        $sysSettingModel = new SysSetting();
        $ktype_arr = $sysSettingModel
            ->group("ktype")
            ->field("ktype")
            ->select();
        $res = json_decode(json_encode($ktype_arr),true);

        $mn = [];
        try{
            foreach ($res as $k => $v){
                foreach ($v as $m => $n){
                    //$mn[] = $n;
                    if ($n == "card"){
                        $mn[$k]["key"] = $n;
                        $mn[$k]["name"] = config("sys.card");
                    }

                    if ($n == "reserve"){
                        $mn[$k]["key"] = $n;
                        $mn[$k]["name"] = config("sys.reserve");
                    }

                    if ($n == "sms" ){
                        $mn[$k]["key"] = $n;
                        $mn[$k]["name"] = config("sys.sms");
                    }

                    if ($n == "sys" ){
                        $mn[$k]["key"] = $n;
                        $mn[$k]["name"] = config("sys.sys");
                    }

                    if ($n == "user" ){
                        $mn[$k]["key"] = $n;
                        $mn[$k]["name"] = config("sys.user");
                    }
                }
            }
        }catch (Exception $e) {
            return $this->com_return(false,$e->getMessage());
        }

        return $this->com_return(true,config('params.SUCCESS'),$mn);
    }

    /**
     * 根据类型查找相应下的数据
     * @return array
     */
    public function get_info()
    {
        $ktype = $this->request->param("ktype","");

        $rule = [
            "ktype" => "require",
        ];

        $request_res = [
            "ktype" => $ktype,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            $sysSettingModel = new SysSetting();

            $column = $sysSettingModel->column;

            $res = $sysSettingModel
                ->where('ktype',$ktype)
                ->field($column)
                ->order('sort asc')
                ->select();

            return $this->com_return(true,config('params.SUCCESS'),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 类型详情编辑提交
     * @return array
     */
    public function edit()
    {
        $key    = $this->request->param("key","");
        $value  = $this->request->param("value","");

        $rule = [
            "key"       => "require",
            "value|内容" => "require"
        ];

        $request_res = [
            "key"   => $key,
            "value" => $value,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        $update_data = [
            "value" => $value
        ];

        try{
            $sysSettingModel = new SysSetting();
            $is_ok = $sysSettingModel
                ->where("key",$key)
                ->update($update_data);
            if ($is_ok !== false){
                return $this->com_return(true,config('params.SUCCESS'));
            }else{
                return $this->com_return(false,config('params.FAIL'));
            }
        }catch (Exception $e){
            return $this->com_return(false,$e->getMessage());
        }
    }

    /**
     * 新增系统设置
     * @return array
     */
    public function create()
    {
        $key           = $this->request->param("key","");
        $ktype         = $this->request->param("ktype","");
        $sort          = $this->request->param("sort","");
        $key_title     = $this->request->param("key_title","");
        $key_des       = $this->request->param("key_des","");
        $vtype         = $this->request->param("vtype","");
        $select_cont   = $this->request->param("select_cont","");
        $value         = $this->request->param("value","");
        $default_value = $this->request->param("default_value","");

        $rule = [
            "key"                   => "require|unique_me:sys_setting|max:50",
            "ktype"                 => "require|max:10",
            "sort|排序"             => "require|number|unique_me:sys_setting",
            "key_title|标题"        => "require|unique_me:sys_setting|max:40",
            "key_des|描述"          => "require|max:200",
            "vtype|内容类型"         => "require|max:20",
            "value|内容"            => "require|max:2000",
            "default_value|默认内容" => "require|max:2000"
        ];

        $request_res = [
            "key"           => $key,
            "ktype"         => $ktype,
            "sort"          => $sort,
            "key_title"     => $key_title,
            "key_des"       => $key_des,
            "vtype"         => $vtype,
            "value"         => $value,
            "default_value" => $default_value,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $authorization = $this->request->header("Authorization",'');
        //获取当前登录用户id
        $sysAdminUserModel = new SysAdminUser();

        try{
            $admin_info = $sysAdminUserModel
                ->where("token",$authorization)
                ->field("id")
                ->find();

            $admin_info = json_decode(json_encode($admin_info),true);
            $admin_id   = $admin_info['id'];

            $sysSettingModel = new SysSetting();

            //要写入的数据
            $insert_data = [
                "key"           => $key,
                "ktype"         => $ktype,
                "sort"          => $sort,
                "key_title"     => $key_title,
                "key_des"       => $key_des,
                "vtype"         => $vtype,
                "select_cont"   => $select_cont,
                "value"         => $value,
                "default_value" => $default_value,
                "last_up_time"  => time(),
                "last_up_admin" => $admin_id
            ];
            $is_ok = $sysSettingModel
                ->insert($insert_data);
            if ($is_ok !== false){
                return $this->com_return(true,config('params.SUCCESS'));
            }else{
                return $this->com_return(false,config('params.FAIL'));
            }
        }catch (Exception $e){
            return $this->com_return(false,$e->getMessage());
        }
    }
}