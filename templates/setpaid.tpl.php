<?php
/**
 * @var cmsTemplate $this
 * @var cmsForm $form
 * @var string $title
 * @var array $item
 * @var array $errors
 * @var array $breadcrumbs
 */
$this->renderAsset('icms2ext/backend/form', [
    'title' => 'Внесение сведений об оплате',
    'form' => $form,
    'item' => $item,
    'errors' => $errors,
    'breadcrumbs' => $breadcrumbs ?? null,
]);