<?php

/**

 * @link http://www.zjhejiang.com/

 * @copyright Copyright (c) 2018 浙江禾匠信息科技有限公司

 * @author Lu Wei

 * Created by IntelliJ IDEA.

 * User: luwei

 * Date: 2017/12/28

 * Time: 15:53

 */

/** @var \app\models\alipay\MpConfig $model */

defined('YII_ENV') or exit('Access Denied');

$this->title = '小程序配置';

?>

<div class="panel mb-3" id="app">

    <div class="panel-header"><?= $this->title ?></div>

    <div class="panel-body">

        <form class="auto-form" method="post">

            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">小程序AppID</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="tt_app_id" value="<?= $model->tt_app_id ?>">

                     <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">小程序密钥</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="tt_app_secret" value="<?= $model->tt_app_secret ?>">

                     <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">商户AppID</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="tt_mch_app_id" value="<?= $model->tt_mch_app_id ?>">

                     <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">商户号</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="tt_mch_id" value="<?= $model->tt_mch_id ?>">

                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">商户密钥</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="tt_mch_secret" value="<?= $model->tt_mch_secret ?>">

                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">支付宝应用AppID</label>

                </div>

                <div class="col-sm-6">

                    <input class="form-control" name="alipay_app_id" value="<?= $model->alipay_app_id ?>">

                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">支付宝公钥</label>

                </div>

                <div class="col-sm-6">

                    <textarea

                            style="font-family: 'SFMono-Regular',Consolas,'Liberation Mono',Menlo,Courier,monospace !important;"

                            class="form-control"

                            name="alipay_public_key"

                            rows="6"><?= $model->alipay_public_key ?></textarea>

                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

                 尚未开放功能

                <div class="col-sm-4">

                    <a href="javascript:" class="btn btn-secondary show-key-helper" data-toggle="modal"

                       data-target="#keyHelper">公钥/私钥助手</a>

                </div>

                

            </div>



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                    <label class="col-form-label required">支付宝应用私钥</label>

                </div>

                <div class="col-sm-6">

                    <textarea

                            style="font-family: 'SFMono-Regular',Consolas,'Liberation Mono',Menlo,Courier,monospace !important;"

                            class="form-control"

                            name="alipay_private_key"

                            rows="6"><?= $model->alipay_private_key ?></textarea>

                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>

                </div>

            </div>





<!--            <div class="form-group row">-->

<!--                <div class="form-group-label col-sm-2 text-right">-->

<!--                    <label class="col-form-label required">微信商户号</label>-->

<!--                </div>-->

<!--                <div class="col-sm-6">-->

<!--                    <input class="form-control" name="wechat_mch_id" value="--><?//= $model->wechat_mch_id ?><!--">-->

<!--                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>-->

<!--                </div>-->

<!--            </div>-->



<!--            <div class="form-group row">-->

<!--                <div class="form-group-label col-sm-2 text-right">-->

<!--                    <label class="col-form-label required">微信支付KEY</label>-->

<!--                </div>-->

<!--                <div class="col-sm-6">-->

<!--                    <input class="form-control" name="wechat_mch_secret" value="--><?//= $model->wechat_mch_secret ?><!--">-->

<!--                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>-->

<!--                </div>-->

<!--            </div>-->



<!--            <div class="form-group row">-->

<!--                <div class="form-group-label col-sm-2 text-right">-->

<!--                    <label class="col-form-label required">微信应用AppID</label>-->

<!--                </div>-->

<!--                <div class="col-sm-6">-->

<!--                    <input class="form-control" name="wechat_app_id" value="--><?//= $model->wechat_app_id ?><!--">-->

<!--                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>-->

<!--                </div>-->

<!--            </div>-->



<!--            <div class="form-group row">-->

<!--                <div class="form-group-label col-sm-2 text-right">-->

<!--                    <label class="col-form-label required">微信公钥</label>-->

<!--                </div>-->

<!--                <div class="col-sm-6">-->

<!--                    <textarea-->

<!--                            style="font-family: 'SFMono-Regular',Consolas,'Liberation Mono',Menlo,Courier,monospace !important;"-->

<!--                            class="form-control"-->

<!--                            name="wechat_public_key"-->

<!--                            rows="6">--><?//= $model->wechat_public_key ?><!--</textarea>-->

<!--                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>-->

<!--                </div>-->

                <!-- 尚未开放功能

                <div class="col-sm-4">

                    <a href="javascript:" class="btn btn-secondary show-key-helper" data-toggle="modal"

                       data-target="#keyHelper">公钥/私钥助手</a>

                </div>

                -->

<!--            </div>-->



<!--            <div class="form-group row">-->

<!--                <div class="form-group-label col-sm-2 text-right">-->

<!--                    <label class="col-form-label required">微信应用私钥</label>-->

<!--                </div>-->

<!--                <div class="col-sm-6">-->

<!--                    <textarea-->

<!--                            style="font-family: 'SFMono-Regular',Consolas,'Liberation Mono',Menlo,Courier,monospace !important;"-->

<!--                            class="form-control"-->

<!--                            name="wechat_private_key"-->

<!--                            rows="6">--><?//= $model->wechat_private_key ?><!--</textarea>-->

<!--                    <div class="tip-block">已隐藏内容，点击查看或编辑</div>-->

<!--                </div>-->

<!--            </div>-->



            <div class="form-group row">

                <div class="form-group-label col-sm-2 text-right">

                </div>

                <div class="col-sm-6">

                    <a class="btn btn-primary auto-form-btn" href="javascript:">保存</a>

                </div>

            </div>

        </form>



        <!-- Modal key helper-->

        <div class="modal" id="keyHelper" data-backdrop="static">

            <div class="modal-dialog" role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="exampleModalLabel">支付宝公钥/应用私钥</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <div v-if="!key_loading">

                            <div v-if="key">

                                <label>支付宝公钥：</label>

                                <textarea rows="5" class="form-control mb-3"

                                          readonly>{{key.alipay_public_key}}</textarea>

                                <label>应用私钥：</label>

                                <textarea rows="5" class="form-control mb-3"

                                          readonly>{{key.alipay_private_key}}</textarea>

                                <div class="text-right">

                                    <div>请将支付宝公钥和应用私钥</div>

                                    <a href="javascript:" class="btn btn-primary">确认使用此密钥对</a>

                                </div>

                            </div>

                            <div v-else class="text-center text-danger p-3">{{error_msg}}</div>

                        </div>

                        <div v-else class="text-center text-muted p-3">密钥生成中...</div>

                    </div>

                </div>

            </div>

        </div>



    </div>

</div>

<script>

var app = new Vue({

    el: '#app',

    data: {

        key_loading: false,

        key: null,

        error_msg: '',

    },

});

$(document).on('click', '.show-key-helper', function () {

    app.key_loading = true;

    $.ajax({

        url: '<?=Yii::$app->urlManager->createUrl(['mch/alipay/key-generate'])?>',

        dataType: 'json',

        success: function (res) {

            console.log(res);

            if (res.code == 0) {

                app.key = res.data;

            } else {

                app.error_msg = res.msg;

            }

        },

        complete: function () {

            app.key_loading = false;

        }

    });

    $('#keyHelper').modal('show');

});



$(document).on('click', '.generate-key', function () {



});



</script>