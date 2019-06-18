<?php
    /** @var cmsTemplate $this
     *  @var array $payTypes
     *  @var payInvoice $invoice
     */
    $this->addControllerCSS('pay');
?>
<h1>К оплате:</h1>

<?php $this->renderChild('invoice_data', ['invoice' => $invoice]); ?>

<p>
    <?= $invoice->amount ?>
</p>

<?php if (!isset($payTypes[$invoice->pay_type])): ?>

    <?php if (!empty($payTypes)): ?>

    <h1>Выберите способ оплаты</h1>

        <ul id="payTypes">
    <?php foreach ($payTypes as $payTypeId => $payType): ?>
        <li id="pay_<?= $payTypeId ?>">
            <a href="#" data-paytype="<?= $payTypeId ?>" title="<?= $payType ?>"><?= $payType ?></a>
        </li>
    <?php endforeach; ?>
        </ul>


    <?php else: ?>
        <h1>Система оплаты на сайте временно отключена. </h1>
        <p>На сайте не включен ни один способ оплаты, поэтому в данный момент оплата на сайте отключена.</p>
    <?php endif; ?>

<?php else:
    $payTypeId = $invoice->pay_type;
    $payType = $payTypes[$payTypeId];
    ?>
    <h1>Оплатить:</h1>
    <ul id="payTypes">
        <li id="pay_<?= $payTypeId ?>">
            <a href="#" data-paytype="<?= $payTypeId ?>" title="<?= $payType ?>"><?= $payType ?></a>
        </li>
    </ul>
    <p><a href="<?= $this->href_to('reset', $invoice->id) ?>">Выбрать другой способ оплаты</a></p>
<?php endif; ?>

<script>
    $(function() {
       $('#payTypes a').click(function () {
           var $a = $(this);
           var payType = $a.attr('data-paytype');
           location.href = "<?= $this->href_to('') ?>/"+payType+"/payment/<?= $invoice->id ?>"
       });
    });
</script>
