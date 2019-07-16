<?php

namespace pdima88\icms2pay\fields;

use cmsTemplate;
use fieldString;
use pdima88\icms2pay\frontend as pay;

class field_paytype extends fieldString {
    public function getInput($value) {

        return cmsTemplate::getInstance()->renderFormField('paytype', array(
            'field' => $this,
            'value' => $value,
            'payTypes' => pay::getInstance()->getPayTypeList()
        ));
    }
}