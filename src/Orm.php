<?php

namespace TuplesOrm;
use PDO;

/**
 * Clase para contruir queries
 * v 1 - 2022-03-22
 */
class Orm {
    
    /**
     * 
     * @var Db
     */
    private $db;
    
    public $table;
    public $key;
    
    /* for select */
    public $selects = [];
    
    /* for insert/update */
    public $fields      =   [];
    
    public $orderByMode     =   'ASC';
    public $orderBy         =   [];
    
    /* for all query */
    public $joins   = [];
    public $wheres  = [];
    public $whereRaw = [];
    public $limit;
    public $offset;
    
    public $limitStyle = '';
    
    public $whereBinds = [];
    public $insertUpdateBinds = [];
    public $bindCount = 0;
    
    public $groupsBy;
    
    public $updatedIdValue  =   null;
    public $isUpdate        =   false;
    public $isSelect        =   false;
    
    private function __construct($table, $dbConfigSet, $key) {
        $this->table        = $table;
        $this->key          = $key;
        $this->db           = Db::getInstance($dbConfigSet);
        
        if ( in_array($this->db->dbEngine, ['mssql', 'dblib', 'sqlsrv']) ) {
            $this->limitStyle = 'TOP';
        } else {
            $this->limitStyle = 'LIMIT';
        }
    }
    
    /**
     * 
     * @param string $table
     * @param string $dbConfigSet
     * @param string $key
     * @return Orm
     */
    public static function table(string $table, string $dbConfigSet = 'default', string $key = 'id') : Orm
    {
        return new Orm($table, $dbConfigSet, $key);
    }
    
    public function set($field, $value, $exprValue = false)
    {        
        if ($field == $this->key && isset($value)) {
            $this->isUpdate = true;
            $this->updatedIdValue = $value;
            $this->where($this->key, $value);
            return $this; // no agrego el field para que no sea tenido en cuenta ni en el INSERT ni en el UPDATE
        }
        
        if (!$exprValue) {
            $bind = ":".$this->bindCount+1;
            $this->insertUpdateBinds[$bind] = $value;
            $this->bindCount++;
        } else {
            $bind = false; // no hacemos bind si es un set expression ejemplo NOW()
        }
        
        $this->fields[] = [
            'field'     => $field,
            'value'     => isset($value) ? $value : NULL,
            'exprValue' => $exprValue,
            'bind'      => $bind,
        ];
        
        return $this;
    }
    
    public function orderBy(array $fields, $mode = 'ASC') 
    {
        $this->orderBy          = $fields;
        $this->orderByMode      = $mode;
        return $this;
    }
    
    public function limit(int $number)
    {
        $this->limit = $number;
        return $this;
    }
    
    public function offset(int $number)
    {
        $this->offset = $number;
        return $this;
    }
    
    public function setArray(array $data)
    {
        foreach ($data as $field => $value) {
            if (is_array($field) || is_array($value)) {
                throw new \Exception("No se puede realizar setArray con un array multidimensional");
            }
            $this->set($field, $value);
        }
        return $this;
    }
    
    public function select($fieldOrExpr, $as = null) 
    {
        $this->isSelect = true;
        $this->selects[] = [
            'fieldOrExpr'   => $fieldOrExpr,
            'as'            => $as
        ];
        return $this;
    }
    
    public function leftJoin($table, $constraint)
    {
        return $this->join($table, $constraint, "LEFT JOIN");
    }
    
    public function innerJoin($table, $constraint)
    {
        return $this->join($table, $constraint, "INNER JOIN");
    }
    
    public function joinArray(array $joins)
    {
        foreach ($joins as $j) {
            $joinType = isset($j[2]) ? $j[2] : 'INNER JOIN';
            $this->join($j[0], $j[1], $joinType);
            
        }
        return $this;
    }
    
    public function join($table, $constraint, $joinType = 'INNER JOIN')
    {
        $this->joins[] = [
            'table' => $table,
            'constraint' => $constraint,
            'joinType' => $joinType
        ];
        return $this;
    }
    
    /**
     * se debe ingresar un array multidimensional:
     * [
     *  [$campo, $valor, $comaracion],
     *  [$campo, $valor, $comaracion],
     * ]
     * @param array $wheres
     * @return $this
     */
    public function whereArray(array $wheres)
    {
        // pprint($wheres); die();
        foreach ($wheres as $w) {
            $comparation = isset($w[2]) ? $w[2] : '=';
            $this->where($w[0], $w[1], $comparation);
        }
        //pprint($this);
        return $this;
    }
    
