<?php

namespace pdima88\icms2pay\actions;

use pdima88\icms2ext\Model;
use pdima88\icms2ext\Table;
use Exception;
use fieldString;
use fieldCheckbox;
/**
 * @property modelPay $model;
 */
class payme extends base {

    protected $isTestMode = false;
    protected $merchantId = '';
    protected $login = 'Paycom';
    protected $key = '';
    protected $checkoutUri = 'https://checkout.paycom.uz';

    protected function loadOptions() {
        $this->isTestMode = isset($this->options['payme_testmode']) ? $this->options['payme_testmode'] : false;
        $this->merchantId = $this->options['payme_merchantid'];
        $keyOption = $this->isTestMode ? 'payme_testkey' : 'payme_secretkey';
        $this->key = $this->options[$keyOption];
        if ($this->isTestMode) $this->checkoutUri = 'https://test.paycom.uz';
    }

    /** @var PaycomRequest */
    protected $paycomRequest;

    /** @var PaycomTransaction */
    protected $paycomTransaction;

    protected $invoiceId = null;
    protected $amount = null;
    protected $invoiceTitle = '';

    public function process($param) {
        $this->loadOptions();
        try {
            $this->paycomRequest = new PaycomRequest();
            $this->paycomTransaction = new PaycomTransaction(
                Model::zendDb(),
                Table::prefix('pay_payme')
            );
            // authorize session
            $this->authorize($this->paycomRequest->id);
            $this->invoiceId = $this->paycomRequest->account('invoice_id');
            $this->amount = $this->paycomRequest->amount;

            // handle request
            switch ($this->paycomRequest->method) {
                case 'CheckPerformTransaction':
                    $this->checkPerformTransaction();
                    break;
                case 'CheckTransaction':
                    $this->checkTransaction();
                    break;
                case 'CreateTransaction':
                    $this->createTransaction();
                    break;
                case 'PerformTransaction':
                    $this->performTransaction();
                    break;
                case 'CancelTransaction':
                    $this->cancelTransaction();
                    break;
                case 'ChangePassword':
                    $this->changePassword();
                    break;
                case 'GetStatement':
                    $this->getStatement();
                    break;
                default:
                    throw new PaycomException($this->paycomRequest->id,
                        'Method not found.',
                        PaycomException::ERROR_METHOD_NOT_FOUND,
                        $this->paycomRequest->method
                    );
                    break;
            }
        } catch (PaycomException $e) {
            $e->send();
            exit;
        }

    }

