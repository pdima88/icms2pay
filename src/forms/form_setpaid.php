<?php

namespace pdima88\icms2pay\forms;

use fieldDate;
use fieldString;
use fieldCheckbox;
use fieldNumber;
use fieldList;
use fieldListGroups;
use cmsCore;
use cmsForm;
use pdima88\icms2pay\frontend;

class form_setpaid extends cmsForm {

    public function init(){

        $form = [
            [
                'type' => 'fieldset',
                'title' => 'Общие настройки',
                'childs' => [
                    new fieldList('pay_type', [
                        'title' => 'Тип оплаты',
                        'items' => frontend::getInstance()->getPayTypeList(false),
                    ]),

                    new fieldDate('date_paid', [
                        'title' => 'Дата/время оплаты',
                        'default' => 'current'
                    ])

                    new fieldText('pay_info', [
                        'title' => 'Сведения об оплате',
                        'rules' => [
                            ['required']
                        ]
                    ]),



                    new fieldList('payments_list_style', array(
                        'title' => 'Шаблон выбора системы оплаты',
                        'items' => array(
                            'basic' => 'Список кнопок',
                            'default' => 'Кнопка оплатить с выбором',
                        )
                    )),

                    new fieldListGroups('invoice_access', array(
                        'title' => 'Доступ к управлению счетами',

                    ))
                )

            )

        );

        $paySystemOptions = [];
        $paySystemSortOrder = [];



            }
        }

        asort($paySystemSortOrder);
        foreach ($paySystemSortOrder as $paySystem => $sortOrder) {
            $options[] = $paySystemOptions[$paySystem];
        }

        return $options;

    }

}
