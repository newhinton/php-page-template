<?php


namespace App\Mail;

use App\Logger;
use App\PDF\PDF;
use App\Persistence\Confighandler;
use App\Persistence\DatabaseGames;
use App\Persistence\DatabaseVehicles;
use FPDF;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailHandler
{

    private $cfg;

    public function __construct()
    {
       $this->cfg = new Confighandler();
    }

    private function prepareMail(){
        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        //Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $this->cfg->getSetting('smtp-server');   // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $this->cfg->getSetting('smtp-username'); // SMTP username
        $mail->Password   = $this->cfg->getSetting('smtp-password'); // SMTP password
        $mail->Port       = $this->cfg->getSetting('smtp-port');     // TCP port to connect to

        $security = $this->cfg->getSetting('smtp-security');

        if($security == 'star'){
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        }

        if($security == 'tls'){
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        }
        return $mail;
    }

    public function sendTestmail($recipent){
        $this->sendGenericMail($recipent, 'Testemail: Success!', 'Erfolg!', 'Die E-Mail Einstellungen sind korrekt!');
    }

    public function sendGenericMail($recipent, $subject, $title, $body_content, $attachment=null){
        $mail = $this->prepareMail();
        $pageurl= $this->cfg->getSetting('pagedomain');
        try {
            //Recipients
            $mail->setFrom($this->cfg->getSetting('smtp-username'), $this->cfg->getSetting('smtp-alias'));
            $mail->addAddress($recipent, '');     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $body =
                "
                    <h4 class=\"text-center\">".$title."</h4>
                    <p class=\"text-center\">".$body_content."</p>
                ";

            $mail->Body    = $this->getMailTemplate(utf8_encode($body), $pageurl);
            $mail->AltBody = $body_content;

            if($attachment!=null){
                $mail->AddStringAttachment($attachment['file'], $attachment['filename'], 'base64', 'application/pdf');
            }

            //if we dont buffer the output, it gets dumped to the client. We cant allow that.
            ob_start();
            $mail->send();
            ob_get_clean();

        } catch (Exception $e) {
            (new Logger())->log( "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    public function sendResetMail($recipent, $token){
        $mail = $this->prepareMail();

        $pageurl= $this->cfg->getSetting('pagedomain');
        try {
            //Recipients
            $mail->setFrom($this->cfg->getSetting('smtp-username'), $this->cfg->getSetting('smtp-alias'));
            $mail->addAddress($recipent, '');     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = utf8_encode('Neues Passwort');

            $body =
                "
                    <h4 class=\"text-center\">Passwort zur&uuml;cksetzen</h4>
                    <p class=\"text-center\">Hier kannst du dein Passwort zur&uuml;cksetzen. Solltest du dies nicht angefordert haben, kannst du diese Mail ignorieren.</p>
                    <a class=\"btn btn-primary btn-lg mx-auto mt-2\" href=\"".$pageurl."/recover?token=".$token."\">Zur&uuml;cksetzen</a>
                ";

            $mail->Body    = $this->getMailTemplate(utf8_encode($body), $pageurl);
            $mail->AltBody = 'Hier kannst du dein Passwort zur&uuml;cksetzen: '.$pageurl."/recover?token=".$token;

            //if we dont buffer the output, it gets dumped to the client. We cant allow that.
            ob_start();
            $mail->send();
            ob_get_clean();

        } catch (Exception $e) {
            (new Logger())->log( "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

        public function sendCredentialMail($recipent, $password){
        $mail = $this->prepareMail();

        $pageurl= $this->cfg->getSetting('pagedomain');
        try {
            //Recipients
            $mail->setFrom($this->cfg->getSetting('smtp-username'), $this->cfg->getSetting('smtp-alias'));
            $mail->addAddress($recipent, '');     // Add a recipient

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = utf8_encode('Willkommen!');

            $body =
                "
                    <h4 class=\"text-center\">Herzlich Wilkommen!</h4>
                    <p class=\"text-center\">Du kannst dich nun anmelden. Dein initiales Passwort lautet: <b>".$password."</b></p>
                    <p class=\"text-center\">Bitte &auml;ndere es nach dem ersten Login.</p>
                    <a class=\"btn btn-primary btn-lg mx-auto mt-2\" href=\"".$pageurl."\">Zum Login!</a>
                ";

            $mail->Body    = $this->getMailTemplate(utf8_encode($body), $pageurl);
            $mail->AltBody = 'Herzlich Wilkommen! Du kannst dich nun anmelden. Dein initiales Passwort lautet: \''. $password . '\'. Bitte ändere es nach dem ersten Login. '.$pageurl;

            //if we dont buffer the output, it gets dumped to the client. We cant allow that.
            ob_start();
            $mail->send();
            ob_get_clean();

        } catch (Exception $e) {
            (new Logger())->log( "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    public function getMailTemplate($body, $pageurl){
        //todo fix umlauts
        $templateFile = fopen("templates/mail.html", "r") or die("Unable to open file!");
        $template = fread($templateFile,filesize("templates/mail.html"));
        fclose($templateFile);

        $template = utf8_encode($template);
        $template = str_replace('{{pageurl}}', $pageurl, $template);
        $template = str_replace('{{body}}', $body, $template);
        return $template;

    }
}