<?php

namespace pdima88\icms2pay\forms;

use fieldString;
use fieldCheckbox;
use fieldNumber;
use fieldList;
use fieldListGroups;
use cmsCore;
use cmsForm;

class form_setpaid extends cmsForm {

    public function init(){

        $options = array(

            array(
                'type' => 'fieldset',
                'title' => 'Общие настройки',
                'childs' => array(

                    new fieldString('curr_short', array(
                        'title' => 'Валюта сайта',
                        'hint' => 'Сокращенное обозначение',
                        'default' => 'сўм',
                        'rules' => array(
                            array('required')
                        )
                    )),

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


        $systems = files_tree_to_array('system/controllers/pay/actions/');
        if ($systems) {
            foreach ($systems as $value) {
                $value = str_replace('.php', '', $value);
                if(in_array($value, array('base', 'pay'))) continue;
                $childs = cmsCore::getController('pay')->runExternalAction($value, array('options'));
                $payTitle = isset($childs['title']) ? $childs['title'] : ucfirst($value);

                $mass_opt_start = array(
                    new fieldCheckbox($value.'_on', array(
                        'title' => 'Платежная система включена',
                    )),
                    new fieldString($value.'_name', array(
                        'title' => 'Название',
                        'default' => $payTitle,
                        'rules' => array(
                            array('required')
                        )
                    )),
                    new fieldString($value.'_hint', array(
                        'title' => 'Подсказка. Краткая информация о платежной системе',
                        'default' => ''
                    )),
                );

                $mass_opt_end = array(
                    new fieldNumber($value.'_order', array(
                        'title' => 'Порядковый номер для сортировки в списке платежных систем',
                    ))
                );

                $paySystemOptions[$value] = array(
                    'type' => 'fieldset',
                    'title' => $payTitle,
                    'childs' => array_merge($mass_opt_start, isset($childs['form']) ? $childs['form'] : $childs, $mass_opt_end)
                );
                $paySystemSortOrder[$value] = $this->controller->options[$value.'_order'] ?? 0;
            }
        }

        asort($paySystemSortOrder);
        foreach ($paySystemSortOrder as $paySystem => $sortOrder) {
            $options[] = $paySystemOptions[$paySystem];
        }

        return $options;

    }

}
