<?php if (isset($invoice['data']) && !empty($invoice['data'])) {
    $data = [];
    if (is_string($invoice['data'])) $data = cmsModel::yamlToArray($invoice['data']);
    if (is_array($invoice['data'])) $data = $invoice['data'];

    if (isset($data['items']) && is_array($data['items'])) {
        $items = $data['items'];
        $columnCount = 2;
        foreach ($items as $item) {
            if (!is_array($item)) {
                $items = [$items];
                $columnCount = count($items);
            } else {
                $columnCount = count($item);
            }
            break;
        }

        $columns = ['Наименование', 'Сумма'];

        ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                <?php foreach($columns as $c => $columnTitle): ?>
                    <th><?= $columnTitle ?></th>
                <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                    <?php foreach ($columns as $c => $columnTitle): ?>
                        <td><?= $item[$c] ?></td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


<?php

    }
}

