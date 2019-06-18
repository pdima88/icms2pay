<?php

namespace pdima88\icms2pay\backend\forms;

use cmsForm;
use cmsCore;
use fieldString;
use fieldList;
use fieldListGroups;
use fieldNumber;
use fieldCheckbox;

/**
 * Class formPayOptions
 * @property backendPay $controller
 */
class form_options extends cmsForm {
	
	public $is_tabbed = true;
	
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

		$payTypes = cmsCore::getController('pay')->getPayTypes();

		foreach ($payTypes as $payTypeId => $payTypeOptions) {
			$payTitle = isset($payTypeOptions['title']) ? $payTypeOptions['title'] : ucfirst($payTypeId);
			$mass_opt_start = array(
				new fieldCheckbox($payTypeId.'_on', array(
					'title' => 'Платежная система включена',
				)),
				new fieldString($payTypeId.'_name', array(
					'title' => 'Название',
					'default' => $payTitle,
					'rules' => array(
						array('required')
					)
				)),
				new fieldString($payTypeId.'_hint', array(
					'title' => 'Подсказка. Краткая информация о платежной системе',
					'default' => ''
				)),
			);

			$mass_opt_end = array(
				new fieldNumber($payTypeId.'_order', array(
					'title' => 'Порядковый номер для сортировки в списке платежных систем',
				))
			);

			$options[] = array(
				'type' => 'fieldset',
				'title' => $payTitle,
				'childs' => array_merge($mass_opt_start, isset($payTypeOptions['form']) ? $payTypeOptions['form'] : $payTypeOptions, $mass_opt_end)
			);

		}

		return $options;
	
	}
	
}
