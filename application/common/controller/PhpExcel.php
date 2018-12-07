<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/11/7
 * Time: 上午10:53
 */
namespace app\common\controller;

use think\Controller;
use think\Loader;

class PhpExcel extends Controller
{
    /**
     * 按推荐人统计下载
     * @param $data
     * @param $name
     * @param $title
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportReferrer($data,$name,$title)
    {
        Loader::import('PHPExcel.PHPExcel');//引入PHPExcel.php

        $PHPExcel = new \PHPExcel();//实例化

        $PHPExcel->getActiveSheet()->setTitle($name);//当前活动sheet设置名称

        $t = 2;

        foreach ($data as $k => $v) {
            $num = $k + $t;
            $PHPExcel->setActiveSheetIndex(0)
                ->setTitle("$title")
                ->setCellValue("A1", "推荐人姓名")
                ->setCellValue("A".$num, $v['referrer_name'])
                ->setCellValue("B1", "开卡")
                ->setCellValue("B".$num, $v['open_card_sum'])
                ->setCellValue("C1", "充值")
                ->setCellValue("C".$num, $v['recharge_money_sum'])
                ->setCellValue("D1", "赠送")
                ->setCellValue("D".$num, $v['card_cash_gift_sum'] + $v['card_job_cash_gif_sum'] + $v['recharge_cash_gift']);
        }

        $PHPExcel->getActiveSheet()->getColumnDimension("A")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("B")->setWidth(14);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("C")->setWidth(17);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("D")->setWidth(17);//设置宽

        $PHPWriter = @\PHPExcel_IOFactory::createWriter($PHPExcel,"Excel2007");//创建生成格式

        header('Content-Disposition: attachment;filename='.$name.$title.'.xlsx');//下载下来的表格名
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $PHPWriter->save("php://output");//输出文件
        exit;
    }

    /**
     * 会籍明细按卡统计导出
     * @param $data
     * @param $name
     * @param $title
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportCard($data,$name,$title)
    {
        Loader::import('PHPExcel.PHPExcel');//引入PHPExcel.php

        $PHPExcel = new \PHPExcel();//实例化

        $PHPExcel->getActiveSheet()->setTitle($name);//当前活动sheet设置名称

        $t = 2;

        foreach ($data as $k => $v) {
            $num = $k + $t;
            $PHPExcel->setActiveSheetIndex(0)
                ->setTitle("$title")
                ->setCellValue("A1", "卡种")
                ->setCellValue("A".$num, $v['card_name'])
                ->setCellValue("B1", "开卡")
                ->setCellValue("B".$num, $v['open_card_sum'])
                ->setCellValue("C1", "充值")
                ->setCellValue("C".$num, $v['recharge_money_sum'])
                ->setCellValue("D1", "赠送")
                ->setCellValue("D".$num, $v['card_cash_gift_sum'] + $v['card_job_cash_gif_sum'] + $v['recharge_cash_gift']);
        }

        $PHPExcel->getActiveSheet()->getColumnDimension("A")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("B")->setWidth(14);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("C")->setWidth(17);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("D")->setWidth(17);//设置宽

        $PHPWriter = @\PHPExcel_IOFactory::createWriter($PHPExcel,"Excel2007");//创建生成格式

        header('Content-Disposition: attachment;filename='.$name.$title.'.xlsx');//下载下来的表格名
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $PHPWriter->save("php://output");//输出文件
        exit;

    }

    /**
     * 会籍明细按会员分组
     * @param $data
     * @param $name
     * @param $title
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportThree($data,$name,$title)
    {
        Loader::import('PHPExcel.PHPExcel');//引入PHPExcel.php

        $PHPExcel = new \PHPExcel();//实例化

        $PHPExcel->getActiveSheet()->setTitle($name);//当前活动sheet设置名称

        $t = 2;
        foreach ($data as $k => $v) {
            $num = $k + $t;
            $PHPExcel->setActiveSheetIndex(0)
                ->setTitle("$title")
                ->setCellValue("A1", "客户姓名")
                ->setCellValue("A".$num, $v['name'])
                ->setCellValue("B1", "电话")
                ->setCellValue("B".$num, $v['phone'])
                ->setCellValue("C1", "卡种")
                ->setCellValue("C".$num, $v['card_name'])
                ->setCellValue("D1", "推荐人")
                ->setCellValue("D".$num, $v['referrer_name'])
                ->setCellValue("E1", "开卡")
                ->setCellValue("E".$num, $v['open_card_sum'])
                ->setCellValue("F1", "充值")
                ->setCellValue("F".$num, $v['recharge_money_sum'])
                ->setCellValue("G1", "赠送")
                ->setCellValue("G".$num, $v['card_cash_gift_sum'] + $v['card_job_cash_gif_sum'] + $v['recharge_cash_gift']);
        }

        $PHPExcel->getActiveSheet()->getColumnDimension("A")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("B")->setWidth(14);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("C")->setWidth(17);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("D")->setWidth(17);//设置宽
//        $PHPExcel->getActiveSheet()->getColumnDimension("E")->setAutoSize(true);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(13);
        $PHPExcel->getActiveSheet()->getColumnDimension("F")->setWidth(14);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("G")->setWidth(14);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("H")->setWidth(14);//设置宽

        $PHPWriter = @\PHPExcel_IOFactory::createWriter($PHPExcel,"Excel2007");//创建生成格式

        header('Content-Disposition: attachment;filename='.$name.$title.'.xlsx');//下载下来的表格名
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $PHPWriter->save("php://output");//输出文件
        exit;
    }


    /**
     * 会籍明细日期分组下载
     * @param $data
     * @param $name
     * @param $title
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportTwo($data,$name,$title)
    {
        Loader::import('PHPExcel.PHPExcel');//引入PHPExcel.php

        $PHPExcel = new \PHPExcel();//实例化

        $PHPExcel->getActiveSheet()->setTitle($name);//当前活动sheet设置名称

        $t = 2;
        foreach ($data as $k => $v) {
            $num = $k + $t;
            $PHPExcel->setActiveSheetIndex(0)
                ->setTitle("$title")
                ->setCellValue("A1" , "日期")
                ->setCellValue("A".$num, $v['date_time'])
                ->setCellValue("B1", "客户姓名")
                ->setCellValue("B".$num, $v['name'])
                ->setCellValue("C1", "电话")
                ->setCellValue("C".$num, $v['phone'])
                ->setCellValue("D1", "卡种")
                ->setCellValue("D".$num, $v['card_name'])
                ->setCellValue("E1", "推荐人")
                ->setCellValue("E".$num, $v['referrer_name'])
                ->setCellValue("F1", "开卡")
                ->setCellValue("F".$num, $v['open_card_sum'])
                ->setCellValue("G1", "充值")
                ->setCellValue("G".$num, $v['recharge_money_sum'])
                ->setCellValue("H1", "赠送")
                ->setCellValue("H".$num, $v['card_cash_gift_sum'] + $v['card_job_cash_gif_sum'] + $v['recharge_cash_gift']);
        }

        $PHPExcel->getActiveSheet()->getColumnDimension("A")->setAutoSize(true);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("B")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("C")->setWidth(17);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("D")->setWidth(17);//设置宽
//        $PHPExcel->getActiveSheet()->getColumnDimension("E")->setAutoSize(true);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(11);
        $PHPExcel->getActiveSheet()->getColumnDimension("F")->setAutoSize(true);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("G")->setAutoSize(true);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("H")->setAutoSize(true);//设置宽

        $PHPWriter = @\PHPExcel_IOFactory::createWriter($PHPExcel,"Excel2007");//创建生成格式

        header('Content-Disposition: attachment;filename='.$name.$title.'.xlsx');//下载下来的表格名
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $PHPWriter->save("php://output");//输出文件
        exit;
    }

    /**
     * 导出数据
     * @param $data '二维数组'
     * @param $name '导出的表格命名'
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportOne($data,$name,$title)
    {
        Loader::import('PHPExcel.PHPExcel');//引入PHPExcel.php

        $PHPExcel = new \PHPExcel();//实例化

        $PHPExcel->getActiveSheet()->setTitle($name);//当前活动sheet设置名称

        $t = 2;
        foreach ($data as $k => $v) {
            $num = $k + $t;
            $PHPExcel->setActiveSheetIndex(0)
                ->setTitle("$name.$title")
                ->setCellValue("A1" , "日期")
                ->setCellValue("A".$num,$v['date_time'])
                ->setCellValue("B1" , "现金开卡")
                ->setCellValue("B".$num, $v['cash_pay_money'])
                ->setCellValue("C1" , "poss机开卡")
                ->setCellValue("C".$num, $v['bank_pay_money'])
                ->setCellValue("D1" , "微信支付宝")
                ->setCellValue("D".$num, $v['wx_alipay_open_card'])
                ->setCellValue("E1" , "线上开卡")
                ->setCellValue("E".$num, $v['wxpay_pay_money'])
                ->setCellValue("F1" , "赠送金额")
                ->setCellValue("F".$num, $v['open_card_give_gift'])
                ->setCellValue("G1" , "开卡合计")
                ->setCellValue("G".$num, $v['open_card_sum'])
                ->setCellValue("H1" , "现金充值")
                ->setCellValue("H".$num, $v['cash_recharge'])
                ->setCellValue("I1" , "poss机充值")
                ->setCellValue("I".$num, $v['bank_recharge'])
                ->setCellValue("J1" , "微信支付宝充值")
                ->setCellValue("J".$num, $v['wx_alipay_recharge'])
                ->setCellValue("K1" , "线上充值")
                ->setCellValue("K".$num, $v['wxpay_pay_recharge'])
                ->setCellValue("L1" , "赠送金额")
                ->setCellValue("L".$num, $v['recharge_cash_gift_sum'])
                ->setCellValue("M1" , "充值合计")
                ->setCellValue("M".$num, $v['recharge_sum'])
                ->setCellValue("N1" , "总收入")
                ->setCellValue("N".$num, $v['all_money'])
                ->setCellValue("O1" , "总赠送")
                ->setCellValue("O".$num, $v['all_give'])
                ->setCellValue("P1", "结算人")
                ->setCellValue("P".$num, $v['check_user']);
        }

        $PHPExcel->getActiveSheet()->getColumnDimension("A")->setWidth(19);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("B")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("C")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("D")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("E")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("F")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("G")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("H")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("I")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("J")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("K")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("L")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("M")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("N")->setWidth(12);//设置宽
        $PHPExcel->getActiveSheet()->getColumnDimension("O")->setWidth(12);//设置宽

        $PHPWriter = @\PHPExcel_IOFactory::createWriter($PHPExcel,"Excel2007");//创建生成格式

        header('Content-Disposition: attachment;filename='.$name.$title.'.xlsx');//下载下来的表格名
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $PHPWriter->save("php://output");//输出文件
        exit;
    }

    /**
     * 导入数据
     * @param $file_name
     */
    public static function import($file_name)
    {

    }
}