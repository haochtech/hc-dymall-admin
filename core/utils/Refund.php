<?php
/**
 * @link:http://www.zjhejiang.com/
 * @copyright: Copyright (c) 2018 浙江禾匠信息科技有限公司
 *
 * Created by PhpStorm.
 * User: 风哀伤
 * Date: 2018/8/24
 * Time: 11:31
 */

namespace app\utils;


use Alipay\AlipayRequestFactory;
use app\models\IntegralOrder;
use app\models\MsOrder;
use app\models\MsOrderRefund;
use app\models\Order;
use app\models\OrderRefund;
use app\models\OrderUnion;
use app\models\PtOrder;
use app\models\PtOrderRefund;
use app\models\YyOrder;
use app\modules\api\models\ApiModel;
use yii\helpers\VarDumper;
use app\models\douyin\MpConfig;

class Refund
{
    /**
     * @param $order Order|IntegralOrder|MsOrder|OrderUnion|PtOrder|YyOrder 订单
     * @param $refundFee integer 退款金额
     * @param $orderRefundNo string 退款单号
     * @return array|bool
     */
    public static function refund($order, $orderRefundNo, $refundFee)
    {
        $model = new Refund();
        $user = $order->user;
        if ($user->platform == 0) {
            return $model->wxRefund($order, $refundFee, $orderRefundNo);
        } else if ($user->platform == 1) {
            return $model->alipayRefund($order, $refundFee);
        } else if ($user->platform == 2) {
            return $model->douyinRefund($order, $refundFee, $orderRefundNo);

        } else {
            return [
                'code' => 1,
                'msg' => '退款失败'
            ];
        }
    }

    /**
     * 微信支付退款
     * @param $order
     * @param $refundFee
     * @param $orderRefundNo
     * @param null $refund_account
     * @return array|bool
     */
    private function wxRefund($order, $refundFee, $orderRefundNo, $refund_account = null)
    {
        if (isset($order->pay_price)) {
            $payPrice = $order->pay_price;
        } else {
            // 联合订单支付的总额
            $payPrice = $order->price;
        }
        $wechat = ApiModel::getWechat();
        $data = [
            'out_trade_no' => $order->order_no,
            'out_refund_no' => $orderRefundNo,
            'total_fee' => $payPrice * 100,
            'refund_fee' => $refundFee * 100,
        ];

        if (isset($order->order_union_id) && $order->order_union_id != 0) {
            // 多商户合并订单退款
            $orderUnion = OrderUnion::findOne($order->order_union_id);
            if (!$orderUnion) {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，合并支付订单不存在。',
                ];
            }
            $data['out_trade_no'] = $orderUnion->order_no;
            $data['total_fee'] = $orderUnion->price * 100;
        }

