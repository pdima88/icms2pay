<?php

namespace pdima88\icms2pay\actions;

use pdima88\icms2ext\Model;
use pdima88\icms2ext\Table;

require_once __DIR__."/base.php";

/**
 * @property modelPay $model;
 */
class click extends actionPayBase {

    const ACTION_PREPARE = 0;
    const ACTION_COMPLETE = 1;

    protected $merchantId = '';
    protected $serviceId = '';
    protected $secretKey = '';
    protected $userId = '';
    protected $authToken = '';

    protected $checkoutUri = 'https://my.click.uz/services/pay';

    protected function loadOptions() {
        $this->merchantId = $this->options['click_merchantid'];
        $this->serviceId = $this->options['click_serviceid'];
        $this->secretKey = $this->options['click_secretkey'];
        $this->userId = $this->options['click_userid'];
        $this->authToken = $this->options['click_auth_token'];
    }

    protected $amount = null;

    protected $clickTransId;
    protected $merchantTransId;
    protected $merchantPrepareId;
    protected $clickAction = 0;
    protected $clickPaydocId = 0;
    protected $error = 0;
    protected $errorNote = 0;

    protected $requestId = 0;

    /** @var Zend_Db_Adapter_Mysqli */
    protected $db = null;
    protected $tableName = 'pay_click';


    protected function checkSign() {
        $this->clickTransId = $this->request->get('click_trans_id', 0);
        $serviceId = $this->request->get('service_id', 0);

        $this->merchantTransId = $this->request->get('merchant_trans_id', '');
        $this->clickAction = $this->request->get('action', 0);
        if ($this->clickAction == self::ACTION_COMPLETE) {
            $this->merchantPrepareId = $this->request->get('merchant_prepare_id', 0);
        }
        $this->clickPaydocId = $this->request->get('click_paydoc_id', 0);
        $this->amount = $this->request->get('amount', 0.0);
        $signTime = $this->request->get('sign_time', '');
        $signString = $this->request->get('sign_string', '');
        $this->error = $this->request->get('error', 0);
        $this->errorNote = $this->request->get('error_note', '');

        $this->db->insert($this->tableName, [
            'click_trans_id' => $this->clickTransId,
            'click_paydoc_id' => $this->clickPaydocId,
            'service_id' => $serviceId,
            'merchant_trans_id' => $this->merchantTransId,
            'merchant_prepare_id' => $this->merchantPrepareId,
            'amount' => $this->amount,
            'action' => $this->clickAction,
            'error' => $this->error,
            'error_note' => $this->errorNote,
            'sign_time' => $signTime,
            'sign_string' => $signString
        ]);

        $this->requestId = $this->db->lastInsertId($this->tableName);

        if ($this->serviceId != $serviceId) return false;
        return (md5($this->clickTransId.$this->serviceId.$this->secretKey.$this->merchantTransId.
                (($this->clickAction == self::ACTION_COMPLETE) ? $this->merchantPrepareId : '').
                $this->amount.$this->clickAction.$signTime) == $signString);
    }

    public function process($param) {
        if (!isset($this->db)) {
            $this->db = Model::zendDb();
            $this->tableName = Table::prefix($this->tableName);
        }
        $this->loadOptions();
        if (!empty($this->authToken)) {
            if ($param != $this->authToken) cmsCore::error404();
        }
        try {
            if ($this->checkSign()) {
                if ($this->clickAction == self::ACTION_PREPARE) {
                    $this->prepare();
                } elseif ($this->clickAction == self::ACTION_COMPLETE) {
                    $this->complete();
                } else {
                    throw new ClickException(ClickException::ERROR_ACTION_NOT_FOUND);
                }
            } else {
                throw new ClickException(ClickException::ERROR_SIGN_CHECK_FAILED);
            }
        } catch (ClickException $e) {
            $this->error = $e->getCode();
            $this->errorNote = $e->getMessage();
        }

        header('Content-Type: application/json; charset=UTF-8');

        $response = [
            'click_trans_id' => $this->clickTransId,
            'merchant_trans_id' => $this->merchantTransId,
            'error' => $this->error,
            'error_note' => $this->errorNote,
        ];
        if ($this->clickAction == self::ACTION_PREPARE) {
            $response['merchant_prepare_id'] = $this->requestId;
        } elseif ($this->clickAction == self::ACTION_COMPLETE) {
            $response['merchant_confirm_id'] = $this->requestId;
        }

        $answer = json_encode($response);

        if ($this->requestId) {
            $this->db->update($this->tableName, [
                'answer' => $answer,
                'answer_error' => $this->error,
                'answer_error_note' => $this->errorNote,
            ], ['id = ?' => $this->requestId]);
        }

        echo $answer;
        exit;
    }

    private function prepare()
    {
        $invoice = $this->model->getInvoice($this->merchantTransId);
        if (!$invoice || $invoice['pay_type'] != 'click') {
            throw new ClickException(ClickException::ERROR_USER_DOES_NOT_EXIST, 'Invoice not found');
        }
        if ($invoice['status'] != 0) {
            throw new ClickException(ClickException::ERROR_ALREADY_PAID);
        }
        if ($invoice['amount'] != $this->amount) {
            throw new ClickException(ClickException::ERROR_INCORRECT_PARAMETER_AMOUNT);
        }
    }

