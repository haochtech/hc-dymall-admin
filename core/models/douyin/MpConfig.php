<?php
/**
 * @copyright ©2018 浙江禾匠信息科技
 * @author Lu Wei
 * @link http://www.zjhejiang.com/
 * Created by IntelliJ IDEA
 * Date Time: 2018/8/3 11:49
 */

namespace app\models\douyin;

use Alipay\Key\AlipayKeyPair;
use app\models\Option;
use app\modules\mch\models\MchModel;
use Alipay\Exception\AlipayException;
use Alipay\AlipayCurlRequester;

class MpConfig extends MchModel
{
    public $store_id;

    public $tt_app_id;
    public $tt_app_secret;
    public $tt_mch_app_id;
    public $tt_mch_id;
    public $tt_mch_secret;
    public $alipay_app_id;
    public $alipay_public_key;
    public $alipay_private_key;

    public $wechat_mch_id;
    public $wechat_mch_secret;
    public $wechat_app_id;
    public $wechat_public_key;
    public $wechat_private_key;

    const OPTION_KEY = 'douyin_mp_config';

    public function rules()
    {
        return [
            [['tt_app_id','tt_app_secret', 'tt_mch_app_id', 'tt_mch_id', 'tt_mch_secret', 'alipay_app_id', 'alipay_public_key', 'alipay_private_key','wechat_mch_id','wechat_mch_secret','wechat_app_id','wechat_public_key','wechat_private_key'], 'trim'],
            [['tt_app_id','tt_app_secret'], 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'tt_app_id' => '小程序AppID',
            'tt_app_secret' => '小程序密钥',
            'tt_mch_app_id' => '商户app id',
            'tt_mch_id' => '商户号',
            'tt_mch_secret' => '支付密钥',
            'alipay_app_id' => '支付宝应用AppID',
            'alipay_public_key' => '支付宝公钥',
            'alipay_private_key' => '应用私钥',
            'wechat_mch_id' => '微信商户号',
            'wechat_mch_secret' => '微信支付key',
            'wechat_app_id' => '微信AppID',
            'wechat_public_key' => '微信公钥',
            'wechat_private_key' => '微信应用私钥',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }
        $data = $this->attributes;
        unset($data['store_id']);
        Option::set(self::OPTION_KEY, $data, $this->store_id);
        return [
            'code' => 0,
            'msg' => '保存成功。',
        ];
    }

    /**
     * 根据 Store Id 获取其配置实例
     *
     * @param string|int $storeId
     * @return static
     */
    public static function get($storeId)
    {
        $instance = new static();
        $instance->store_id = $storeId;

        $data = Option::get(self::OPTION_KEY, $storeId);
        if ($data != null) {
            $instance->attributes = (array)$data;
        }

        return $instance;
    }

    /**
     * 返回支付宝 AopClient
     *
     * @return \Alipay\AopClient
     */
    public function getClient()
    {
        if ($this->app_id == null) {
            throw new \InvalidArgumentException('支付宝小程序 appid 为空，请检查是否配置支付宝小程序');
        }
        try {
            $kp = AlipayKeyPair::create($this->app_private_key, $this->alipay_public_key);
        } catch (AlipayException $ex) {
            throw new \InvalidArgumentException('支付宝小程序密钥异常，请检查是否配置支付宝小程序');
        }
        $requester = new AlipayCurlRequester([
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        return new \Alipay\AopClient($this->app_id, $kp, null, $requester);
    }

    private function pregReplaceAll($find, $replacement, $s)
    {
        while (preg_match($find, $s)) {
            $s = preg_replace($find, $replacement, $s);
        }
        return $s;
    }
}
