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

    private $option = ['join' => '', 'on' => '', 'where' => array("sql"=>'',"data"=>array()), 'order' => '', 'limit' => '','field'=>"*"];

    /*
     * 构造，析构函数
     * */
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
        $match='/^`.*`$/';
        if(!preg_match($match,$this->tableName)) $this->tableName="`$this->tableName`";

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

    /*
     * 功能函数
     * */
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
    //重置参数
    public function resetOption() {
        $this->sql="";
        $this->option["field"]="*";
        $this->option["order"]="";
        $this->option["where"]=array("sql"=>'',"data"=>array());
        $this->chain["show"]="";
        $this->chain["lock"]="";
    }
    //获取sql操作方法字符串
    private function sqlOptions(){
        $options='';
        if($this->option['where']['sql']!=''){
            $options.=" WHERE ".$this->option['where']['sql'];
        }
        if($this->option['order']!=''){
            $options.=" ORDER BY ".$this->option['order'];
        }
        if($this->option['limit']!=''){
            $options.=" LIMIT ".$this->option['limit'];
        }

        return $options;

    }
    //绑定数据
    public function bind($data) {
        foreach ($data as $k=>$d){
            $this->option["where"]['data']["$k"] = $d;
        }
        return $this;
    }
    //显示sql语句
    public function show($bool = true) {

        $this->chain['show'] = $bool;

        return $this;
    }
    //获取sql参数字符串
    private function sqlParms($args){
        $parms = '';
        if(is_array($args)){
            foreach ($args as $k => $v) {
                if ($v == '' || $k == 'show') {
                    continue;
                }
                $parms .= "`$k`='$v'".',';
            }

            return rtrim($parms, ',');
        }
    }

    /*
     * 数据库操作函数
     * */
    /*
     * 限制技
     * */
    //查询指定字段
    public function field($field="*"){
        $field=trim($field);
        if($field == '*') {
            $this->option['field'] = "*";
        }else{
            $tempfield=explode(",",$field);
            foreach ($tempfield as $k=>$f){
                $match='/^`.*`$/';
                if(!preg_match($match,$f)) $f="`$f`";
                $tempfield[$k]=$f;
            }
            $field=implode(",",$tempfield);
            if ($this->option['field'] == '*') {
                $this->option['field'] = $field;
            } else {
                $this->option['field'] = $this->option['field'] . ",$field";
            }
        }
        return $this;
    }
    //过滤条件与
    public function where($where1='',$where2=false,$where3='') {
        if($this->option["where"]['sql']!=''){
            $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
        }
        $this->option["where"]['sql'] = $this->option["where"]['sql'].'(';

        if(!is_array($where1)&&!$where2){
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1;
        }
        if(gettype($where1)=="string"&&gettype($where2)=="string"){
            $where1=str_replace("`","",$where1);
            $match='/^`.*`$/';
            $key=$where1.rand(10000,99999);
            if(!preg_match($match,$where1)) $where1="`$where1`";
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1.$where2.":".$key;
            $this->option["where"]['data'][":$key"] = $where3;
        }
        if(gettype($where1)=="array"){
            foreach ($where1 as $k=>$w){
                if($k>0){
                    $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
                }
                $w[0]=str_replace("`","",$w[0]);
                $match='/^`.*`$/';
                $key=$w[0].rand(10000,99999);
                if(!preg_match($match,$w[0])) $w[0]="`$w[0]`";
                $this->option["where"]['sql'] = $this->option["where"]['sql'].$w[0].$w[1].":".$key;
                $this->option["where"]['data'][":$key"] = $w[2];
            }
        }

        $this->option["where"]['sql'] = $this->option["where"]['sql'].')';
        return $this;
    }
    //过滤条件或
    public function whereOr($where1='',$where2=false,$where3='') {
        if($this->option["where"]['sql']!=''){
            $this->option["where"]['sql'] = $this->option["where"]['sql'].' OR ';
        }
        $this->option["where"]['sql'] = $this->option["where"]['sql'].'(';

        if(!is_array($where1)&&!$where2){
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1;
        }
        if(gettype($where1)=="string"&&gettype($where2)=="string"){
            $match='/^`.*`$/';
            $where1=str_replace("`","",$where1);
            $key=$where1.rand(10000,99999);
            if(!preg_match($match,$where1)) $where1="`$where1`";
            $this->option["where"]['sql'] = $this->option["where"]['sql'].$where1.$where2.":".$key;
            $this->option["where"]['data'][":$key"] = $where3;
        }
        if(gettype($where1)=="array"){
            foreach ($where1 as $k=>$w){
                if($k>0){
                    $this->option["where"]['sql'] = $this->option["where"]['sql'].' AND ';
                }
                $w[0]=str_replace("`","",$w[0]);
                $match='/^`.*`$/';
                $key=$w[0].rand(10000,99999);
                if(!preg_match($match,$w[0])) $w[0]="`$w[0]`";
                $this->option["where"]['sql'] = $this->option["where"]['sql'].$w[0].$w[1].":".$key;
                $this->option["where"]['data'][":$key"] = $w[2];
            }
        }

        $this->option["where"]['sql'] = $this->option["where"]['sql'].')';
        return $this;
    }
    //排序方式
    public function order($order = 'id DESC') {
        $temporder=explode(",",$order);
        foreach ($temporder as $k=>$o){
            $tempsimpleorder=explode(" ",$o);
            $match='/^`.*`$/';
            if(!preg_match($match,$tempsimpleorder[0])) $tempsimpleorder[0]="`$tempsimpleorder[0]`";
            $temporder[$k]=implode(" ",$tempsimpleorder);
        }
        $order=implode(",",$temporder);
        if($this->option['order']!=''){
            $this->option["order"]=$this->option["order"].",";
        }
        $this->option["order"] = $this->option["order"]. $order;
        return $this;
    }
    // 限制数量
    public function limit($offset,$rows = 0) {
        $offset = intval($offset);
        $rows = intval($rows);

        if(!$offset){
            $this->option["limit"]="";
        }else {
            if (!$rows) {
                $this->option["limit"] = "0," . $offset;
            } else {
                $this->option["limit"] = "$offset," . $rows;
            }
        }

        return $this;
    }
    /*
     * 终结技
     * */
    //查询单条数据，如果reset=false，则不清理limit以外的各种条件
    public function find($reset=true) {
        if(C('DB_DEPLOY_TYPE')){
            //选择从库连接
            $this->db = $this->slaveDB;
        }else{

            //选择主库连接
            $this->db = $this->masterDB;
        }

        $this->limit(1);

        $this->sql = "SELECT ".$this->option['field']." FROM $this->tableName";
        $this->sql .= $this->sqlOptions();

        if($this->chain['lock']){
            $this->sql .= ' ' .$this->chain['lock'];
        }

        if($this->chain['show'] === true){
            foreach ($this->option['where']['data'] as $k=>$d){
                $this->sql=str_replace($k,"'$d'",$this->sql);
            }
            return $this->sql;
        }

        $this->stmt = $this->db->prepare($this->sql);
        $this->stmt->execute($this->option['where']['data']);

        if($reset){
            $this->resetOption();
        }
        $this->limit(0);

        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->fetch(PDO::FETCH_BOTH);

    }
    //查询数据集，如果reset=false，则不清理包括limit在内的各种条件
    public function select($reset = true){
        if(C('DB_DEPLOY_TYPE')){
            //选择从库连接
            $this->db = $this->slaveDB;
        }else{

            //选择主库连接
            $this->db = $this->masterDB;
        }

        $this->sql = "SELECT ".$this->option['field']." FROM $this->tableName";
        $this->sql .= $this->sqlOptions();

        if($this->chain['lock']){
            $this->sql .= ' ' .$this->chain['lock'];
        }

        if($this->chain['show'] === true){
            foreach ($this->option['where']['data'] as $k=>$d){
                $this->sql=str_replace($k,"'$d'",$this->sql);
            }
            return $this->sql;
        }

        $this->stmt = $this->db->prepare($this->sql);
        $this->stmt->execute($this->option['where']['data']);

        if($reset){
            $this->resetOption();
            $this->limit(0);
        }

        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->fetchAll(PDO::FETCH_BOTH);
    }
    //添加单条数据
    public function insert($data){
        if(!$data || !is_array($data)){
            return 0;
        }
        $columns=array();
        $pdata=array();
        $ddata=array();
        foreach ($data as $c=>$d){
            $c=str_replace("`","",$c);
            $pdata[]=":$c";
            $ddata[":$c"]=$d;
            $match='/^`.*`$/';
            if(!preg_match($match,$c)) $c="`$c`";
            $columns[]=$c;
        }
        //选择主库连接
        $this->db = $this->masterDB;

        $this->sql= "INSERT INTO $this->tableName(".implode(",",$columns).") VALUE (".implode(",",$pdata).")";

        if($this->chain['show'] === true){
            foreach ($ddata as $k=>$d){
                $this->sql=str_replace($k,"'$d'",$this->sql);
            }
            return $this->sql;
        }

        $this->stmt = $this->db->prepare($this->sql);
        $this->stmt->execute($ddata);

        $this->resetOption();
        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->db->lastInsertId();

    }
    //添加多条数据
    public function insertAll($initdata){
        if(!$initdata || !is_array($initdata)){
            return 0;
        }
        $data=array();
        foreach ($initdata as $simpledata){
            $tempdata=array();
            foreach ($simpledata as $c=>$v){
                $tempdata[str_replace("`","",$c)]=$v;
            }
            $data[]=$tempdata;
        }
        $columns=array();
        $pdata=array();
        $ddata=array();
        foreach ($data as $k=>$sd){
            foreach ($sd as $c=>$d) {
                $c=str_replace("`","",$c);
                if(!in_array($c,$columns)) {
                    $columns[] = $c;
                }
                $ddata[":$c$k"] = $d;
            }
        }
        foreach ($data as $k=>$sd){
            $temppdate=array();
            foreach ($columns as $ck=>$c){
                $temppdate[]=":$c$k";
                if(isset($sd[$c])) {
                    $ddata[":$c$k"] = $sd[$c];
                }else{
                    $ddata[":$c$k"] = "";
                }
            }
            $pdata[] = $temppdate;
        }
        foreach ($columns as $ck=>$c){
            $columns[$ck] = "`$c`";
        }
        //选择主库连接
        $this->db = $this->masterDB;

        $this->sql= "INSERT INTO $this->tableName (".implode(",",$columns).") VALUES ";
        foreach ($pdata as $d){
            $this->sql.="(".implode(",",$d)."),";
        }
        $this->sql = substr($this->sql,0,strlen($this->sql)-1);


        if($this->chain['show'] === true){
            foreach ($ddata as $k=>$d){
                $this->sql=str_replace($k,"'$d'",$this->sql);
            }
            return $this->sql;
        }

        $this->stmt = $this->db->prepare($this->sql);
        $this->stmt->execute($ddata);

        $this->resetOption();
        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->rowCount();

    }
    // 更新数据
    public function update($data){
        if(!$data || !is_array($data)){
            return false;
        }
        if($this->option['where']['sql']){
            //连接主库
            $this->db = $this->masterDB;
            $this->sql= "UPDATE $this->tableName SET ";

            $ddata=array();
            foreach ($data as $c=>$v){
                $c=str_replace("`","",$c);
                if(is_array($v)) {
                    $v[0]=str_replace("`","",$v[0]);
                    $this->sql .= "`$c`=`$v[0]`$v[1]:$c,";
                    $ddata[":$c"] = $v[2];
                }else {
                    $this->sql .= "`$c`=:$c,";
                    $ddata[":$c"] = $v;
                }
            }
            $this->sql = substr($this->sql,0,strlen($this->sql)-1);
            $ddata=array_merge($ddata,$this->option['where']['data']);
            $this->sql .= " ".$this->sqlOptions();

            if($this->chain['show'] === true){
                foreach ($ddata as $k=>$d){
                    $this->sql=str_replace($k,"'$d'",$this->sql);
                }
                return $this->sql;
            }


            $this->stmt = $this->db->prepare($this->sql);
            $res=$this->stmt->execute($ddata);
            $this->resetOption();

            if($this->stmt === false){
                return $this->sqlError();
            }

            return true;
        }else{
            echo "更新语句必须有where，如果全部更新，清where 1<br>";
            return false;
        }

    }
    // 删除数据
    public function delete(){
        //连接主库
        $this->db = $this->masterDB;

        if($this->option['where']["sql"]){
            $this->sql = "DELETE FROM $this->tableName";
            $this->sql .= $this->sqlOptions();

            if($this->chain['show'] === true){
                foreach ($this->option['where']['data'] as $k=>$d){
                    $this->sql=str_replace($k,"'$d'",$this->sql);
                }
                return $this->sql;
            }

            $this->stmt = $this->db->prepare($this->sql);
            $res=$this->stmt->execute($this->option['where']['data']);
            $this->resetOption();

            if($this->stmt === false){
                return $this->sqlError();
            }

            return true;

        }else{
            echo "删除语句必须有where，如果全部删除，清where 1<br>";
            return false;
        }
    }
    //执行原生sql语句
    public function query($sql,$fetch=false){
        $this->sql=$sql;
        if($this->chain['show'] === true){
            foreach ($this->option['where']['data'] as $k=>$d){
                $this->sql=str_replace($k,"'$d'",$this->sql);
            }
            return $this->sql;
        }
        $this->stmt = $this->db->prepare($this->sql);
        $res=$this->stmt->execute($this->option['where']['data']);
        $this->resetOption();

        if($this->stmt === false){
            return $this->sqlError();
        }
        if($fetch){
            return $this->stmt->fetchAll(PDO::FETCH_BOTH);
        }

        return true;
    }
    /*
     * 辅助技
     * */
    //获取当前库的所有表名
    public function getTablesName() {

        //选择主库连接
        $this->db = $this->masterDB;

        $this->sql = 'SHOW TABLES FROM '.$this->dbname;

        $this->stmt = $this->db->query($this->sql);

        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->fetchAll(PDO::FETCH_COLUMN);

    }
    //获取当前表的所有字段
    public function getColumnNmae(){
        //选择主库连接
        $this->db = $this->masterDB;

        $this->sql = 'DESC '.$this->tableName;

        if($this->chain['show'] === true){
            return $this->sql;
        }

        $this->stmt = $this->db->query($this->sql);

        if($this->stmt === false){
            return $this->sqlError();
        }

        return $this->stmt->fetchAll(PDO::FETCH_COLUMN);

    }
    //获取当前表的主键字段
    public function getPK(){
        //选择主库连接
        $this->db = $this->masterDB;

        $this->stmt = $this->db->query('DESC '.$this->tableName);

        if($this->stmt === false){
            return $this->sqlError();
        }

        $table_fields = $this->stmt->fetchAll();
        foreach($table_fields as $table_field){
            if($table_field['Key'] == 'PRI'){
                return $table_field['Field'];
            }
        }

        return '';

    }

    /*
     * 事务函数
     * */
    //加锁，仅支持innodb，一般用于事务
    public function lock() {
        $this->chain['lock'] = 'FOR UPDATE';

        return $this;
    }
    //开启事务(需要数据库支持，否则无效)
    public function startTrans(){
        //选择主库连接
        $this->db = $this->masterDB;

        //关闭自动提交
        $this->db->setAttribute(PDO::ATTR_AUTOCOMMIT,0);
        return $this->db->beginTransaction();
    }
    //执行开启事务(需要数据库支持，否则无效)
    public function commit(){
        //选择主库连接
        $this->db = $this->masterDB;

        return $this->db->commit();
    }
    //事务回滚(需要数据库支持，否则无效)
    public function rollback(){
        //选择主库连接
        $this->db = $this->masterDB;

        return $this->db->rollBack();

    }

    /*
     * 异常操作
     * */
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