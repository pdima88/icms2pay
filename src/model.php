<?php

namespace pdima88\icms2pay;

use pdima88\icms2ext\GridHelper;
use pdima88\icms2ext\Model as BaseModel;
use pdima88\icms2ext\Table;
use cmsCore;
use cmsDatabase;
use cmsEventsManager;
use Exception;

/**
 * Class modelPay
 * @property tablePay_Invoices $invoices
 */
class model extends BaseModel {
    
    const TABLE_INVOICES = 'pay_invoices';

    const INVOICE_STATUS_CREATED = 0;
    const INVOICE_STATUS_PAID = 1;
    const INVOICE_STATUS_ERROR = 2;
    const INVOICE_STATUS_CANCELLED = 3;
    const INVOICE_STATUS_DELETED = -1;

    function __get($name)
    {
        if ($name == 'invoices') {
            return $this->getTable($name);
        }
        throw new Exception('Unknown property '.$name);
    }

    function getInvoicesGrid() {

        $select = BaseModel::zendDbSelect()->from(Table::prefix(self::TABLE_INVOICES));

        $grid = [
            'id' => 'invoices',
            'select' => $select,
            'sort' => [
                'id' => 'desc',
            ],

            'rownum' => false,

            'multisort' => true,
            'paging' => 15,

            'url' => cmsCore::getInstance()->uri_absolute,
            'ajax' => cmsCore::getInstance()->uri_absolute,
            'actions' => GridHelper::getActions([
                    'edit' => [
                        'title' => 'Изменить',
                        'href'  => href_to('admin', 'controllers', ['edit', 'pay', 'tariffs_edit', '{id}']) . '?back={returnUrl}'
                    ],
                    'delete' => [
                        'title' => 'Удалить',
                        'href' => '',
                        'confirmDelete' => true,
                    ]
            ]),
            'delete' => href_to('admin', 'controllers', ['edit', 'pay', 'tariffs_delete', '{id}']). '?back={returnUrl}',
            'columns' => [
                'id' => [
                    'title' => '№ счета',
                    'width' => 70,
                    'sort' => true,
                    'filter' => 'equal'
                ],
                'user_id' => [
                    'title' => 'ID польз.',
                    'width' => 70,
                    'sort' => true,
                    'filter' => 'equal'
                ],
                'fullname' => [
                    'title' => 'Ф.И.О. пользователя',
                    'sort' => true,
                    'filter' => 'text',
                ],
                'email' => [
                    'title' => 'E-mail',
                    'sort' => true,
                    'filter' => 'text',
                ],
                'phone' => [
                    'title' => 'Номер телефона',
                    'sort' => true,
                    'filter' => 'text',
                ],
                'title' => [
                    'title' => 'Наименование счета',
                    'sort' => true,
                    'filter' => 'text'
                ],
                'amount' => [
                    'title' => 'Сумма',
                    'sort' => true,
                    'filter' => 'equal'
                ],
                'status' => [
                    'title' => 'Статус',
                    'sort' => true,
                    'filter' => 'multiselect',
                ],
                'date_add' => [
                    'title' => 'Когда создан',
                    'sort' => true,
                    'filter' => 'dateRange',
                    'filterOpens' => 'left',
                ],
                'date_paid' => [
                    'title' => 'Когда оплачен',
                    'sort' => true,
                    'filter' => 'dateRange',
                    'filterOpens' => 'left',
                ],
                'pay_system' => [
                    'title' => 'Тип оплаты',
                    'sort' => true,
                    'filter' => 'select'
                ],
                'pay_info' => [
                    'title' => 'Сведения о платеже',
                    'filter' => 'text',
                ],
            ]
        ];

        return $grid;
    }

    function getInvoice($id) {
        $this->filter('status >= 0');
        return $this->getItemById(self::TABLE_INVOICES, $id);
    }

    function validateInvoice($invoiceId, $amount) {
        $invoice = $this->getInvoice($invoiceId);
        return ($invoice && $invoice['status'] == 0 && $invoice['amount'] == $amount);
    }
    
    function setInvoicePaid($id, $payInfo, $payType = null, $payUserId = null) {
        $needTransaction = false;
        $db = cmsDatabase::getInstance();
        if (!$db->isAutocommitOn()) {
            $needTransaction = true;
        }
        if ($needTransaction) {
            $db->beginTransaction();
        }

        $invoice = $this->getInvoice($id);

        if (!$invoice) throw new Exception('Invoice not found!');

        if ($invoice['status'] != 0) throw new Exception('Invoice already paid!');

        $data = [
            'date_paid' => date('Y-m-d H:i:s'),
            'pay_info' => $payInfo,
            'status' => self::INVOICE_STATUS_PAID
        ];
        if ($payType) {
            $data['pay_type'] = $payType;
        }
        if ($payUserId) {
            $data['pay_user_id'] = $payUserId;
        }

        if (($result = cmsEventsManager::hook('pay_invoice_set_paid', array_merge(
            $data,
            ['invoice' => $invoice])
            , true)) !== true)
        {
            throw new Exception('Can`t set invoice paid'.($result ? ': '.$result : ''));
        }

        $this->update(self::TABLE_INVOICES, $id, $data);

        if ($needTransaction) {
            $db->commit();
        }

    }

    function cancelInvoice($id, $payType, $cancelInfo) {
        $needTransaction = false;
        $db = cmsDatabase::getInstance();
        if (!$db->isAutocommitOn()) {
            $needTransaction = true;
        }
        if ($needTransaction) {
            $db->beginTransaction();
        }

        $invoice = $this->getInvoice($id);

        if (!$invoice) return true;

        if ($invoice['pay_type'] != $payType) return true;

        if ($invoice['status'] == self::INVOICE_STATUS_CANCELLED) return true;

        $data = [
            'date_cancel' => date('Y-m-d H:i:s'),
            'cancel_info' => $cancelInfo,
            'status' => self::INVOICE_STATUS_CANCELLED,
        ];

        if (($result = cmsEventsManager::hook('pay_invoice_cancel', array_merge(
                    $data,
                    ['invoice' => $invoice])
                , true)) !== true)
        {
            throw new Exception('Can`t cancel invoice'.($result ? ': '.$result : ''));
        }

        $this->update(self::TABLE_INVOICES, $id, $data);

        if ($needTransaction) {
            $db->commit();
        }
        return true;

    }
}
