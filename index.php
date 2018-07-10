<?php
/**
 * Created by PhpStorm.
 * User: cr
 * Date: 2018/7/10
 * Time: 12:07
 */

define('RAMS_ROOT', dirname(__FILE__));

include_once RAMS_ROOT."\\core\\init.php";

/*$a=new ramsPDO();
$a->test();*/

//$res=M("player")->where("p_id=1");
//$res=M("player")->where("p_id","=",1);
//$res=M("player")->where(array(array("p_id","=",1),array("p_cmid","=",1)));
$res=M("player")->where("p_id=:p_id and p_cmid=:p_cmid",array(array(":p_cmid",1),array(":p_id",1)));
var_dump($res);