<?php


namespace App\Persistence\Migrations;

use App\Logger;
use SQLite3;

class version_0_0_0
{

    protected $database;
    private $migrationVersion = 0;

    protected $logger;

    public function __construct($database, $migrationVersion)
    {

        $this->logger = new Logger(true);
        $this->database = $database;
        $this->setMigrationVersion($migrationVersion);

    }

    public function updateDatabase()
    {
        if($this->compareVersions($this->getDBVersion(), $this->migrationVersion)){
            $this->runMigration();
            $this->setDBVersion($this->migrationVersion);
        }
    }

    public function runMigration()
    {
        $queryCreateDBINFOTable = "CREATE TABLE dbInfo (
  name varchar(45) DEFAULT NULL UNIQUE,
  value varchar(45) DEFAULT NULL);
  INSERT INTO dbInfo (name, value) VALUES ('version', '0.0.0');";
        $this->database->exec($queryCreateDBINFOTable);

    }


    /**
     * @param String $migrationVersion
     */
    protected function setMigrationVersion(String $migrationVersion): void
    {
        $this->migrationVersion = $migrationVersion;
    }

    protected function getDBVersion()
    {

        $sqlQueryExists = "SELECT name FROM sqlite_master WHERE type='table' AND name='dbInfo'";

        if($this->database->querySingle($sqlQueryExists) == null){
            return null;
        }

        $sqlQuery = 'SELECT * from dbInfo where name = "version"';
        return $this->database->querySingle($sqlQuery, true)['value'];
    }

    private function setDBVersion($version="0.0.0")
    {
        $sqlQuery = 'UPDATE dbInfo SET value = :value where name = "version"';

        $prepared = $this->database->prepare($sqlQuery);
        $prepared->bindValue(':value', $version, SQLITE3_TEXT);

        $prepared->execute();
    }


    protected function compareVersions($currentDB, $newVersion){

        if($currentDB==null){
            return true;
        }

        $v = version_compare($currentDB, $newVersion);
        if($v==-1){
            //new is newer than current
            return true;
        }

        if($v==0){
            //they are equal
            return false;
        }

        if($v==1){
            //new is older as current
            return false;
        }

        return false;
    }


}
