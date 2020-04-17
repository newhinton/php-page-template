<?php
declare(strict_types=1);

use App\Application\Actions\Password\PasswordHandler;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Logger;
use App\Mail\MailHandler;
use App\PDF\PDF;
use App\Persistence\Confighandler;
use App\Persistence\Database;
use App\Persistence\DatabaseGames;
use App\Persistence\DatabaseVehicles;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    $app->get('/', function (Request $request, Response $response, $args) {
        if((new PasswordHandler())->verify($request, $response, $args)){
            $array=[
                'pagetitle' => (new Confighandler())->getPagetitle(),
                'is_user_logged_in' => true,
                'role' => $_SESSION['user_role'],
                'user_surname' => $_SESSION["user_surname"],
                'user_name' => $_SESSION["user_name"],
                'siteid' => 'main',
                'user_greeting' => $_SESSION["user_surname"].' '.$_SESSION["user_name"]
            ];
            return $this->get('view')->render($response, 'main.html', $array);
        }
        return PasswordHandler::getLogin($this, $request, $response);

    });

    $app->post('/', function (Request $request, Response $response, $args) {
        if((new PasswordHandler())->verify($request, $response, $args)){
            return $response->withStatus(200)->withHeader('Location', '/');
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/logout', function (Request $request, Response $response, $args) {
        PasswordHandler::killSession();
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/settings', function (Request $request, Response $response, $args) {

        $cfg = new Confighandler();

        if(isset($_SESSION["user_role"])){
            if($_SESSION["user_role"]<Database::ROLE_KING){
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }else{
            return $response->withStatus(200)->withHeader('Location', '/');
        }

        if((new PasswordHandler())->verify($request, $response, $args)){
            if(true){
                return $this->get('view')->render($response, 'settings.html', [
                    'pagetitle' => 'Einstellungen',
                    'is_user_logged_in' => true,
                    'siteid' => 'settings',
                    'role' => $_SESSION['user_role'],
                    'user_surname' => $_SESSION["user_surname"],
                    'user_name' => $_SESSION["user_name"],
                    'smtpserver' => $cfg->getSetting('smtp-server'),
                    'smtpusername' => $cfg->getSetting('smtp-username'),
                    'smtppassword' => $cfg->getSetting('smtp-password'),
                    'smtpport' => $cfg->getSetting('smtp-port'),
                    'smtpsecurity' => $cfg->getSetting('smtp-security'),
                    'smtpalias' => $cfg->getSetting('smtp-alias'),
                    'logs' => (new Database())->getLogs(),
                    'users' => (new Database())->getUsers(),
                ]);
            }else{
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }
        return PasswordHandler::getLogin($this, $request, $response);

    });

    $app->post('/settings', function (Request $request, Response $response, $args) {

        if(isset($_SESSION["user_role"])){
            if($_SESSION["user_role"]<Database::ROLE_KING){
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }else{
            return $response->withStatus(200)->withHeader('Location', '/');
        }

        if((new PasswordHandler())->verify($request, $response, $args)){
            (new Confighandler())->storeSMTP($request);
            return $response->withStatus(200)->withHeader('Location', '/settings');
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/settings/sendmail', function (Request $request, Response $response, $args) {
        if(isset($_SESSION["user_role"])){
            if($_SESSION["user_role"]<Database::ROLE_KING){
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }else{
            return $response->withStatus(200)->withHeader('Location', '/');
        }

        if((new PasswordHandler())->verify($request, $response, $args)){
            (new MailHandler())->sendTestmail($_SESSION["user_email"]);
            return $response->withStatus(200)->withHeader('Location', '/settings');
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

     $app->get('/settings/adduser', function (Request $request, Response $response, $args) {
         if(isset($_SESSION["user_role"])){
             if($_SESSION["user_role"]<Database::ROLE_KING){
                 return $response->withStatus(200)->withHeader('Location', '/');
             }
         }else{
             return $response->withStatus(200)->withHeader('Location', '/');
         }

         if((new PasswordHandler())->verify($request, $response, $args)){
             return $this->get('view')->render($response, 'useradd.html', [
                 'pagetitle' => 'Neuer Nutzer',
                 'is_user_logged_in' => true,
                 'siteid' => 'newuser',
                 'role' => $_SESSION['user_role']
             ]);
         }
         return PasswordHandler::getLogin($this, $request, $response);
     });

    $app->post('/settings/adduser', function (Request $request, Response $response, $args) {
        if(isset($_SESSION["user_role"])){
            if($_SESSION["user_role"]<Database::ROLE_KING){
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }else{
            return $response->withStatus(200)->withHeader('Location', '/');
        }

        if((new PasswordHandler())->verify($request, $response, $args)){
            if($_SESSION['user_role']==Database::ROLE_KING){
                (new Database())->createUser($request);
            }
            return $response->withStatus(200)->withHeader('Location', '/settings');
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/user/{id}', function ($request, $response, $args) {
        $userid_override=$_SESSION["user_id"];
        $show_passwordchange=true;
        if($_SESSION["user_role"]==Database::ROLE_KING){
            $userid_override=$args['id'];
            $show_passwordchange=false;
            if($args['id']==$_SESSION["user_id"]){
                $show_passwordchange=true;
            }
        }
        $userobject=(new Database())->getUserByID($userid_override);
        if((new PasswordHandler())->verify($request, $response, $args)){
            return $this->get('view')->render($response, 'user.html', [
                'pagetitle' => 'Nutzer',
                'is_user_logged_in' => true,
                'user_surname' => $_SESSION["user_surname"],
                'user_name' => $_SESSION["user_name"],
                'role' => $_SESSION["user_role"],
                'user_role' => $userobject["role"],
                'o_user_surname' => $userobject["surname"],
                'o_user_name' => $userobject["name"],
                'user_email' => $userobject["email"],
                'user_id' => $userid_override,
                'siteid' => 'user',
                'edit' => true, //to distinguish this route from the normal user one.
                'userid_override' => $userid_override,
                'show_passwordchange' => $show_passwordchange,
            ]);
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/user', function (Request $request, Response $response, $args) {
        if((new PasswordHandler())->verify($request, $response, $args)){
            return $this->get('view')->render($response, 'user.html', [
                'pagetitle' => 'Nutzer',
                'is_user_logged_in' => true,
                'role' => $_SESSION['user_role'],
                'o_user_surname' => $_SESSION["user_surname"],
                'user_surname' => $_SESSION["user_surname"],
                'user_name' => $_SESSION["user_name"],
                'o_user_name' => $_SESSION["user_name"],
                'user_email' => $_SESSION["user_email"],
                'user_id' => $_SESSION["user_id"],
                'siteid' => 'user',
                'show_passwordchange' => true,
            ]);
        }
        return PasswordHandler::getLogin($this, $request, $response);

    });

    $app->post('/user', function (Request $request, Response $response, $args) {
        if((new PasswordHandler())->verify($request, $response, $args)){

            $state=(new Database())->updatePassword($request);

            $error_wrongconfirmation=false;
            $error_newempty=false;
            $error_wrongpassword=false;
            $error_short=false;

            switch ($state) {
                case 1: $error_wrongpassword=true; break;
                case 2: $error_wrongconfirmation=true; break;
                case 3: $error_newempty=true; break;
                case 4: $error_short=true; break;
            }

            if($state==0){

                return PasswordHandler::getLogin($this, $request, $response, true, $_SESSION['user_email']);
            }
            return $this->get('view')->render($response, 'user.html', [
                'pagetitle' => 'Nutzer',
                'is_user_logged_in' => true,
                'role' => $_SESSION['user_role'],
                'user_surname' => $_SESSION["user_surname"],
                'user_name' => $_SESSION["user_name"],
                'user_email' => $_SESSION["user_email"],
                'user_id' => $_SESSION["user_id"],
                'siteid' => 'user',
                'show_passwordchange' => true,
                'error_wrongpassword' => $error_wrongpassword,
                'error_wrongconfirmation' => $error_wrongconfirmation,
                'error_newempty' => $error_newempty,
                'error_too_short' => $error_short,
            ]);
        }
        return PasswordHandler::getLogin($this, $request, $response);

    });

    $app->post('/user/update', function (Request $request, Response $response, $args) {
        if(isset($_SESSION["user_role"])){
            if($_SESSION["user_role"]<Database::ROLE_KING){
                return $response->withStatus(200)->withHeader('Location', '/');
            }
        }else{
            return $response->withStatus(200)->withHeader('Location', '/');
        }

        if((new PasswordHandler())->verify($request, $response, $args)){
            $appendix=0;
            if($_SESSION['user_role']==Database::ROLE_KING){
                $appendix=(new Database())->updateUser($request);
            }
            return $response->withStatus(200)->withHeader('Location', '/user/'.$appendix);
        }
        return PasswordHandler::getLogin($this, $request, $response);
    });

    $app->get('/recover', function (Request $request, Response $response, $args) {
        $get = $request->getQueryParams();

        $pagevalues = [
            'pagetitle' => 'Passwort zurücksetzen',
            'is_user_logged_in' => false,
            'sendmail' => false,
            'reset' => false,
            'passwordui' => false,
            'invalidtoken' => false,
        ];


        if(isset($get['token'])){
            //STEP 4: Open tokenlink and show reset ui
            $tokenInfo = (new Database())->getTokenInfo($get['token']);
            $tokenValidity =(24*60*60);
            if($tokenInfo['userid']!=null && $tokenInfo['timestamp_unix']!=-1 && $tokenInfo['timestamp_unix']>time()-$tokenValidity){
                $pagevalues['passwordui']=true;
                $pagevalues['token']=$get['token'];
            }else{
                $pagevalues['invalidtoken']=true;
            }
            return $this->get('view')->render($response, 'recover.html', $pagevalues);

        }elseif(isset($get['send'])){
            //STEP 3: Inform user to check his emails
            $pagevalues['sendmail']=true;
            return $this->get('view')->render($response, 'recover.html', $pagevalues);
        }else{
            //STEP 1: Enter email
            $pagevalues['reset']=true;
            return $this->get('view')->render($response, 'recover.html', $pagevalues);
        }
    });

    $app->post('/recover', function (Request $request, Response $response, $args) {
        $post = $request->getParsedBody();

        $db = new Database();
        //STEP 2: Verify email and create token email
        if(isset($post['email'])){
            (new Logger())->log('Password reset requested: '.$post['email']);
            $id=$db->getUserByEMail($post['email'])['userid'];
            if($id!=NULL){
                $token = $db->createToken($id);
                (new MailHandler())->sendResetMail($post['email'], $token);
            }
        }


        //STEP 5: use token and email to handle password reset
        if(isset($post['token'])){
            $pagevalues = [
                'pagetitle' => 'Passwort zurücksetzen',
                'is_user_logged_in' => false,
                'sendmail' => false,
                'reset' => false,
                'passwordui' => false,
                'invalidtoken' => false,
            ];
            $pagevalues['passwordui']=true;
            $pagevalues['token']=$post['token'];

            $pagevalues['error_wrongconfirmation']=false;
            $pagevalues['error_newempty']=false;
            $pagevalues['error_too_short']=false;

            if(!isset($post['change_pw_new']) || $post['change_pw_new'] == ""){
                $pagevalues['passwordui']=true;
                $pagevalues['error_newempty']=true;
                return $this->get('view')->render($response, 'recover.html', $pagevalues);
            }

            if(strlen($post['change_pw_new'])<10){
                $pagevalues['passwordui']=true;
                $pagevalues['error_too_short']=true;
                return $this->get('view')->render($response, 'recover.html', $pagevalues);
            }

            if($post['change_pw_new']!=$post['change_pw_confirm']){
                $pagevalues['passwordui']=true;
                $pagevalues['error_wrongconfirmation']=true;
                return $this->get('view')->render($response, 'recover.html', $pagevalues);
            }

            $tokenInfo = $db->getTokenInfo($post['token']);
            $db->updatePasswordNoValidation($post['change_pw_new'], $tokenInfo['userid']);
            $db->invalidateToken($post['token']);
            return $response->withStatus(200)->withHeader('Location', '/');
        }
        return $response->withStatus(301)->withHeader('Location', '/recover?send');
    });

};
