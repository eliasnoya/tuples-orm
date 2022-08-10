<?php

namespace TuplesOrm;

use PDO;
use PDOStatement;

/* 
* Helper para uso de PHP/PDO
*/
class Db {
    
    /**
     * Db instances
     * @var Db[]
     */
    private static $instance    =   [];
    
    /**
     * 
     * @var \PDO
     */
    protected $pdo;
    public $dbEngine;
    
    public static $configs = [
        'default' => [
            'dsn' => 'mysql:host=127.0.0.1;dbname=tuples_master',
            'usr' => 'root',
            'psw' => '',
            'options' => [
                PDO::MYSQL_ATTR_INIT_COMMAND    => 'SET NAMES \'UTF8\'',
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC
            ]
        ],
    ];

    public $lastQuery;
    public $lastQueryRaw;
    
    public static function setConnection($configSetName, $dsn, $usr, $psw, $options = []) 
    {
        Db::$configs[$configSetName] = [
            'dsn'       => $dsn,
            'usr'       => $usr,
            'psw'       => $psw,
            'options'   => $options
        ];
    }

    private function __construct($configSet)
    {
        $current        = Db::$configs[$configSet];
        $this->pdo      = new PDO($current['dsn'], $current['usr'], $current['psw'], $current['options']);
        $this->dbEngine = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    /**
     * 
     */
    public function getDb() : PDO
    {
        return $this->pdo;
    }
    
    /**
     * 
     */
    public static function getInstance($configSet = 'default')
    {
        if (!isset(self::$instance[$configSet])) {
            self::$instance[$configSet] = new Db($configSet);
        }
        return self::$instance[$configSet];
    }
    
    public function query($query, array $params = []) : PDOStatement 
    {
        // echo $query . "<br/>";
        $stmt = $this->pdo->prepare($query);
        $searchs = [];
        $replacements = [];
        // print_r($params); die();
        foreach ($params as $param => &$value) {
            $searchs[]        = $param;
            $replacements[]   = "'$value'";
            $stmt->bindParam($param, $value, $this->getValueType($value));
        }
        $this->lastQueryRaw     =   $stmt->queryString;
        $this->lastQuery        =   str_replace($searchs, $replacements, $this->lastQueryRaw);
        $stmt->execute();
        return $stmt;
    }
    
    public function getLastIntertedId()
    {
        return $this->pdo->lastInsertId();
    }
    
    public function getLastQuery()
    {
        return $this->lastQuery;
    }
    
    public function getLastQueryRaw()
    {
        return $this->lastQueryRaw;
    }
    
    private function getValueType($value) 
    {
        if (is_null($value)) {
            return PDO::PARAM_NULL;
        } else if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } else if (is_int($value)) {
            return PDO::PARAM_INT;
        }
        return PDO::PARAM_STR;
    }
}