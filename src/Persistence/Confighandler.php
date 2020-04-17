<?php


namespace App\Persistence;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Confighandler
{

    private $configname = '../config.ini';

    public function storeSMTP(Request $request){
        $post = $request->getParsedBody();

        if (isset($post['smtp-server']) && $post['smtp-server'] != "") {
            $this->setSetting('smtp-server',$post['smtp-server']);
        }

        if (isset($post['smtp-port']) && $post['smtp-port'] != "") {
            $this->setSetting('smtp-port',$post['smtp-port']);
        }

        if (isset($post['smtp-username']) && $post['smtp-username'] != "") {
            $this->setSetting('smtp-username',$post['smtp-username']);
        }

        if (isset($post['smtp-password']) && $post['smtp-password'] != "") {
            $this->setSetting('smtp-password',$post['smtp-password']);
        }
        if (isset($post['smtp-alias']) && $post['smtp-alias'] != "") {
            $this->setSetting('smtp-alias',$post['smtp-alias']);
        }

        if (isset($post['smtp-security']) && $post['smtp-security'] != "") {
            $this->setSetting('smtp-security',$post['smtp-security']);
        }

    }

    function getSettings()
    {
        if(file_exists($this->configname)){
            return parse_ini_file($this->configname);
        }

        return null;
    }

    function getSetting($key)
    {
        $values = $this->getSettings();

        if(isset($values[$key])){
            return $values[$key];
        }
        return $key;
    }

    function setSetting($key, $value){
        $ini=$this->getSettings();
        $ini[$key]=$value;
        $this->write_ini_file($ini);
    }

    /**
     * Write an ini configuration file
     * https://stackoverflow.com/questions/5695145/how-to-read-and-write-to-an-ini-file-with-php
     * @param array $array
     * @return bool
     */
    private function write_ini_file($array = [])
    {

        // check second argument is array
        if (!is_array($array)) {
            throw new \InvalidArgumentException('Function argument 2 must be an array.');
        }

        // process array
        $data = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey . '[] = ' . (is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"' . $_sval . '"'));
                            } else {
                                $data[] = $skey . '[' . $_skey . '] = ' . (is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"' . $_sval . '"'));
                            }
                        }
                    } else {
                        $data[] = $skey . ' = ' . (is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"' . $sval . '"'));
                    }
                }
            } else {
                $data[] = $key . ' = ' . (is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"' . $val . '"'));
            }
            // empty line
            $data[] = null;
        }

        // open file pointer, init flock options
        $fp = fopen($this->configname, 'w');
        $retries = 0;
        $max_retries = 100;

        if (!$fp) {
            return false;
        }

        // loop until get lock, or reach max retries
        do {
            if ($retries > 0) {
                usleep(rand(1, 5000));
            }
            $retries += 1;
        } while (!flock($fp, LOCK_EX) && $retries <= $max_retries);

        // couldn't get the lock
        if ($retries == $max_retries) {
            return false;
        }

        // got lock, write data
        fwrite($fp, implode("\n", $data));

        // release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    public function getPagetitle(){

        return $this->getSetting('pagetitle');
    }


}