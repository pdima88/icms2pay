<?php

namespace pdima88\icms2pay;

class backend extends cmsBackend {
   
    public $useDefaultOptionsAction = true;
    public $useOptions = true;

    public function actionIndex(){
        $this->redirectToAction('invoices');
    }

    public function getBackendMenu(){
        return array(
            array(
                'title' => 'Счета на оплату',
                'url' => href_to($this->root_url, 'invoices')
            ),
            array(
                'title' => 'Настройки',
                'url' => href_to($this->root_url, 'options')
            ),
        );
    }

}
