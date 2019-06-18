<?php

namespace pdima88\icms2pay\actions;

use cmsAction;
use cmsCore;
use cmsUser;

/**
 * @property modelPay $model
 */
class base extends cmsAction {

    public function run($do = false, $param = false) {
        $core = cmsCore::getInstance();

        if (!$do) {
            cmsCore::error404();
        }

        else if ($do == 'process') {
            return $this->process($param);
        }

        else if ($do == 'success') {
            return $this->success($param);

        }
        else if ($do == 'payment') {
            if (!cmsUser::isLogged()) cmsUser::goLogin();

            $invoice = $this->model->invoices->getById($param);

            if (!$invoice || $invoice->user_id != cmsUser::get('id')) {
                cmsCore::error404();
            }

            if ($invoice->status != 0 || $invoice->date_paid) {
                cmsUser::addSessionMessage('Счет уже был оплачен');
                $this->redirectToAction('invoices', $param);
            }
            
            if (!$invoice->pay_type) {
                $invoice->pay_type = $this->current_action;
                $invoice->save();
            }

            if ($invoice->pay_type !== $this->current_action) {
                cmsCore::error404();
            }
            return $this->payment($invoice);
        }
        else if ($do == 'options') {
            if (!cmsUser::isAdmin()) cmsCore::error404();
            return $this->options();
        }
        else {
            cmsCore::error404();

        }
    }

    public function process($param) {
        cmsCore::error404();
    }

    public function success($param) {
        cmsCore::error404();
    }

    /**
     * @param payInvoice $invoice
     */
    public function payment($invoice) {
        cmsCore::error404();
    }

    public function options() {
        $options = array(
            'title' => 'Вручную',
            'form' => array(
            )
        );

        return $options;
    }
}
