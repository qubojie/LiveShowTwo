<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/16
 * Time: 下午5:37
 */
namespace app\admin\controller\structure;

use app\common\controller\AdminAuthAction;
use app\common\model\MstSalesmanType;
use think\Db;
use think\Exception;
use think\Validate;

class SalesType extends AdminAuthAction
{
    /**
     * 列表
     * @return array
     */
    public function index()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//当前页,不传时为10
        $nowPage  = $this->request->param("nowPage","1");
        $keyword  = $this->request->param("keyword","");

        if (empty($pagesize))   $pagesize = config('page_size');

        $where = [];
        if (!empty($keyword)){
            $where['stype_name'] = ["like","%$keyword%"];
        }

        $config = [
            "page" => $nowPage,
        ];

        try {
            $salesmanTypeModel = new MstSalesmanType();

            $list = $salesmanTypeModel
                ->where($where)
                ->where('is_enable',1)
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
        $stype_key        = $this->request->param("stype_key","vip");//销售员类型key
        $stype_name       = $this->request->param("stype_name","");//销售员等级名称
        $stype_desc       = $this->request->param("stype_desc","");//销售员等级描述
        $commission_ratio = $this->request->param("commission_ratio",0);//佣金比例 （百分比整数，6代表 6‰）

        $rule = [
            "stype_name|销售员等级名称"   => "require|max:20|unique:mst_salesman_type",
            "stype_desc|销售员等级描述"   => "require|max:400",
            "commission_ratio|佣金比例"  => "require|number",
        ];

        $request_res = [
            "stype_name"       => $stype_name,
            "stype_desc"       => $stype_desc,
            "commission_ratio" => $commission_ratio,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        $time = time();

        $insert_data = [
            "stype_name"       => $stype_name,
            "stype_desc"       => $stype_desc,
            "commission_ratio" => $commission_ratio,
            "created_at"       => $time,
            "updated_at"       => $time
        ];

        try {
            $salesmanTypeModel = new MstSalesmanType();
            $res = $salesmanTypeModel
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
     * 编辑
     * @return array
     */
    public function edit()
    {

        $stype_id         = $this->request->param("stype_id","");
        $stype_name       = $this->request->param("stype_name","");//销售员等级名称
        $stype_desc       = $this->request->param("stype_desc","");//销售员等级描述
        $commission_ratio = $this->request->param("commission_ratio","");//佣金比例 （千分比整数，6代表 6‰）

        $rule = [
            "stype_id|销售员id"          => "require",
            "stype_name|销售员等级名称"   => "require|max:20|unique:mst_salesman_type",
            "stype_desc|销售员等级描述"   => "require|max:400",
            "commission_ratio|佣金比例"  => "require|number",
        ];

        $request_res = [
            "stype_id"         => $stype_id,
            "stype_name"       => $stype_name,
            "stype_desc"       => $stype_desc,
            "commission_ratio" => $commission_ratio,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            "stype_name"       => $stype_name,
            "stype_desc"       => $stype_desc,
            "commission_ratio" => $commission_ratio,
            "updated_at"       => time()
        ];
        try {

            $salesmanTypeModel = new MstSalesmanType();

            $res = $salesmanTypeModel
                ->where("stype_id",$stype_id)
                ->update($update_data);

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
        $stype_ids  = $this->request->param("stype_id","");

        $rule = [
            "stype_id|销售员"          => "require",
        ];
        $request_res = [
            "stype_id"         => $stype_ids,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError());
        }
        $id_array = explode(",",$stype_ids);

        Db::startTrans();
        try {
            $salesmanTypeModel = new MstSalesmanType();

            foreach ($id_array as $stype_id){
                $res = $salesmanTypeModel
                    ->where("stype_id",$stype_id)
                    ->delete();
                if ($res === false) {
                    return $this->com_return(false, config("params.FAIL"));
                }
            }
            Db::commit();
            return $this->com_return(true, config("params.SUCCESS"));
        } catch (Exception $e) {
            Db::rollback();
            return $this->com_return(false, $e->getMessage());
        }
    }
}