<?php
namespace s4d\db;
use s4d\payment\thawani;

class sqlite extends thawani {
    public $dbFile = __DIR__.'/db/data.sqlite3';
    public $pdo;
    public function __construct($dbFile = ''){
        $this->dbFile = !empty($dbFile)? $dbFile : $this->dbFile;
        if(!is_dir(dirname($this->dbFile))){
            $this->createDbFolder();
        }
        $this->pdo = new \PDO('sqlite:'.$this->dbFile);
        $this->createDbTables();
    }
    public function createDbFolder(){
        $directory = dirname($this->dbFile);
        if(!is_dir($directory)) {
            if(is_writable(__DIR__)) {
                mkdir($directory, 0777);
                $this->protect_directory_access($directory, 'comprehensive');
            }else{
                trigger_error('Cannot write db file at ' . __DIR__, E_USER_ERROR);
            }
        }
    }
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            return FALSE;
        }
        return $result !== FALSE;
    }
    public function createDbTables(){
        if(!$this->tableExists($this->pdo, 'session_list')){
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS session_list (
                    id INTEGER PRIMARY KEY, 
                    ip_address TEXT, 
                    session_id TEXT, 
                    raw_data JSON, 
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)');
        }
    }
    public function saveSession($ip, $session, $raw){
        $raw = json_encode(is_array($raw)? $raw : []);
        $stmt = $this->pdo->prepare('insert into session_list(ip_address, session_id, raw_data) values (:ip_address, :session_id, :raw_data)');
        $stmt->bindParam(':ip_address', $ip);
        $stmt->bindParam(':session_id', $session);
        $stmt->bindParam(':raw_data', $raw);
        $stmt->execute();
    }
    public function getLastSession($ip){
        $stmt = $this->pdo->prepare('select * from session_list where ip_address like :ip_address order by timestamp DESC limit 1');
        $stmt->bindParam(':ip_address', $ip);
        $stmt->execute();
        $output = $stmt->fetch(\PDO::FETCH_ASSOC);
        if($output){
            $output['raw_data'] = json_decode($output['raw_data'], true);
        }else{
            $output = [];
        }
        return $output;
    }
}