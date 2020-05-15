<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/7/18
 * Time: 12:11
 */

namespace app\modules\api\models;

use Alipay\AlipayRequestFactory;
use app\hejiang\ApiCode;
use app\hejiang\ApiResponse;
use app\models\common\api\CommonOrder;
use app\models\douyin\MpConfig;
use app\models\FormId;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderUnion;
use app\models\OrderWarn;
use app\models\User;
use luweiss\wechat\Wechat;

use app\models\YyOrder;
use app\models\MsOrder;
use app\models\PtOrder;

/**
 * @property User $user
 * @property Order $order
 */
class OrderPayDataForm extends ApiModel
{
    public $store_id;
    public $order_id;
    public $order_id_list;
    public $pay_type;
    public $user;
    public $form_id;
    public $parent_user_id;
    public $condition;
    public $pay_way;
    public $out_order_no;
    public $unifiedorderUrl =  'https://api.mch.weixin.qq.com/pay/unifiedorder';

    /** @var  Wechat $wechat */
    private $wechat;
    private $order;


    public function rules()
    {
        return [
            [['pay_type'], 'required'],
            [['pay_type'], 'in', 'range' => ['ALIPAY', 'WECHAT_PAY', 'HUODAO_PAY', 'BALANCE_PAY']],
            [['pay_way','out_order_no'], 'trim'],
            [['form_id', 'order_id_list'], 'string'],
            [['order_id', 'parent_user_id', 'condition'], 'integer'],
        ];
    }

