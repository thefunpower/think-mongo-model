<?php  
use lib\Validate;
class model{
    protected $table   = '';
    protected $primary = 'id';

    protected $field = [];
    protected $validate_add = [];
    protected $validate_edit = [];
    protected $unique_message = []; 
    /**
    * 字段映射 名字=>数据库中字段名
    * 仅支持find方法
    */
    protected $field_ln = [
        
    ];
    /*
    https://github.com/vlucas/valitron
    */
    protected $validate = []; 
    public function __construct(){
        $lang = 'zh-cn';
        Validate::lang($lang);
        $this->init();
    }
    protected function init(){}
    /**
    * 查寻前
    */
    public function before_find(&$where){
    }
    /**
    * 查寻后
    */
    public function after_find(&$data){
        $ln = $this->field_ln;
        if($ln){
            $data['_has_ln'] = true;
            foreach($ln as $k=>$v){
                if($data[$v]){
                    $data[$k] = $data[$v];
                }
            }
        }
    }
    
    /**
    * 写入数据前
    */
    public function before_insert(&$data){
        $validate = $this->validate_add?:$this->validate; 
        if($this->field && $validate){
            $unique = $validate['unique']; 
            unset($validate['unique']);
            $vali = validate($this->field,$data,$validate);
            if($vali){
                json($vali);
            } 
            if($unique){
                foreach($unique as $i=>$v){
                    $where = [];
                    $f1 = "";
                    foreach($v as $f){
                        $where[$f] = $data[$f];
                        if(!$f1){
                            $f1 = $f;
                        }
                    }
                    $res = $this->find($where);
                    if($res){
                        json_error(['msg'=>$this->unique_message[$i]?:'记录已存在','key'=>$f1]);
                    }
                }
            }
        } 
    }
    /**
    * 写入数据后
    */
    public function after_insert($id){
    }
    
    /**
    * 更新数据前
    */
    public function before_update(&$data,$where){
        $id = $where[$this->primary];
        $validate = $this->validate_edit?:$this->validate;  
        if($this->field && $validate){ 
            $unique = $validate['unique']; 
            unset($validate['unique']);
            $vali  = validate($this->field,$data,$validate); 
            if($vali){
                json($vali);
            } 
            if($unique){
                foreach($unique as $i=>$v){
                    $con = [];
                    $f1 = "";
                    foreach($v as $f){
                        $con[$f] = $data[$f];
                        if(!$f1){
                            $f1 = $f;
                        }
                    } 
                    $res = $this->find($con,1);
                    if($res && $res[$this->primary] != $id){ 
                        json_error(['msg'=>$this->unique_message[$i]?:'记录已存在','key'=>$f1]);  
                    }
                    
                }
            }
        }

    }
    /**
    * 更新数据后
    */
    public function after_update($row_count,$data,$where){
    }
    /**
    * 删除前
    */
    public function before_del(&$where)
    {        
    }
    /**
    * 删除后
    */
    public function after_del($where)
    {        
    }   
    /**
    * 更新数据
    */
    public function update($data,$where = ''){
        if(!$where){
            return false;
        }
        $this->_where($where);
        $this->before_update($data,$where);
        $data_db = db_allow($this->table,$data);
        $row_count = db_update($this->table,$data_db,$where);
        $this->after_update($row_count,$data,$where);
        return $row_count;
    }
    /**
    * 写入数据
    */
    public function insert($data){
        $this->before_insert($data);
        $data_db = db_allow($this->table,$data);
        $id = db_insert($this->table,$data_db);
        $this->after_insert($id);
        return $id;
    }
    /**
    * 分页
    */
    public function pager($join, $columns = null, $where = null){
        $this->_where($where);
        return db_pager($this->table,$join, $columns, $where);
    }
    /**
    * SUM
    */
    public function sum($filed,$where = ''){
        $this->_where($where);
        return db_get_sum($this->table,$filed,$where);
    }
    /**
    * COUNT
    */
    public function count($where = ''){
        $this->_where($where);
        return db_get_count($this->table,$this->primary,$where);
    } 
    /**
    * MAX
    */
    public function max($filed,$where = ''){
        $this->_where($where);
        return db_get_max($this->table,$filed,$where);
    }
    /**
    * MIN
    */
    public function min($filed,$where = ''){
        $this->_where($where);
        return db_get_min($this->table,$filed,$where);
    }
    /**
    * AVG
    */
    public function avg($filed,$where = ''){
        $this->_where($where);
        return db_get_avg($this->table,$filed,$where);
    }
    /**
    * DEL
    */
    public function del($where = ''){
        $this->_where($where);
        $this->before_del($where);
        $res = db_del($this->table,$where);
        $this->after_del($where);
        return $res;
    }
    /**
    * 原生
    * select(['@phone']) distinct
    */
    public function select($join , $columns = null, $where = null){
        $res = medoo_db()->select($this->table, $join, $columns, $where);
        return $res;
    }
    /**
    * 查寻一条记录
    */
    public function find_one($where = ''){
        return $this->find($where,1);
    }
    /**
    * 查寻多条记录
    */
    public function find_all($where = ''){
        return $this->find($where);
    }
    /**
    * 查寻记录
    */
    public function find($where='',$limit='',$use_select = false){
        if(!is_array($where) && $where){
            $limit = 1;
        }
        $this->_where($where);
        if($limit){
            $where['LIMIT'] = $limit;
        }  
        $this->before_find($where);
        $ln = $this->field_ln; 
        if($use_select){
            foreach($where as $k=>$v){ 
                if(is_string($v) && substr($v,0,1) == '@'){
                    $find = substr($v,1);
                    if($ln && $ln[$find]){
                        $where[$k] = "@".$ln[$find];
                    } 
                }
                if(is_object($v)){
                    $vv = $v->value;
                    if($vv && is_string($vv) && strpos($vv,'DISTINCT')!==false){
                        preg_match_all("/<(.*)>/", $vv, $matches); 
                        $a = $matches[0];
                        $b = $matches[1];
                        if($a && $b){
                            foreach($b as $k_b=>$b1){ 
                                if($ln[$b1]){
                                    $vv = str_replace($a[$k_b],$ln[$b1],$vv);
                                }
                            }
                            $where[$k]->value = $vv; 
                        } 
                        $use_select = true;
                    }
                }
            } 
        } 
        if($limit && $limit==1){
            if($use_select){
                $res = $this->select($where); 
            }else{
                $res = db_get_one($this->table,"*",$where);
            }
            $this->after_find($res);
        }else{      
            if($use_select){
                $res = $this->select($where); 
            }else{
                $res = db_get($this->table,"*",$where);     
            } 
            foreach($res as &$v){
                $this->after_find($v);
            }
        } 
        return $res;
    }

    protected function _where(&$where){
        if($where && !is_array($where)){
            $where = [$this->primary=>$where];
        }
        if(!$where){
            $where = [];
        }
        $ln = $this->field_ln;
        if($ln){
            foreach($where as $k=>$v){
                if(strpos($k,'[') !==false ){
                    $k1 = substr($k,0,strpos($k,'['));
                    $k2 = substr($k,strpos($k,'[')); 
                    if($ln[$k1]){
                        unset($where[$k]);
                        $where[$ln[$k1].$k2] = $v;
                    }
                }else if($ln[$k]){
                    unset($where[$k]);
                    $where[$ln[$k]] = $v;
                }
            }
        }
    }
}