    public function authorize()
    {
        if (!function_exists('getallheaders')) {
            function getallheaders()
            {
                $headers = '';
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                    }
                }
                return $headers;
            }
        }

        $headers = getallheaders();

        if (!$headers || !isset($headers['Authorization']) ||
            !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) ||
            base64_decode($matches[1]) != $this->login . ":" . $this->key
        ) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Insufficient privilege to perform this method.',
                PaycomException::ERROR_INSUFFICIENT_PRIVILEGE
            );
        }

        return true;
    }

    private $invoice = null;

    private function loadInvoice() {
        $invoice = $this->model->getInvoice($this->invoiceId);
        if (!$invoice || $invoice['status'] != 0 || $invoice['pay_type'] != 'payme') {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Invalid account.',
                PaycomException::ERROR_INVALID_ACCOUNT
            );
        }
        if ($invoice['amount'] != $this->amount/100) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Invalid amount.',
                PaycomException::ERROR_INVALID_AMOUNT
            );
        }
        $this->invoice = $invoice;
        return true;
    }

    private function checkPerformTransaction()
    {
        $this->loadInvoice();

        // Check is there another active or completed transaction for this order
        $transaction = $this->paycomTransaction->find($this->paycomRequest->params);

        if ($transaction && ($transaction->state == PaycomTransaction::STATE_CREATED
                || $transaction->state == PaycomTransaction::STATE_COMPLETED)) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'There is other active/completed transaction for this order.',
                PaycomException::ERROR_COULD_NOT_PERFORM
            );
        }

        // if control is here, then we pass all validations and checks
        // send response, that order is ready to be paid.
        $this->response(['allow' => true]);
    }

    private function checkTransaction()
    {
        $transaction = $this->paycomTransaction->find($this->paycomRequest->params);
        if (!$transaction) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Transaction not found.',
                PaycomException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        $this->response([
            'create_time' => $transaction->getCreateTimeAsMilliseconds(),
            'perform_time' => $transaction->getPerformTimeAsMilliseconds(),
            'cancel_time' => $transaction->getCancelTimeAsMilliseconds(),
            'transaction' => $transaction->id,
            'state' => $transaction->state,
            'reason' => $transaction->reason
        ]);
    }

    private function createTransaction()
    {
        $this->loadInvoice();

        $transaction = $this->paycomTransaction->find($this->paycomRequest->params);

        if ($transaction) {
            if ($transaction->state != PaycomTransaction::STATE_CREATED) { // validate transaction state
                throw new PaycomException(
                    $this->paycomRequest->id,
                    'Transaction found, but is not active.',
                    PaycomException::ERROR_COULD_NOT_PERFORM
                );
            } elseif ($transaction->isExpired()) { // if transaction timed out, cancel it and send error
                $transaction->cancel(PaycomTransaction::REASON_CANCELLED_BY_TIMEOUT);
                throw new PaycomException(
                    $this->paycomRequest->id,
                    'Transaction is expired.',
                    PaycomException::ERROR_COULD_NOT_PERFORM
                );
            } else { // if transaction found and active, send it as response
                $this->response([
                    'create_time' => $transaction->getCreateTimeAsMilliseconds(),
                    'transaction' => $transaction->id,
                    'state' => $transaction->state,
                    'receivers' => $transaction->receivers
                ]);
            }
        } else { // transaction not found, create new one
            $err = ''; $errCode = 0;
            if (!$this->paycomTransaction->canCreate($this->paycomRequest->params, $err, $errCode)) {
                throw new PaycomException(
                    $this->paycomRequest->id,
                    $err,
                    $errCode
                );
            }

            $create_time = round(microtime(true) * 1000); //timestamp in ms

            // validate new transaction time
            if (PaycomTransaction::timestamp2milliseconds(1 * $this->paycomRequest->params['time'])
                - $create_time >= PaycomTransaction::TIMEOUT) {
                throw new PaycomException(
                    $this->paycomRequest->id,
                    'Create new transaction timed out',
                    PaycomException::ERROR_INVALID_ACCOUNT
                );
            }

            $transaction = $this->paycomTransaction->create($this->paycomRequest->params, $this->invoiceId, $this->amount);

            // send response
            $this->response([
                'create_time' => $transaction->getCreateTimeAsMilliseconds(),
                'transaction' => $transaction->id,
                'state' => $transaction->state,
                'receivers' => null
            ]);
        }
    }

    private function performTransaction()
    {
        $transaction = $this->paycomTransaction->find($this->paycomRequest->params);

        // if transaction not found, send error
        if (!$transaction) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Transaction not found.',
                PaycomException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        switch ($transaction->state) {
            case PaycomTransaction::STATE_CREATED: // handle active transaction
                if ($transaction->isExpired()) { // if transaction is expired, then cancel it and send error
                    $transaction->cancel(PaycomTransaction::REASON_CANCELLED_BY_TIMEOUT);
                    throw new PaycomException(
                        $this->paycomRequest->id,
                        'Transaction is expired.',
                        PaycomException::ERROR_COULD_NOT_PERFORM
                    );
                } else { // perform active transaction
                    $db = cmsDatabase::getInstance();
                    $db->beginTransaction();

                    try {
                        $payInfo = 'payme:' . $transaction->id;
                        $this->model->setInvoicePaid($transaction->invoice_id, $payInfo);

                        $transaction->perform();
                        $db->commit();
                    } catch (Exception $e) {
                        $db->rollback();
                        throw new PaycomException(
                            $this->paycomRequest->id,
                            $e->getMessage(),
                            PaycomException::ERROR_COULD_NOT_PERFORM
                        );
                    }
                    $this->response([
                        'transaction' => $transaction->id,
                        'perform_time' => $transaction->getPerformTimeAsMilliseconds(),
                        'state' => $transaction->state
                    ]);
                }
                break;

            case PaycomTransaction::STATE_COMPLETED: // handle complete transaction
                // If transaction completed, just return it
                $this->response([
                    'transaction' => $transaction->id,
                    'perform_time' => $transaction->getPerformTimeAsMilliseconds(),
                    'state' => $transaction->state
                ]);
                break;

            default:
                // unknown situation
                throw new PaycomException(
                    $this->paycomRequest->id,
                    'Could not perform this operation.',
                    PaycomException::ERROR_COULD_NOT_PERFORM
                );
                break;
        }
    }

    private function cancelTransaction()
    {
        $transaction = $this->paycomTransaction->find($this->paycomRequest->params);

        // if transaction not found, send error
        if (!$transaction) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Transaction not found.',
                PaycomException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        switch ($transaction->state) {
            // if already cancelled, just send it
            case PaycomTransaction::STATE_CANCELLED:
            case PaycomTransaction::STATE_CANCELLED_AFTER_COMPLETE:
                $this->response([
                    'transaction' => $transaction->id,
                    'cancel_time' => $transaction->getCancelTimeAsMilliseconds(),
                    'state' => $transaction->state
                ]);
                break;

            // cancel active transaction
            case PaycomTransaction::STATE_CREATED:
                // cancel transaction with given reason
                $transaction->cancel(intval($this->paycomRequest->params['reason']));

                // send response
                $this->response([
                    'transaction' => $transaction->id,
                    'cancel_time' => $transaction->getCancelTimeAsMilliseconds(),
                    'state' => $transaction->state
                ]);
                break;

            case PaycomTransaction::STATE_COMPLETED:
                // find order and check, whether cancelling is possible this order
                $db = cmsDatabase::getInstance();
                $db->beginTransaction();
                try {
                    $reason = 1 * @$this->paycomRequest->params['reason'];
                    if ($this->model->cancelInvoice($transaction->invoice_id, 'payme', 'Reason: '.$reason)) {
                        // cancel and change state to cancelled
                        $transaction->cancel($reason);
                        // after cancel(), cancel_time and state properties populated with data
                        // send response
                    } else {
                        // todo: If cancelling after performing transaction is not possible, then return error -31007
                        throw new Exception(
                            'Could not cancel transaction. Order is delivered/Service is completed.'
                        );
                    }

                    $db->commit();

                }
                catch (Exception $e) {
                    $db->rollback();
                    throw new PaycomException(
                        $this->paycomRequest->id,
                        $e->getMessage(),
                        PaycomException::ERROR_COULD_NOT_CANCEL
                    );
                }

                $this->response([
                    'transaction' => $transaction->id,
                    'cancel_time' => $transaction->getCancelTimeAsMilliseconds(),
                    'state' => $transaction->state
                ]);
                break;
        }
    }

    private function changePassword()
    {
        // validate, password is specified, otherwise send error
        if (!isset($this->paycomRequest->params['password']) || !trim($this->paycomRequest->params['password'])) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'New password not specified.',
                PaycomException::ERROR_INVALID_ACCOUNT
            );
        }

        // if current password specified as new, then send error
        if ($this->key == $this->paycomRequest->params['password']) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Insufficient privilege. Incorrect new password.',
                PaycomException::ERROR_INSUFFICIENT_PRIVILEGE
            );
        }

        try {
            $keyOption = $this->isTestMode ? 'payme_testkey' : 'payme_secretkey';
            $newOptions = $this->options;
            $newOptions[$keyOption] = $this->paycomRequest->params['password'];

            $this->controller->saveOptions($this->controller->name, $newOptions);
        }
        catch (Exception $e) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Can`t change password: '.$e->getMessage(),
                PaycomException::ERROR_INTERNAL_SYSTEM
            );
        }
        //\S4Y_Log::log('payme', 0, 'New password: '.$this->request->params['password'],'change_password');
        $this->response(['success' => true]);
    }

    private function getStatement()
    {
        // validate 'from'
        if (!isset($this->paycomRequest->params['from'])) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Incorrect period.',
                PaycomException::ERROR_INVALID_ACCOUNT
            );
        }

        // validate 'to'
        if (!isset($this->paycomRequest->params['to'])) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Incorrect period.',
                PaycomException::ERROR_INVALID_ACCOUNT
            );
        }

        $from = 1 * $this->paycomRequest->params['from'];
        $to = 1 * $this->paycomRequest->params['to'];

        // validate period
        if ($from >= $to) {
            throw new PaycomException(
                $this->paycomRequest->id,
                'Incorrect period. (from >= to)',
                PaycomException::ERROR_INVALID_ACCOUNT
            );
        }

        // get list of transactions for specified period
        $transactions = $this->transaction->report($from, $to);

        // send results back
        $this->response(['transactions' => $transactions]);
    }

    public function response($result, $error = null)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $response['jsonrpc'] = '2.0';
        $response['id'] = $this->paycomRequest->id;
        $response['result'] = $result;
        $response['error'] = $error;

        echo json_encode($response);
        exit;
    }

    public function payment($invoice)
    {
        $success_url = $this->request->get('success_url', cmsConfig::get('host').href_to($this->controller->name, 'payme', ['success', $invoice->id]));
        $this->loadOptions();

        $amount = (int)($invoice->amount*100);

        $data = 'm='.$this->merchantId.';ac.invoice_id='.$invoice->id
            .';a='.$amount.';c='.urlencode($success_url);
        if (cmsCore::getLanguageName() == 'uz') $data.=';l=uz';
        $b = base64_encode($data);//.'?detail='.$invoice['title'];
        $this->redirect($this->checkoutUri.'/'.$b);
    }

    public function success($param) {
        cmsUser::addSessionMessage('Платеж успешно завершен.', 'success');
        if($back_url = cmsUser::getSession('pay_back_url', true))
            $this->redirectTo($back_url);
        else
            $this->redirectToHome();
    }

    public function options() {
        $options = array(
            new fieldString('payme_merchantid', array(
                'title' => 'ID кассы',
            )),
            new fieldString('payme_secretkey', array(
                'title' => 'Секретный ключ',
            )),
            new fieldString('payme_testkey', array(
                'title' => 'Тестовый ключ',
            )),
            new fieldCheckbox('payme_testmode', array(
                'title' => 'Тестовый режим'
            )),
        );

        return $options;
    }

}

class PaycomRequest
{
    /** @var array decoded request payload */
    public $payload;

    /** @var int id of the request */
    public $id;

    /** @var string method name, such as <em>CreateTransaction</em> */
    public $method;

    /** @var array request parameters, such as <em>amount</em>, <em>account</em> */
    public $params;

    /** @var int amount value in coins */
    public $amount;

    /**
     * Request constructor.
     * Parses request payload and populates properties with values.
     */
    public function __construct()
    {
        $request_body = file_get_contents('php://input');
        $this->payload = json_decode($request_body, true);

        if (!$this->payload) {
            throw new PaycomException(
                null,
                'Invalid JSON-RPC object.',
                PaycomException::ERROR_INVALID_JSON_RPC_OBJECT
            );
        }

        // populate request object with data
        $this->id = isset($this->payload['id']) ? 1 * $this->payload['id'] : null;
        $this->method = isset($this->payload['method']) ? trim($this->payload['method']) : null;
        $this->params = isset($this->payload['params']) ? $this->payload['params'] : [];
        $this->amount = isset($this->payload['params']['amount']) ? 1 * $this->payload['params']['amount'] : null;

        // add request id into params too
        $this->params['request_id'] = $this->id;
    }

    /**
     * Gets account parameter if such exists, otherwise returns null.
     * @param string $param name of the parameter.
     * @return mixed|null account parameter value or null if such parameter doesn't exists.
     */
    public function account($param)
    {
        return isset($this->params['account'], $this->params['account'][$param]) ? $this->params['account'][$param] : null;
    }
}

class PaycomException extends Exception
{
    const ERROR_INTERNAL_SYSTEM = -32400;
    const ERROR_INSUFFICIENT_PRIVILEGE = -32504;
    const ERROR_INVALID_JSON_RPC_OBJECT = -32600;
    const ERROR_METHOD_NOT_FOUND = -32601;
    const ERROR_INVALID_AMOUNT = -31001;
    const ERROR_TRANSACTION_NOT_FOUND = -31003;
    const ERROR_INVALID_ACCOUNT = -31050;
    const ERROR_COULD_NOT_CANCEL = -31007;
    const ERROR_COULD_NOT_PERFORM = -31008;

    public $request_id;
    public $error;
    public $data;

    /**
     * PaycomException constructor.
     * @param int $request_id id of the request.
     * @param string $message error message.
     * @param int $code error code.
     * @param string|null $data parameter name, that resulted to this error.
     */
    public function __construct($request_id, $message, $code, $data = null)
    {
        $this->request_id = $request_id;
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;

        // prepare error data
        $this->error = ['code' => $this->code];

        if ($this->message) {
            $this->error['message'] = $this->message;
        }

        if ($this->data) {
            $this->error['data'] = $this->data;
        }
    }

    public function send()
    {
        header('Content-Type: application/json; charset=UTF-8');

        // create response
        $response['id'] = $this->request_id;
        $response['result'] = null;
        $response['error'] = $this->error;

        echo json_encode($response);
    }

    public static function message($ru, $uz = '', $en = '')
    {
        return ['ru' => $ru, 'uz' => $uz, 'en' => $en];
    }
}

/**
 * Class PaycomTransaction
 *
 * Example MySQL table might look like to the following:
 *
 * CREATE TABLE `transactions` (
 *   `id` INT(11) NOT NULL AUTO_INCREMENT,
 *   `paycom_transaction_id` VARCHAR(25) NOT NULL COLLATE 'utf8_unicode_ci',
 *   `paycom_time` VARCHAR(13) NOT NULL COLLATE 'utf8_unicode_ci',
 *   `paycom_time_datetime` DATETIME NOT NULL,
 *   `create_time` DATETIME NOT NULL,
 *   `perform_time` DATETIME NULL DEFAULT NULL,
 *   `cancel_time` DATETIME NULL DEFAULT NULL,
 *   `amount` INT(11) NOT NULL,
 *   `state` TINYINT(2) NOT NULL,
 *   `reason` TINYINT(2) NULL DEFAULT NULL,
 *   `receivers` VARCHAR(500) NULL DEFAULT NULL COMMENT 'JSON array of receivers' COLLATE 'utf8_unicode_ci',
 *   `invoice_id` INT(11) NOT NULL,
 *
 *   PRIMARY KEY (`id`)
 * )
 *   COLLATE='utf8_unicode_ci'
 *   ENGINE=InnoDB
 *   AUTO_INCREMENT=1;
 *
 */
class PaycomTransaction
{
    /** Transaction expiration time in milliseconds. 43 200 000 ms = 12 hours. */
    const TIMEOUT = 43200000;

    const STATE_CREATED = 1;
    const STATE_COMPLETED = 2;
    const STATE_CANCELLED = -1;
    const STATE_CANCELLED_AFTER_COMPLETE = -2;

    const REASON_RECEIVERS_NOT_FOUND = 1;
    const REASON_PROCESSING_EXECUTION_FAILED = 2;
    const REASON_EXECUTION_FAILED = 3;
    const REASON_CANCELLED_BY_TIMEOUT = 4;
    const REASON_FUND_RETURNED = 5;
    const REASON_UNKNOWN = 10;

    /** @var string Paycom transaction id. */
    public $paycom_transaction_id;

    /** @var int Paycom transaction time as is without change. */
    public $paycom_time;

    /** @var string Paycom transaction time as date and time string. */
    public $paycom_time_datetime;

    /** @var string Transaction id in the merchant's system. */
    public $id;

    /** @var string Transaction create date and time in the merchant's system. */
    public $create_time;

    /** @var string Transaction perform date and time in the merchant's system. */
    public $perform_time;

    /** @var string Transaction cancel date and time in the merchant's system. */
    public $cancel_time;

    /** @var int Transaction state. */
    public $state;

    /** @var int Transaction cancelling reason. */
    public $reason;

    /** @var int Amount value in coins, this is service or product price. */
    public $amount;

    /** @var string Pay receivers. Null - owner is the only receiver. */
    public $receivers;

    // additional fields:
    // - to identify order or product, for example, code of the order
    // - to identify client, for example, account id or phone number

    /** @var string Code to identify the order or service for pay. */
    public $invoice_id;

    /** @var \Zend_Db_Adapter_Mysqli */
    private $db;

    private $tableName = null;

    public function __construct($db, $tableName)
    {
        $this->db = $db;
        $this->tableName = $tableName;
    }

    public function toArray() {
        return [
            'id' => strval($this->id),
            'paycom_transaction_id' => $this->paycom_transaction_id,
            'paycom_time' => $this->paycom_time,
            'paycom_time_datetime' => $this->paycom_time_datetime,
            'create_time' => $this->create_time,
            'perform_time' => $this->perform_time,
            'cancel_time' => $this->cancel_time,
            'state' => $this->state,
            'reason' => $this->reason,
            'amount' => $this->amount,
            'receivers' => $this->receivers,
            'invoice_id' => $this->invoice_id
        ];
    }

    private function getTransactionById($id) {
        $rows = $this->db->select()->from($this->tableName)->where('id = ?', $id)
            ->query()->fetchAll();
        if (count($rows) > 0) {
            return $rows[0];
        }
    }

    public function getCreateTimeAsMilliseconds() {
        return self::timestamp2milliseconds(self::datetime2timestamp($this->create_time));
    }

    public function getPerformTimeAsMilliseconds() {
        return self::timestamp2milliseconds(self::datetime2timestamp($this->perform_time));
    }

    public function getCancelTimeAsMilliseconds() {
        return self::timestamp2milliseconds(self::datetime2timestamp($this->cancel_time));
    }

    public function create($params, $invoiceId, $amount) {
        $this->paycom_transaction_id = $params['id'];
        $this->paycom_time = $params['time'];
        $this->paycom_time_datetime = self::timestamp2datetime($params['time']);
        $this->create_time = self::currentDatetime();
        $this->state = self::STATE_CREATED;
        $this->amount = $amount;
        $this->invoice_id = $invoiceId;
        $this->save(); // after save $transaction->id will be populated with the newly created transaction's id.
        return $this;
    }

    public function perform() {
        if (!$this->id) throw new Exception('Id not set');
        $this->perform_time = self::currentDatetime();
        $this->state = self::STATE_COMPLETED;
        $this->save();
    }

    /**
     * Saves current transaction instance in a data store.
     * @return void
     */
    public function save()
    {
        if ($this->id) {
            $this->update();
        } else {
            $this->id = strval($this->insert());
        }
    }

    public function insert() {
        $data = $this->toArray();
        unset($data['id']);

        $this->db->beginTransaction();
        $res = $this->db->insert($this->tableName, $data);
        if ($res > 0) {
            $id = $this->db->lastInsertId($this->tableName);
        } else throw new Exception('No rows affected');

        $this->db->commit();
        return $id;
    }

    public function update($fields = [])
    {
        if (!isset($this->id)) throw new Exception('Id not set');
        $data = $this->toArray();
        unset($data['id']);
        if (!empty($fields)) {
            $data_src = $data;
            $data = [];
            foreach ($fields as $f) {
                $data[$f] = @$data_src[$f];
            }
        }

        /*$log = 'Before update:' . PHP_EOL . json_encode($this->getTransactionById($this->id)) . PHP_EOL .
            'Update:' . PHP_EOL . json_encode($data) . PHP_EOL;*/
        //$this->db->beginTransaction();
        $res = $this->db->update($this->tableName, $data, ['id = ?' => $this->id]);
        /*$log .= 'Rows affected: ' . $res;
        Log::log('transactions_payme', $this->id, $log, 'update');*/
        //$this->db->commit();
    }

    /**
     * Cancels transaction with the specified reason.
     * @param int $reason cancelling reason.
     * @return void
     */
    public function cancel($reason)
    {
        $this->cancel_time = self::currentDatetime();

        if ($this->state == self::STATE_COMPLETED) {
            $this->state = self::STATE_CANCELLED_AFTER_COMPLETE;
        } else {
            $this->state = self::STATE_CANCELLED;
        }

        if (!$reason) {
            $reason = (($this->state == self::STATE_CANCELLED_AFTER_COMPLETE) ?
                self::REASON_FUND_RETURNED : self::REASON_PROCESSING_EXECUTION_FAILED);
        }
        $this->reason = $reason;

        /*Log::log('transactions_payme', $this->id, 'Reason: '.$reason.PHP_EOL.
            ', State: '.$this->state , 'cancel');*/
        $this->update(['cancel_time', 'state', 'reason']);
    }

    /**
     * Determines whether current transaction is expired or not.
     * @return bool true - if current instance of the transaction is expired, false - otherwise.
     */
    public function isExpired()
    {
        // for example, if transaction is active and passed TIMEOUT milliseconds after its creation, then it is expired
        return $this->state == self::STATE_CREATED && self::datetime2timestamp($this->create_time) - time() > self::TIMEOUT;
    }

    /**
     * Find transaction by given parameters.
     * @param mixed $params parameters
     * @return PaycomTransaction|null
     */
    public function find($params)
    {
        $row = false;
        $db_stmt = null;

        if (isset($params['id'])) {
            $q = $this->db->select()->from($this->tableName)
                ->where('paycom_transaction_id = ?', $params['id'])->query();
            $row = $q->fetch();
            $q->closeCursor();
        } elseif (isset($params['account']['invoice_id'])) {
            $invoiceId = $params['account']['invoice_id'];
            $rows = $this->db->select()->from($this->tableName)
                ->where('invoice_id = ?', $invoiceId)->query()->fetchAll();
            foreach ($rows as $r) {
                if ($r['state'] != self::STATE_CANCELLED) {
                    $row = $r;
                    break;
                }
            }
        }

        // if there is row available, then populate properties with values
        if ($row) {
            $this->id = strval($row['id']);
            $this->paycom_transaction_id = $row['paycom_transaction_id'];
            $this->paycom_time = 1 * $row['paycom_time'];
            $this->paycom_time_datetime = $row['paycom_time_datetime'];
            $this->create_time = $row['create_time'];
            $this->perform_time = $row['perform_time'];
            $this->cancel_time = $row['cancel_time'];
            $this->state = 1 * $row['state'];
            $this->reason = $row['reason'] ? 1 * $row['reason'] : null;
            $this->amount = 1 * $row['amount'];
            $this->invoice_id = 1 * $row['invoice_id'];

            // assume, receivers column contains list of receivers in JSON format as string
            $this->receivers = $row['receivers'] ? json_decode($row['receivers'], true) : null;

            // return populated instance
            return $this;
        }

        // transaction not found, return null
        return null;

        // Possible features:
        // Search transaction by product/order id that specified in $params
        // Search transactions for a given period of time that specified in $params
    }

    public function canCreate($params, &$errMsg, &$errCode) {
        if (isset($params['account']['invoice_id'])) {
            $invoiceId = $params['account']['invoice_id'];
            $rows = $this->db->select()->from($this->tableName)
                ->where('invoice_id = ?', $invoiceId)->query()->fetchAll();
            foreach ($rows as $r) {
                if ($r['state'] != self::STATE_CANCELLED && $r['state'] != self::STATE_CANCELLED_AFTER_COMPLETE) {
                    $errMsg = 'Transaction '.$r['id'].'('.
                        $r['paycom_transaction_id'].') with invoice_id: '.$invoiceId.' already exists!';
                    $errCode = PaycomException::ERROR_INVALID_ACCOUNT;
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Gets list of transactions for the given period including period boundaries.
     * @param int $from_date start of the period in timestamp.
     * @param int $to_date end of the period in timestamp.
     * @return array list of found transactions converted into report format for send as a response.
     */
    public function report($from_date, $to_date)
    {
        $from_date = self::timestamp2datetime($from_date);
        $to_date = self::timestamp2datetime($to_date);

        // container to hold rows/document from data store
        $rows = $this->db->select()->from($this->tableName)
            ->where('paycom_time_datetime BETWEEN ? AND ?', [$from_date, $to_date])
            ->query()->fetchAll();

        // assume, here we have $rows variable that is populated with transactions from data store
        // normalize data for response
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row['paycom_transaction_id'], // paycom transaction id
                'time' => 1 * $row['paycom_time'], // paycom transaction timestamp as is
                'amount' => 1 * $row['amount'],
                'account' => [
                    'order_id' => $row['order_id'], // account parameters to identify client/order/service
                    // ... additional parameters may be listed here, which are belongs to the account
                ],
                'create_time' => self::datetime2timestamp($row['create_time']),
                'perform_time' => self::datetime2timestamp($row['perform_time']),
                'cancel_time' => self::datetime2timestamp($row['cancel_time']),
                'transaction' => $row['id'],
                'state' => 1 * $row['state'],
                'reason' => isset($row['reason']) ? 1 * $row['reason'] : null,
                'receivers' => $row['receivers']
            ];
        }

        return $result;
    }

    /**
     * Converts timestamp value from seconds to milliseconds.
     * @param int $timestamp timestamp in seconds.
     * @return int timestamp in milliseconds.
     */
    public static function timestamp2milliseconds($timestamp)
    {
        // is it already as milliseconds
        if (strlen((string)$timestamp) == 13) {
            return $timestamp;
        }

        return $timestamp * 1000;
    }

    /**
     * Converts timestamp value from milliseconds to seconds.
     * @param int $timestamp timestamp in milliseconds.
     * @return int timestamp in seconds.
     */
    public static function timestamp2seconds($timestamp)
    {
        // is it already as seconds
        if (strlen((string)$timestamp) == 10) {
            return $timestamp;
        }

        return floor(1 * $timestamp / 1000);
    }

    /**
     * Converts timestamp to date time string.
     * @param int $timestamp timestamp value as seconds or milliseconds.
     * @return string string representation of the timestamp value in 'Y-m-d H:i:s' format.
     */
    public static function timestamp2datetime($timestamp)
    {
        // if as milliseconds, convert to seconds
        if (strlen((string)$timestamp) == 13) {
            $timestamp = self::timestamp2seconds($timestamp);
        }

        // convert to datetime string
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Converts date time string to timestamp value.
     * @param string $datetime date time string.
     * @return int timestamp as seconds.
     */
    public static function datetime2timestamp($datetime)
    {
        if ($datetime) {
            return strtotime($datetime);
        }

        return $datetime;
    }
    public static function currentDatetime() {
        return date('Y-m-d H:i:s');
    }
}