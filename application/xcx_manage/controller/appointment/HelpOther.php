<?php
/**
 * Created by 棒哥的IDE.
 * Email QuBoJie@163.com
 * QQ 3106954445
 * WeChat 17703981213
 * User: QuBoJie
 * Date: 2018/12/3
 * Time: 下午3:07
 * App: LiveShowTwo
 */

namespace app\xcx_manage\controller\appointment;


use app\common\controller\ManageAuthAction;
use app\common\controller\UserCommon;
use think\Exception;
use think\Request;

class HelpOther extends ManageAuthAction
{
    /**
     * 手机号码检索
     * @param Request $request
     * @return array
     */
    public function phoneRetrieval(Request $request)
    {
        $type  = $request->param("type","");//类型,user为用户;sales为员工
        $phone = $request->param("phone","");//电话号码

        try {
            $userCommonObj = new UserCommon();
            if ($type == "user"){
                //用户检索
                $res =  $userCommonObj->userPhoneRetrieval($phone);
                if (empty($res)){
                    return $this->com_return(true,config("params.USER")['NOT_REGISTER_M']);
                }
            }elseif($type == "sales"){
                //员工检索
                $res = $userCommonObj->salesPhoneRetrieval($phone);
                if (empty($res)){
                    return $this->com_return(true,config("params.USER")['USER_NOT_EXIST']);
                }
            }else{
                return $this->com_return(false,config("params.PARAM_NOT_EMPTY"));
            }
            //将数组中的 Null  转换为 "" 空字符串
            $res = _unsetNull($res);
            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

}