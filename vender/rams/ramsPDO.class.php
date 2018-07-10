<?php
/**
 * Created by PhpStorm.
 * User: sheva7
 * Date: 2018/7/10
 * Time: 12:04
 */

class ramsPDO{
    private  $dbtype; //数据库类型
    private  $dbhost; //数据库地址
    private  $dbport; //数据库端口号
    private  $dbname; //数据库名
    private  $dbuser; //数据库用户名
    private  $dbpass; //数据库密码
    private  $dbprefix; //表前缀
    private  $dbcharset; //数据库字符编码
    private  $stmt = null;
    private  $masterDB = null;
    private  $slaveDB = null;
    private  $db = null;
    private  $sql = '';
    private  $connect = true; // 是否长连接

    private $chain = ['show' => '', 'lock' => '','count' => ''];

    private $option = ['join' => '', 'on' => '', 'where' => array("sql"=>'',"data"=>array()), 'order' => '', 'limit' => ''];

    //构造函数
    public function __construct($tableName = '',$tablePrefix = '') {
        if(!$tablePrefix){
            if(C('DB_DEPLOY_TYPE')){
                $this->dbprefix = C('SLAVE.DB_PREFIX','');
            }else{
                //选择主库连接
                $this->dbprefix = C('MASTER.DB_PREFIX','');
            }
        }
        //完整表名
        $this->tableName = $this->dbprefix.$tableName;

        $this->masterDB = $this->masterConnect();
        if(C('DB_DEPLOY_TYPE')){
            //选择从库连接
            $this->slaveDB = $this->slaveConnect();
        }else{
            //选择主库连接
            $this->slaveDB = $this->masterConnect();
        }

    }

    //析构函数
    public function __destruct() {
        $this->close();
    }

    //关闭链接
    public function close() {
        //关闭主库连接
        $this->masterDB = null;
        //关闭从库连接
        $this->slaveDB = null;
    }

    //主库链接
    private function masterConnect() {
        //获取主库配置信息
        $this->dbtype = C('MASTER.DB_TYPE');
        $this->dbhost = C('MASTER.DB_HOST');
        $this->dbname = C('MASTER.DB_NAME');
        $this->dbuser = C('MASTER.DB_USER');
        $this->dbpass = C('MASTER.DB_PWD');
        $this->dbport = C('MASTER.DB_PORT');
        $this->dbprefix  = C('MASTER.DB_PREFIX');
        $this->dbcharset = C('MASTER.DB_CHARSET');

        try {
            $this->db = new PDO($this->dbtype . ':host=' . $this->dbhost . ';port=' . $this->dbport . ';dbname=' . $this->dbname, $this->dbuser, $this->dbpass, array(
                PDO::ATTR_PERSISTENT => $this->connect
            ));

            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $this->db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            //设置字符集
            $this->db->exec('SET NAMES ' . $this->dbcharset);

            return $this->db;
        }
        catch(PDOException $e) {
            $this->sqlException("Connect Error Infomation:" . $e->getMessage());
        }
    }

    //从库连接
    private function slaveConnect() {
        //获取从库配置信息
        $this->dbtype = C('SLAVE.DB_TYPE', 'mysql');
        $this->dbhost = C('SLAVE.DB_HOST');
        $this->dbname = C('SLAVE.DB_NAME');
        $this->dbuser = C('SLAVE.DB_USER');
        $this->dbpass = C('SLAVE.DB_PWD');
        $this->dbport = C('SLAVE.DB_PORT', '3306');
        $this->dbprefix  = C('SLAVE.DB_PREFIX', '');
        $this->dbcharset = C('SLAVE.DB_CHARSET', 'UTF8');

        try {
            $this->db = new PDO($this->dbtype . ':host=' . $this->dbhost . ';port=' . $this->dbport . ';dbname=' . $this->dbname, $this->dbuser, $this->dbpass, array(
                PDO::ATTR_PERSISTENT => $this->connect
            ));
            $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $this->db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            //设置字符集
            $this->db->exec('SET NAMES ' . $this->dbcharset);

            return $this->db;
        }
        catch(PDOException $e) {
            $this->sqlException("Connect Error Infomation:" . $e->getMessage());
        }
    }

