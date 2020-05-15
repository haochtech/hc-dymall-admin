<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/10/2
 * Time: 16:02
 */

namespace app\modules\user\models;

use app\models\Admin;
use app\models\Mch;
use app\models\User;
use Yii;

class LoginForm extends Model
{
    public $tel;
    public $password;
    public $captcha_code;

    public function rules()
    {
        return [
            [['tel', 'captcha_code'], 'trim'],
            [['tel', 'captcha_code', 'password'], 'required'],
            [['captcha_code',], 'captcha', 'captchaAction' => 'admin/passport/captcha',],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => '用户名',
            'password' => '密码',
            'captcha_code' => '图片验证码',
        ];
    }

    public function login()
    {
        if (!$this->validate()) {
            return $this->errorResponse;
        }
        $mch = Mch::find()->where(['tel'=>$this->tel,'password'=>$this->password,'store_id' => $this->store->id])->one();

        if (!$mch){
            return [
                'code' => 1,
                'msg' => '账号或者密码错误'
            ];
        }
        if ($mch->is_open === Mch::IS_OPEN_FALSE) {
            return [
                'code' =>1,
                'msg' => '店铺已被关闭,请联系管理员'
            ];
        }

        $user = User::findOne($mch->user_id);
        \Yii::$app->user->login($user);
//        $mch = Mch::find()->where(['store_id' => $this->store->id, 'user_id' => $m->user_id])->one();
        \Yii::$app->session->set('store_id', $this->store->id);
        return [
            'code' => 0,
            'msg' => '登录成功',
        ];


    }
}
