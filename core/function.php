<?php

if(!function_exists('C')){
    function C($key = '', $default = ''){

        return ramsConfig::get($key, $default);

    }
}
if(!function_exists('M')){
    function M($tableName = '', $tablePrefix = ''){

        return new ramsPDO($tableName, $tablePrefix);

    }
}














