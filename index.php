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

//$res=M("player")->field("p_id")->where("p_id='' or ''=''")->show(true)->find();
//$res=M("player")->field("p_id,p_cmid")->where("p_id='' or ''=''")->find(false);

//$res=M("player")->where("p_id=1")->show(true)->find();
//$res=M("player")->where("p_id='16'")->find();
//$res=M("player")->where("p_id","=",1)->show(true)->find();
//$res=M("player")->where("p_id","=","' or ''='")->find();
//$res=M("player")->where(array(array("p_id","=",1),array("p_cmid","=",1)))->show(true)->find();
//$res=M("player")->where(array(array("p_id","=",1),array("p_cmid","=",1)))->find();

//$res=M("player")->where("p_id","=","0")->whereOr("p_id","=",16)->find();

/*$res=M("player")
    ->where("p_id=:p_id1")
    ->whereOr("p_id=:p_id2")
    ->bind(array(
        ":p_id1"=>1,
        ":p_id2"=>16
    ))
    ->field("p_id,p_cmid")
    ->select();*/
/*$res=M("player")
    ->where("p_id=:p_id1")
    ->whereOr("p_id=:p_id2")
    ->where("p_cmid=:p_cmid")
    ->bind(array(
        ":p_id1"=>1,
        ":p_id2"=>16,
        ":p_cmid"=>3
    ))
    ->find();

$res=M("player")
    ->where(array(
        array("p_id","=",1),
        array("p_cmid","=",1)
    ))
    ->show(true)->find();*/

//$res=M("player")->field("p_id,p_cmid")->where(array(array("p_id","=",1),array("p_cmid","=",1)))->show()->find(false);
//$res=M("player")->order("p_id desc,p_name asc")->show()->select();
//$res=M("player")->getTablesName();
//$res=M("player")->getColumnNmae();
//$res=M("player")->getPK();
$value1=array(
    "p_name"=>"33121",
    "p_cmid"=>array("p_cmid","+",1),
);
//$value=array($value1,$value2);
//var_dump(gettype(false));
//$res=M("luck")->where("l_id","=","3")->show(false)->delete($value1);
//$res=M("luck")->show(false)->bind(array(":l_id1"=>4,":l_id2"=>5))->query("select * from tyt_luck where l_id=:l_id1 or l_id=:l_id2",true);
var_dump($res);