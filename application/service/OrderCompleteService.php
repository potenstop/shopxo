<?php
/**
 * Created by PhpStorm.
 * User: UPC
 * Date: 2019/11/24
 * Time: 8:02
 */

namespace app\service;
use think\Db;
use think\Container;
use phpmailer\Exception;

class OrderCompleteService
{
    private static function log($log, $type = 'order')
    {
        Container::get('log')->record($log, $type);
    }
    public static function notice_scact ($order_id) 
    {
        self::log('order_id=' . $order_id.'start notice_scact');
        $order_detail_list = Db::name('OrderDetail')->where(['order_id' => $order_id])->select();
        self::log('order_id=' . $order_id.' order_detail_list=' . json_encode($order_detail_list, JSON_UNESCAPED_UNICODE));
        foreach ($order_detail_list as $order_detail) {
            $body = ['outOrderId' => $order_detail['id'], 'outProductId' => $order_detail['goods_id'], 'amount' => round($order_detail['price'] * 100, 0)];
            try {
                self::log('/mis/acc/sep/record/pay/created http request start:'. json_encode($body, JSON_UNESCAPED_UNICODE));
                $result = call_scact_api('/mis/acc/sep/record/pay/created', $body, 'POST');
                self::log('/mis/acc/sep/record/pay/created http request suc:'. json_encode($result, JSON_UNESCAPED_UNICODE));
            } catch (Exception $e) {
                self::log('/mis/acc/sep/record/pay/created http request error:'. $e->getMessage());
            }
        }
    }
}