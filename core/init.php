<?php
/**
 * Created by PhpStorm.
 * User: cr
 * Date: 2018/7/10
 * Time: 15:07
 */

include_once "core/constant.php";
include_once "core/function.php";

spl_autoload_register(function ($classname){
    $filename=RAMS_ROOT."/vender/rams/$classname.class.php";
    if(file_exists($filename)){
        require($filename);
    }
    $filename=RAMS_ROOT."/vender/other/$classname.class.php";
    if(file_exists($filename)){
        require($filename);
    }
});