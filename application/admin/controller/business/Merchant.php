<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/15
 * Time: 下午3:32
 */
namespace app\admin\controller\business;

use app\common\controller\AdminAuthAction;
use app\common\model\MstMerchant;
use think\Exception;
use think\Validate;

class Merchant extends AdminAuthAction
{
    /**
     * 联盟商家列表
     * @return array
     */
    public function index()
    {
        $cat_id     = $this->request->param("cat_id","");//联盟商家分类id
        $pagesize   = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage    = $this->request->param("nowPage","1");
        $keyword    = $this->request->param("keyword","");

        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        $where = [];
        if (!empty($keyword)){
            $where["m.merchant"] = ["like","%$keyword%"];
        }

        $cat_where = [];
        if (!empty($cat_id)){
            $cat_where['m.cat_id'] = ['eq',$cat_id];
        }

        try {
            $mstMerchantModel = new MstMerchant();
            $column = $mstMerchantModel->column;

            foreach ($column as $key => $val){
                $column[$key] = "m.".$val;
            }

            $res = $mstMerchantModel
                ->alias("m")
                ->join("mst_merchant_category mc","mc.cat_id = m.cat_id")
                ->where($where)
                ->where($cat_where)
                ->order("m.sort")
                ->field($column)
                ->field("mc.cat_name")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$res);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家添加
     * @return array
     */
    public function add()
    {
        $cat_id        = $this->request->param("cat_id","");//联盟商家分类id
        $merchant      = $this->request->param("merchant","");//联盟商家名称
        $merchant_desc = $this->request->param("merchant_desc","");//联盟商家描述
        $address       = $this->request->param("address","");//联盟商家地址
        $sort          = $this->request->param("sort","");//排序
        $is_enable     = $this->request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "cat_id|联盟商家分类id"      => "require",
            "merchant|联盟商家名称"      => "require|max:30",
            "merchant_desc|联盟商家描述" => "max:500",
            "address|联盟商家地址"       => "max:200",
            "sort|排序"                 => "number",
            "is_enable|是否启用"         => "require|number",
        ];

        $check_res = [
            "cat_id"        => $cat_id,
            "merchant"      => $merchant,
            "merchant_desc" => $merchant_desc,
            "address"       => $address,
            "sort"          => $sort,
            "is_enable"     => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "cat_id"       => $cat_id,
            "merchant"     => $merchant,
            "merchant_desc" => $merchant_desc,
            "address"      =>  $address,
            "sort"         => $sort,
            "is_enable"    => $is_enable,
            "created_at"   => time(),
            "updated_at"   => time(),
        ];

        try {
            $mstMerchantModel = new MstMerchant();

            $res = $mstMerchantModel
                ->insertGetId($params);
            if ($res){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家编辑提交
     * @return array
     */
    public function edit()
    {
        $merchant_id   = $this->request->param("merchant_id","");//联盟商家id
        $cat_id        = $this->request->param("cat_id","");//联盟商家分类id
        $merchant      = $this->request->param("merchant","");//联盟商家名称
        $merchant_desc = $this->request->param("merchant_desc","");//联盟商家描述
        $address       = $this->request->param("address","");//联盟商家地址
        $sort          = $this->request->param("sort","");//排序
        $is_enable     = $this->request->param("is_enable","");//是否启用  0否 1是

        $rule = [
            "merchant_id|联盟商家id"    => "require",
            "cat_id|联盟商家分类id"      => "require",
            "merchant|联盟商家名称"      => "require|max:30",
            "merchant_desc|联盟商家描述" => "max:500",
            "address|联盟商家地址"       => "max:200",
            "sort|排序"                => "number",
            "is_enable|是否启用"        => "require|number",
        ];

        $check_res = [
            "merchant_id"   => $merchant_id,
            "cat_id"        => $cat_id,
            "merchant"      => $merchant,
            "merchant_desc" => $merchant_desc,
            "address"       => $address,
            "sort"          => $sort,
            "is_enable"     => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }
        $params = [
            "cat_id"        => $cat_id,
            "merchant"      => $merchant,
            "merchant_desc" => $merchant_desc,
            "address"       =>  $address,
            "sort"          => $sort,
            "is_enable"     => $is_enable,
            "created_at"    => time(),
            "updated_at"    => time(),
        ];

        try {
            $mstMerchantModel = new MstMerchant();
            $is_ok = $mstMerchantModel
                ->where('merchant_id',$merchant_id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家删除
     * @return array
     */
    public function delete()
    {
        $merchant_ids = $this->request->param("merchant_id","");//联盟商家id

        $rule = [
            "merchant_id|联盟商家id"      => "require",
        ];

        $check_res = [
            "merchant_id" => $merchant_ids,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $id_array = explode(",",$merchant_ids);
            $mstMerchantModel = new MstMerchant();

            $where['merchant_id'] = array('in',$id_array);
            $res = $mstMerchantModel
                ->where($where)
                ->delete();
            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家排序
     * @return array
     */
    public function sortEdit()
    {
        $merchant_id = $this->request->param("merchant_id","");//联盟商家id
        $sort        = $this->request->param("sort","");//排序

        $rule = [
            "merchant_id|联盟商家id" => "require",
            "sort|排序"             => "require|number",
        ];

        $check_res = [
            "merchant_id" => $merchant_id,
            "sort"        => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "sort"       => $sort,
            "updated_at" => time()
        ];

        try {
            $mstMerchantModel = new MstMerchant();
            $res = $mstMerchantModel
                ->where("merchant_id",$merchant_id)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 联盟商家是否启用
     * @return array
     */
    public function enable()
    {
        $merchant_id = $this->request->param("merchant_id","");//联盟商家id
        $is_enable   = $this->request->param("is_enable","");//是否启用

        $rule = [
            "merchant_id|联盟商家id" => "require",
            "is_enable|是否启用"     => "require|number",
        ];

        $check_res = [
            "merchant_id" => $merchant_id,
            "is_enable"   => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        $params = [
            "is_enable"  => $is_enable,
            "updated_at" => time()
        ];

        try {
            $mstMerchantModel = new MstMerchant();

            $res = $mstMerchantModel
                ->where("merchant_id",$merchant_id)
                ->update($params);

            if ($res !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

}