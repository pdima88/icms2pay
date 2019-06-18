<?php

namespace pdima88\icms2pay\backend\actions;

use cmsAction;
use cmsTemplate;
use pdgrid\Grid;

/**
 * @property modelPay $model
 */
class invoices extends cmsAction {

    public function run() {

        $grid = new Grid($this->model->getInvoicesGrid());

        if ($this->request->has('export')) {
            $grid->export('csv', 'Счета на оплату', 'invoices', true);
        }

        if ($this->request->isAjax()) {
            $grid->ajax();
        }

        return cmsTemplate::getInstance()->render('backend/invoices', array(
            'grid' => $grid,
            'page_title' => 'Счета на оплату',
            'page_url' => href_to($this->root_url, $this->current_action),
        ));
    }


}
