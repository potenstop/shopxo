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
use think\facade\Hook;
use app\service\GoodsService;
use app\service\GoodsCommentsService;
use app\service\SeoService;
use think\Container;

/**
 * 商品详情
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class Goods extends Common
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

    /**
     * 详情
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-12-02T23:42:49+0800
     */
    public function Index()
    {
        $goods_id = input('id');
        $params = [
            'where' => [
                'id'                => $goods_id,
                'is_delete_time'    => 0,
            ],
            'is_photo'  => true,
            'is_spec'   => true,
        ];
        $ret = GoodsService::GoodsList($params);
        if(empty($ret['data'][0]) || $ret['data'][0]['is_delete_time'] != 0)
        {
            $this->assign('msg', '资源不存在或已被删除');
            return $this->fetch('/public/tips_error');
        } else {
            // 当前登录用户是否已收藏
            $ret_favor = GoodsService::IsUserGoodsFavor(['goods_id'=>$goods_id, 'user'=>$this->user]);
            $ret['data'][0]['is_favor'] = ($ret_favor['code'] == 0) ? $ret_favor['data'] : 0;

            // 商品评价总数
            $ret['data'][0]['comments_count'] = GoodsCommentsService::GoodsCommentsTotal(['goods_id'=>$goods_id, 'is_show'=>1]);

            // 商品收藏总数
            $ret['data'][0]['favor_count'] = GoodsService::GoodsFavorTotal(['goods_id'=>$goods_id]);

            // 钩子
            $this->PluginsHook($goods_id, $ret['data'][0]);

            // 商品数据
            $this->assign('goods', $ret['data'][0]);

            // seo
            $seo_title = empty($ret['data'][0]['seo_title']) ? $ret['data'][0]['title'] : $ret['data'][0]['seo_title'];
            $this->assign('home_seo_site_title', SeoService::BrowserSeoTitle($seo_title, 2));
            if(!empty($ret['data'][0]['seo_keywords']))
            {
                $this->assign('home_seo_site_keywords', $ret['data'][0]['seo_keywords']);
            }
            if(!empty($ret['data'][0]['seo_desc']))
            {
                $this->assign('home_seo_site_description', $ret['data'][0]['seo_desc']);
            }

            // 二维码
            $this->assign('qrcode_url', MyUrl('index/qrcode/index', ['content'=>urlencode(base64_encode(MyUrl('index/goods/index', ['id'=>$goods_id], true, true)))]));

            // 商品评分
            $goods_score = GoodsCommentsService::GoodsCommentsScore($goods_id);
            $this->assign('goods_score', $goods_score['data']);

            // 商品访问统计
            GoodsService::GoodsAccessCountInc(['goods_id'=>$goods_id]);

            // 用户商品浏览
            GoodsService::GoodsBrowseSave(['goods_id'=>$goods_id, 'user'=>$this->user]);

            // 左侧商品 看了又看
            $params = [
                'where'     => [
                    'is_delete_time'=>0,
                    'is_shelves'=>1
                ],
                'order_by'  => 'access_count desc',
                'field'     => 'id,title,title_color,price,images',
                'n'         => 10,
            ];
            $right_goods = GoodsService::GoodsList($params);
            $this->assign('left_goods', $right_goods['data']);

            // 详情tab商品 猜你喜欢
            $params = [
                'where'     => [
                    'is_delete_time'=>0,
                    'is_shelves'=>1,
                    'is_home_recommended'=>1,
                ],
                'order_by'  => 'sales_count desc',
                'field'     => 'id,title,title_color,price,images,home_recommended_images',
                'n'         => 16,
            ];
            $like_goods = GoodsService::GoodsList($params);
            $this->assign('detail_like_goods', $like_goods['data']);

            return $this->fetch();
        }
    }

    /**
     * 钩子处理
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-04-22
     * @desc    description
     * @param   [int]             $goods_id [商品id]
     * @param   [array]           $params   [输入参数]
     */
    private function PluginsHook($goods_id, &$goods)
    {
        // 商品页面相册内部钩子
        $hook_name = 'plugins_view_goods_detail_photo_within';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面相册底部钩子
        $hook_name = 'plugins_view_goods_detail_photo_bottom';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));
        
        // 商品页面基础信息顶部钩子
        $hook_name = 'plugins_view_goods_detail_base_top';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面基础信息面板底部钩子
        $hook_name = 'plugins_view_goods_detail_panel_bottom';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面基础信息面板底部钩子
        $hook_name = 'plugins_view_goods_detail_base_bottom';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面tabs顶部钩子
        $hook_name = 'plugins_view_goods_detail_tabs_top';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面tabs顶部钩子
        $hook_name = 'plugins_view_goods_detail_tabs_bottom';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面左侧顶部钩子
        $hook_name = 'plugins_view_goods_detail_left_top';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面基础信息标题里面钩子
        $hook_name = 'plugins_view_goods_detail_title';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));

        // 商品页面基础信息面板售价顶部钩子
        $hook_name = 'plugins_view_goods_detail_panel_price_top';
        $this->assign($hook_name.'_data', Hook::listen($hook_name,
            [
                'hook_name'    => $hook_name,
                'is_backend'   => false,
                'goods_id'     => $goods_id,
                'goods'        => &$goods,
            ]));
    }

    /**
     * 商品收藏
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-13
     * @desc    description
     */
    public function Favor()
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return $this->error('非法访问');
        }
        
        // 是否登录
        $this->IsLogin();

        // 开始处理
        $params = input('post.');
        $params['user'] = $this->user;
        return GoodsService::GoodsFavor($params);
    }

    /**
     * 商品规格类型
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-12-14
     * @desc    description
     */
    public function SpecType()
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return $this->error('非法访问');
        }

        // 开始处理
        $params = input('post.');
        return GoodsService::GoodsSpecType($params);
    }

    /**
     * 商品规格信息
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-12-14
     * @desc    description
     */
    public function SpecDetail()
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return $this->error('非法访问');
        }

        // 开始处理
        $params = input('post.');
        return GoodsService::GoodsSpecDetail($params);
    }

    /**
     * 商品评论
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-05-13T21:47:41+0800
     */
    public function Comments()
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return $this->error('非法访问1');
        }

        // 参数
        $params = input();
        if(empty($params['goods_id']))
        {
            return DataReturn('参数有误', -1);
        }

        // 分页
        $number = 10;
        $page = max(1, isset($params['page']) ? intval($params['page']) : 1);

        // 条件
        $where = [
            'goods_id'      => $params['goods_id'],
            'is_show'       => 1,
        ];

        // 获取总数
        $total = GoodsCommentsService::GoodsCommentsTotal($where);
        $page_total = ceil($total/$number);
        $start = intval(($page-1)*$number);

        // 获取列表
        $data_params = array(
            'm'         => $start,
            'n'         => $number,
            'where'     => $where,
            'is_public' => 1,
        );
        $data = GoodsCommentsService::GoodsCommentsList($data_params);

        // 返回数据
        $result = [
            'number'            => $number,
            'total'             => $total,
            'page_total'        => $page_total,
            'data'              => $this->fetch(null, ['data'=>$data['data']]),
        ];
        return DataReturn('请求成功', 0, $result);
    }
    private function make_password( $length = 8 )
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        // 在 $chars 中随机取 $length 个数组元素键名
        $keys = array_rand($chars, $length);
        $password = '';
        for($i = 0; $i < $length; $i++)
        {
            // 将 $length 个数组元素连接成字符串
            $password .= $chars[$keys[$i]];
        }
        return $password;
    }
    private function log($log, $type = 'push')
    {
        Container::get('log')->record($log, $type);
    }
    public function Push () {
        $result = ['retCode' => 1, 'data'=> [], 'retMsg' => ''];
        // 是否ajax
        if(!IS_AJAX)
        {
            $result['retMsg'] = '不是ajax';
            return $result;
        }
        // 开始操作
        $params = input('post.');
        $this->log('start push params:'. json_encode($params, JSON_UNESCAPED_UNICODE));
        if (!is_array($params)) {
            $result['retMsg'] = '参数不是数组';
            return $result;
        }
        foreach ($params as $item) {
            $this->log('start push item:'. json_encode($item, JSON_UNESCAPED_UNICODE));
            if (!isset($item['productId'])) {
                $result['retMsg'] = '产品id错误';
                return $result;
            }
            if (!isset($item['price'])) {
                $result['retMsg'] = '产品价格错误';
                return $result;
            }
            $default_data_stt =  '
                        {
              "title_color": "",
              "title": "",
              "simple_desc": "",
              "model": "",
              "inventory_unit": "件",
              "give_integral": "11",
              "buy_min_number": "1",
              "buy_max_number": "",
              "home_recommended_images": "/static/upload/images/goods/2019/01/14/1547453895416529.jpg",
              "specifications_price": [
                "168.00"
              ],
              "specifications_number": [
                "99999999"
              ],
              "specifications_weight": [
                ""
              ],
              "specifications_coding": [
                ""
              ],
              "specifications_barcode": [
                ""
              ],
              "specifications_original_price": [
                "99999999"
              ],
              "specifications_extends": [
                ""
              ],
              "photo": [
                "/static/upload/images/goods/2019/01/14/1547453895416529.jpg"
              ],
              "content_app_images_543": "/static/upload/images/goods/2019/01/14/1547453910353340.jpg",
              "content_app_text_543": "",
              "content_web": "<p>图文&nbsp;<br/></p>",
              "seo_title": "",
              "seo_keywords": "",
              "seo_desc": "",
              "place_origin": "0",
              "category_id": "68",
              "is_deduction_inventory": "1",
              "is_shelves": "1",
              "is_home_recommended": "1"
            }';
            $default_data = json_decode($default_data_stt, JSON_UNESCAPED_UNICODE);
            $default_data['title'] = '活动产品' . time() . $this->make_password(5);
            $default_data['specifications_price'] = [number_format($item['price']/100,2)];
            $this->log('start insert default_data:'. json_encode($default_data, JSON_UNESCAPED_UNICODE));
            $ret = ['code' => 1, 'message' => '插入异常'];
            try {
                $ret = GoodsService::GoodsSave($default_data);
                $this->log('start insert suc default_data:'. json_encode($default_data, JSON_UNESCAPED_UNICODE) . ' ret:' . json_encode($ret, JSON_UNESCAPED_UNICODE));
            } catch (Exception $e) {
                $ret['message'] = $e->getMessage();
                $this->log('insert goods error message:'.$e->getMessage());
            }
            if ($ret['code'] != 0) {
                $result['retMsg'] = '创建失败:' . json_encode($ret, JSON_UNESCAPED_UNICODE);
                return $result;
            }
            $item['productName'] = $default_data['title'];
            $item['outProductId'] = $ret['data']['goods_id'];
            array_push($result['data'], $item);
        }
        $result['retCode'] = 0;
        $result['retMsg'] = 'suc';
        $this->log('start insert end: params'. json_encode($params, JSON_UNESCAPED_UNICODE) . ' result:' . json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    } 
}
?>