    public function whereLike($field, $value)
    {
        return $this->where($field, $value."%", "LIKE");
    }
    
    public function whereLT($field, $value)
    {
        return $this->where($field, $value, "<");
    }
    
    public function whereLET($field, $value)
    {
        return $this->where($field, $value, "<=");
    }
    
    public function whereGT($field, $value)
    {
        return $this->where($field, $value, ">");
    }
    
    public function whereGET($field, $value)
    {
        return $this->where($field, $value, ">=");
    }
    
    public function whereNOTIN($field, $values)
    {
        if (empty($values) || !is_array($values)) {
            return $this;
        }
        
        $str    = "'".implode("','", $values)."'";
        return  $this->whereRaw("$field NOT IN ($str)");
    }
    
    public function whereIN($field, $values)
    {
        if (empty($values) || !is_array($values)) {
            return $this;
        }
        
        $str    = "'".implode("','", $values)."'";
        return  $this->whereRaw("$field IN ($str)");
    }
    
    public function whereSomeLike(array $fields, $value)
    {
        if (!empty($fields)) {
            $str = implode(" LIKE '%VAR%%' OR ", $fields);
            $whereStr = str_replace("%VAR%", $value, $str) . " LIKE '$value%'"; // agrego el ultimo like a mano
            return $this->whereRaw("($whereStr)");
        }
        return $this;
    }
    
    public function where($field, $value, $comparation = '=')
    {
        if (!empty($value)) {
            $bind = ":".$this->bindCount+1;
            $this->wheres[] = [
                'field'         => $field,
                'comparation'   => $comparation,
                'value'         => $value,
                'bind'          => $bind,
            ];
            $this->whereBinds[$bind] = $value;
            $this->bindCount++;
        } elseif ($value == 0) {
            $bind = ":".$this->bindCount+1;
            $this->wheres[] = [
                'field'         => $field,
                'comparation'   => $comparation,
                'value'         => 0,
                'bind'          => $bind,
            ];
            $this->whereBinds[$bind] = $value;
            $this->bindCount++;
        }
        return $this;
    }
    
    public function whereRaw($whereStr)
    {
        $this->whereRaw[] = $whereStr;
        return $this;
    }
    
    private function copileInserts()
    {
        $fields = [];
        $values = [];
        foreach ($this->fields as $f) {
            $fields[]   =   $f['field'];
            $expr       =   $f['exprValue'];
            if ($expr) { // si es una expresion SQL
                $values[] = $f['value'];
            } else { // set comun
                $values[] = $f['bind'];
            }
        }
        return [
            'fields' => implode(' , ', $fields),
            'values' => implode(' , ', $values)
        ];
    }
    
    private function copileUpdateSets()
    {
        $sets = [];
        foreach ($this->fields as $f) {
            
            if ($f['field'] == $this->key) {
                continue;
            }
            
            $expr   =   $f['exprValue'];
            if ($expr) { // si es una expresion SQL
                $value              =   $f['value'];
                $field              =   $f['field'];
                $sets[] = "$field = $value";
            } else { // set comun
                $bind               =   $f['bind'];
                $field              =   $f['field'];
                $sets[] = "$field = $bind";
            }
        }
        return implode(' , ', $sets);
    }
    
    private function copileSelects()
    {
        $sels = array();
        foreach ($this->selects as $s) {
            $fieldOrExpr = $s['fieldOrExpr'];
            $as = (isset($s['as'])) ? " AS " . $s['as'] : '';
            $sels[] = $fieldOrExpr . $as;
        }
        return implode(', ', $sels);
    }
    
    private function copileJoins()
    {
        $joins = [];
        foreach ($this->joins as $j) {
            $table      =   $j['table'];
            $constraint =   $j['constraint'];
            $joinType   =   $j['joinType'];
            $joins[] = "$joinType $table ON $constraint";
        }
        return implode(" ", $joins);
    }
    
    private function copileWheres()
    {
        $wheres = [];
        foreach ($this->wheres as $w) {
            $field          = $w['field'];
            $comparation    = $w['comparation'];
            $bind           = $w['bind'];
            $wheres[]       = "$field $comparation $bind";
        }
        $whereStr = implode(" AND " ,$wheres);
        if (!empty($this->whereRaw)) {
            $preRaw = (empty($whereStr)) ? ' ' : ' AND ';
            $whereStr .= $preRaw . implode(" AND ", $this->whereRaw);
        }
        return $whereStr;
    }
    
