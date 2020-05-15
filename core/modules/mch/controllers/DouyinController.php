<?php
/**
 * @copyright ©2018 浙江禾匠信息科技
 * @author Lu Wei
 * @link http://www.zjhejiang.com/
 * Created by IntelliJ IDEA
 * Date Time: 2018/8/3 9:51
 */


namespace app\modules\mch\controllers;


use Alipay\Key\AlipayKeyPair;
use Alipay\Key\AlipayPrivateKey;
use app\models\douyin\MpConfig;
// use app\models\alipay\TplMsgForm;
use Comodojo\Zip\Zip;
use yii\web\Response;

class DouyinController extends Controller
{
    //小程序配置
    public function actionMpConfig()
    {
        $form = MpConfig::get($this->store->id);
        if (\Yii::$app->request->isPost) {
            $form->attributes = \Yii::$app->request->post();
            return $form->save();
        } else {
            return $this->render('mp-config', [
                'model' => $form,
            ]);
        }
    }

    /**
     * 模版消息
     */
    public function actionTemplateMsg()
    {

        $form = TplMsgForm::get($this->store->id);
        if (\Yii::$app->request->isPost) {
            $tpl = new TplMsgForm();
            $tpl->store_id = $this->store->id;
            $tpl->attributes = \Yii::$app->request->post();
            return $tpl->save();
        } else {
            $newData = [];
            foreach ($form as $k => $item) {
                if (in_array($k, ['pay_tpl', 'refund_tpl', 'send_tpl', 'revoke_tpl'])) {
                    $newData['store'][$k] = $item;
                }
                if (in_array($k, ['cash_fail_tpl', 'cash_success_tpl', 'apply_tpl'])) {
                    $newData['share'][$k] = $item;
                }
                if (in_array($k, ['pt_fail_notice', 'pt_success_notice'])) {
                    $newData['pintuan'][$k] = $item;
                }
                if (in_array($k, ['yy_refund_notice', 'yy_success_notice'])) {
                    $newData['book'][$k] = $item;
                }
                if (in_array($k, ['mch_tpl_1', 'mch_tpl_2'])) {
                    $newData['mch'][$k] = $item;
                }
                if (in_array($k, ['tpl_msg_id'])) {
                    $newData['fxhb'][$k] = $item;
                }
            }

            // 根据插件权限显示
            $plugin = $this->getUserAuth();
            // 有模板消息功能的插件
            $tplMsgPlugin = ['store', 'share', 'pintuan', 'book', 'mch', 'fxhb', 'lottery', 'bargain'];
            // 这里是为了防止数据库没有相应插件的数据，导致前端不显示
            foreach ($plugin as $item) {
                if (in_array($item, $tplMsgPlugin)) {
                    foreach ($newData as $k => $v) {
                        if ($k != $item) {
                            $newData[$item]['is_show'] = true;
                        }
                    }
                }
            }

            foreach ($newData as $k => $item) {
                if (in_array($k, $plugin) || $k == 'store') {
                    $newData[$k]['is_show'] = true;
                } else {
                    $newData[$k]['is_show'] = false;
                }
            }

            // 参与活动通用模板，只要有相应插件用到都应显示
            if (in_array('bargain', $plugin)) {
                $newData['activity']['is_show'] = true;
            }

            return $this->render('template-msg', [
                'model' => $newData,
            ]);
        }
    }

    /**
     * 发布小程序
     *
     * @return void
     */
    public function actionPublish()
    {
        return $this->render('publish');
    }

    /**
     * 下载前端包
     *
     * @return void
     */
    public function actionDownload()
    {
        $entryUri = str_replace('http://', 'https://', \Yii::$app->request->hostInfo . \Yii::$app->request->baseUrl . '/index.php?store_id=' . $this->store->id . '&r=api/'); // API 入口
        $siteUrl = str_replace('http://', 'https://', \Yii::$app->request->hostInfo .'/app/index.php'); // 后端 入口
        $toutiaoDir = \Yii::$app->basePath . '/web/toutiaoapp'; // 配置前端包目录
        
        $account_app = \Yii::$app->db->createCommand("SELECT * FROM ims_account_wxapp WHERE acid = ".$this->store->acid)->queryOne();

        $siteinfoPath = $toutiaoDir . '/siteinfo.js'; // siteinfo.js 路径
        $siteinfo = <<<EOF
var siteinfo = {
	'name': '{$this->store->name}',
	'uniacid': '{$account_app["uniacid"]}',
    'acid': '{$this->store->acid}',
    'version': '2.0.0',
    'siteroot': '{$siteUrl}',
    'apiroot': '{$entryUri}',
    'design_method': '3',
};
module.exports = siteinfo;
EOF;
        // siteinfo.js内容
        $lockFile = sys_get_temp_dir() . '/hejiang-alipay-publish-lock'; // 锁文件，保证独占
        $zipFile = sys_get_temp_dir() . '/hejiang-alipay-publish-archive'; // 打包文件

        $lock = fopen($lockFile, 'w+');
        flock($lock, LOCK_EX);

        // --- 打包逻辑开始 ---
        file_put_contents($siteinfoPath, $siteinfo);

        if (is_file($zipFile)) {
            unlink($zipFile);
        }
        $zip = Zip::create($zipFile);
        $zip->add($toutiaoDir, true);
        $zip->close();

        // --- 打包逻辑结束 ---

        flock($lock, LOCK_UN);
        fclose($lock);
        return \Yii::$app->response->sendFile($zipFile, 'toutiao-app.zip');
    }

    /**
     * 公钥私钥生成
     */
    public function actionKeyGenerate()
    {
        // 生成密钥对
        $configargs = [];
        try {
            $keyPair = AlipayKeyPair::generate();
        } catch (\Exception $e) {
            $configargs = [
                'config' => \Yii::$app->basePath . '/config/openssl.cnf',
            ];
            $keyPair = AlipayKeyPair::generate($configargs);
        }
        $public_key = $keyPair->getPublicKey()->asString();
        $private_key = AlipayPrivateKey::toString($keyPair->getPrivateKey()->asResource(), $configargs);
        return [
            'code' => 0,
            'data' => [
                'alipay_public_key' => $public_key,
                'alipay_private_key' => $private_key,
            ],
        ];
    }

}