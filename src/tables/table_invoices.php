<?php

namespace pdima88\icms2pay\tables;

use pdima88\icms2ext\Table;
use cmsUser;
use cmsModel;
use Zend_Db_Table_Row_Abstract;

/**
 * Class payInvoice
 * @property int $id ID счета к оплате (инвойса)
 * @property int $user_id ID пользователя для которого создан счет
 * @property rowUser $user Пользователь
 * @property string $title Наименование счета
 * @property string $controller Имя компонента, который выставил счет
 * @property string $type Тип счета (символьный код), имеет значение в компоненте
 * @property string $order_id ID заказа для которого выписан счет
 * @property array $data Массив дополнительных данных, например items - массив строк в счете
 * @property double $amount Сумма
 * @property int $status Состояние счета: 0 - создан, ожидает оплаты, 1 - оплачен, 2 - ошибка, 3 - отменен,  -1 - удален,
 * @property string $date_created Дата создания счета
 * @property string $date_paid Дата оплаты счета
 * @property string $pay_type Тип оплаты (символьный код способа оплаты)
 * @property string $pay_info Информация об оплате
 * @property string $date_approved Дата отметки об оплате (в случае внесения сведений об оплате администратором)
 * @property int $approved_by_user_id Кто внес сведения об оплате (ID пользователя)
 * @property string $date_cancel Дата отмены платежа (если отменен)
 * @property string $cancel_info Сведения об отмене платежа
 * @property string $cancelled_by_user_id Кто отменил платеж (ID пользователя)
 * @property int $error Код ошибки
 * @property int $error_info Сведения об ошибке
 */
class row_invoice extends Zend_Db_Table_Row_Abstract {
    function activate() {
        $this->getTable()->activateOrder($this);
    }

    function __get($columnName)
    {
        if ($columnName == 'data') {
            return cmsModel::yamlToArray($this->_data['data']);
        }
        if ($columnName == 'user') {
            return $this->findParenttableUsers();
        }
        return parent::__get($columnName);
    }
     function __set($columnName, $value)
     {
         if ($columnName == 'data') {
             $value = cmsModel::arrayToYaml($value);
         }
         parent::__set($columnName, $value);
     }
}

/**
 * CREATE TABLE `cms_pay_invoices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(250) NULL DEFAULT NULL,
    `controller` VARCHAR(50) NULL DEFAULT NULL,
    `type` VARCHAR(50) NULL DEFAULT NULL,
    `order_id` VARCHAR(50) NULL DEFAULT NULL,
    `data` TEXT NULL,
    `amount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `status` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 - создан, ожидает оплаты, 1 - оплачен, 2 - ошибка, 3 - отменен,  -1 - удален, ',
    `date_created` DATETIME NOT NULL,
    `date_paid` DATETIME NULL DEFAULT NULL,
    `pay_type` VARCHAR(50) NULL DEFAULT NULL,
    `pay_info` VARCHAR(250) NULL DEFAULT NULL,
    `date_approve` DATETIME NULL DEFAULT NULL,
    `approved_by_user_id` INT(10) UNSIGNED NULL DEFAULT NULL,
    `date_cancel` DATETIME NULL DEFAULT NULL,
    `cancel_info` VARCHAR(250) NULL DEFAULT NULL,
    `cancelled_by_user_id` INT(11) NULL DEFAULT NULL,
    `error` INT(11) NULL DEFAULT NULL,
    `error_info` VARCHAR(250) NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
    )
    COLLATE='utf8_general_ci'
    ENGINE=InnoDB
    AUTO_INCREMENT=18
    ;



 *
 * @method static row_invoice getById($id) Возвращает заказ по ID
 * @method row_invoice createRow(array $data = [], $defaultSource = null)
 */
class table_invoices extends Table {
    const STATUS_CANCELLED = 3;

    protected $_name = 'pay_invoices';

    protected $_rowClass = __NAMESPACE__.'\\row_invoice';

    protected $_primary = ['id'];

    protected $_referenceMap = [
        'User' => [
            self::COLUMNS           => 'user_id',
            self::REF_TABLE_CLASS   => 'tableUsers',
            self::REF_COLUMNS       => 'id'
        ]
    ];

    const FK_USER = 'pdima88\\icms2pay\\tables\\table_invoices.User';

    /**
     * Creates new invoice for current user
     * @return Invoice
     */
    function make($amount, $title, $controller, $data = null, $userId = null) {
        $invoice = $this->createRow();
        $invoice->user_id = $userId ?? cmsUser::getInstance()->id;
        $invoice->amount = $amount;
        $invoice->title = $title;
        $invoice->controller = $controller;
        if (isset($data['type'])) {
            $invoice->type = $data['type'];
            unset($data['type']);
        }
        if (isset($data['order_id'])) {
            $invoice->order_id = $data['order_id'];
            unset($data['order_id']);
        }
        if (isset($data)) {
            $invoice->data = $data;
        }
        $invoice->date_created = now();
        $invoice->status = 0;
        return $invoice;
    }
}