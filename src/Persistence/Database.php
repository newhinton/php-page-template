<?php


namespace App\Persistence;


use App\Application\Actions\Password\PasswordHandler;
use App\Logger;
use App\Mail\MailHandler;
use App\Persistence\Migrations\version_0_0_0;
use App\Persistence\Migrations\version_0_0_1;
use Psr\Http\Message\ServerRequestInterface;
use SQLite3;

class Database
{

    protected $database;
    protected $dbname= '../myDatabase.sqlite';


    const LOG_ERROR = 100;
    const LOG_DEBUG = 80;
    const LOG_VERBOSE = 60;
    const LOG_INFO = 30;



    const ROLE_KING = 4;        //site admin
    const ROLE_LORD = 3;        //content manager
    const ROLE_PEASAMT = 2;     //user
    const ROLE_UNWORTHY = 1;    //disabled

    const PASSWORD_ALGORITHM = PASSWORD_BCRYPT;

    public function __construct()
    {
        $alreadyExisting=file_exists ( $this->dbname );
        $this->database = new SQLite3($this->dbname);

        $migrations = [];

        array_push($migrations,new version_0_0_0($this->database, '0.0.0'));
        array_push($migrations,new version_0_0_1($this->database, '0.0.1'));

        foreach ($migrations as &$value) {
            $value->updateDatabase();
        }

    }

    public function readSQLfile($string): String
    {
        $myfile = fopen($string, "r") or die("Unable to open file!");
        $res = fread($myfile, filesize($string));
        fclose($myfile);
        return $res;
    }

    function writeToLog($content, $level = Database::LOG_INFO){
        $query = $this->database->prepare("INSERT INTO logs (level, content, timestamp) VALUES (:level,:content,:timestamp)");
        $query->bindValue(':timestamp', time(), SQLITE3_INTEGER);
        $query->bindValue(':level', $level, SQLITE3_INTEGER);
        $query->bindValue(':content', $content, SQLITE3_TEXT);
        $query->execute();
    }

    function getLogs($level = Database::LOG_ERROR, $rows=100){
        $query = $this->database->prepare('SELECT * FROM logs WHERE level<=:level ORDER BY timestamp DESC LIMIT :rows;');
        $query->bindValue(':level', $level, SQLITE3_INTEGER);
        $query->bindValue(':rows', $rows, SQLITE3_INTEGER);
        $result = $query->execute();

        $resVar=[];
        while ($row = $result->fetchArray()) {
            $res['level'] = $row['level'];
            $res['timestamp'] = date('H:i  d.m.Y',$row['timestamp']);
            $res['content'] = $row['content'];
            array_push($resVar, $res);
        }
        return $resVar;
    }

    function getUsers($rows=100){
        $query = $this->database->prepare('SELECT userid, name, surname, password, email, role from users LIMIT :rows;');
        $query->bindValue(':rows', $rows, SQLITE3_INTEGER);
        $result = $query->execute();

        $resVar=[];
        while ($row = $result->fetchArray()) {
            $res['id'] = $row['userid'];
            $res['role'] = $row['role'];
            $res['name'] = $row['name'];
            $res['surname'] = $row['surname'];
            $res['email'] = $row['email'];
            array_push($resVar, $res);
        }
        return $resVar;
    }

    function createUser(ServerRequestInterface $request){
        $post = $request->getParsedBody();

        if (isset($post['user_surname']) &&
            isset($post['user_name']) &&
            isset($post['user_email']) &&
            isset($post['user_role']) ) {


            (new Logger())->log("Created a new User: ".$post['user_email']);

            $newPassword = (new PasswordHandler())->getRandomPassword(15);
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            (new MailHandler())->sendCredentialMail($post['user_email'], $newPassword);

            $statement = "INSERT INTO users (name, surname, email, role, password) VALUES (:name, :surname, :email, :role, :pw)"; // the hashed pw is admin

            $query = $this->database->prepare($statement);
            $query->bindValue(':name', $post['user_name'], SQLITE3_TEXT);
            $query->bindValue(':surname', $post['user_surname'], SQLITE3_TEXT);
            $query->bindValue(':email', $post['user_email'], SQLITE3_TEXT);
            $query->bindValue(':pw', $hashedPassword, SQLITE3_TEXT);
            $query->bindValue(':role', $post['user_role'], SQLITE3_INTEGER);
            $query->execute();
        }

    }

    function getUserByID($id){
        $sqlQuery = 'SELECT userid, name, surname, password, email, role from users where userid = :uid';

        $prepared = $this->database->prepare($sqlQuery);
        $prepared->bindValue(':uid', $id, SQLITE3_INTEGER);

        $row = $prepared->execute()->fetchArray();
        return $row;
    }

    function getUserByEMail($mail){
        $sqlQuery = 'SELECT userid, name, surname, password, email, role from users where email = :mail';

        $prepared = $this->database->prepare($sqlQuery);
        $prepared->bindValue(':mail', $mail, SQLITE3_TEXT);

        $row = $prepared->execute()->fetchArray();
        return $row;
    }

