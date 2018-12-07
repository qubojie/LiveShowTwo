<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/21
 * Time: 上午9:55
 */
namespace app\xcx_manage\controller\personal;



use app\common\controller\ManageAuthAction;
use app\common\controller\ReservationCommon;
use app\common\model\ManageSalesman;
use app\common\model\MstTableImage;
use app\common\model\TableRevenue;
use think\Exception;
use think\Request;
use think\Validate;

class ManageInfo extends ManageAuthAction
{
    /**
     * 小程序获取当前登录人员的所有信息
     * @return array
     */
    public function getManageAuth()
    {
        try {
            $token = $this->request->header("Token","");
            $manageInfo = $this->tokenGetManageInfo($token);
            return $this->com_return(true,config("params.SUCCESS"),$manageInfo);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 服务人员变更密码
     * @param Request $request
     * @return array
     */
    public function changePass(Request $request)
    {
        $old_password   = $request->param("old_password","");
        $password       = $request->param("password","");
        $remember_token = $request->header("Token","");

        $rule = [
            "old_password|旧密码" => "require|alphaNum|length:6,16",
            "password|新密码"     => "require|alphaNum|length:6,16",
        ];
        $request_res = [
            "old_password" => $old_password,
            "password"     => $password,
        ];
        $validate = new Validate($rule);
        if (!$validate->check($request_res)){
            return $this->com_return(false,$validate->getError(),null);
        }

        try {
            /*权限判断 on*/
            $manageInfo = $this->tokenGetManageInfo($remember_token);
            $statue     = $manageInfo['statue'];

            if ($statue != \config("salesman.salesman_status")['working']['key']){
                return $this->com_return(false,\config("params.MANAGE_INFO")['UsrLMT']);
            }
            /*权限判断 off*/

            $old_password = jmPassword($old_password);

            $manageModel = new ManageSalesman();

            $is_true = $manageModel
                ->where("remember_token",$remember_token)
                ->where('password',$old_password)
                ->count();
            if(!$is_true){
                return $this->com_return(false,config("params.PASSWORD_PP"));
            }
            $new_token = jmToken($password.time().$old_password);

            $params = [
                "password"       => jmPassword($password),
                "remember_token" => $new_token,
                "updated_at"     => time()
            ];
            $is_ok = $manageModel
                ->where('remember_token',$remember_token)
                ->update($params);

            if ($is_ok !== false){
                return $this->com_return(true,config("params.SUCCESS"),$new_token);
            }else{
                return $this->com_return(false,config("params.FAIL"));
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 我的预约列表
     * @param Request $request
     * @return array
     */
    public function reservationOrder(Request $request)
    {
        $pagesize = $request->param("pagesize",config('xcx_page_size'));//显示个数,不传时为10
        $nowPage  = $request->param("nowPage","1");
        $status   = $request->param("status",'');//  0待付定金或结算   1 预定成功   2已开台  3已清台   9取消预约

        if (empty($status))  $status = 0;
        $where_status['tr.status'] = ["eq",$status];
        $config = [
            "page" => $nowPage,
        ];
        try {
            $token       =  $request->header('Token','');
            $manageInfo  = $this->tokenGetManageInfo($token);
            $sid = $manageInfo['sid'];
            $tableRevenueModel = new TableRevenue();
            $list = $tableRevenueModel
                ->alias("tr")
                ->join("mst_table t","t.table_id = tr.table_id")
                ->join("mst_table_appearance tap","tap.appearance_id = t.appearance_id","LEFT")
                ->join("mst_table_area ta","ta.area_id = t.area_id","LEFT")
                ->join("mst_table_location tl","ta.location_id = tl.location_id","LEFT")
                ->join("manage_salesman ms","ms.sid = tr.sid","LEFT")
                ->join("user u","u.uid = tr.uid")
                ->where('tr.sid',$sid)
                ->where($where_status)
                ->field("tr.trid,tr.status,tr.type,tr.table_id,tr.reserve_way,tr.reserve_time,tr.subscription,tr.turnover_limit,tr.buid")
                ->field("t.table_no")
                ->field("u.uid,u.name,u.nickname,u.phone as userPhone")
                ->field("ms.sid,ms.sales_name,ms.phone")
                ->field("tl.location_title")
                ->field("ta.area_title")
                ->field("tap.appearance_title")
                ->paginate($pagesize,false,$config);
            $list = json_decode(json_encode($list),true);
            $data = $list["data"];
            $tableImageModel = new MstTableImage();

            for ($i = 0; $i <count($data); $i++){
                $table_id = $data[$i]['table_id'];
                $tableImage = $tableImageModel
                    ->where('table_id',$table_id)
                    ->field("image")
                    ->select();
                $tableImage = json_decode(json_encode($tableImage),true);
                for ($m = 0; $m < count($tableImage); $m++){
                    $list["data"][$i]['image_group'][] = $tableImage[$m]['image'];
                }
            }
            return $this->com_return(true,config("params.SUCCESS"),$list);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}