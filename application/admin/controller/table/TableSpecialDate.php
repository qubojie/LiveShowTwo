<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 上午11:19
 */

namespace app\admin\controller\table;


use app\common\controller\AdminAuthAction;
use app\common\model\MstTableReserveDate;
use think\Exception;
use think\Request;
use think\Validate;

class TableSpecialDate extends AdminAuthAction
{
    /**
     * 特殊日期列表
     * @return array
     */
    public function index()
    {
        $pagesize = $this->request->param("pagesize",config('page_size'));//显示个数,不传时为10
        $nowPage = $this->request->param("nowPage","1");
        if (empty($pagesize)) $pagesize = config('page_size');

        $config = [
            "page" => $nowPage,
        ];
        try {
            $specialDateModel = new MstTableReserveDate();

            $column = $specialDateModel->column;

            $list  = $specialDateModel
                ->field($column)
                ->paginate($pagesize,false,$config);

            return $this->com_return(true,config("params.SUCCESS"),$list);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(),null,500);
        }
    }

    /**
     * 特殊日期添加
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $appointment     = $request->param('appointment','');//指定押金预定日期
//        $type            = $request->param('type','');//日期类型   0普通日  1周末假日  2节假日
        $subscription    = $request->param('subscription','');//订台押金
        $desc            = $request->param('desc','');//
        $is_revenue      = $request->param('is_revenue','');//是否允许预定  0否  1是
        $is_refund_sub   = $request->param('is_refund_sub','');//是否可退押金 0不退  1退
        $refund_end_time = $request->param('refund_end_time','');//可退押金场合的截止时间
        $is_enable       = $request->param('is_enable','');//是否启用  0否 1是

        $rule = [
            "appointment|指定押金预定日期" => "require",
//            "type|日期类型"               => "require",
            "subscription|订台押金"      => "require",
            "is_revenue|是否允许预定"     => "require",
            "is_refund_sub|是否可退押金"  => "require",
            "is_enable|是否启用"          => "require",
            "desc|描述"                  => "max:100",
        ];

        $check_data = [
            "appointment"   => $appointment,
//            "type"          => $type,
            "subscription"  => $subscription,
            "desc"          => $desc,
            "is_revenue"    => $is_revenue,
            "is_refund_sub" => $is_refund_sub,
            "is_enable"     => $is_enable,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $specialDateModel = new MstTableReserveDate();
            $is_exist = $specialDateModel
                ->where('appointment',$appointment)
                ->count();
            if ($is_exist > 0){
                return $this->com_return(false,config("params.DATE_IS_EXIST"));
            }

            $insert_data = [
                "appointment"     => $appointment,
//                "type"            => $type,
                "desc"            => $desc,
                "subscription"    => $subscription,
                "is_revenue"      => $is_revenue,
                "is_refund_sub"   => $is_refund_sub,
                "refund_end_time" => $refund_end_time,
                "is_enable"       => $is_enable,
                'created_at'      => time(),
                'updated_at'      => time()
            ];

            $is_ok = $specialDateModel
                ->insert($insert_data);
            if ($is_ok){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 特殊日期编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        $appointment      = $request->param('appointment','');
        $subscription     = $request->param('subscription','');//
        $desc             = $request->param('desc','');//
        $is_enable        = $request->param('is_enable','');//是否启用  0否 1是
        $is_revenue       = $request->param('is_revenue','');//是否允许预定  0否  1是
        $is_refund_sub    = $request->param('is_refund_sub','');//是否可退押金 0不退  1退
        $refund_end_time  = $request->param('refund_end_time','');//是否可退押金 0不退  1退

        $rule = [
            "appointment|指定押金预定日期" => "require",
            "subscription|订台押金"       => "max:100",
            "desc|描述"                  => "max:100",
        ];

        $check_data = [
            "appointment"    => $appointment,
            "subscription"   => $subscription,
            "desc"           => $desc,
        ];

        $validate = new Validate($rule);

        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        $update_data = [
            'desc'              => $desc,
            'subscription'      => $subscription,
            'is_enable'         => $is_enable,
            'is_revenue'        => $is_revenue,
            'is_refund_sub'     => $is_refund_sub,
            'refund_end_time'   => $refund_end_time,
            'updated_at'        => time()
        ];

        try {
            $specialDateModel = new MstTableReserveDate();
            $is_ok = $specialDateModel
                ->where('appointment',$appointment)
                ->update($update_data);
            if ($is_ok){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 特殊日期删除
     * @param Request $request
     * @return array]
     */
    public function delete(Request $request)
    {
        $appointment = $request->param('appointment','');
        $rule = [
            "appointment|指定押金预定日期" => "require",
        ];
        $check_data = [
            "appointment"    => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            $specialDateModel = new MstTableReserveDate();
            $is_ok = $specialDateModel
                ->where('appointment',$appointment)
                ->delete();
            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }

    }

    /**
     * 是否启用
     * @param Request $request
     * @return array
     */
    public function enable(Request $request)
    {
        $is_enable    = (int)$request->param("is_enable","");
        $appointment  = $request->param("appointment","");


        $rule = [
            "is_enable|是否激活"          => "require",
            "appointment|指定押金预定日期" => "require",
        ];
        $check_data = [
            "is_enable"          => $is_enable,
            "appointment"   => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        $update_data = [
            'is_enable'  => $is_enable,
            'updated_at' => time()
        ];

        try {
           return $this->isOkAction($appointment,$update_data);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 是否开启预约
     * @param Request $request
     * @return array
     */
    public function revenue(Request $request)
    {
        $is_revenue    = (int)$request->param("is_revenue","");
        $appointment  = $request->param("appointment","");

        $rule = [
            "is_revenue|是否允许预约"          => "require",
            "appointment|指定押金预定日期" => "require",
        ];
        $check_data = [
            "is_revenue"  => $is_revenue,
            "appointment" => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }
        $update_data = [
            'is_revenue'  => $is_revenue,
            'updated_at' => time()
        ];

        try {
            return $this->isOkAction($appointment,$update_data);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 是否启用,是否允许预定,是否可退押金
     * @param Request $request
     * @return array
     */
    public function enableOrRevenueOrRefund(Request $request)
    {
        $type          = $request->param("type","");
        $status        = (int)$request->param("status","");
        $appointment   = $request->param("appointment","");

        $rule = [
            "type|类型" => "require",
            "appointment|指定押金预定日期" => "require",
        ];
        $check_data = [
            "type"          => $type,
            "appointment"   => $appointment,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_data)){
            return $this->com_return(false,$validate->getError());
        }

        if ($type == "is_revenue" || $type == "is_refund_sub" || $type == "is_enable"){
            try {
                $update_data = [
                    $type        => $status,
                    'updated_at' => time()
                ];
                return $this->isOkAction($appointment,$update_data);
            } catch (Exception $e) {
                return $this->com_return(false, $e->getMessage(), null, 500);
            }
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

    public function isOkAction($appointment,$update_data)
    {
        $specialDateModel = new MstTableReserveDate();
        $is_ok = $specialDateModel
            ->where('appointment',$appointment)
            ->update($update_data);

        if ($is_ok !== false){
            return $this->com_return(true,config("params.SUCCESS"));
        }else{
            return $this->com_return(false,config("params.FAIL"));
        }
    }

}