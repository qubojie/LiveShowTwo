<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/6
 * Time: 下午6:16
 * App: LiveShowTwo
 */

namespace app\xcx_manage\controller\appointment;


use app\common\controller\ManageAuthAction;
use app\common\controller\TableCommon;
use think\Exception;
use think\Validate;

class TableAction extends ManageAuthAction
{
    /**
     * 清台
     * @return array
     */
    public function cleanTable()
    {
        $buid   = $this->request->param("buid","");//开台buid
        $rule = [
            "buid|开台id" => "require",
        ];
        $check_res = [
            "buid" => $buid,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($check_res)){
            return $this->com_return(false,$validate->getError());
        }

        try {
            /*登陆前台人员信息 on*/
            $token       = $this->request->header("Token",'');
            $manageInfo  = $this->tokenGetManageInfo($token);
            $stype_name  = $manageInfo["stype_name"];
            $sales_name  = $manageInfo["sales_name"];
            $action_user = $stype_name . " ". $sales_name;
            /*登陆前台人员信息 off*/
            $tableCommonObj = new TableCommon();
            $res = $tableCommonObj->cleanTablePublic($buid,$action_user);
            return $res;
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}