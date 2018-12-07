<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/19
 * Time: 下午3:59
 */

namespace app\admin\controller\whore;


use app\common\controller\AdminAuthAction;
use app\common\model\BillCardFees;
use app\common\model\ManageSalesman;
use app\common\model\SysAdminUser;
use app\common\model\SysMenu;
use think\Exception;
use think\Request;

class Menus extends AdminAuthAction
{
    /**
     * 后台菜单列表获取
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        if ($request->method() == "OPTIONS"){
            return $this->com_return(true,"params.SUCCESS");
        }

        try {
            $Authorization = $this->request->header("authorization","");

            //查看当前用户角色
            $action_list_str = self::tokenGetAdminLoginInfo($Authorization);

            $action_list = $action_list_str['action_list'];
            $where = [];
            if ($action_list == 'all'){
                $where = [];
            }else{
                $where['id'] = array('IN',$action_list);//查询字段的值在此范围之内的做显示
            }

            $result = array('menu' => array());

            $menus = new SysMenu();
            $menus_all =  $menus
                ->where('is_show_menu','1')
                ->where($where)
                ->select();

            $menus_all = json_decode(json_encode($menus_all),true);

            for ($i=0;$i<count($menus_all);$i++){
                $id = $menus_all[$i]['id'];
                $parent = substr($id,0,3);
                $level  = substr($id,3,3);
                $last   = substr($id,-3);

                if ($level == "000"){
                    $result['menu'][] = $menus_all[$i];
                }else{
                    if ($last == 0) {
                        $level2[] = $menus_all[$i];
                    }else{
                        $level3[] = $menus_all[$i];
                    }
                }


            }
            if (isset($level2)){
                for ($m=0;$m<count($result["menu"]);$m++){
                    $menu_id = $result["menu"][$m]["id"];
                    $menu_id_p = substr($menu_id,0,3);
                    for ($n=0;$n<count($level2);$n++){
                        $level2_id = $level2[$n]['id'];
                        $parent_level2 = substr($level2_id,0,3);
                        if ($menu_id_p == $parent_level2){
                            $result["menu"][$m]["children"][] = $level2[$n];
                        }
                    }
                }
            }
            return $this->com_return(true,"获取成功",$result);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取所有的设置列表
     * @return array
     */
    public function lists()
    {
        $result = array();

        try {
            $sysMenuModel = new SysMenu();

            $menus_all =  $sysMenuModel
                ->where('is_show_menu','1')
                ->select();
            $menus_all = json_decode(json_encode($menus_all),true);
            for ($i=0;$i<count($menus_all);$i++){
                $id = $menus_all[$i]['id'];
                $parent = substr($id,0,3);
                $level  = substr($id,3,3);
                $last   = substr($id,-3);

                if ($level == "000"){
                    $result[] = $menus_all[$i];
                }else{
                    if ($last == 0) {
                        $level2[] = $menus_all[$i];
                    }else{
                        $level3[] = $menus_all[$i];
                    }
                }
            }
            if (isset($level2)){
                for ($m=0;$m<count($result);$m++){
                    $menu_id = $result[$m]["id"];
                    $menu_id_p = substr($menu_id,0,3);
                    for ($n=0;$n<count($level2);$n++){
                        $level2_id = $level2[$n]['id'];
                        $parent_level2 = substr($level2_id,0,3);
                        if ($menu_id_p == $parent_level2){
                            $result[$m]["children"][] = $level2[$n];
                        }
                    }
                }
            }

            return $this->com_return(true,"获取成功",$result);

        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * 小红点统计
     * @return array
     */
    public function menuRedDot()
    {
        try {
            $billCardModel = new BillCardFees();
            $salesmanModel = new ManageSalesman();

            $needShipCount = $billCardModel
                ->where("sale_status",config("order.open_card_status")['pending_ship']['key'])
                ->count();//待发货

            $needPayCount = $billCardModel
                ->where("sale_status",config("order.open_card_status")['pending_payment']['key'])
                ->count();//未付款总记录数

            $needVerifyCount = $salesmanModel
                ->where("statue",config("salesman.salesman_status")['pending']['key'])
                ->count();//待审核总记录数

            $is_show = $needShipCount + $needPayCount;

            $sales_is_show = $needVerifyCount;

            $res = [
                "member"        => $is_show,
                "openCardOrder" => $needPayCount,
                "giftSend"      => $needShipCount,
                "sales"         => $sales_is_show,
                "salesUser"     => $needVerifyCount
            ];

            return $this->com_return(true,config("params.SUCCESS"),$res);
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }


}