<?php
/**
 * @var cmsTemplate $this
 * @var cmsForm $form
 * @var string $title
 * @var array $item
 * @var array $errors
 * @var array $breadcrumbs
 */
 if ($form) {

     $this->renderAsset('icms2ext/backend/form', [
         'title' => 'Внесение сведений об оплате',
         'form' => $form,
         'item' => $item,
         'errors' => $errors,
         'breadcrumbs' => $breadcrumbs ?? null,
     ]);

 } else {
     ?>
     Счет уже оплачен или отменен
    <br><br>
     <input type="button" class="button" value="Назад" onclick="javascript:history.back(-1)">
<?php
 }