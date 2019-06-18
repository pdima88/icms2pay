<?php
namespace pdima88\icms2pay;

class manifest
{
    function hooks()
    {
        return array(
            'hooks' => array(
            )
        );
    }

    function getRootPath() {
        return realpath(dirname(__FILE__).'/..');
    }
}