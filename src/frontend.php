<?php

namespace pdima88\icms2pay;

use pdgrid\Grid;
use cmsFrontend;
use cmsUser;
use cmsTemplate;
use cmsCore;

/**
 * Class pay
 * @property modelPay $model
 */
class frontend extends cmsFrontend {

    const CACHE_PAYTYPES = 'pay::getPayTypes';

    protected $useOptions = true;

    public function routeAction($action_name)
    {
        if (is_numeric($action_name)) {
            array_unshift($this->current_params, $action_name);
            return 'pay';
        }
        return parent::routeAction($action_name);
    }

    public function actionIndex() {

        $this->redirectToAction('invoices');
    }

    protected function validateInvoiceForPay($invoiceId) {
        if (!cmsUser::isLogged()) cmsUser::goLogin();
        $invoice = $this->model->invoices->getById($invoiceId);

        if (!$invoice || $invoice->user_id != cmsUser::get('id')) {
            cmsCore::error404();
        }

        if ($invoice->status != 0 || $invoice->date_paid) {
            $this->redirectToAction('invoices', $invoiceId);
        }

        return $invoice;
    }

    /**
     * Выполняет перенаправление на оплату по указанному ID счета
     * @param $invoiceId
     */
    public function actionPay($invoiceId) {
        $invoice = $this->validateInvoiceForPay($invoiceId);

        $payTypes = $this->getPayTypeList();

        $payType = $invoice->pay_type;

            cmsTemplate::getInstance()->render('pay', [
                'payTypes' => $payTypes,
                'invoice' => $invoice
            ]);
        //} else {
        //    $this->redirectToAction($payType, ['payment', $id]);
        //}
    }

    public function actionReset($invoiceId) {
        $invoice = $this->validateInvoiceForPay($invoiceId);
        $invoice->pay_type = null;
        $invoice->save();

        $this->redirectToAction('pay', $invoiceId);


    }
    
    public function actionAdmin() {
        if (!cmsUser::isAllowed('pay','admin')) cmsCore::error404();

        $grid = new Grid($this->model->getInvoicesGrid());

        if ($this->request->has('export')) {
            $grid->export('csv', 'Счета на оплату', 'invoices', true);
        }

        if ($this->request->isAjax()) {
            $grid->ajax();
        }

        $tpl = cmsTemplate::getInstance();
        $tpl->setLayout('full');
        $tpl->addBreadcrumb('Оплата на сайте', $tpl->href_to(''));
        return $tpl->render('backend/invoices', array(
            'grid' => $grid,
            'page_title' => 'Счета на оплату',
            'page_url' => $tpl->href_to('admin'),
        ));
    }

    public function setPaid() {
        if (!cmsUser::isAllowed('pay','admin')) cmsCore::error404();
    }

    function getPayTypes() {
        $res = $this->model->getCachedResult(self::CACHE_PAYTYPES);
        if (!isset($res)) {
            $res = [];
            $paySystemOptions = [];
            $paySystemSortOrder = [];

            $systems = files_tree_to_array('system/controllers/pay/actions/');
            if ($systems) {
                foreach ($systems as $value) {
                    $value = str_replace('.php', '', $value);
                    if (in_array($value, array('base', 'pay'))) continue;
                    $paySystemOptions[$value] = cmsCore::getController('pay')->runExternalAction($value, array('options'));
                    $paySystemSortOrder[$value] = $this->options[$value . '_order'] ?? 0;
                }
            }

            asort($paySystemSortOrder);
            foreach ($paySystemSortOrder as $paySystem => $sortOrder) {
                $res[$paySystem] = $paySystemOptions[$paySystem];
            }

            $this->model->cacheResult(self::CACHE_PAYTYPES, $res);
        }

        return $res;
    }

    function getPayTypeList($activeOnly = true) {
        $payTypes = $this->getPayTypes();
        $res = [];
        foreach ($payTypes as $payTypeId => $payTypeOptions) {
            if ($activeOnly) {
                $isActive = $this->options[$payTypeId . '_on'] ?? false;
                if (!$isActive) continue;
            }
            $payTitle = isset($payTypeOptions['title']) ? $payTypeOptions['title'] : ucfirst($payTypeId);
            $res[$payTypeId] = $this->options[$payTypeId . '_name'] ?? $payTitle;
        }
        return $res;
    }
}