    public function updateUser(ServerRequestInterface $request){
        (new Logger())->log("update user");
        $post = $request->getParsedBody();

        if (isset($post['user_surname']) &&
            isset($post['user_id']) &&
            isset($post['user_name']) &&
            isset($post['user_email']) &&
            isset($post['user_role']) ) {

            $statement = 'UPDATE users SET 
            name = :name,
            surname = :surname,
            email = :email,
            role = :role
            where userid = :uid'; // the hashed pw is admin

            $query = $this->database->prepare($statement);
            $query->bindValue(':name', $post['user_name'], SQLITE3_TEXT);
            $query->bindValue(':surname', $post['user_surname'], SQLITE3_TEXT);
            $query->bindValue(':email', $post['user_email'], SQLITE3_TEXT);
            $query->bindValue(':role', $post['user_role'], SQLITE3_INTEGER);
            $query->bindValue(':uid', $post['user_id'], SQLITE3_INTEGER);
            $query->execute();
            return $post['user_id'];
        }
        return -1;
    }

    function updatePassword(ServerRequestInterface $request){
        (new Logger())->log("update pw");
        $post = $request->getParsedBody();

        if (isset($post['change_pw_old']) &&
            isset($post['change_pw_new']) &&
            isset($post['change_pw_confirm']) ) {

            (new Logger())->log("all set!");
            if($post['change_pw_new']!=$post['change_pw_confirm']){

                (new Logger())->log("error! new does not match!");
                return 2;
            }

            if($post['change_pw_new']==""){
                (new Logger())->log("error! new is empty!");
                return 3;
            }

            if(strlen($post['change_pw_new'])<10){
                (new Logger())->log("error! new is to short!");
                return 4;
            }

            $sqlQuery = 'SELECT userid, name, surname, password, email, role from users where email = :email';

            $prepared = $this->database->prepare($sqlQuery);
            $prepared->bindValue(':email', $_SESSION["user_email"], SQLITE3_TEXT);
            $result = $prepared->execute();

            while ($row = $result->fetchArray()) {
                $resultpwd = $row['password'];
            }

            if(password_verify($post['change_pw_old'], $resultpwd) && $post['change_pw_old'] != "") {
                (new Logger())->log("Done!");
                $this->updatePasswordNoValidation($post['change_pw_new'],$_SESSION['user_id']);
                return 0;
            }else{
                (new Logger())->log("Error! Old one is bad");
                return 1;
            }
        }
    }

    function updatePasswordNoValidation($password, $uid){
        $hashedPassword = password_hash($password, Database::PASSWORD_ALGORITHM);
        $statement = 'UPDATE users SET password = :pw where userid = :uid';

        $query = $this->database->prepare($statement);
        $query->bindValue(':pw', $hashedPassword, SQLITE3_TEXT);
        $query->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $query->execute();
    }

    function verifyPassword($email, $pswd): bool
    {
        $sqlQuery = 'SELECT userid, name, surname, password, email, role from users where email = ? COLLATE NOCASE';

        $prepared = $this->database->prepare($sqlQuery);
        $prepared->bindValue(1, strtolower($email), SQLITE3_TEXT);
        $result = $prepared->execute();

        while ($row = $result->fetchArray()) {
            $resultpwd = $row['password'];
            $uid = $row['userid'];
            $uname = $row['name'];
            $usurname = $row['surname'];
            $uemail = strtolower($row['email']);
            $urole = $row['role'];
        }

        if(!isset($resultpwd)){
            $_POST['successfullogin'] = "0";
            $_SESSION["sloginallowed"] = "false";
            return false;
        }

        if (password_verify($pswd, $resultpwd) && $pswd != "") {
            $_SESSION["user_id"] = $uid;
            $_SESSION["user_role"] = $urole;
            $_SESSION["user_name"] = $uname;
            $_SESSION["user_surname"] = $usurname ;
            $_SESSION["user_email"] = $uemail;

            if($urole==Database::ROLE_UNWORTHY){
                $_POST['successfullogin'] = "0";
                $_SESSION["sloginallowed"] = "false";
                $this->writeToLog("Disabled User ".$uemail." tried logging in!", Database::LOG_VERBOSE);
                return false;
            }
            $this->writeToLog("User ".$uemail." logged in!", Database::LOG_VERBOSE);
            return true;
        } else {
            $_POST['successfullogin'] = "0";
            $_SESSION["sloginallowed"] = "false";
            return false;
        }
    }


    function getTokenInfo($token){
        $query = $this->database->prepare('SELECT * FROM recover WHERE token = :token;');
        $query->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $query->execute();

        $resVar=[];
        while ($row = $result->fetchArray()) {
            $res['recoverid'] = $row['recoverid'];
            $res['userid'] = $row['userid'];
            $res['timestamp'] = date('H:i  d.m.Y',$row['timestamp']);
            $res['timestamp_unix'] = $row['timestamp'];
            $res['token'] = $row['token'];
            array_push($resVar, $res);
        }
        if(isset($resVar[0])){
            return $resVar[0];

        }
        return null;
    }

    function invalidateToken($token){

        $statement = 'UPDATE recover SET timestamp = -1 where token = :token'; // the hashed pw is admin
        $query = $this->database->prepare($statement);
        $query->bindValue(':token', $token, SQLITE3_TEXT);
        $query->execute();
    }

    function createToken($userid){
        $token = (new PasswordHandler())->getRandomPassword(50);
        $query = $this->database->prepare("INSERT INTO recover (token, timestamp, userid) VALUES (:token,:timestamp,:userid)");
        $query->bindValue(':token', $token, SQLITE3_TEXT);
        $query->bindValue(':timestamp', time(), SQLITE3_INTEGER);
        $query->bindValue(':userid', $userid, SQLITE3_TEXT);
        $query->execute();
        return $token;
    }


}
