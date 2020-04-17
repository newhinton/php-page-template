<?php


namespace App;


use App\Persistence\Database;

class Logger
{
    private $ignoreDB=false;

    public function __construct($ignoreDB=false)
    {
        $this->ignoreDB = $ignoreDB;
    }

    function log($content, $level = Database::LOG_INFO){
        if(!$this->ignoreDB){
            (new Database())->writeToLog($content, $level);
        }

        ob_start();
        var_dump($content);
        $result = ob_get_clean();

        file_put_contents("../log.txt", $result."\n", FILE_APPEND | LOCK_EX);
    }
}