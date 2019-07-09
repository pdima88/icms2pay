<?php

namespace pdima88\icms2pay\actions;

use fieldString;
use cmsTemplate;

class receipt extends base {

    public function options() {
        $options = array(
            'title' => 'Квитанция в банк',
            'form' => array(
                new fieldString('receipt_receiver', array(
                    'title' => 'Получатель',
                    'hint' => 'Юридическое наименование организации - получателя денежных средств'
                )),
                new fieldString('receipt_account', array(
                    'title' => 'Расчетный счет',
                )),
                new fieldString('receipt_bank', array(
                    'title' => 'Банк',
                    'hint' => 'Наименование банка, где открыт расчетный счет'
                )),
                new fieldString('receipt_mfo', array(
                    'title' => 'МФО'
                )),
                new fieldString('receipt_inn', array(
                    'title' => 'ИНН'
                )),
                new fieldString('receipt_oked', array(
                    'title' => 'ОКЭД'
                )),
            )
        );

        return $options;
    }

    public function payment($invoice)
    {
        $options = $this->controller->options;
        $receipt = [];
        foreach ($options as $name => $value) {
            if (string_starts($name, 'receipt_')) {
                $receipt[string_trim($name, 'receipt_')] = $value;
            }
        }

        cmsTemplate::getInstance()->render('receipt', [
            'invoice' => $invoice,
            'receipt' => $receipt
        ]);
    }
}
