<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午5:13
 */

namespace app\admin\controller\member;


use app\common\controller\AdminAuthAction;
use app\common\model\MstRefillAmount;
use think\Exception;
use think\Validate;

class RefillAmount extends AdminAuthAction
{
    /**
     * 列表
     * @return array
     */
    public function index()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage  = $this->request->param("nowPage","1");

        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];

        try {
            $refillAmountModel = new MstRefillAmount();

            $list = $refillAmountModel
                ->order("sort")
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 添加
     * @return array
     */
    public function add()
    {
        $amount    = $this->request->param("amount","");//充值金额
        $cash_gift = $this->request->param("cash_gift","");//赠送礼金数
        $desc      = $this->request->param("desc","");//描述
        $sort      = $this->request->param("sort","");//排序
        $is_enable = $this->request->param("is_enable","");//是否激活

        $rule = [
            "amount|充值金额"      => "require|number|max:20|unique:mst_refill_amount|gt:0",
            "cash_gift|赠送礼金数" => "require|number|max:20|egt:0",
            "sort|排序"           => "require|number|max:6",
            "is_enable|是否激活"   => "require|number",
        ];

        $request_res = [
            "amount"    => $amount,
            "cash_gift" => $cash_gift,
            "sort"      => $sort,
            "is_enable" => $sort,
        ];

        $validate = new Validate($rule);

        if ($sort == 0) $sort = 100;

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $params = [
            "amount"     => $amount,
            "cash_gift"  => $cash_gift,
            "desc"       => $desc,
            "sort"       => $sort,
            "is_enable"  => $is_enable,
            "created_at" => time(),
            "updated_at" => time(),
        ];

        try {
            $refillAmountModel = new MstRefillAmount();

            $is_ok = $refillAmountModel
                ->insert($params);

            if ($is_ok !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 编辑
     * @return array
     */
    public function edit()
    {
        $id        = $this->request->param("id","");//id
        $amount    = $this->request->param("amount","");//充值金额
        $cash_gift = $this->request->param("cash_gift","");//赠送礼金数
        $desc      = $this->request->param("desc","");//描述
        $sort      = $this->request->param("sort","");//排序
        $is_enable = $this->request->param("is_enable","");//是否激活

        $rule = [
            "id|参数id"           => "require",
            "amount|充值金额"      => "require|number|max:20|unique:mst_refill_amount|gt:0",
            "cash_gift|赠送礼金数" => "require|number|max:20|egt:0",
            "sort|排序"           => "require|number|max:6",
            "is_enable|是否激活"   => "require|number",
        ];

        $request_res = [
            "id"        => $id,
            "amount"    => $amount,
            "cash_gift" => $cash_gift,
            "sort"      => $sort,
            "is_enable" => $sort,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        if ($sort == 0) $sort = 100;

        $time = time();

        $params = [
            "amount"     => $amount,
            "cash_gift"  => $cash_gift,
            "desc"       => $desc,
            "sort"       => $sort,
            "is_enable"  => $is_enable,
            "updated_at" => $time,
        ];

        try {
            $refillAmountModel = new MstRefillAmount();

            $res = $refillAmountModel
                ->where('id',$id)
                ->update($params);

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
     * 删除
     * @return array
     */
    public function delete()
    {
        $refillAmountModel = new MstRefillAmount();

        $id = $this->request->param("id","");//id
        $rule = [
            "id|参数id"           => "require",
        ];
        $request_res = [
            "id"        => $id,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            $res = $refillAmountModel
                ->where('id',$id)
                ->delete();

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
     * 是否启用
     * @return array
     */
    public function enable()
    {
        $is_enable = (int)$this->request->param("is_enable","");//是否启用
        $id        = $this->request->param("id","");//id

        $rule = [
            "id|参数id"           => "require",
            "is_enable|是否启用"   => "require",
        ];
        $request_res = [
            "id"        => $id,
            "is_enable" => $is_enable,
        ];
        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $params = [
            "is_enable"  => $is_enable,
            "updated_at" => time(),
        ];

        try {
            $refillAmountModel = new MstRefillAmount();

            $is_ok = $refillAmountModel
                ->where('id',$id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }

    /**
     * 排序编辑
     * @return array
     */
    public function sortEdit()
    {
        $sort = (int)$this->request->param("sort","");//排序
        $id   = $this->request->param("id","");//id

        $rule = [
            "id|参数id"   => "require",
            "sort|排序"   => "require",
        ];
        $request_res = [
            "id"   => $id,
            "sort" => $sort,
        ];
        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $params = [
            "sort"       => $sort,
            "updated_at" => time(),
        ];

        try {
            $refillAmountModel = new MstRefillAmount();

            $is_ok = $refillAmountModel
                ->where('id',$id)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true, config("params.SUCCESS"));
            }else{
                return $this->com_return(false, config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage());
        }
    }




}