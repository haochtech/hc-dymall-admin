<?php


defined('YII_ENV') or exit('Access Denied');

use app\models\Option;

/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/10/2
 * Time: 14:08
 */
$this->title = '管理员登录';
$logo = Option::get('logo', 0, 'admin', null);
$logo = $logo ? $logo : Yii::$app->request->baseUrl . '/statics/admin/images/logo.png';
$copyright = Option::get('copyright', 0, 'admin');
$copyright = $copyright ? $copyright : '©2017 <a href="http://www.zjhejiang.com" target="_blank">禾匠信息科技</a>';
$passport_bg = Option::get('passport_bg', 0, 'admin', Yii::$app->request->baseUrl . '/statics/admin/images/passport-bg.jpg');
$open_register = Option::get('open_register', 0, 'admin', false);
?>
<style>
    html {
        position: relative;
        min-height: 100%;
        height: 100%;
    }

    body {
        margin: 0 0 0 0;
        padding-bottom: 70px;
        height: 100%;
        overflow: hidden;
    }

    .main1 {
        margin: 0 0 0 0;
        background-image: url("<?=$passport_bg?>");
        background-size: cover;
        background-position: center;
        height: 100%;
    }

    .card {
        max-width: 360px;
        margin: 0 auto;
    }

    .card {
        border: none;
        background: rgba(255, 255, 255, .85);
        padding: 16px 10px;
    }

    .card h1 {
        font-size: 20px;
        font-weight: normal;
        text-align: center;
        margin: 0 0 32px 0;
    }

    .card .custom-checkbox .custom-control-indicator {
        border: 1px solid #ccc;
        background-color: #eee;
    }

    .card .custom-control-input:checked ~ .custom-control-indicator {
        border-color: transparent;
    }

    .header {
        height: 50px;
        background: rgba(255, 255, 255, .5);
        margin-bottom: 120px;
    }

    .header a {
        display: inline-block;
        height: 50px;
        padding: 8px 30px;
    }

    .logo {
        display: inline-block;
        height: 100%;
    }

    .footer {
        position: absolute;
        height: 70px;
        background: #fff;
        bottom: 0;
        left: 0;
        width: 100%;
    }

    .copyright {
        padding: 24px 0;
    }
</style>
<div class="main1" id="app">

    <div class="header">
<!--        <a href="--><?//= Yii::$app->request->baseUrl ?><!--">-->
<!--            <img class="logo" src="--><?//= $logo ?><!--">-->
<!--        </a>-->
    </div>
    <div class="card">
        <div class="card-body">
            <h1>商户登录</h1>
            <input class="form-control mb-3 tel" name="tel" placeholder="请输入手机号码">
            <input class="form-control mb-3 password" name="password" placeholder="请输入密码" type="password">
            <div class="form-inline mb-3">
                <div class="w-100">
                    <input class="form-control captcha_code" name="captcha_code" placeholder="图片验证码" style="width: 150px">
                    <img class="refresh-captcha"
                         data-refresh="<?= Yii::$app->urlManager->createUrl(['admin/passport/captcha', 'refresh' => 1,]) ?>"
                         src="<?= Yii::$app->urlManager->createUrl(['admin/passport/captcha',]) ?>"
                         style="height: 33px;width: 80px;float: right;cursor: pointer;" title="点击刷新验证码">
                </div>
            </div>
            <button class="btn btn-block btn-primary mb-3 login">登录</button>
        </div>
    </div>

</div>


<div class="footer">
    <div class="text-center copyright"></div>
</div>
<script>
    var app = new Vue({
        el: '#app',
        data: {
            admin_list: [],
        },
    });
    $(document).on('click', '.refresh-captcha', function () {
        var img = $(this);
        var refresh_url = img.attr('data-refresh');
        $.ajax({
            url: refresh_url,
            dataType: 'json',
            success: function (res) {
                img.attr('src', res.url);
            }
        });
    });

    $(document).on('click', '.login', function () {
        var tel = $('.tel').val();
        var password = $('.password').val();
        var captcha_code = $('.captcha_code').val();
        $.ajax({
            url:'<?=Yii::$app->urlManager->createUrl('user/passport/login')?>',
            type: 'post',
            dataType: 'json',
            data: {
                'tel': tel,
                'password': password,
                'captcha_code': captcha_code,
                _csrf: _csrf,
            },
            success:function (res) {
                if (res.code === 1) {
                    alert( res.msg)
                    
                }else  {
                    location.href = '<?=Yii::$app->urlManager->createUrl(['user'])?>';
                }
            }
        })
    });


</script>