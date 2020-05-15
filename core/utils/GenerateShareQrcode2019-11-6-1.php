<?php

namespace app\utils;

use Alipay\AlipayRequestFactory;
use Curl\Curl;
use luweiss\wechat\Wechat;
use app\models\douyin\MpConfig;
use app\modules\api\models\ApiModel;
use yii\data\ArrayDataProvider;

class GenerateShareQrcode
{

    /**

     * @param $storeId integer 商城ID

     * @param $scene string 二维码参数

     * @param int $width 二维码大小

     * @param null $page 跳转页面

     * @param int $platform 小程序类型 0--微信 1--支付宝

     */

    public static function getQrcode($storeId, $scene, $width = 430, $page = null)

    {

        if ($page == null) {

            $page = 'pages/index/index';

        }

        $model = new GenerateShareQrcode();

        if (\Yii::$app->fromAlipayApp()) {

            return $model->alipay($scene, $storeId, $page, '二维码');

        } elseif (\Yii::$app->fromTouTiaoApp()) {

            return $model->toutiao($scene, $storeId, $width, $page);

        } else {

            //return $model->wechat($scene, $width, $page);
            return $model->toutiao($scene, $storeId, $width, $page);

        }

    }


    public function request_post($url, $data)
    {
//        $postUrl = $url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
//                'Content-Length: ' . strlen($data)
            ]
        );
//        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
//        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); //解压缩
//        curl_setopt($ch, CURLOPT_ENCODING, "");
        $data = curl_exec($ch);
