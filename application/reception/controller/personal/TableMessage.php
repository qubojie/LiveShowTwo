<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 下午2:50
 */

namespace app\reception\controller\personal;


use app\common\controller\ReceptionAuthAction;
use think\Exception;
use think\Request;

class TableMessage extends ReceptionAuthAction
{
    /**
     * 消息列表
     * @param Request $request
     * @return array
     */
    public function messageList(Request $request)
    {
        if ($request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }

        try {
            $tableMessageModel = new \app\common\model\TableMessage();

            $list = $tableMessageModel
                ->where("status",0)
                ->order("created_at DESC")
                ->select();
            $list = json_decode(json_encode($list),true);

            for ($i = 0; $i < count($list); $i ++){
                if ($list[$i]['type'] == "revenue"){
                    $list[$i]['type_name'] = "预约通知";
                }else{
                    $list[$i]['type_name'] = "服务呼叫";
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 确认消息
     * @param Request $request
     * @return array
     */
    public function confirm(Request $request)
    {
        if ($request->method() == "OPTIONS"){
            return $this->com_return(true,config("params.SUCCESS"));
        }

        $message_id = $request->param("message_id","");//消息id
        $token      = $request->header("Token");

        try {
            $manageInfo = $this->receptionTokenGetManageInfo($token);
            $params = [
                "status"       => "1",
                "check_user"   => $manageInfo['sales_name'],
                "check_time"   => time(),
                "check_reason" => "手动确认",
                "updated_at"   => time()
            ];
            $tableMessageModel = new \app\common\model\TableMessage();
            $is_ok = $tableMessageModel
                ->where("message_id",$message_id)
                ->update($params);
            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"));
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }




}