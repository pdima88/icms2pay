<?php

namespace pdima88\icms2pay\backend\actions;

use cmsAction;
use cmsTemplate;
use pdgrid\Grid;
use pdima88\icms2pay\backend as backendPay;
use pdima88\icms2pay\frontend as pay;
use pdima88\icms2pay\model as modelPay;

/**
 * @property modelPay $model
 * @mixin backendPay
 */
class invoices extends cmsAction {

    public function run() {

        $grid = new Grid(pay::getInstance()->getInvoicesGrid());

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
