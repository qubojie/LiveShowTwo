<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/28
 * Time: 下午3:49
 */

namespace app\admin\controller\whore;


use app\common\controller\AdminAuthAction;
use app\common\model\MstPayMethod;
use think\Exception;
use think\Validate;

class PayMethod extends AdminAuthAction
{
    /**
     * 支付方式列表
     */
    public function index()
    {
        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->where("is_delete",0)
                ->order("sort,updated_at DESC")
                ->select();
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 支付方式添加
     * @return array
     */
    public function add()
    {
        $pay_method_key       = $this->request->param("pay_method_key","");//支付方式Key
        $pay_method_name      = $this->request->param("pay_method_name","");//支付方式名称
        $sort                 = $this->request->param("sort","");//排序
        $user_is_enable       = $this->request->param("user_is_enable","");//用户端是否激活
        $reception_is_enable  = $this->request->param("reception_is_enable","");//前台是否激活
        $rule = [
            "pay_method_name|支付方式名称" => "require|unique:mst_pay_method",
        ];
        $request_res = [
            "pay_method_name" => $pay_method_name,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($sort)) $sort = 100;
        if (empty($user_is_enable)) $user_is_enable = 0;
        if (empty($reception_is_enable)) $reception_is_enable = 0;

        $params = [
            "pay_method_key"      => $pay_method_key,
            "pay_method_name"     => $pay_method_name,
            "sort"                => $sort,
            "user_is_enable"      => $user_is_enable,
            "reception_is_enable" => $reception_is_enable,
        ];

        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->insert($params);
            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 支付方式编辑
     * @return array
     */
    public function edit()
    {
        $pay_method_id        = $this->request->param("pay_method_id","");//支付方式id
        $pay_method_name      = $this->request->param("pay_method_name","");//支付方式名称
        $sort                 = $this->request->param("sort","");//排序
        $user_is_enable       = $this->request->param("user_is_enable","");//用户端是否激活
        $reception_is_enable  = $this->request->param("reception_is_enable","");//前台是否激活
        $rule = [
            "pay_method_id|支付方式id"    => "require",
            "pay_method_name|支付方式名称" => "require",
        ];
        $request_res = [
            "pay_method_id"   => $pay_method_id,
            "pay_method_name" => $pay_method_name,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if (empty($sort)) $sort = 100;
        if (empty($user_is_enable)) $user_is_enable = 0;
        if (empty($reception_is_enable)) $reception_is_enable = 0;

        $params = [
            "pay_method_name"     => $pay_method_name,
            "sort"                => $sort,
            "user_is_enable"      => $user_is_enable,
            "reception_is_enable" => $reception_is_enable,
        ];

        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->where("pay_method_id",$pay_method_id)
                ->update($params);
            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 支付方式删除
     * @return array
     */
    public function delete()
    {
        $pay_method_id        = $this->request->param("pay_method_id","");//支付方式id
        $rule = [
            "pay_method_id|支付方式id"    => "require",
        ];
        $request_res = [
            "pay_method_id"   => $pay_method_id,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->where("pay_method_id",$pay_method_id)
                ->delete();
            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * 是否激活
     * @return array
     */
    public function enable()
    {
        $type                 = $this->request->param("type","");//是否激活操作类型
        $pay_method_id        = $this->request->param("pay_method_id","");//支付方式id
        $user_is_enable       = $this->request->param("user_is_enable","");//用户端是否激活
        $rule = [
            "type|类型"               => "require",
            "pay_method_id|支付方式id" => "require",
            "user_is_enable|是否激活"  => "require",
        ];
        $request_res = [
            "type"            => $type,
            "pay_method_id"   => $pay_method_id,
            "user_is_enable"  => $user_is_enable,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }
        $params = [
            "$type" => $user_is_enable,
        ];

        try {
            $payMethodModel = new MstPayMethod();
            $res = $payMethodModel
                ->where("pay_method_id",$pay_method_id)
                ->update($params);
            if ($res !== false) {
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}