    private function complete()
    {
        $invoice = $this->model->getInvoice($this->merchantTransId);
        if ($invoice['status'] == modelPay::INVOICE_STATUS_PAID) {
            throw new ClickException(ClickException::ERROR_ALREADY_PAID);
        }
        $transaction = $this->db->fetchRow($this->db->select()->from($this->tableName)->where('id = ?', $this->merchantPrepareId));

        if (!$transaction) {
            throw new ClickException(ClickException::ERROR_TRANSACTION_DOES_NOT_EXIST);
        }

        if (!$this->error) {
            if (!$invoice || $invoice['pay_type'] != 'click') {
                throw new ClickException(ClickException::ERROR_USER_DOES_NOT_EXIST, 'Invoice not found');
            }
            if ($invoice['amount'] != $this->amount) {
                throw new ClickException(ClickException::ERROR_INCORRECT_PARAMETER_AMOUNT);
            }

            if ($invoice['status'] == modelPay::INVOICE_STATUS_ERROR ||
                $invoice['status'] == modelPay::INVOICE_STATUS_CANCELLED) {
                throw new ClickException(ClickException::ERROR_TRANSACTION_CANCELLED);
            }

            if ($this->merchantTransId != $transaction['merchant_trans_id'] ||
                $this->clickPaydocId != $transaction['click_paydoc_id'] ||
                $this->amount != $transaction['amount'] ||
                $this->clickTransId != $transaction['click_trans_id'] ||
                $this->serviceId != $transaction['service_id']
            ) {

                // Inconsistent transaction data
                $this->error = ClickException::ERROR_IN_REQUEST_FROM_CLICK;
                $this->errorNote = 'Transaction data inconsistent';
            }
        }

        if ($this->error) {
            if ($invoice['status'] == modelPay::INVOICE_STATUS_CREATED ||
                $invoice['status'] == modelPay::INVOICE_STATUS_ERROR) {
                $this->model->update(modelPay::TABLE_INVOICES, $this->merchantTransId, [
                    'status' => modelPay::INVOICE_STATUS_ERROR,
                    'error' => $this->error,
                    'error_info' => $this->errorNote,
                ]);
            }

            throw new ClickException(ClickException::ERROR_TRANSACTION_CANCELLED);
        } else {
            // perform active transaction
            $db = cmsDatabase::getInstance();
            $db->beginTransaction();

            try {
                $this->model->setInvoicePaid($this->merchantTransId, 'click:'.$this->requestId);
                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                throw new ClickException(
                    ClickException::ERROR_FAILED_TO_UPDATE_USER,
                    $e->getMessage()
                );
            }
        }
    }

    public function payment($invoice)
    {
        $return_url = $this->request->get('success_url', cmsConfig::get('host').href_to($this->controller->name, 'click', ['success', $invoice->id]));
        $this->loadOptions();

        $amount = (int)($invoice->amount*100);

        $params = [

            'transaction_param' => $invoice->id,
            'return_url' => $return_url
        ];


        $data = 'm='.$this->merchantId.';ac.invoice_id='.$invoice->id
            .';a='.$amount.';c='.urlencode($return_url);
        if (cmsCore::getLanguageName() == 'uz') $data.=';l=uz';
        $b = base64_encode($data);//.'?detail='.$invoice['title'];
        $this->redirect($this->checkoutUri.'/'.$b);
    }

    public function success($param) {
        cmsUser::addSessionMessage('Платеж успешно завершен.', 'success');
        if($back_url = cmsUser::getSession('userpay_back_url', true))
            $this->redirectTo($back_url);
        else
            $this->redirectToHome();
    }

    public function options() {
        $options = array(
            new fieldString('click_merchantid', array(
                'title' => 'ID мерчанта',
            )),
            new fieldString('click_secretkey', array(
                'title' => 'Секретный ключ',
            )),
            new fieldNumber('click_serviceid', array(
                'title' => 'ID услуги',
            )),            
            new fieldNumber('click_userid', array(
                'title' => 'ID пользователя'
            )),
            new fieldString('click_auth_token', array(
                'title' => 'Дополнительный ключ URL',
                'hint' => 'Это значение будет добавлено в URL API-интерфейса поставщика: http://<адрес вашего сайта>/pay/click/process/<Дополнительный ключ URL>'
            ))
        );

        return $options;
    }

}


class ClickException extends Exception
{
    const ERROR_SUCCESS = 0;
    const ERROR_SIGN_CHECK_FAILED = -1;
    const ERROR_INCORRECT_PARAMETER_AMOUNT = -2;
    const ERROR_ACTION_NOT_FOUND = -3;
    const ERROR_ALREADY_PAID = -4;
    const ERROR_USER_DOES_NOT_EXIST = -5;
    const ERROR_TRANSACTION_DOES_NOT_EXIST = -6;
    const ERROR_FAILED_TO_UPDATE_USER = -7;
    const ERROR_IN_REQUEST_FROM_CLICK = -8;
    const ERROR_TRANSACTION_CANCELLED = -9;

    public function __construct($code, $message = null)
    {
        $this->code = $code;
        if (isset($message)) {
            $this->message = $message;
        } else {
            $this->message = self::message($code);
        }
    }

    public static function message($code)
    {
        switch ($code) {
            case self::ERROR_SUCCESS: return 'Success';
            case self::ERROR_SIGN_CHECK_FAILED: return 'SIGN CHECK FAILED!';
            case self::ERROR_INCORRECT_PARAMETER_AMOUNT: return 'Incorrect parameter amount';
            case self::ERROR_ACTION_NOT_FOUND: return 'Action not found';
            case self::ERROR_ALREADY_PAID: return 'Already paid';
            case self::ERROR_USER_DOES_NOT_EXIST: return 'User does not exist';
            case self::ERROR_TRANSACTION_DOES_NOT_EXIST: return 'Transaction does not exist';
            case self::ERROR_FAILED_TO_UPDATE_USER: return 'Failed to update user';
            case self::ERROR_IN_REQUEST_FROM_CLICK: return 'Error in request from click';
            case self::ERROR_TRANSACTION_CANCELLED: return 'Transaction cancelled';
        }
        return '';
    }
}