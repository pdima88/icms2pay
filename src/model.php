<?php

namespace pdima88\icms2pay;

use pdima88\icms2ext\GridHelper;
use pdima88\icms2ext\Model as BaseModel;
use pdima88\icms2ext\Table;
use cmsCore;
use cmsDatabase;
use cmsEventsManager;
use Exception;
use pdima88\icms2pay\tables\table_invoices;

/**
 * Class modelPay
 * @property table_invoices $invoices
 */
class model extends BaseModel {
    
    const TABLE_INVOICES = 'pay_invoices';

    const INVOICE_STATUS_CREATED = 0;
    const INVOICE_STATUS_PAID = 1;
    const INVOICE_STATUS_ERROR = 2;
    const INVOICE_STATUS_CANCELLED = 3;
    const INVOICE_STATUS_DELETED = -1;

    static $invoiceStatusList = [
        self::INVOICE_STATUS_CREATED => 'Не оплачен',
        self::INVOICE_STATUS_PAID => 'Оплачен',
        self::INVOICE_STATUS_ERROR => 'Ошибка',
        self::INVOICE_STATUS_CANCELLED => 'Отменен',
        self::INVOICE_STATUS_DELETED => 'Удален'
    ];

    function __get($name)
    {
        if ($name == 'invoices') {
            return $this->getTable($name);
        }
        throw new Exception('Unknown property '.$name);
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
