<?php


namespace App\Application\Actions\Password;


use App\Logger;
use App\Persistence\Confighandler;
use App\Persistence\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PasswordHandler
{


    /**
     * PasswordHandler constructor.
     */
    public function __construct(){
    }

    public function verify(Request $request, Response $response, $args){
        $allPostPutVars = $request->getParsedBody();
        $db = new Database();

        if (isset($allPostPutVars['username']) && !isset($_SESSION["session_user"])) {
            $_SESSION["session_user"] = $allPostPutVars['username'];
        }

        if (isset($_SESSION["user_authenticated"])) {
            if ($_SESSION["user_authenticated"] === true) {
                return true;
            }
        }

        $auth=false;
        if (isset($allPostPutVars['username']) && isset($allPostPutVars['password'])) {
            $auth = $db->verifyPassword($allPostPutVars['username'], $allPostPutVars['password']);
        }

        if ($auth === true) {
            $_SESSION["user_authenticated"] = true;
            return true;
        } else {
            session_destroy();
            return false;
        }
    }

    public static function getLogin($that, Request $request, Response $response, $relogin=false, $prefill=""){
        $post = $request->getParsedBody();
        $tried_login=false;
        if (isset($post['username']) && isset($post['password'])) {
            $tried_login=true;
        }

        if($relogin){
            PasswordHandler::killSession();
        }


        return $that->get('view')->render($response, 'login.html', [
            'pagetitle' => (new Confighandler())->getPagetitle(),
            'is_user_logged_in' => false,
            'tried_login' => $tried_login,
            'relogin' => $relogin,
            'prefill' => $prefill,
        ]);
    }

    public static function killSession(){
        $_SESSION["session_user"] ="";
        $_SESSION["user_authenticated"]=false;
        session_destroy();
    }

    function getRandomPassword($n=15) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }

}