//        $data = mb_convert_encoding($data, 'utf-8', 'GBK,UTF-8,ASCII');
//        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $data;
//        return gzdecode($data);
//        return gzinflate(substr($data, 36352));
    }

    public function toutiao($scene, $storeId, $width = 430, $page = null)

    {

        $toutiao = MpConfig::get($storeId);

        $access_token = $this->getAccessToken($toutiao);


        if (isset($access_token['errCode'])) {

            return [

                'code' => 1,

                'msg' => $access_token['errMsg'].'=>86',

            ];

        }

        $api = "https://developer.toutiao.com/api/apps/qrcode";

//        $curl = new Curl();
//
//        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
//
//        $data = [
//            'appname' => 'toutiao',
//            'width' => $width,
//            'access_token' => $access_token,
//        ];
//
//        if ($page) {
//            $data['path'] = urlencode($page.'?'.$scene);
//        }
//
//        $data = json_encode($data);
//
//        \Yii::trace("GET WXAPP QRCODE:" . $data);
//
//        $curl->post($api, $data);

        $data = [
            'appname' => 'douyin', //
            'width' => $width,
            'access_token' => $access_token,
//            'path' => urlencode($page.'?'.$scene),
//            'path' => urlencode("{$page}"."?"."{$scene}"),
//            'path' => "pages%2Fbook%2Fclerk%2Fclerk%3Forder_id%3D49",
//            'path' => urlencode($page).'?'.urlencode($scene)
//            'path' => rawurlencode($page.'?'.$scene),
//            'path' => urlencode($page)
//            'path' => urlencode('pages?param=true')
        ];
        if ($page) {
            $data['path'] = urlencode($page."?".$scene);
//            $data['path'] = urlencode($page).'?'.urlencode($scene);
//            $data['path'] = "pages%2Fbook%2Fclerk%2Fclerk%3Forder_id%3D49";
        }
//        return [
//
//            'code' => 1,
//
//            'msg' => json_encode($data),
//
//        ];

        $curl = $this->request_post($api,json_encode($data));
//        var_dump(base64_decode($curl));
//        var_dump($curl->response_headers);
//        var_dump($curl);
//        exit();

//        return [
//            'code' => 1,
////            'msg' =>$curl,
//            'msg' =>base64_encode($curl)
//        ];

//        if (in_array('Content-Type: image/jpeg', $curl->response_headers)) {

            //返回图片

            return [

                'code' => 0,

                'file_path' => $this->saveTempImageByContent($curl),

            ];

//        } else {
//
//            //返回文字
//
//            $res = json_decode($curl->response, true);
//
//            return [
//
//                'code' => 1,
//
////                'msg' => $res['errcode'].$res['errmsg'].$access_token.'帅2',
//            ];
//
//        }

    }



    public function wechat($scene, $width = 430, $page = null)

    {

        $wechat = ApiModel::getWechat();

        $access_token = $wechat->getAccessToken();

        if (!$access_token) {

            return [

                'code' => 1,

                'msg' => $wechat->errMsg,

            ];

        }

        $api = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$access_token}";

        $curl = new Curl();

        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);

        $data = [

            'scene' => $scene,

            'width' => $width,

        ];

        if ($page) {

            $data['page'] = $page;

        }

        $data = json_encode($data);

        \Yii::trace("GET WXAPP QRCODE:" . $data);

        $curl->post($api, $data);

        if (in_array('Content-Type: image/jpeg', $curl->response_headers)) {

            //返回图片

            return [

                'code' => 0,

                'file_path' => $this->saveTempImageByContent($curl->response),

            ];

        } else {

            //返回文字

            $res = json_decode($curl->response, true);

            return [

                'code' => 1,

                'msg' => $res['errcode'].$res['errmsg'],

            ];

        }

    }



    /**

     * 小程序生成推广二维码接口

     *

     * @see https://docs.open.alipay.com/api_5/alipay.open.app.qrcode.create

     */

    public function alipay($scene, $storeId, $page = null, $describe = '')

    {



        try {

            $aop = ApiModel::getAlipay($storeId);



            $request = AlipayRequestFactory::create('alipay.open.app.qrcode.create', [

                'biz_content' => [

                    'url_param' => $page,

                    'query_param' => $scene,

                    'describe' => $describe,

                ],

            ]);

            $data = $aop->execute($request)->getData();

        } catch (\Exception $e) {

            return [

                'code' => 1,

                'msg' => $e->getMessage()

            ];

        }

        $curl = new Curl();

        $curl->setopt(CURLOPT_SSL_VERIFYPEER, false);

        $curl->get($data['qr_code_url']);

        $image = $curl->response;

        $path = $this->saveTempImageByContent($image);



        return [

            'code' => 0,

            'file_path' => $path,

        ];

    }



    //保存图片内容到临时文件

    private function saveTempImageByContent($content)

    {

        $save_path = \Yii::$app->runtimePath . '/image/' . md5(base64_encode($content)) . '.jpg';

        if(!is_dir(\Yii::$app->runtimePath . '/image')) {

            mkdir(\Yii::$app->runtimePath . '/image');

        }

        $fp = fopen($save_path, 'w');

        fwrite($fp, $content);

        fclose($fp);

        return $save_path;

    }

    /**
     * 获取微信接口的accessToken
     *
     * @param boolean $refresh 是否刷新accessToken
     * @param integer $expires accessToken缓存时间（秒）
     * @return string|null
     */
    public function getAccessToken($toutiao)
    {
        $api = "https://developer.toutiao.com/api/apps/token?grant_type=client_credential&appid=".$toutiao['tt_app_id']."&secret=".$toutiao['tt_app_secret'];

        $curl = new Curl();

        $curl->setopt(CURLOPT_SSL_VERIFYPEER, false);
//        $curl->setopt(CURLOPT_SSL_VERIFYPEER, true);

        $curl->get($api);

        $res = json_decode($curl->response, true);

//        if (empty($res['access_token'])) {
//            $this->errCode = isset($res['errcode']) ? $res['errcode'] : null;
//            $this->errMsg = isset($res['errmsg']) ? $res['errmsg'] : null;
//            return [
//                'errCode' => $res['errcode'],
//                'errMsg' => $res['errmsg']
//            ];
//        }

        $accessToken = $res['access_token'];
//        return $curl;
        return $accessToken;
    }


}

