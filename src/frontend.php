<?php

namespace pdima88\icms2pay;

use pdgrid\Grid;
use cmsFrontend;
use cmsUser;
use cmsTemplate;
use cmsCore;
use pdima88\icms2pay\model;
use pdima88\icms2ext\Table;
use pdima88\icms2ext\GridHelper;
use pdima88\icms2pay\tables\table_invoices;

/**
 * Class pay
 * @property model $model
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

        $grid = new Grid($this->getInvoicesGrid());

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

    public function getInvoicesGrid() {

        $statusList = model::$invoiceStatusList;
        $payTypes = $this->getPayTypeList(false);

        $select = $this->model->invoices->selectAs('i')->joinBy(table_invoices::FK_USER, 'u')
            ->columns([
                'i.*',
                'u.nickname',
                'u.email',
                'u.phone'
            ]);

        $grid = [
            'id' => 'invoices',
            'select' => $select,
            'sort' => [
                'id' => 'desc',
            ],

            'rownum' => false,

            'multisort' => true,
            'paging' => 10,

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
                    'filter' => 'equal',
                    'align' => 'right',
                ],
                'user_id' => [
                    'title' => 'ID польз.',
                    'width' => 70,
                    'sort' => true,
                    'filter' => 'equal',
                    'align' => 'center'
                ],
                'nickname' => [
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
                    'format' => 'format_currency',
                    'align' => 'right',
                    'sort' => true,
                    'filter' => 'equal',
                    'style' => 'font-weight:bold',
                ],
                'status' => [
                    'title' => 'Статус',
                    'sort' => true,
                    'filter' => 'multiselect',
                    'format' => $statusList
                ],
                'date_created' => [
                    'title' => 'Когда создан',
                    'sort' => true,
                    'filter' => 'dateRange',
                    'filterOpens' => 'left',
                    'format' => 'datetime',
                ],
                'date_paid' => [
                    'title' => 'Когда оплачен',
                    'sort' => true,
                    'format' => 'datetime',
                    'filter' => 'dateRange',
                    'filterOpens' => 'left',
                ],
                'pay_type' => [
                    'title' => 'Тип оплаты',
                    'sort' => true,
                    'filter' => 'select',
                    'format' => $payTypes,
                ],
                'pay_info' => [
                    'title' => 'Сведения о платеже',
                    'filter' => 'text',
                ],
            ]
        ];

        return $grid;
    }

    function getPayTypes() {
        $payController = self::getInstance();
        $res = $this->model->getCachedResult(self::CACHE_PAYTYPES);
        if (!isset($res)) {
            $res = [];
            $paySystemOptions = [];
            $paySystemSortOrder = [];

            $systems = files_tree_to_array(realpath(__DIR__.'/actions'));
            if ($systems) {
                foreach ($systems as $value) {
                    $value = str_replace('.php', '', $value);
                    if (in_array($value, array('base', 'pay'))) continue;
                    $paySystemOptions[$value] = $payController->runExternalAction($value, array('options'));
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
