<?php
use pdima88\icms2ext\Format;

/**
 * @var payInvoice $invoice
 */

$t = new \pdima88\icms2ext\Translate('pay:transfer_');

?>
<h1><?= t('pay:tranfer_title', 'Оплата перечислением для юридических лиц') ?></h1>
<div id="transfer">    
    <p class="no_print">
        <?= $t->_('desc'); ?>
        Распечатайте счет на оплату и оплатите перечислением на указанные реквизиты.
        Как только средства поступят на счет, мы поставим отметку об оплате и уведомим вас по телефону
        <?= $t() ?>
    </p>

        

            <div style="margin-left: 200px; font-size: 14pt">
                <?php t_('pay_invoice:right1', $invoice->order_id, format_date($invoice->date_created),
                    $transfer['receiver'], $transfer['address'], $transfer['phone'], $transfer['account'],
                    $transfer['bank'], $transfer['mfo'], $transfer['inn'], $transfer['oked']
                    ); ?>
                <h2>СЧЕТ НА ОПЛАТУ № %s</h2>
                <h3>от %sг.</h3>
                <br>
                <table border="0">
                    <tbody>
                    <tr>
                        <td width="150">Поставщик</td>
                        <td>%s</td>
                    </tr>
                    <tr>
                        <td>Адрес</td>
                        <td>%s</td>
                    </tr>
                    <tr>
                        <td>Телефон</td>
                        <td>%s</td>
                    </tr>
                    <tr>
                        <td>Р/сч:</td>
                        <td>%s</td>
                    </tr>
                    <tr>
                        <td>Банк:</td>
                        <td>%s &nbsp; &nbsp; МФО: %s</td>
                    </tr>
                    <tr>
                        <td>ИНН</td>
                        <td>%s</td>
                    </tr>
                    <tr>
                        <td>ОКЭД</td>
                        <td>%s</td>
                    </tr>
                    </tbody>
                </table>
                <?= _t(); ?>
            </div>

            <div style="clear:both;font-size:14pt;font-weight:bold">
                <br>
                <?php t_('pay_invoice:center1'); ?>
                Оплата данного счета свидетельствует о заключении договора - публичной оферты
                по предоставлению образовательных услуг и приему денежных средств (ст.367-375 ГК РУз).
                <?= _t() ?>
            </div>

            <br>

            <table border="1" width="90%" class="invoice">
                <tbody>
                <tr>
                    <td>№</td>
                    <td><?= t('pay:transfer_item_head', 'Наименование') ?></td>
                    <td><?= t('pay:transfer_valut_head', 'Ед.изм.') ?></td>
                    <td><?= t('pay:transfer_amount_head', 'Стоимость услуг') ?></td>
                </tr>
                <tr>
                    <td>1</td>
                    <td>&PRODUCT_TITLE;</td>
                    <td><!--#t id="pay_invoice:valut" s="сум."--></td>
                    <td style="white-space: nowrap;">&PRICE;</td>
                </tr>
                <tr>
                    <td colspan="4">
                        <?php t_('pay:transfer_total', $invoice->amount, Format::currencyToWordsRu($invoice->amount)) ?>
                        Всего к оплате: %s (%s) Без НДС.
                        <?= _t() ?>
                    </td>
                </tr>
                </tbody>
            </table>
            <br>
            <div class="font-size:16pt">
                <?php t_('pay:transfer_note') ?>
                При оплате счета в платежном поручении в графе «Назначение платежа» укажите:<br>
                <ul class="dashed">
                    <li>Оказание услуг по повышению квалификации</li>
                    <li>Дата и номер счета.</li>
                    <li>Ф. И.О. соискателя.</li>
                </ul>
                <?= _t() ?>
            </div>

            <table border="0" width="80%" style="font-size: 16pt; margin-top: -30px;height: 180px; background: url('/files/bir.uz/images/payment-btns/sign2.jpg') 300px 0 no-repeat; background-size: 350px">
                <tr>
                    <td style="text-align: center; width: 250px">
                        <?= t('pay:transfer_chief', 'Директор') ?>
                    </td>
                    <td>
                        <img src="/files/bir.uz/images/payment-btns/sign2.jpg" width="350" class="hidden_print">
                    </td>
                    <td style="text-align: right">
                        <?= $transfer['chief'] ?>
                    </td>
                </tr>
            </table>

            <br>

            <div class="text-center no_print">
                <button class="orange-btn" onclick="print()"><!--#t id="pay_receipt:print" s="Распечатать"--></button>
            </div>

            <br>

        </div>
    </div>
</div>
<!--#else-->
<div class="container">
    <div class="content">
        <h2><!--#t id="error" s="Ошибка"--></h2>
    </div>
</div>
<!--#endfor-->

<!--#end-->

<!--#include tpl="blocks/bottom" -->