    public function detail()
    {
        $order = Order::findOne([
            'order_no' => $this->out_order_no
        ]);

        $YyOrder = YyOrder::findOne([
            'order_no' => $this->out_order_no,
        ]);
        $MsOrder = MsOrder::findOne([
            'order_no' => $this->out_order_no,
        ]);

        $OrderUnion = OrderUnion::findOne([
            'order_no' => $this->out_order_no,
        ]);
        $PtOrder = PtOrder::findOne([
            'order_no' => $this->out_order_no,
        ]);

        if ($order){
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $order->is_pay,
            ];
        }elseif($YyOrder){
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $YyOrder->is_pay,
            ];
        }elseif($MsOrder){
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $MsOrder->is_pay,
            ];
        }elseif($OrderUnion){
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $OrderUnion->is_pay,
            ];
        }elseif($PtOrder){
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $PtOrder->is_pay,
            ];
        }else{
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => 0,
            ];
        }

    }

    public function search()
    {
        $this->wechat = $this->getWechat();
        if (!$this->validate()) {
            return $this->errorResponse;
        }
        $this->user->money = doubleval($this->user->money);
        if ($this->order_id_list) {
            $order_id_list = json_decode($this->order_id_list, true);
            if (is_array($order_id_list) && count($order_id_list) == 1) {
                $this->order_id = $order_id_list[0];
                $this->order_id_list = '';
            }
        }
        if ($this->order_id) { //单个订单付款
            $this->order = Order::findOne([
                'store_id' => $this->store_id,
                'id' => $this->order_id,
            ]);
            if (!$this->order) {
                return [
                    'code' => 1,
                    'msg' => '订单不存在',
                ];
            }
            if ($this->order->is_delete == 1 || $this->order->is_cancel == 1) {
                return [
                    'code' => 1,
                    'msg' => '订单已取消',
                ];
            }
            try {
                $this->checkGoodsConfine($this->order);
            } catch (\Exception $e) {
                return [
                    'code' => ApiCode::CODE_ERROR,
                    'msg' => $e->getMessage()
                ];
            }

            $commonOrder = CommonOrder::saveParentId($this->parent_user_id);

            $goods_names = '';
            $goods_list = OrderDetail::find()->alias('od')->leftJoin(['g' => Goods::tableName()], 'g.id=od.goods_id')->where([
                'od.order_id' => $this->order->id,
                'od.is_delete' => 0,
            ])->select('g.name')->asArray()->all();
            foreach ($goods_list as $goods) {
                $goods_names .= $goods['name'] . ';';
            }
            $goods_names = mb_substr($goods_names, 0, 32, 'utf-8');

            $this->setReturnData($this->order);
            $this->order->order_union_id = 0;
            $this->order->save();
            if ($this->pay_type == 'WECHAT_PAY') {
                if ($this->order->pay_price == 0) {

                    $this->order->is_pay = 1;
                    $this->order->pay_type = 1;
                    $this->order->pay_time = time();
                    $this->order->save();

                    //支付完成后，相关操作
                    $form = new OrderWarn();
                    $form->order_id = $this->order->id;
                    $form->order_type = 0;
                    $form->notify();

                    return [
                        'code' => 0,
                        'msg' => '0元支付',
                        'data' => [
                            'price' => 0
                        ]
                    ];
                }

                // 支付宝
                if (\Yii::$app->fromAlipayApp()) {
                    return $this->alipayUnifiedOrder($goods_names);
                }
                //微信
                if (\Yii::$app->fromWechatApp()) {
                    $res = $this->unifiedOrder($goods_names);
                    if (isset($res['code']) && $res['code'] == 1) {
                        return $res;
                    }
                }
                //TouTiao
                if (\Yii::$app->fromTouTiaoApp()) {
                    return $this->dyUnifiedOrder($goods_names);
                }

                //记录prepay_id发送模板消息用到
                FormId::addFormId([
                    'store_id' => $this->store_id,
                    'user_id' => $this->user->id,
                    'wechat_open_id' => $this->user->wechat_open_id,
                    'form_id' => $res['prepay_id'],
                    'type' => 'prepay_id',
                    'order_no' => $this->order->order_no,
                ]);

                $pay_data = [
                    'appId' => $this->wechat->appId,
                    'timeStamp' => '' . time(),
                    'nonceStr' => md5(uniqid()),
                    'package' => 'prepay_id=' . $res['prepay_id'],
                    'signType' => 'MD5',
                ];
                $pay_data['paySign'] = $this->wechat->pay->makeSign($pay_data);
                return [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => (object)$pay_data,
                    'res' => $res,
                    'body' => $goods_names,
                ];
            }
            //货到付款和余额支付数据处理
            if ($this->pay_type == 'HUODAO_PAY' || $this->pay_type == 'BALANCE_PAY') {
                $order = $this->order;
                //余额支付  用户余额变动
                if ($this->pay_type == 'BALANCE_PAY') {
                    $user = User::findOne(['id' => $order->user_id]);
                    if ($user->money < $order->pay_price) {
                        return [
                            'code' => 1,
                            'msg' => '支付失败，余额不足',
                        ];
                    }
                    $user->money -= floatval($order->pay_price);
                    $user->save();
                    $order->is_pay = 1;
                    $order->pay_type = 3;
                    $order->pay_time = time();
                    $order->save();
                }
                //支付完成后，相关操作
                $form = new OrderWarn();
                $form->order_id = $order->id;
                $form->order_type = 0;
                $form->notify();

                return [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => '',
                ];
            }
        } elseif ($this->order_id_list) { //多个订单合并付款
            $order_id_list = json_decode($this->order_id_list, true);
            if (!$order_id_list) {
                return [
                    'code' => 1,
                    'msg' => '数据错误：订单格式不正确。',
                ];
            }
            $order_list = [];
            $total_pay_price = 0;
            foreach ($order_id_list as $order_id) {
                $order = Order::findOne([
                    'store_id' => $this->store_id,
                    'id' => $order_id,
                    'is_delete' => 0,
                ]);
                if (!$order) {
                    return [
                        'code' => 1,
                        'msg' => '订单不存在',
                    ];
                }
                if ($order->is_pay == 1) {
                    return [
                        'code' => 1,
                        'msg' => '存在已付款的订单，订单合并支付失败，请到我的订单重新支付。',
                    ];
                }
                try {
                    $this->checkGoodsConfine($order);
                } catch (\Exception $e) {
                    return [
                        'code' => ApiCode::CODE_ERROR,
                        'msg' => $e->getMessage()
                    ];
                }
                $order_list[] = $order;
                $total_pay_price += doubleval($order->pay_price);

                $this->setReturnData($order);
            }

            //微信支付
            if ($this->pay_type == 'WECHAT_PAY') {
                $res = $this->unifiedUnionOrder($order_list, $total_pay_price);
                if (isset($res['code']) && $res['code'] == 1) {
                    return $res;
                }
                //记录prepay_id发送模板消息用到
                FormId::addFormId([
                    'store_id' => $this->store_id,
                    'user_id' => $this->user->id,
                    'wechat_open_id' => $this->user->wechat_open_id,
                    'form_id' => $res['prepay_id'],
                    'type' => 'prepay_id',
                    'order_no' => $res['order_no'],
                ]);

                $pay_data = [
                    'appId' => $this->wechat->appId,
                    'timeStamp' => '' . time(),
                    'nonceStr' => md5(uniqid()),
                    'package' => 'prepay_id=' . $res['prepay_id'],
                    'signType' => 'MD5',
                ];
                $pay_data['paySign'] = $this->wechat->pay->makeSign($pay_data);
                return [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => (object)$pay_data,
                    'res' => $res,
                    'body' => $res['body'],
                ];
            }
            //货到付款和余额支付数据处理
            if ($this->pay_type == 'HUODAO_PAY' || $this->pay_type == 'BALANCE_PAY') {
                //余额支付  用户余额变动
                if ($this->pay_type == 'BALANCE_PAY') {
                    if ($this->user->money < $total_pay_price) {
                        return [
                            'code' => 1,
                            'msg' => '支付失败，余额不足',
                        ];
                    }
                    $this->user->money = $this->user->money - $total_pay_price;
                    $this->user->save();
                    foreach ($order_list as $order) {
                        $order->is_pay = 1;
                        $order->pay_type = 3;
                        $order->pay_time = time();
                        $order->save();
                    }
                }
                foreach ($order_list as $order) {
                    //支付完成后，相关操作
                    $form = new OrderWarn();
                    $form->order_id = $order->id;
                    $form->order_type = 0;
                    $form->notify();
                }
                return [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => '',
                ];
            }
        }
    }

    /**
     * 购买成功首页提示
     */
    private function buyData($order_no, $store_id, $type)
    {
        $order = Order::find()->select(['u.nickname', 'g.name', 'u.avatar_url', 'od.goods_id'])->alias('c')
            ->where('c.order_no=:order', [':order' => $order_no])
            ->andwhere('c.store_id=:store_id', [':store_id' => $store_id])
            ->leftJoin(['u' => User::tableName()], 'u.id=c.user_id')
            ->leftJoin(['od' => OrderDetail::tableName()], 'od.order_id=c.id')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id = g.id')
            ->asArray()->one();

        $key = "buy_data";
        $data = (object)null;
        $data->type = $type;
        $data->store_id = $store_id;
        $data->order_no = $order_no;
        $data->user = $order['nickname'];
        $data->goods = $order['goods_id'];
        $data->address = $order['name'];
        $data->avatar_url = $order['avatar_url'];
        $data->time = time();
        $new = json_encode($data);
        $cache = \Yii::$app->cache;
        $cache->set($key, $new, 300);
    }

    /**
     * 设置佣金
     * @param Order $order
     */
    private function setReturnData($order)
    {
        $form = new ShareMoneyForm();
        $form->order = $order;
        $form->order_type = 0;
        return $form->setData();
    }

    //单个订单微信支付下单
    private function unifiedOrder($goods_names)
    {
        $res = $this->wechat->pay->unifiedOrder([
            'body' => $goods_names,
            'out_trade_no' => $this->order->order_no,
            'total_fee' => $this->order->pay_price * 100,
            'notify_url' => pay_notify_url('/pay-notify.php'),
            'trade_type' => 'JSAPI',
            'openid' => $this->user->wechat_open_id,
        ]);

        if (!$res) {
            return [
                'code' => 1,
                'msg' => '支付失败',
            ];
        }
        if ($res['return_code'] != 'SUCCESS') {
            return [
                'code' => 1,
                'msg' => '支付失败，' . (isset($res['return_msg']) ? $res['return_msg'] : ''),
                'res' => $res,
            ];
        }
        if ($res['result_code'] != 'SUCCESS') {
            if ($res['err_code'] == 'INVALID_REQUEST') { //商户订单号重复
                $this->order->order_no = (new OrderSubmitForm())->getOrderNo();
                $this->order->save();
                return $this->unifiedOrder($goods_names);
            } else {
                return [
                    'code' => 1,
                    'msg' => '支付失败，' . (isset($res['err_code_des']) ? $res['err_code_des'] : ''),
                    'res' => $res,
                ];
            }
        }
        return $res;
    }

    //合并订单微信支付下单
    private function unifiedUnionOrder($order_list, $total_pay_price)
    {
        // 支付宝
        if (\Yii::$app->fromAlipayApp()) {
            $data = [
                'body' => count($order_list) . '笔订单合并支付', // 对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加
                'subject' => count($order_list) . '笔订单合并支付', // 商品的标题 / 交易标题 / 订单标题 / 订单关键字等
                'out_trade_no' => $this->getOrderUnionNo(), // 商户网站唯一订单号
                'total_amount' => $total_pay_price, // 订单总金额，单位为元，精确到小数点后两位，取值范围 [0.01,100000000]
                'buyer_id' => $this->user->wechat_open_id, // 购买人的支付宝用户 ID

            ];

            $request = AlipayRequestFactory::create('alipay.trade.create', [
                'notify_url' => pay_notify_url('/alipay-notify.php'),
                'biz_content' => $data,
            ]);

            try {
                $aop = $this->getAlipay();
                $res = $aop->execute($request)->getData();
            } catch (\Exception $e) {
                if ($e->getCode() == 40004 || $e->getCode() == 'ACQ.CONTEXT_INCONSISTENT') {
                    return $this->unifiedUnionOrder($order_list, $total_pay_price);
                } else {
                    return [
                        'code' => 1,
                        'msg' => '支付失败，' . $e->getMessage()
                    ];
                }
            }

            $order_union = new OrderUnion();
            $order_union->store_id = $this->store_id;
            $order_union->user_id = $this->user->id;
            $order_union->order_no = $data['out_trade_no'];
            $order_union->price = $total_pay_price;
            $order_union->is_pay = 0;
            $order_union->addtime = time();
            $order_union->is_delete = 0;
            $order_id_list = [];
            foreach ($order_list as $order) {
                $order_id_list[] = $order->id;
            }
            $order_union->order_id_list = json_encode($order_id_list);
            if (!$order_union->save()) {
                return $this->getErrorResponse($order_union);
            }
            foreach ($order_list as $order) {
                $order->order_union_id = $order_union->id;
                $order->save();
            }

            return new ApiResponse(0, '成功', $res);
        }

        $data = [
            'body' => count($order_list) . '笔订单合并支付',
            'out_trade_no' => $this->getOrderUnionNo(),
            'total_fee' => $total_pay_price * 100,
            'notify_url' => pay_notify_url('/pay-notify.php'),
            'trade_type' => 'JSAPI',
            'openid' => $this->user->wechat_open_id,
        ];
        $order_union = new OrderUnion();
        $order_union->store_id = $this->store_id;
        $order_union->user_id = $this->user->id;
        $order_union->order_no = $data['out_trade_no'];
        $order_union->price = $total_pay_price;
        $order_union->is_pay = 0;
        $order_union->addtime = time();
        $order_union->is_delete = 0;
        $order_id_list = [];
        foreach ($order_list as $order) {
            $order_id_list[] = $order->id;
        }
        $order_union->order_id_list = json_encode($order_id_list);
        if (!$order_union->save()) {
            return $this->getErrorResponse($order_union);
        }
        $res = $this->wechat->pay->unifiedOrder($data);
        if (!$res) {
            return [
                'code' => 1,
                'msg' => '支付失败',
            ];
        }
        if ($res['return_code'] != 'SUCCESS') {
            return [
                'code' => 1,
                'msg' => '支付失败，' . (isset($res['return_msg']) ? $res['return_msg'] : ''),
                'res' => $res,
            ];
        }
        if ($res['result_code'] != 'SUCCESS') {
            if ($res['err_code'] == 'INVALID_REQUEST') { //商户订单号重复
                return $this->unifiedUnionOrder($order_list, $total_pay_price);
            } else {
                return [
                    'code' => 1,
                    'msg' => '支付失败，' . (isset($res['err_code_des']) ? $res['err_code_des'] : ''),
                    'res' => $res,
                ];
            }
        }
        foreach ($order_list as $order) {
            $order->order_union_id = $order_union->id;
            $order->save();
        }
        $res['order_no'] = $data['out_trade_no'];
        $res['body'] = $data['body'];
        return $res;
    }

    //单个订单DY支付下单
    private function dyUnifiedOrder($goods_names){
        $config = MpConfig::get($this->store->id);
        $order_no = date('YmdHis') . mt_rand(100000, 999999);
        $this->order->order_no = $order_no;
        $this->order->save();
        //TODO 获取微信支付
        $url = $this->WechatH5Pay($this->order->order_no, $this->order->pay_price, pay_notify_url('/pay-notify.php'), $goods_names, '', '');

        //获取支付宝公共参数
        $response = $this->AlipayPay($goods_names,$config);
        $data = [
            'merchant_id' => $config['tt_mch_id'],//1900011537 $config['tt_mch_id']
            'app_id' => $config['tt_mch_app_id'],//800115372407 $config['tt_mch_app_id']
            'sign_type' => 'MD5',
            'timestamp' => time(),
            'version' => '2.0',
            'trade_type' => 'H5',
            'product_code' => 'pay',
            'payment_type' => 'direct',
            'out_order_no' => $order_no,
            'uid' => $this->user->wechat_open_id,
            'total_amount' => intval($this->order->pay_price * 100),
            'currency' => 'CNY',
            'subject' => $goods_names,
            'body' => $goods_names,
            'trade_time' => time(),
            'valid_time' => 3000,
            'notify_url' => 'https://developer.toutiao.com',
            'alipay_url' => $response,
            'wx_url' => $url,
            'wx_type' => 'MWEB',
        ];
//        var_dump($data);
//        exit();

        ksort($data);
        $var = '';
        foreach ($data as $key => $value) {
            $var .= $key . '=' . $value . '&';
        }
        $var = trim($var, '&');

        $string = $var . $config['tt_mch_secret'];//s4eb6exfdni1kdmks46hqb9sjjki5wbmkew4z3c2  $config['tt_mch_secret']

        $sign = md5($string);

        $data['sign'] = $sign;
        $data['pay_way'] = 'wechat';
        $data['risk_info'] = json_encode([
            "ip" => $_SERVER["REMOTE_ADDR"],
        ]);
//        $data['risk_info'] = json_encode([
//            "ip" => $_SERVER["REMOTE_ADDR"],
//            "device_id" => "100256"
//        ]);
        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $data,
        ];
    }

    private function AlipayPay($goods_name,$config){

        require_once dirname(__FILE__) . '/alipay-sdk/aop/AopClient.php';
        require_once dirname(__FILE__) . '/alipay-sdk/aop/request/AlipayTradeAppPayRequest.php';

        $aop = new \AopClient();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = $config['alipay_app_id'];
        $aop->rsaPrivateKey = $config['alipay_private_key'];
        $aop->format = "json";
        $aop->postCharset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = $config['alipay_public_key'];
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest();

        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"{$goods_name}\","
            . "\"subject\":\"{$goods_name}\","
            . "\"out_trade_no\":\"{$this->order->order_no}\","
            . "\"timeout_express\":\"30m\","
            . "\"passback_params\":\"1\","
            . "\"total_amount\":\"{$this->order->pay_price}\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";

        $request->setNotifyUrl(pay_notify_url('/alipay-notify.php'));
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        return $response;
    }


    //单个订单TT支付下单
    private function ttUnifiedOrder($goods_names)
    {

        $config = MpConfig::get($this->store->id);

        if (!$config['alipay_app_id'] || !$config['alipay_public_key'] || !$config['alipay_private_key'] || !$config['tt_mch_app_id'] || !$config['tt_mch_id'] || !$config['tt_mch_secret'] || !$config['wechat_public_key'] || !$config['wechat_private_key'] || !$config['wechat_app_id'] || !$config['wechat_mch_id'] || !$config['wechat_mch_secret']) {
            return [
                'code' => 1,
                'msg' => '支付参数错误',
//                'data' => $arr,
            ];
        }
        if ($this->pay_way == 'alipay') {

            require_once dirname(__FILE__) . '/alipay-sdk/aop/AopClient.php';
            require_once dirname(__FILE__) . '/alipay-sdk/aop/request/AlipayTradeAppPayRequest.php';
            try {
                $aop = new \AopClient();
                $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
                $aop->appId = $config['alipay_app_id'];
                $aop->rsaPrivateKey = $config['alipay_private_key'];
                $aop->format = "json";
                $aop->postCharset = "UTF-8";
                $aop->signType = "RSA2";
                $aop->alipayrsaPublicKey = $config['alipay_public_key'];
                //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
                $request = new \AlipayTradeAppPayRequest();

                //SDK已经封装掉了公共参数，这里只需要传入业务参数
                $bizcontent = "{\"body\":\"{$goods_names}\","
                    . "\"subject\":\"{$goods_names}\","
                    . "\"out_trade_no\":\"{$this->order->order_no}\","
                    . "\"timeout_express\":\"30m\","
                    . "\"passback_params\":\"1\","
                    . "\"total_amount\":\"{$this->order->pay_price}\","
                    . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                    . "}";

                $request->setNotifyUrl(pay_notify_url('/alipay-notify.php'));
                $request->setBizContent($bizcontent);
                //这里和普通的接口调用不同，使用的是sdkExecute
                $response = $aop->sdkExecute($request);

                $arr = [
                    'app_id' => $config['tt_mch_app_id'],
                    'sign_type' => 'MD5',
                    'timestamp' => time(),
                    'trade_no' => $this->order->order_no,
                    'merchant_id' => $config['tt_mch_id'],
                    'uid' => $this->user->wechat_open_id,
                    'total_amount' => intval($this->order->pay_price * 100),
//                'params' => "{\"url\":\"" . $response . "\"}",
//                'params' => json_encode($response),
//                'params' => $response,
                    'params' => json_encode([
                        "url" => $response
                    ]),

                ];
                ksort($arr);
                $var = '';
                foreach ($arr as $key => $value) {

                    $var .= $key . '=' . $value . '&';

                }
                $var = trim($var, '&');
                $string = $var . $config['tt_mch_secret'];
                $sign = md5($string);
                $arr['sign'] = $sign;
                $arr["method"] = "tp.trade.confirm";
                $arr["pay_channel"] = "ALIPAY_NO_SIGN";
                $arr["pay_type"] = "ALIPAY_APP";
                $arr["risk_info"] = json_encode([
                    "ip" => $_SERVER["REMOTE_ADDR"]
                ]);
                $arr['alipay_url'] = $response;
                $arr["string"] = $string;
                $arr['pay_way'] = 'alipay';


//            var_dump($arr);


                return [
                    'code' => 0,
                    'msg' => 'success',
                    'data' => $arr,
                ];


//            var_dump($res);
//            exit;

//            $data = [
//                'app_id' => '800115372407',
//                'method' => 'tp.trade.confirm',
//                'sign_type' => 'MD5',
//                'trade_no' => $this->order->order_no,
//                'merchant_id' => 1,
//                'uid' => $this->user->wechat_open_id,
//                'total_amount' => $this->order->pay_price *100 ,
//                'params' => $response,
//            ];
//            return [
//                'code' => 0,
//                'msg' => 'success',
//                'data' => $data,
//            ];

            } catch (\Exception $e) {
                return [
                    'code' => 1,
                    'msg' => '支付失败，' . $e->getMessage()
                ];
            }

        }
        if ($this->pay_way == 'wechat') {
            //这里写微信支付逻辑

            $url = $this->WechatH5Pay($this->order->order_no, $this->order->pay_price, pay_notify_url('/pay-notify.php'), $goods_names, '', '');
            $arr = [
                'app_id' => $config['tt_mch_app_id'],
                'sign_type' => 'MD5',
                'timestamp' => time(),
                'trade_no' => $this->order->order_no,
                'merchant_id' => $config['tt_mch_id'],
                'uid' => $this->user->wechat_open_id,
                'total_amount' => intval($this->order->pay_price * 100),
                'version' => '2.0',
                'trade_type' => 'H5',
                'product_code' => 'pay',
                'payment_type' => 'direct',
                'out_order_no' => $this->order->order_no,
                'currency' => 'CNY',
                'subject' => $goods_names,//$goods_names
                'body' => $goods_names,
                'trade_time' => time(),
                'valid_time' => 5000,
                'notify_url' => pay_notify_url('/pay-notify.php'),
//                'alipay_url' => ''
//                'wx_url' => $url,
//                'wx_type' => 'MWEB',
            ];
            ksort($arr);
            $var = '';
            foreach ($arr as $key => $value) {

                $var .= $key . '=' . $value . '&';

            }
            $var = trim($var, '&');
            $string = $var . $config['tt_mch_secret'];
            var_dump($string);
            exit;
            $sign = md5($string);

            $arr['sign'] = $sign;

            $arr['pay_way'] = 'wechat';
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $arr,
            ];

        }

    }


    public function WechatH5Pay($out_trade_no, $total_fee, $notifyUrl, $body, $web_url, $redirect_url)
    {
        $appId = $this->wechat->appId;
        $mchId = $this->wechat->mchId;
//        $apiKey = $this->wechat->apiKey;
//        var_dump($appId);
//        var_dump($mchId);
//        var_dump($apiKey);
//        exit();

        $paydata = array(
            'appid' => $appId,//"wxc7811d73da68c79e",  $config['wechat_app_id']
            'mch_id' => $mchId,//"1491703102", $config['wechat_mch_id']
            'nonce_str' => $this->nonce_str(),
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'total_fee' => intval($total_fee * 100),     //单位 转为分
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $notifyUrl,
            'trade_type' => 'MWEB',
            'scene_info' => '{"h5_info": {"type":"Wap","wap_url": "' . "snssdk.com" . '","wap_name": "h5pay"}}',
        );
        $paydata['sign'] = $this->getSign($paydata);
        $responseXml = $this->postXmlOrJson($this->unifiedorderUrl, $this->arrayToXml($paydata));
        $resultData = $this->XmlToArr($responseXml);
//        var_dump($resultData);
//        exit;
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
//                $url_encode_redirect_url = urlencode($redirect_url);
//                $url = $resultData['mweb_url'] . '&redirect_url=' . $url_encode_redirect_url;
                $url = $resultData['mweb_url'];
                return $url;
            }
        }
        return false;
    }

    //生成随机字符串
    protected function nonce_str()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()+-';
        $random = $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)];
        $content = uniqid() . $random;
        return md5(sha1($content));
    }

    //Xml转数组
    public function XmlToArr($xml)
    {
        if ($xml == '') return '';
        libxml_disable_entity_loader(true);
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $arr;
    }

    //转换xml
    public function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml = $xml . '<' . $key . '>' . $this->arrayToXml($val) . '</' . $key . '>';
            } else {
                $xml = $xml . '<' . $key . '>' . $val . '</' . $key . '>';
            }

        }
        $xml .= '</xml>';
        return $xml;
    }

    //提交XML方法
    protected function postXmlOrJson($url, $data)
    {
        //$data = 'XML或者JSON等字符串';
        $ch = curl_init();
        $params[CURLOPT_URL] = $url;    //请求url地址
        $params[CURLOPT_HEADER] = false; //是否返回响应头信息
        $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
        $params[CURLOPT_FOLLOWLOCATION] = true; //是否重定向
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $data;

        //防止curl请求 https站点报错 禁用证书验证
        $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;


        //curl_setopt($ch, CURLOPT_SSLCERT,app_path('/Cert/apiclient_cert.pem'));
        curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
        return $content;
    }

    //生成签名
    protected function getSign($data)
    {
//        $config = MpConfig::get($this->store->id);
        $apiKey = $this->wechat->apiKey;
        //去除数组空键值
        $data = array_filter($data);
        //如果数组中有签名删除签名
        if (isset($data['sing'])) {
            unset($data['sing']);
        }
        //按照键名字典排序
        ksort($data);

//        $str = http_build_query($data) . "&key=" . 'fnR8NbPlqZ9DtGQteyRwcFRsjMcpoek4';//$config['wechat_mch_secret']
        $str = http_build_query($data) . "&key=" . $apiKey;//$config['wechat_mch_secret']

        //转码
        $str = $this->arrToUrl($str);

        return strtoupper(md5($str));
    }

    //URL解码为中文
    public function arrToUrl($str)
    {
        return urldecode($str);
    }

    function PostCurl($url = "", $requestData = array())
    {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        //普通数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($requestData));
        $res = curl_exec($curl);

        //$info = curl_getinfo($ch);
        curl_close($curl);
        return $res;
    }

    function send_post($url)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

    public function getOrderUnionNo()
    {
        $order_no = null;
        while (true) {
            $order_no = 'U' . date('YmdHis') . mt_rand(10000, 99999);
            $exist_order_no = OrderUnion::find()->where(['order_no' => $order_no])->exists();
            if (!$exist_order_no) {
                break;
            }
        }
        return $order_no;
    }


    // 单个支付宝下单
    private function alipayUnifiedOrder($goods_names)
    {
        $request = AlipayRequestFactory::create('alipay.trade.create', [
            'notify_url' => pay_notify_url('/alipay-notify.php'),
            'biz_content' => [
                'body' => $goods_names, // 对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加
                'subject' => $goods_names, // 商品的标题 / 交易标题 / 订单标题 / 订单关键字等
                'out_trade_no' => $this->order->order_no, // 商户网站唯一订单号
                'total_amount' => $this->order->pay_price, // 订单总金额，单位为元，精确到小数点后两位，取值范围 [0.01,100000000]
                'buyer_id' => $this->user->wechat_open_id, // 购买人的支付宝用户 ID

            ],
        ]);
        try {
            $aop = $this->getAlipay();
            $res = $aop->execute($request)->getData();

        } catch (\Exception $e) {
            if ($e->getCode() == 'ACQ.CONTEXT_INCONSISTENT') { //订单号重复
                $this->order->order_no = (new OrderSubmitForm())->getOrderNo();
                $this->order->save();
                return $this->alipayUnifiedOrder($goods_names);
            } else {
                return [
                    'code' => 1,
                    'msg' => '支付失败，' . $e->getMessage()
                ];
            }
        }
        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $res,
            'res' => $res,
            'body' => $goods_names,
        ];
    }

    /**
     * @param Order $order
     * @throws \Exception
     */
    private function checkGoodsConfine($order)
    {
        foreach ($order->detail as $detail) {
            /* @var Goods $goods */
            /* @var OrderDetail $detail */
            $goods = $detail->goods;
            if ($goods->confine_count && $goods->confine_count > 0) {
                $goodsNum = Goods::getBuyNum($this->user, $goods->id);
                if ($goodsNum) {

                } else {
                    $goodsNum = 0;
                }
                $goodsTotalNum = intval($goodsNum + $detail->num);
                if ($goodsTotalNum > $goods->confine_count) {
                    throw new \Exception('商品：' . $goods->name . ' 超出购买数量', 1);
                }
            }
        }
    }
}
