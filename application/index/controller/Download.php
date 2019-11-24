<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2019 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\index\controller;
use phpmailer\Exception;
use think\Container;
use think\Db;
/**
 * 下载
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2017-03-02T22:48:35+0800
 */
class Download extends Common
{
    /**
     * 构造方法
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-11-30
     * @desc    description
     */
    public function __construct()
    {
        parent::__construct();
    }
    private function log($log, $type = 'download')
    {
        Container::get('log')->record($log, $type);
    }
    public function Ticket () {
        $time = time();
        $params = input();
        if (!isset($params['id'])) {
            return '参数错误';
        }
        $this->log('开始下载 order_id:'.$params['id']);
        $order_detail_list = Db::name('OrderDetail')->where(['order_id' => $params['id']])->select();
        $xlsData = [];

        foreach ($order_detail_list as $order_detail) {
            try {
                $result = call_scact_api('/mis/product/ticket/info', ['outOrderId' => $order_detail['id'], 'outProductId' => $order_detail['goods_id']], 'GET');
                $this->log('券列表下载 /mis/product/ticket/info order_detail:'.json_encode($order_detail, JSON_UNESCAPED_UNICODE).' result:'.json_encode($result, JSON_UNESCAPED_UNICODE));
                if ($result['retCode'] == 0) {
                    $xlsData = array_merge($xlsData, $result['data']);
                }
            } catch (Exception $e) {
                $this->log('下载异常 order_id:'.$params['id'] . ' order_detail:'.json_encode($order_detail, JSON_UNESCAPED_UNICODE).' message'.$e->getMessage());
            }
        }
        foreach ($xlsData as &$item) {
            $item['nominalValue'] = round($item['nominalValue'] / 100, 2);
        }
        unset($item);
        $this->log('券列表写execl order_detail:'.json_encode($xlsData, JSON_UNESCAPED_UNICODE));
        //这里引入PHPExcel文件注意路径修改
        $excel = new \base\Excel(array('filename'=>'券码', 'title'=>[
            'ticketId' =>  [
                'name' => 'ID',
                'type' => 'string',
            ],
            'ticketCode' =>  [
                'name' => '券码',
                'type' => 'string',
                'width' => '120'
            ],
            'ticketPwd' =>  [
                'name' => '券密',
                'type' => 'string',
            ],
            'nominalValue' => [
                'name' => '面值',
                'type' => 'string',
                'width' => '60'
            ],
            'createTime'      =>  [
                'name' => '创建时间',
                'type' => 'string',
                'width' => '100'
            ],
            'updateTime'      =>  [
                'name' => '更新时间',
                'type' => 'string',
                'width' => '100'
            ],
        ], 'data'=>$xlsData, 'msg'=>'没有相关数据'));
        return $excel->Export();
    }
}
?>