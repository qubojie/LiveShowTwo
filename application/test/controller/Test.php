<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/22
 * Time: 下午1:26
 */
namespace app\test\controller;

use app\common\controller\BaseController;
use app\common\controller\ReservationCommon;
use app\common\controller\TableCommon;
use app\common\model\Dishes;
use app\common\model\MstCardVip;
use app\common\model\MstTableImage;
use app\common\model\PageArticles;
use app\common\model\PageBanner;
use app\common\model\ResourceFile;
use app\common\model\User;
use app\services\YlyPrint;
use think\Exception;

class Test extends BaseController
{
    public function index()
    {
        return $this->printYly();
    }

    /**
     * 打印测试
     * @return array
     */
    public function printYly()
    {
        $pid       = "P2018989891321341";
        $tableName = "S007";

        $dishInfo = [
            [
                'att_name' => '热菜',
                'dis_name' => '意大利牛排',
                'quantity' => '1',
                'printer_sn' => '4004571805',
                'print_num' => '1'
            ],
            [
                'att_name' => '凉菜',
                'dis_name' => '凉拌可乐大虾',
                'quantity' => '1',
                'printer_sn' => '4004571805',
                'print_num' => '1'
            ],
            [
                'att_name' => '酒水',
                'dis_name' => '白兰地',
                'quantity' => '1',
                'printer_sn' => '4004571805',
                'print_num' => '1'
            ],
        ];

        $ylyObj       = new YlyPrint();
        $accessToken  = $ylyObj->getToken();
        $access_token = $accessToken['data']['access_token'];

        $nowTime = date("Y-m-d H:i");

        $api = new \YLYOpenApiClient();

        foreach ($dishInfo as $key => $val){

            $content = "";                          //打印内容
            $content .= '<MS>0,0</MS>';
            $content .= '<FS><center>'.$val['att_name'].'分单</center></FS>';
            $content .= '<FS2> 桌号: '.$tableName.'</FS2>'."\n";
            $content .= '单号:'.$pid.'   '.$nowTime.''."\n";
            $content .= '<FS>'.str_repeat('-',36)."</FS>\n";
            $content .= '<FS><table>';
            $content .= '<tr><td>商品</td><td>数量</td></tr>';
            $content .= '<tr><td>'.$val['dis_name'].'</td><td>'.$val['quantity'].'</td></tr>';
            $content .= '</table></FS>';
            $content .= '<FS>'.str_repeat('-',36)."</FS>\n";

            $machineCode = $val['printer_sn'];  //授权的终端号
            $originId    = '1234567890';     //商户自定义id
            $timesTamp   = time();          //当前服务器时间戳(10位)

            $print_num = $val['print_num'];

            for ($i = 0; $i < $print_num; $i ++){

                $res = $api->printIndex($machineCode,$access_token,$content,$originId,$timesTamp);
                $res = json_decode($res,true);

                if ($res['error'] != "0"){
                    //落单失败
                    return $this->com_return(false,$res['error_description'],$pid);
                }
            }
        }

        return $this->com_return(true,config('params.SUCCESS'));
    }

    //退款接口
    public function refundMoney()
    {
        $order_id  = $this->request->param("order_id","");
        $total_fee = $this->request->param("total_fee","");
        $reservationCommonObj = new ReservationCommon();
        $res = $reservationCommonObj->callBackPay("$order_id","$total_fee","$total_fee");
        return $res;
    }

    public function updateQiImageUrl()
    {
        try {
            $resourceFileModel = new MstCardVip();
            $res = $resourceFileModel
                ->select();
            $res = json_decode(json_encode($res),true);
            for ($i = 0; $i < count($res); $i ++) {
                $l = $res[$i]['card_image'];
                $new_l = str_replace("pavbmee9v.bkt.clouddn.com","t.img.nana.cn",$l);
                $p = [
                    "card_image" => $new_l
                ];
                $is_ok = $resourceFileModel
                    ->where("card_image",$l)
                    ->update($p);
                dump($is_ok);
            }
        } catch (Exception $e) {
            return $this->com_return(false, $e->getMessage(), null, 500);
        }
    }
}