    //过滤条件
    public function where($where1='',$where2=false,$where3='') {
        if(gettype($where1)=="string"&&!$where2){
            if($this->option["where"]['sql']!=''){
                $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
            }
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1;
        }
        if(gettype($where1)=="string"&&gettype($where2)=="string"){
            if($this->option["where"]['sql']!=''){
                $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
            }
            $match='/^`.*`$/';
            $key=$where1;
            if(!preg_match($match,$where1)) $where1="`$where1`";
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1.$where2.":".$key;
            $this->option["where"]['data'][":$key"] = $where3;
        }
        if(gettype($where1)=="array"){
            foreach ($where1 as $w){
                if($this->option["where"]['sql']!=''){
                    $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
                }
                $match='/^`.*`$/';
                $key=$w[0];
                if(!preg_match($match,$w[0])) $w[0]="`$w[0]`";
                $this->option["where"]['sql'] = $this->option["where"]['sql'].$w[0].$w[1].":".$key;
                $this->option["where"]['data'][":$key"] = $w[2];
            }
        }
        if(gettype($where1)=="string"&&gettype($where2)=="array"){
            if($this->option["where"]['sql']!=''){
                $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
            }
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1;
            foreach ($where2 as $d){
                $this->option["where"]['data'][$d[0]] = $d[1];
            }
        }
        var_dump($this->option['where']);
        return $this;
    }

    //显示sql语句
    public function show($bool = false) {

        $this->chain['show'] = $bool;

        return $this;
    }

    //查询单条数据
    public function find($field = '*',$include = true) {
        $field = $this->field($field, $include);

        if(C('DB_DEPLOY_TYPE')){
            //选择从库连接
            $this->db = $this->slaveDB;
        }else{

            //选择主库连接
            $this->db = $this->masterDB;
        }

        $this->limit(1);

        $this->sql = "SELECT $field FROM $this->tableName";
        $this->sql .= $this->sqlOptions();

        if($this->chain['lock']){
            $this->sql .= ' ' .$this->chain['lock'];
        }

        if($this->chain['show'] === true){
            return $this->sql;
        }

        $this->stmt = $this->db->query($this->sql);

        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->fetch(PDO::FETCH_BOTH);

    }

    //查询指定字段
    private function field($field,$include = true){
        if($include || $field == '*'){
            return $field;
        }

        $fields  =  explode(',',$field);

        $all_fields     =  $this->getFields();
        $field      =  $fields?array_diff($all_fields,$fields):'*';

        return implode(',', $field);
    }

    // 限制数量
    public function limit($offset,$rows = 0) {
        $offset = intval($offset);
        $rows = intval($rows);

        if (!$rows ){
            $this->option["limit"] = "LIMIT 0," . $offset;
        }else{
            $this->option["limit"] = "LIMIT $offset," . $rows;
        }

        return $this;
    }

    //获取sql操作方法字符串
    private function sqlOptions(){
        $options = array();

        foreach($this->option as $k => $v){
            if(is_array($v)){
                foreach($v as $k1 => $v1){
                    $options[$k1] .= ' '.$v1;
                }
            }else{
                $options[$k] = $v;
            }
        }

        return str_replace('  ', ' ', implode(" ", $options));

    }

    //获取sql执行错误信息
    private function sqlError($sql = '') {

        if(!$sql){
            $sql = $this->sql;
        }
        $errorInfo = $this->db->errorInfo();

        $msg = '';

        /*if($errorInfo[0] !== '00000'){
            $msg= '<br>';
            $msg.= 'Query Error:'.$errorInfo[2];
            $msg.= '<br>';
            $msg.= 'Error SQL:'.$sql;
            $msg.= '<br>';

        }
        if(C('EMAIL_NOTICE')){
            send_email(C('SYTEM_EMAIL'), 'SQL异常警告', $msg);
        }

        if(C('WEIXIN_NOTICE')){
            weixin_helper::send_template('SQL异常警告', $errorInfo[2], "异常SQL:{$sql}");
        }

        //如果没有开启调试模式，不输出错误信息
        if(!APP_DEBUG){
            return ;
        }*/

        return $msg;
    }

    //获取sql异常信息
    private function sqlException($msg){
        /*if(C('EMAIL_NOTICE')){
            send_email(C('SYTEM_EMAIL'), '数据库连接错误警告', $msg);
        }

        if(C('WEIXIN_NOTICE')){
            weixin_helper::send_template(['title' => '数据库连接错误警告', 'msg' => $msg]);
        }

        if(!APP_DEBUG){
            return ;
        }
        die("Connect Error Infomation:" . $msg);*/
        var_dump($msg);
    }
}