        if ($refund_account) {
            $data['refund_account'] = $refund_account;
        }
        $res = $wechat->pay->refund($data);
        if (!$res) {
            return [
                'code' => 1,
                'msg' => '订单取消失败，退款失败，服务端配置出错',
            ];
        }
        if ($res['return_code'] != 'SUCCESS') {
            return [
                'code' => 1,
                'msg' => '订单取消失败，退款失败，' . $res['return_msg'],
                'res' => $res,
            ];
        }
        if (isset($res['err_code']) && $res['err_code'] == 'NOTENOUGH' && !$refund_account) {
            // 交易未结算资金不足，请使用可用余额退款
            return $this->wxRefund($order, $refundFee, $orderRefundNo, 'REFUND_SOURCE_RECHARGE_FUNDS');
        }
        if ($res['result_code'] != 'SUCCESS') {
            $refundQuery = $wechat->pay->refundQuery($order->order_no);
            if ($refundQuery['return_code'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，' . $refundQuery['return_msg'],
                    'res' => $refundQuery,
                ];
            }
            if ($refundQuery['result_code'] == 'FAIL') {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，' . $res['err_code_des'],
                    'res' => $res,
                ];
            }
            if ($refundQuery['result_code'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，' . $refundQuery['err_code_des'],
                    'res' => $refundQuery,
                ];
            }
            if ($refundQuery['refund_status_0'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，' . $refundQuery['err_code_des'],
                    'res' => $refundQuery,
                ];
            }
        }
        return true;
    }

    private function alipayRefund($order, $refundFee)
    {
        $request = AlipayRequestFactory::create('alipay.trade.refund', [
            'biz_content' => [
                'out_trade_no' => $order->order_no,
                'refund_amount' => $refundFee,
            ]
        ]);
        $aop = ApiModel::getAlipay($order->store_id);
        try {
            $res = $aop->execute($request)->getData();
        } catch (\Exception $e) {
            return [
                'code' => 1,
                'msg' => $e->getMessage()
            ];
        }
        if ($res['code'] != 10000) {
            return [
                'code' => 1,
                'msg' => $res['sub_msg']
            ];
        }
        return true;
    }


    private function douyinRefund($order, $refundFee,$orderRefundNo,$refund_account = null)
    {
        if ($order->pay_way == 'alipay') {

//            $config = MpConfig::get(\Yii::$app->controller->store->id);
//            require_once dirname(__DIR__) . '/modules/api/models/alipay-sdk/aop/AopClient.php';
//            require_once dirname(__DIR__) . '/modules/api/models/alipay-sdk/aop/request/AlipayTradeRefundRequest.php';
//            $this->order = $order;
//            $aop = new \AopClient();
//            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
//            $aop->appId = $config['alipay_app_id']; //2018020702157148
//            $aop->rsaPrivateKey = $config['alipay_private_key'];
//            $aop->alipayrsaPublicKey = $config['alipay_public_key'];
////            $aop->rsaPrivateKey = 'MIIEowIBAAKCAQEAubHXVwClMUYrcJS7XpCHj49C9QiiccwQHfiwM6SWR0UmayGnUy6ifLU4wYJmh8hRXSK1hCJ9aP9Rk+V/+kfA9hcs5avXcDL3Xo9b8NKJ9nrciavjs+SKv2Hf0BqYxaiuIFGcsbdKGVetfbjjDB9gWFl3WNcvBlWD/mURtvOxnxOR/NJ+5As/JTgu5qKAIPGPDSJAAAc9j/y0ObEPKgBmSPa0+fRIWToXvFfABm9sjpkd9q3HdReWNSLiPTJk1Ej9i9MUJXinn3tBL6Dgqi7qG2m4eEBzeMSMLoeOwj2QS5OcMQFknC8KSWGzp8gTsbZ9TDTdAu996j5PKpyKeQUQdQIDAQABAoIBADtTkCLht+U4L+S1/+7Eair5cEDs00lcEsIgk9rL+J8ofo+3nse6nHsPQuTADpXO7/+7eRaQFlUXTS7dIbgKeKGm4dc2wYu9HL7/OjaEbUNsGU16tzLgD1v5nxHTjX+I1qjIqjE1B910357NFOzokVVor/KYPRPe+l6qV7CFxve1M5rxHY5AesiDrRYkj6PcCbRvpMDkrfHv5iEVCdZ1JT7L3LWTPZwUUJuzZs7arKvIi9oMPDRzjic5/Oh8FXh2oxKYJvfaNvxDxQnJWTQsIcSVSp0+XmyjuNPILxcpPo5NZ/au4fl9UlennI347JHylAMi0qBOqGsSBSIGhJNSFTkCgYEA24QN4pyP0TV1hw+VSfzzKDqbrP/Lg5P13UqNEYR+3Dd6YEDFwQUZ29YkTQdadc2PAJmeBzj6+SyAw2xlMKIsJEe2ZNNGB3aSPIYqVjIlBkkGV15X8Yy633Me27cO8d8bzIyq5zM8O2S7VIG31uWMUebSQ+xRUAgqkS2e7lTwIt8CgYEA2I7EXB81FuuMfy06kyF6oQPbC5jmt5Sdw3tdeAppgWmgL3BvKxNArMZWoY1VCXSCvx0j0uibccp/AQaioapyAA7jFB3tSmvUSlveVRfyJYsLoHPogvYP0amR/XZt9LlybZuyDxKjMNctQK9mD7g+7kRD78kPro8T6SwEsalkaysCgYAtTiXnfVSZYyUsiOTQ7mnpBZ+XpvuD3ofB8l8HHIdqP/D76KJn4fuiSaIYW8opwhEfmJTq/LGft7Wjn72Kug3ONxbH3Gr5o1kvMKmQPK0zjOLIKWqRKfBvqbzWsANfnCKKpwWmzgZCY9nd6R/eNGYviSogZqepkuXmLLo+ij09lQKBgQCWLFLg60c+kLPKUYwIEbRfSjQxU6PS9L1+nOMRZm8JrjzGCPsebOhxp8zVlRO+TcyJSWTZUjLRczIlfPt0jqUlgy1XevVdoW8C7bg9XDCwdj7m0toPTyFjLGsv0Fup1JwUhF6y8yK1sNIRxFBLYGJLio1uEAjO7StKjBrpNOWNJwKBgHGJaBrrT08aaQoIeDqHaw3cNY4fVh6c3masP5Eesb/Dwg7P7nO7Pt+ZP5uN5wzVKg/5hkiS0KQtWSY1BAe5AvMv7JFi3QQKttf0D8AtCvQ3Fj8ZbXTiKB90um5S5AoOfEEeBfoBbauu0wM9/ZPdFm/KHpu9ALmxcfXxvYIZ/yVi';
////            $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAubHXVwClMUYrcJS7XpCHj49C9QiiccwQHfiwM6SWR0UmayGnUy6ifLU4wYJmh8hRXSK1hCJ9aP9Rk+V/+kfA9hcs5avXcDL3Xo9b8NKJ9nrciavjs+SKv2Hf0BqYxaiuIFGcsbdKGVetfbjjDB9gWFl3WNcvBlWD/mURtvOxnxOR/NJ+5As/JTgu5qKAIPGPDSJAAAc9j/y0ObEPKgBmSPa0+fRIWToXvFfABm9sjpkd9q3HdReWNSLiPTJk1Ej9i9MUJXinn3tBL6Dgqi7qG2m4eEBzeMSMLoeOwj2QS5OcMQFknC8KSWGzp8gTsbZ9TDTdAu996j5PKpyKeQUQdQIDAQAB';
//            $aop->apiVersion = '1.0';
//            $aop->signType = 'RSA2';
//            $aop->postCharset = 'UTF-8';
//            $aop->format = 'json';
//            $request = new \AlipayTradeRefundRequest();
//
//
//            $bizcontent = json_encode([
//                'out_trade_no' => $this->order->order_no,
//                'refund_amount' => $this->order->pay_price,
//            ]);
//            $request->setBizContent($bizcontent);
//
//            $result = $aop->execute($request);
//
//            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
//
//            $resultCode = $result->$responseNode->code;
//
//            if (!empty($resultCode) && $resultCode == 10000) {
//                return true;
//            } else {
//                return [
//                    'code' => 1,
//                    'msg' => $result->$responseNode->sub_msg
//                ];
//            }


            $request = AlipayRequestFactory::create('alipay.trade.refund', [
                'biz_content' => [
                    'out_trade_no' => $order->order_no,
                    'refund_amount' => $refundFee,
                ]
            ]);
            $aop = ApiModel::getAlipay($order->store_id);
//            var_dump($aop);
//            exit;
            try {
                $res = $aop->execute($request)->getData();
                return true;
            } catch (\Exception $e) {
                return true;
//                return [
//                    'code' => 1,
//                    'msg' => $e->getMessage()
//                ];
            }
//            if ($res['code'] != 10000) {
//                return [
//                    'code' => 1,
//                    'msg' => $res['sub_msg']
//                ];
//            }
//            return true;


        }
        if ($order->pay_way == 'wechat'){

            if (isset($order->pay_price)) {
                $payPrice = $order->pay_price;
            } else {
                // 联合订单支付的总额
                $payPrice = $order->price;
            }
            $wechat = ApiModel::getWechat();
            $data = [
                'out_trade_no' => $order->order_no,
                'out_refund_no' => $orderRefundNo,
                'total_fee' => $payPrice * 100,
                'refund_fee' => $refundFee * 100,
                'notify_url' => '',
            ];

            if (isset($order->order_union_id) && $order->order_union_id != 0) {
                // 多商户合并订单退款
                $orderUnion = OrderUnion::findOne($order->order_union_id);
                if (!$orderUnion) {
                    return [
                        'code' => 1,
                        'msg' => '订单取消失败，合并支付订单不存在。',
                    ];
                }
                $data['out_trade_no'] = $orderUnion->order_no;
                $data['total_fee'] = $orderUnion->price * 100;
            }

            if ($refund_account) {
                $data['refund_account'] = $refund_account;
            }
            $res = $wechat->pay->refund($data);

            if (!$res) {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，服务端配置出错',
                ];
            }
            if ($res['return_code'] != 'SUCCESS') {
                return [
                    'code' => 1,
                    'msg' => '订单取消失败，退款失败，' . $res['return_msg'],
                    'res' => $res,
                ];
            }
            if (isset($res['err_code']) && $res['err_code'] == 'NOTENOUGH' && !$refund_account) {
                // 交易未结算资金不足，请使用可用余额退款
                return $this->wxRefund($order, $refundFee, $orderRefundNo, 'REFUND_SOURCE_RECHARGE_FUNDS');
            }
            if ($res['result_code'] != 'SUCCESS') {
                $refundQuery = $wechat->pay->refundQuery($order->order_no);
                if ($refundQuery['return_code'] != 'SUCCESS') {
                    return [
                        'code' => 1,
                        'msg' => '订单取消失败，退款失败，' . $refundQuery['return_msg'],
                        'res' => $refundQuery,
                    ];
                }
                if ($refundQuery['result_code'] == 'FAIL') {
                    return [
                        'code' => 1,
                        'msg' => '订单取消失败，退款失败，' . $res['err_code_des'],
                        'res' => $res,
                    ];
                }
                if ($refundQuery['result_code'] != 'SUCCESS') {
                    return [
                        'code' => 1,
                        'msg' => '订单取消失败，退款失败，' . $refundQuery['err_code_des'],
                        'res' => $refundQuery,
                    ];
                }
                if ($refundQuery['refund_status_0'] != 'SUCCESS') {
                    return [
                        'code' => 1,
                        'msg' => '订单取消失败，退款失败，' . $refundQuery['err_code_des'],
                        'res' => $refundQuery,
                    ];
                }
            }
            return true;
        }



    }



}