    private function copileDeleteQuery()
    {
        $query = "DELETE FROM " . $this->table;
        $query .= $this->copileJoins();
        $whereStr = $this->copileWheres();
        if (!empty($whereStr)) {
            $query .= " WHERE " . $whereStr;
        }
        return $query;
    }
    

    
    private function copileSelectQuery()
    {
        $query = "SELECT ";
        
        
        if ($this->limitStyle == 'TOP' && !empty($this->limit)) {
            $query .= " TOP " . $this->limit . " ";
        }
        
        $selects = $this->copileSelects();
        
        if (empty($selects)) {
            $query .= " * ";
        } else {
            $query .= $selects;
        }
        
        $query .= " FROM " . $this->table . " ";
        $query .= $this->copileJoins();
        
        $whereStr = $this->copileWheres();
        if (!empty($whereStr)) {
            $query .= " WHERE " . $whereStr;
        }
        
        if (!empty($this->groupsBy)) {
            $query .= " GROUP BY " . implode(", ", $this->groupsBy);
        }
        
        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(", ", $this->orderBy) . " " . $this->orderByMode; 
        }
        
        if ($this->limitStyle == 'LIMIT' && !empty($this->limit)) {
            $query .= " LIMIT " . $this->limit . " ";
        }
        
        if ($this->limitStyle == 'LIMIT' && !empty($this->offset)) {
            $query .= " OFFSET " . $this->offset . " ";
        }
        
        return $query;
    }
    
    private function copileUpdateQuery()
    {
        $query = "UPDATE " . $this->table . " AS " . $this->table . " SET " . $this->copileUpdateSets() . " ";
        $query .= $this->copileJoins();
        $query .= "WHERE " . $this->copileWheres();
        return $query.";";
    }
    
    public function save()
    {
        if ($this->isSelect) {
            throw new \Exception("La operación no puede ser SELECT y de INSERT/UPDATE a la vez");
        }
        
        if ($this->isUpdate) {
            
            $query = $this->copileUpdateQuery();
            $binds = array_merge($this->whereBinds, $this->insertUpdateBinds);
            // pprint($binds); echo $query; die();
            $this->db->query($query, $binds);
            return $this->updatedIdValue;
            
        } else {
            
            $insertFieldsValues = $this->copileInserts();
            $query = "INSERT INTO " . $this->table . "(" . $insertFieldsValues['fields'] 
                    . ") VALUES (". $insertFieldsValues['values'] .");";
            // pprint($this->insertUpdateBinds); echo $query; die();
            $this->db->query($query, $this->insertUpdateBinds);
            return $this->db->getLastIntertedId();
            
        }
    }
    
    public function groupBy($fieldOrExpr)
    {
        $this->groupsBy[] = $fieldOrExpr;
        return $this;
    }
    
    /**
     * fast count of rows con PRIMARY KEY
     * IGNORA LOS SELECT PREVIAMENTE DEFINIDOS....
     * @return int
     */
    public function count()
    {
        $query      =   "SELECT ".$this->table.".".$this->key." AS count";
        $query      .=  " FROM " . $this->table . " ";
        $query      .=  $this->copileJoins();
        $whereStr   =   $this->copileWheres();
        if (!empty($whereStr)) {
            $query  .=  " WHERE " . $whereStr;
        }
        if (!empty($this->groupsBy)) {
            $query .= " GROUP BY " . implode(", ", $this->groupsBy);
        }
        $stmt       =   $this->db->query($query, $this->whereBinds);
        $resu       =   $stmt->fetchAll(PDO::FETCH_ASSOC);
        return (empty($resu)) ? 0 : count($resu);
    }
    
    
    public function delete()
    {
        $query  = $this->copileDeleteQuery();
        $this->db->query($query, $this->whereBinds);
        return true;
    }
    
    public function findId($id, $primaryKeyField = 'id')
    {
        $this->limit = 1;
        return $this->where($primaryKeyField, $id)->findOne();
    }
    
    public function findOne()
    {
        $this->limit = 1;
        $query      = $this->copileSelectQuery();
        $stmt       = $this->db->query($query, $this->whereBinds);
        // echo $this->db->getLastQuery() . '<br/>';
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findAll()
    {
        // $this->limit = null;
        $query = $this->copileSelectQuery();
        $stmt = $this->db->query($query, $this->whereBinds);
        // echo $this->db->getLastQuery() . '<br/>'; die();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}