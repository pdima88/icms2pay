<?php
/** @var cmsTemplate $this
 *  @var array $receipt
 *  @var payInvoice $invoice
 */

use pdima88\icms2ext\Translate;

$this->addControllerCSS('receipt');
$t = new Translate('pay:receipt_');
?>
<style>
    .hidden_print {
        display: none;
    }
</style>
<h1><?= $t('title', 'Оплата по квитанции для физических лиц') ?></h1>


<div id="receipt">

    <p class="no_print">
        <?php if(!$t->_('desc')) { ?>
        1. Пожалуйста, заполните адрес проживания и распечатайте квитанцию.<br>
        2. Оплатите сумму по квитанции в отделении банка.<br>
        3. После поступления средств на наш счет, Вам будет предоставлен доступ.
        Мы свяжемся с Вами и сообщим об этом по телефону.23
        <?php $t->_t(); }?>
    </p>
    <?php
    $receipt['address'] = '1';
    $receipt['phone'] = '1';
    $receipt['chief'] = '1';
    $this->renderChild('transfer', ['invoice' => $invoice, 'transfer' => $receipt]);
    echo $t->prefix;
    ?>
            <br>

            <?php for ($i = 0; $i < 2; $i++): ?>
            <table border="1" cellspacing="0" cellpadding="0" class="receipt">
                <tbody>
                <tr>
                    <td rowspan="5" width="200" style="vertical-align: top;">
                        <?php if ($i == 0) {
                            echo $t('left1', 'Извещение');
                        } else {
                            echo $t('left3', 'Квитанция');
                        }
                        ?>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <?= t('pay:receipt_left2', 'Кассир') ?>
                    </td>
                    <td colspan="2">
                        <div style="margin-left: 70px">
                            <?= t_('pay:receipt_right1', $receipt['receiver'] ?? '',
                                $receipt['account'] ?? '', $receipt['bank'] ?? '',
                                $receipt['mfo'] ?? '', $receipt['inn'] ?? '',
                                $receipt['oked'] ?? ''
                                ) ?>
                            Получатель: %s<br>
                            Р/С: %s в %s &nbsp; &nbsp;
                            МФО: %s<br>
                            ИНН: %s &nbsp; &nbsp; &nbsp;
                            ОКЭД: %s<br>
                            <?= t() ?>
                            <br>
                            <br>
                            <div class="username"><?= $invoice->user->lname ?> <?= $invoice->user->fname ?> <?= $invoice->user->mname ?></div>
                            <div style="text-align: center;font-size:10px">
                                <?= t('pay:receipt_right2','(фамилия, имя, отчество)') ?>
                            </div>
                            <br style="clear:both">
                            <div style="float:left">
                                <?= t('pay:receipt_right3', 'Адрес проживания:') ?>
                            </div>
                            <div class="address">
                                <?php if ($i==0): ?>
                                    <textarea id="txtReceiptAddress" type="text" class="no_print"
                                              placeholder="<?= t('pay:receipt_right4', 'Введите адрес проживания') ?>"
                                style="width:100%"></textarea>
                                <span class="hidden_print"></span>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>

                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center">
                        <?= t('pay:receipt_right5', 'Наименование') ?>
                    </td>
                    <td style="text-align: center">
                        <?= t('pay:receipt_right6', 'Сумма') ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?= t('pay:receipt_right7', '%s (№ заказа: %d)', $invoice->title, $invoice->order_id) ?>
                    </td>
                    <td style="text-align: right;white-space: nowrap;">
                        <?= format_currency($invoice->amount)  ?>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: right">
                        <?= t('pay:receipt_right8', 'Итого:') ?>
                    </td>
                    <td style="text-align: right;white-space: nowrap;">
                        <?= format_currency($invoice->amount)  ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?= t('pay:receipt_right9', 'Подпись плательщика:') ?>
                    </td>
                    <td>

                    </td>
                </tr>
                </tbody>

            </table>
            <?php if($i==0): ?>
            <hr>
            <?php else: ?>

            <br>

            <div class="text-center no_print">
                <button class="orange-btn" onclick="print()"><?= t('pay:receipt_print', 'Распечатать') ?></button>
            </div>

            <br>
            <?php endif; ?>
            <?php endfor; ?>


<script>
    $(function() {
        $('#txtReceiptAddress').on('keyup', function() {
            var t = $(this).val().replace(/</g, '&lt;', true)
                .replace(/>/g,'&gt;', true).replace(/\n/g, '<br>');
            $('.receipt .address span').html(t);
        });

    });
</script>