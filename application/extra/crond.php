<?php
/**
 * Created by PhpStorm.
 * User: qubojie
 * Date: 2018/7/2
 * Time: 下午5:29
 */
$crond_list = array(
    '*' => [
        'app\index\controller\ChangeStatus::changeOrderStatus',
        'app\index\controller\ChangeStatus::AutoFinishTime',
        'app\index\controller\ChangeStatus::AutoCancelTableRevenue',
        'app\index\controller\ChangeStatus::AutoCancelBillRefill',
        'app\index\controller\ChangeStatus::autoCancelRevenueListOrder',
//        'app\index\controller\ChangeStatus::autoCancelBillPayAssistOrder',
    ],  //每分钟

    '00:00'      => [
        'app\index\controller\ChangeStatus::AutoDeleteCallMessage',
    ],  //每周 ------------
    '*-01 00:00' => [],  //每月--------
    '*:00'       => [],  //每小时---------
);


return $crond_list;

