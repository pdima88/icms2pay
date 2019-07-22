<?php

namespace pdima88\icms2pay\forms;

use fieldDate;
use fieldText;
use fieldList;
use cmsForm;
use pdima88\icms2pay\frontend as pay;

class form_setpaid extends cmsForm {

    public function init(){

        $form = [
            'basic' => [
                'type' => 'fieldset',
                'title' => 'Общие настройки',
                'childs' => [
                    new fieldList('pay_type', [
                        'title' => 'Тип оплаты',
                        'items' => [
                            'manual' => 'Вручную (администратор)',
                            'receipt' => 'Квитанция в банк',
                            'transfer' => 'Перечислением'
                        ],
                    ]),

                    new fieldDate('date_paid', [
                        'title' => 'Дата/время оплаты',
                        'default' => now(),
                        'options' => [
                            'show_time' => true
                        ]
                    ]),

                    new fieldText('pay_info', [
                        'title' => 'Сведения об оплате',
                        'rules' => [
                            ['required']
                        ]
                    ]),
                ]

            ]

        ];

        return $form;

    }

}
