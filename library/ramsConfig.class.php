<?php

class ramsConfig
{

//获取配置字段的值
    public static function get($key = '', $default = '')
    {
        $rams_config = include RAMS_CONFIG_PATH."\\ramsConfig.php";
        $diy_config = include RAMS_CONFIG_PATH."\\diyConfig.php";
        $db_config = include RAMS_CONFIG_PATH."\\dbConfig.php";

        if($rams_config['isDevelop']){
            $_config = array_merge($db_config['dev'], $rams_config,$diy_config['dev']);
        }else{
            $_config = array_merge($db_config['pro'], $rams_config,$diy_config['pro']);
        }

        if(!$key){
            return $_config;
        }
        if (!strpos($key, '.')) {
            return $_config[$key];
        }

        $_key = explode('.', $key);
        $_value = $_config[$_key[0]][$_key[1]];
        if($default){
            return $_value ? $_value : $default;
        }

        return $_value;

    }

}