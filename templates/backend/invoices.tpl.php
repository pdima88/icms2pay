<?php
/** @var cmsTemplate $this */
$action = 'invoices';
$this->renderAsset('icms2ext/backend/grid', [
    'grid' => $grid,
    'page_title' => $page_title,
    'page_url' => $page_url,
    'toolbar' => [
        'excel' => [
            'title' => 'Экспорт',
            'export' => 'csv',
            'target' => '_blank',
        ]
    ],
]);

?>
<style>
    .cp_toolbar {
        float: right;
        margin-top: -50px;
    }
</style>
