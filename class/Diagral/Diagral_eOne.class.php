<?php

/*
 * This file is part of the Diagral-eOne-API-PHP distribution (https://github.com/mguyard/Diagral-eOne-API-PHP).
 * Copyright (c) 2018 Marc GUYARD (https://github.com/mguyard).
 * Version : 0.2
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Mguyard\Diagral;

class Diagral_eOne{

    /**
     * Enable/Disable verbose define by user when calling API with variable verbose (ex. $MyAlarm->verbose = True;)
     * @var bool
     */
    public $verbose = False;
    /**
     * Verbose Event Table
     * @var array
     */
    private $verboseEvent;
    /**
     * Base URL for Diagral Cloud
     * @var string
     */
    private $baseUrl = "https://appv3.tt-monitor.com/topaze";
    /**
     * Diagral Cloud Username
     * @var string
     */
    public $username;
    /**
     * Digral Cloud Password
     * @var string
     */
    public $password;
    /**
     * Diagral MasterCode
     * @var int
     */
    private $masterCode;
    /**
     * SessionID retreive by login() method
     * @var string
     */
    private $sessionId;
    /**
     * DiagralID retreive by getSystems() method
     * @var string
     */
    private $diagralId;
    /**
     * All systems informations
     * @var array
     */
    private $systems;
    /**
     * SystemID define by user when calling API with method setSystemId
     * @var int
     */
    private $systemId;
    /**
     * TransmetterID retreive by getConfiguration() method
     * @var string
     */
    private $transmitterId;
    /**
     * CentralID retreive by getConfiguration() method
     * @var string
     */
    private $centralId;
    /**
     * ttmSessionId retreive by connect() or createNewSession() method
     * @var string
     */
    private $ttmSessionId;
    /**
     * systemState contain alarm status
     * @var string
     */
    public $systemState;
    /**
     * groups contain list off activated group in alarm
     * @var array
     */
    public $groups;
    /**
     * doRequestRetry define how many time POST/GET requests to Diagral will be attempts
     * Default : 1
     * @var int
     */
    public $doRequestAttempts;
    /**
     * waitBetweenAttempts is number of seconds between attempts
     * Default : 5
     * @var int
     */
    public $waitBetweenAttempts;
    /**
     * eventsRetry retreive by setEventsRetry() method.
     * Default : 100
     * @var int
     */
    private $eventsRetry;
    /**
     * DeviceMultizone retreive by getDevicesMultizone() method
     * Contain all alarm devices informations
     * @var array
     */
    private $DeviceMultizone;
    /**
     * MarchePresence zone list
     * @var array
     */
    public $MarchePresenceZone;





    /**
     * Object construct initialisation
     * @param string $username Diagral Cloud Username
     * @param string $password Diagral Cloud Password
     */
    public function __construct($username,$password) {
        $this->username = $username;
        $this->password = $password;
        $this->eventsRetry = 100;
        $this->doRequestAttempts = 1;
        $this->waitBetweenAttempts = 5;
        $this->MarchePresenceZone = array();
        $this->verboseEvent = array();
    }




    /**
     * Login to Diagral Cloud
     * @return array All user informations like Firstname, Lastname, CGU, etc...
     */
    public function login() {
        // Login Sequence
        $LoginPost = '{"username":"'.$this->username.'","password":"'.$this->password.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/authenticate/login", $LoginPost)) {
                if(isset($data["sessionId"])) {
                    $this->sessionId = $data["sessionId"];
                    return $data;
                } else {
                    if ($data["message"] == "error.connect.mydiagralusernotfound") {
                        throw new \Exception("User not found -- " . json_encode($data), 20);
                    } else {
                        throw new \Exception("sessionId is not in the response -- " . json_encode($data), 30);
                    }
                }
            } else {
                throw new \Exception("Unable to login to Diagral Cloud (http code : " . $httpRespCode . ")", 10);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Retreive all Diagral systems
     * @return array All systems informations like name, if installation is complete
     */
    public function getSystems() {
        // Get System Sequence
        $GetSystemPost = '{}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/configuration/getSystems", $GetSystemPost)) {
                if(isset($data["diagralId"])) {
                    $this->diagralId = $data["diagralId"];
                    $this->systems  = $data["systems"];
                    return $this->systems;
                } else {
                    throw new \Exception("diagralId is not in the response -- " . json_encode($data), 31);
                }
            } else {
                throw new \Exception("Unable to retrieve systems (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Define on which system who want to work
     * @param integer $id ID of Diagral System
     */
    public function setSystemId($id) {
        if (isset($this->systems[$id])) {
            if ($this->systems[$id]["installationComplete"]) {
                $this->systemId = $id;
            } else {
                throw new \Exception("Installation of this SystemID isn't complete. Please finish your installation before using this API.", 90);
            }
        } else {
            throw new \Exception("This systemID don't exist", 40);
        }
    }




    /**
     * Retreive TransmetterID and centralId
     * @return array All configuration informations about a Diagral System
     */
    public function getConfiguration() {
        // Get Configuration Sequence
        $GetConfPost = '{"systemId":'.$this->systems[$this->systemId]["id"].',"role":'.$this->systems[$this->systemId]["role"].'}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/configuration/getConfiguration", $GetConfPost)) {
                if(isset($data["transmitterId"],$data["centralId"])) {
                    $this->transmitterId = $data["transmitterId"];
                    $this->centralId = $data["centralId"];
                    // Verify if user (not principal user) are able to manage alarm system
                    if ($this->systems[$this->systemId]["role"] == 0 && !$data["rights"]["UNIVERSE_ALARMS"]) {
                        throw new \Exception("This account don't have alarm rights.", 91);
                    } else {
                        return $data;
                    }
                } else {
                    throw new \Exception("transmitterId and/or centralId is not in the response" . json_encode($data), 32);
                }
            } else {
                throw new \Exception("Unable to retrieve configuration (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Verify if eOne is connected to Internet
     */
    public function isConnected() {
        $IsConnectedPost = '{"transmitterId":"'.$this->transmitterId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/installation/isConnected", $IsConnectedPost)) {
                if (isset($data["isConnected"]) && $data["isConnected"]) {
                    if($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "eOne Status : Connected to Internet");
                    }
                } else {
                    throw new \Exception("Your eOne isn't connected to Internet -- " . json_encode($data), 11);                }
            } else {
                throw new \Exception("Unable to know if eOne is connected (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Retreive last session ID
     */
    private function getLastTtmSessionId() {
        // Try to find a existing session
        $FindOldSessionPost = '{"systemId":'.$this->systems[$this->systemId]["id"].'}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/authenticate/getLastTtmSessionId", $FindOldSessionPost, true)) {
                if(strlen($data) == 32) {
                    // A valid session already exist.
                    return $data;
                } else {
                    // No valid session exist. Need to create a new session
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "ttmSessionId and/or centralId is not in the response. Need to create a new session\n" . $data);
                    }
                }
            } else {
                throw new \Exception("Unable to request old session (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Connect to a Diagral System
     * @param string $masterCode MasterCode use to enter in Diagral System
     */
    public function connect($masterCode) {
        try {
            $this->isConnected();
        } catch (Exception $e) {
            throw $e;
        }
        if (empty($masterCode)) {
            throw new \Exception("MasterCode cannot be empty", 41);

        }
        if (preg_match("/^[0-9]*$/", $masterCode)) {
            $this->masterCode = $masterCode;
        } else {
            throw new \Exception("masterCode only support numbers. Need to change it in configuration.", 41);
        }
        try {
            $this->createNewSession();
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Create a new session
     */
    private function createNewSession() {
        $ConnectPost = '{"masterCode":"'.$this->masterCode.'","transmitterId":"'.$this->transmitterId.'","systemId":'.$this->systems[$this->systemId]["id"].',"role":'.$this->systems[$this->systemId]["role"].'}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/authenticate/connect", $ConnectPost)) {
                if(isset($data["ttmSessionId"])) {
                    $this->ttmSessionId = $data["ttmSessionId"];
                    $this->systemState = $data["systemState"];
                    $this->groups = $data["groups"];
                } else {
                    switch ($data["message"]) {
                    case 'transmitter.connection.badpincode':
                        throw new \Exception("masterCode invalid. Please verify your configuration.", 41);
                        break;
                    case "transmitter.connection.sessionalreadyopen":
                        // If user is not principal user, so we are unable to reuse a previous session. We need to create a new one.
                        if ($this->systems[$this->systemId]["role"] == 1) {
                            $lastTtmSessionId = $this->getLastTtmSessionId();
                            try {
                                $this->disconnect($lastTtmSessionId);
                            } catch (Exception $e) {
                                throw $e;
                            }
                            try {
                                $this->createNewSession();
                            } catch (Exception $e) {
                                throw $e;
                            }
                        } else {
                            throw new \Exception("Another session is already open. " . $data["details"], 21);
                        }
                        break;
                    default:
                        throw new \Exception("ttmSessionId is not in the response. Please retry later." . json_encode($data) , 33);
                        break;
                    }
                }
            } else {
                throw new \Exception("Unable to get new session (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Get Alarm status
     * @return array Array who contain system state and activate groups
     */
    public function getAlarmStatus() {
        $GetAlarmStatusPost = '{"centralId":"'.$this->centralId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/status/getSystemState", $GetAlarmStatusPost)) {
                if(isset($data["systemState"])) {
                    $this->systemState = $data["systemState"];
                    $this->groups = $data["groups"];
                    return array($this->systemState, $this->groups);
                } else {
                    if ($this->verbose) {
                        $this->addVerboseEvent("WARNING", "systemState is not in the response\n" . $data);
                    }
                    switch ($data["message"]) {
                        case "transmitter.error.invalidsessionid":
                            try {
                                $this->createNewSession();
                            } catch (Exception $e) {
                                throw $e;
                            }
                            break;
                        default:
                            $this->addVerboseEvent("DEBUG", "Unknown error (http code ".$httpRespCode."). Please contact developper");
                            break;
                    }
                }
            } else {
                throw new \Exception("Unable to request Alarm Status (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Partial Alarm Activation
     * @param  array $groups Groups to activate
     */
    public function partialActivation($groups) {
        $groups = implode(",", $groups);
        $partialActivationPost = '{"systemState":"group","group": ['.$groups.'],"currentGroup":[],"nbGroups":"4","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $partialActivationPost)) {
                if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Partial activation completed");
                    }
                } else {
                    throw new \Exception("Partial Activation Failed" . json_encode($data), 50);
                }
            } else {
                throw new \Exception("Unable to request Partial Alarm Activation (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Presence Alarm Activation
     */
    public function presenceActivation() {
        $presenceActivationPost = '{"systemState":"presence","group": [],"currentGroup":[],"nbGroups":"4","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $presenceActivationPost)) {
                if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Presence activation completed");
                    }
                } else {
                    throw new \Exception("Presence Activation Failed" . json_encode($data), 51);
                }
            } else {
                throw new \Exception("Unable to request Presence Alarm Activation (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Complete Alarm Activation
     */
    public function completeActivation() {
        $CompleteActivationPost = '{"systemState":"on","group": [],"currentGroup":[],"nbGroups":"4","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $CompleteActivationPost)) {
                if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Complete activation completed");
                    }
                    //sleep(5);
                } else {
                    throw new \Exception("Complete Activation Failed" . json_encode($data), 52);
                }
            } else {
                throw new \Exception("Unable to request Complete Alarm Activation (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Complete Alarm Desactivation
     */
    public function completeDesactivation() {
        try {
            list($status,$zones) = $this->getAlarmStatus();
        } catch (Exception $e) {
            throw $e;
        }
        if ($status != "off") {
            $CompleteDesactivationPost = '{"systemState":"off","group": [],"currentGroup":[],"nbGroups":"4","ttmSessionId":"'.$this->ttmSessionId.'"}';
            try {
                if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $CompleteDesactivationPost)) {
                    if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
                        if ($this->verbose) {
                            $this->addVerboseEvent("DEBUG", "Complete desactivation complete");
                        }
                        //sleep(5);
                    } else {
                        throw new \Exception("Complete desctivation Failed", 53);
                    }
                } else {
                    throw new \Exception("Unable to request Complete Alarm Desactivation (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
                }
            } catch (Exception $e) {
                throw $e;
            }
        } else {
            $this->addVerboseEvent("INFO", "Alarm isn't active. Unable to desactive alarm");
        }
    }


    /**
     * Define how many time we trying to get events
     * @param int $maxTry
     */
    public function setEventsRetry($maxTry) {
        if (is_int($maxTry)) {
            $this->eventsRetry = $maxTry;
        } else {
            throw new \Exception("Number of retry need to be a integer. Please update your configuration.", 49);
        }
    }




    /**
     * Get all events
     * @param  string $startDate Event start Date
     * @param  string $endDate   Event end Date. If not define, default is now
     * @return array            List of all events already translated with translateEvents() method
     */
    public function getEvents($startDate = "2010-01-01 00:00:00", $endDate = null) {
        require_once('UUID.class.php');
        $v4uuid = UUID::v4();
        // Define default $endDate as it's not possible to do it in function argument
        if(!isset($endDate)) {
            $endDate = date("Y-m-d H:i:s");
        }
        $GetEventsPost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","centralId":"'.$this->centralId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/status/v2/getHistory/".$v4uuid, $GetEventsPost)) {
                $responsePending = True;
                $occurence = 0;
                do {
                        if(list($data,$httpRespCode) = $this->doRequest("/status/v2/getHistory/".$v4uuid, "", False, "GET")) {
                            if(isset($data["status"]) && $data["status"] == "request_status_done") {
                                $responsePending = False;
                                $events = json_decode($data["response"],True);
                                // Remove all element not include between $startDate en $endDate
                                foreach($events as $key => $event) {
                                    if((date_format(date_create($event["date"]),"Y-m-d H:i:s") < $startDate) || (date_format(date_create($event["date"]),"Y-m-d H:i:s") > $endDate) ) {
                                        unset($events[$key]);
                                    }
                                }
                                // Return event after translation (convert code to human read text)
                                return $this->translateEvents($events);
                            } else {
                                if ($occurence < $this->eventsRetry) {
                                    if($this->verbose) {
                                        $this->addVerboseEvent("INFO", "History is in generation... Pending");
                                    }
                                } else {
                                    throw new \Exception("Unable to get History (generation in pending) after ".$this->eventsRetry." try. Please to to increase with calling setEventsRetry() method", 54);
                                }
                                $occurence += 1;
                            }
                        } else {
                            throw new \Exception("Unable to get History (http code : ".$httpRespCode.")... Retrying", 54);
                            $occurence += 1;
                        }
                    } while ($responsePending && $occurence <= $this->eventsRetry);
            } else {
                throw new \Exception("Unable to request History (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * Translate all events in French
     * @param  string $toTranslate Element to translate
     * @param  string $secondlevel Second level to translate if need. Default is null
     * @return string              Text translated
     */
    private function translate($toTranslate,$secondlevel = null) {
        $locale = json_decode(file_get_contents(dirname(__FILE__) . '/localization/locale-fr.json', FILE_USE_INCLUDE_PATH),true);
        if (is_null($secondlevel)) {
            if(isset($locale[$toTranslate])) {
                return $locale[$toTranslate];
            }
        } else {
            if(isset($locale[$toTranslate][$secondlevel])) {
                return $locale[$toTranslate][$secondlevel];
            }
        }
    }




    /**
     * Translate Event. Diagral provide only code. This method permit to translate all events code with natural language
     * @param  array $events Array of all events to translate
     * @return array         Array of all events translated
     */
    private function translateEvents($events) {
        $eventsTranslated = array();
        try {
            $this->getDevicesMultizone();
        } catch (Exception $e) {
            throw $e;
        }
        foreach ($events as $key => $event) {
            $title = $this->translate("logbook.logEvent.".$event["codes"][0]);
            $details = "";
            $detailsAppear = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
            $detailsDisappear = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".disappear");
            $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
            if (strlen($detailsAppear) > 0 && strlen($detailsDisappear) > 0) {
                if ($event["codes"][4] = 1) {
                    $details = str_replace("{0}",$device,$detailsAppear);
                } else {
                    $details = str_replace("{0}",$device,$detailsDisappear);
                }
            }
            $groups = "";
            $toHide = False;
            switch ($event["codes"][0]) {
                case 1:
                    if ($event["codes"][2] === 81) {
                        $device = $this->translate("logbook.logMessages.wiredCentral");
                    } elseif ($event["codes"][2] > 0) {
                        $device = $this->getProductName(2, $event["codes"][2]);
                    }
                    $groups = $this->getActiveZones($event["codes"][3]);
                    break;
                case 5:
                    if (in_array($event["codes"][1],array(1, 6, 2, 3, 4, 5, 7, 9, 17, 19, 20, 21, 22))) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    } else {
                        $toHide = True;
                    }
                    if ($event["codes"][4] = 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".disappear");
                    }
                    $details = str_replace("{0}", $device, $details);
                    break;
                case 7:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    break;
                case 8:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][4] = 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".disappear");
                    }
                    $details = str_replace("{0}", $device, $details);
                    break;
                case 10:
                    if(strlen($this->translate("logbook.logProduct.".$event["codes"][1])) > 0) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 18:
                    if($event["codes"][2] == 8) {
                        $details = $this->translate("logbook.logMessages.wiredCentral");
                    } else {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    }
                    $groups = $this->getActiveZones($event["codes"][3]);
                    break;
                case 21:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][4] = 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".disappear");
                    }
                    $details = str_replace("{0}", $device, $details);
                    break;
                case 23:
                    if ($event["codes"][4] = 1) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                        $details = str_replace("{0}", $device, $details);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 24:
                    if ($event["codes"][4] == 1) {
                        $details = $this->translate("logbook.logMessagesEvent24.deactivate");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent24.activate");
                    }
                    if ((($event["codes"][3] & 0xFE) >> 1) == 60) {
                        $details .= "NoCode";
                    }
                    $details = str_replace("{0}", $this->getProductName($event["codes"][1], $event["codes"][2]), $details);
                    if (($event["codes"][3] & 0x01) == 0) {
                        $details = str_replace("{1}", $this->translate("logbook.logMessages.local"), $details);
                    } else {
                        $details = str_replace("{1}", $this->translate("logbook.logMessages.distant"), $details);
                    }
                    $details = str_replace("{2}", $this->translate("logbook.logAccessCode.".(($event["codes"][3] & 0xFE) >> 1)), $details);
                    break;
                case 25:
                    if ($event["codes"][1] == 2) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 27:
                    if (in_array($event["codes"][1],array(1, 6, 5, 7, 21, 22))) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    } else {
                        $ToHide = True;
                    }
                    break;
                case 32:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][4] = 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".disappear");
                    }
                    $details = str_replace("{0}", $device, $details);
                    break;
                case 34:
                    if ($event["codes"][4] = 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                        $details = str_replace("{0}", $device, $details);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 35:
                    $valid_id = array(2, 4, 17);
                    if (in_array($event["codes"][1],$valid_id)) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                        $details = str_replace("{0}", $device, $details);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 36:
                    $receivedCommand = $event["codes"][1] & 0x0F;
                    $finalState = $event["codes"][1] >> 4;
                    $accessCode = $event["codes"][3] >> 1;
                    $displayGroups = False;
                    $method = "";
                    switch ($receivedCommand) {
                        case 2:
                            $title = $this->translate("logbook.logEvent.".$event["codes"][0],"label1");
                            break;
                        default:
                            $title = $this->translate("logbook.logEvent.".$event["codes"][0],"label2");
                            $displayGroups = True;
                            break;
                    }
                    if ($displayGroups && $finalState == 4) {
                        $displayGroups = False;
                    }
                    $details = $this->translate("logbook.logMessages.receivedCommand").$this->translate("logbook.logReceivedCommand.".$receivedCommand);
                    $details .= " / ".$this->translate("logbook.logMessages.finalState").$this->translate("logbook.logReceivedCommand.".$finalState);
                    if ($displayGroups) {
                        $groups = $this->getActiveZones($event["codes"][2]);
                    }
                    if ($accessCode >= 4 && $accessCode <= 35) {
                        // Code service
                        $method = str_replace("{0}",$accessCode - 3,$this->translate("logbook.logAccessCode.serviceCode"));
                    } elseif ($accessCode >= 36 && $accessCode <= 59) {
                        // Badge
                        $method .= str_replace("{0}", $accessCode - 35, $this->translate("logbook.logAccessCode.badge"));
                    } elseif ($accessCode >= 64 && $accessCode <= 71) {
                        // Badge
                        $method .= str_replace("{0}", $accessCode - 63, $this->translate("logbook.logAccessCode.badge"));
                    } elseif (in_array($accessCode, array(0,1,2,61,63))) {
                        // Label
                        $method .= $this->translate("logbook.logAccessCode.".$accessCode);
                    } // else if unknown code : do not display access type message
                    if (strlen($method) > 0) {
                        $details .= " / ";
                    }
                    $details .= $method;
                    if ($event["codes"][4] >= 1 && $event["codes"][4] < 16) {
                        $device = $this->getProductName(3, $event["codes"][4]);
                    } elseif ($event["codes"][4] >= 101 && $event["codes"][4] < 103) {
                        $device = $this->getProductName(5, $event["codes"][4] - 100);
                    } else {
                        $device = $this->translate("logbook.logDevice.".$event["codes"][4]);
                    }
                    break;
                case 37:
                    if ($event["codes"][2] == 81) {
                        $details = $this->translate("logbook.logMessages.wiredCentral");
                    } elseif ($event["codes"][2] > 0) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    }
                    if ($event["codes"][4] == 0) {
                        $details .= $this->translate("logbook.logMessages.disappear");
                    } else {
                        $details .= $this->translate("logbook.logMessages.appear");
                    }
                    if ($event["codes"][3] >= 1 && $event["codes"][3] <= 4) {
                        $details .= " ".$this->translate("logbook.logDetectEnvelop.".$event["codes"][3]);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 38:
                    if (in_array($event["codes"][1], array(0, 1, 2))) {
                        $details = $this->translate("logbook.logCodeChange.acccess.".$event["codes"][1]);
                    } else {
                        $toHide = True;
                    }
                    if (in_array($event["codes"][3], array(0, 1, 2))) {
                        $details .= $this->translate("logbook.logCodeChange.codeChanged.".$event["codes"][3]);
                    } elseif ($event["codes"][3] == 33 && $event["codes"][4] < 33) {
                        $details .= str_replace("{0}",$event["codes"][4],$this->translate("logbook.logCodeChange.codeChanged.".$event["codes"][3]));
                    } else {
                        $toHide = True;
                    }
                    break;
                case 39:
                    $dispLocalDistant = true;
                    $details = $this->translate("logbook.logMessages.newTime");
                    $details .= str_pad($event["codes"][1], 2, "0", STR_PAD_LEFT).":".str_pad($event["codes"][2], 2, "0",STR_PAD_LEFT);
                    $details .= " / ";
                    switch (($event["codes"][4] & 0xFE) >> 1) {
                        case 0:
                            $details .= $this->translate("logbook.logMessages.user");
                            break;
                        case 1:
                            $details .= $this->translate("logbook.logMessages.installer");
                            break;
                        case 2:
                            $details .= $this->translate("logbook.logMessages.remoteUser");
                            break;
                        case 60:
                            $details .= $this->translate("logbook.logMessages.internetSync");
                            $dispLocalDistant = False;
                            break;
                    }
                    if ($dispLocalDistant) {
                        if (($event["codes"][3] & 0x01) == 0) {
                            $details .= " ".$this->translate("logbook.logMessages.local");
                        } else {
                            $details .= " ".$this->translate("logbook.logMessages.distant");
                        }
                    }
                    break;
                case 40:
                    $dispLocalDistant = true;
                    $details = $this->translate("logbook.logMessages.newDate");
                    $details .= date_format(new \DateTime($event["codes"][2]."/".$event["codes"][3]."/".$event["codes"][1] + 2000), "d/m/Y");
                    switch (($event["codes"][4] & 0xFE) >> 1) {
                        case 0:
                            $details .= $this->translate("logbook.logMessages.user");
                            break;
                        case 1:
                            $details .= $this->translate("logbook.logMessages.installer");
                            break;
                        case 2:
                            $details .= $this->translate("logbook.logMessages.remoteUser");
                            break;
                        case 60:
                            $details .= $this->translate("logbook.logMessages.internetSync");
                            $dispLocalDistant = False;
                            break;
                    }
                    if ($dispLocalDistant) {
                        if (($event["codes"][3] & 0x01) == 0) {
                            $details .= " ".$this->translate("logbook.logMessages.local");
                        } else {
                            $details .= " ".$this->translate("logbook.logMessages.distant");
                        }
                    }
                    break;
                case 42:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][3] < 1 || $event["codes"][3] > 7) {
                        $toHide = true;
                    }
                    $details = $this->translate("logbook.logMessagesEvent42");
                    $details = str_replace("{0}", $device, $details);
                    $details = str_replace("{1}", $this->translate("logbook.logIssues.".$event["codes"][3]), $details);
                    break;
                case 43:
                    if ($event["codes"][4] == 0) {
                        $title = $this->translate("logbook.logEvent.".$event["codes"]["0"].".success");
                    } else {
                        $title = $this->translate("logbook.logEvent.".$event["codes"]["0"].".fail");
                    }
                    $details = str_replace("{0}", $event["codes"][1], $this->translate("logbook.logMessages.callednumber"));
                    $details .= " ".$this->translate("logbook.logMessages.callType");
                    $details .= " ".$this->translate("logbook.logMessages.callProtocol");
                    $details .= $this->translate("logbook.logCallProtocol.".($event["codes"][2] && 0x0F));
                    $details .= " ".$this->translate("logbook.logMessages.callMedia");
                    $details .= $this->translate("logbook.logCallMedia.".(($event["codes"][2] && 0x0F) >> 4));
                    $details .= $this->translate("logbook.logMessages.callResult");
                    $details .= $this->translate("logbook.logCallResult.".$event["codes"][4]);
                    break;
                case 45:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    break;
                case 47:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][3] < 1 || $event["codes"][3] > 7) {
                        $toHide = True;
                    }
                    $details = $this->translate("logbook.logMessagesEvent47");
                    $details = str_replace("{0}", $device, $details);
                    $details = str_replace("{1}", $this->translate("logbook.logIssues.".$event["codes"][3]), $details);
                    break;
                case 49:
                    if (in_array($event["codes"][1], array(1, 3, 5, 6))) {
                        $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    } else {
                        $toHide = True;
                    }
                    $type = $event["codes"][3] >> 4;
                    $details = $this->translate("logbook.logModificationType.".$type);
                    if ($type >= 0 && $type < 3) {
                        $logChangedAccessMessage = $this->translate("logbook.logChangedAccess.".($event["codes"][3] & 0x0F));
                        if ($logChangedAccessMessage != null) {
                            $details .= " ".str_replace("{0}",$event["codes"][4], $logChangedAccessMessage);
                        }
                    }
                    break;
                case 51:
                    $version = "";
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][4] == 24) {
                        $version = $event["codes"][1];
                    } else {
                        $version = $event["codes"][1].".".$event["codes"][2].".".$event["codes"][3];
                    }
                    $details = $this->translate("logbook.logMessagesEvent51");
                    $details = str_replace("{0}", $device, $details);
                    $details = str_replace("{1}", $version, $details);
                    break;
                case 52:
                    $details = str_replace("{0}",(($event["codes"][1] << 8) | $event["codes"][2]),$this->translate("logbook.logMessages.cycleCalls"));
                    $details .= str_replace("{0}",(($event["codes"][3] << 8) | $event["codes"][4]),$this->translate("logbook.logMessages.acqCalls"));
                    break;
                case 54:
                    if (in_array($event["codes"][4], array(0, 1, 2))) {
                        $details = $this->translate("logbook.logSimDefect.".$event["codes"][4]);
                    } else {
                        $toHide = True;
                    }
                    break;
                case 56:
                    $device = $this->getProductName($event["codes"][1], $event["codes"][2]);
                    if ($event["codes"][4] == 1) {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                    } else {
                        $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".desappear");
                    }
                    $details = str_replace("{0}", $device, $details);
                case 57:
                    if ($event["codes"][1] >= 0 && $event["codes"][1] <= 32) {
                        $device = $this->getProductName(-1, $event["codes"][2]);
                        if ($event["codes"][4] == 1) {
                            $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".appear");
                        } else {
                            $details = $this->translate("logbook.logMessagesEvent".$event["codes"][0].".desappear");
                        }
                        $details = str_replace("{0}", $device, $details);
                    } else {
                        $toHide = True;
                    }
                case 58:
                    $title = $this->translate("logbook.logEvent.".$event["codes"][0],"deviceType".$event["codes"][1]);
            }
            if (!$toHide) {
                array_push($eventsTranslated, array("date" => $event["date"], "title" => $title, "details" => $details, "device" => $device, "groups" => $groups, "originCode" => $event["codes"]));
            }
        }
        return $eventsTranslated;
    }




    /**
     * Retreive Group informations
     * @param  integer $index Group id
     * @return array        Diagral Group number
     */
    private function getActiveZones($index) {
        $activeZones = array();
        if (($index & 0x01) > 0) {
            array_push($activeZones,1);
        }
        if (($index & 0x02) > 0) {
            array_push($activeZones,2);
        }
        if (($index & 0x04) > 0) {
            array_push($activeZones,3);
        }
        if (($index & 0x08) > 0) {
            array_push($activeZones,4);
        }
        if (($index & 0x10) > 0) {
            array_push($activeZones,5);
        }
        if (($index & 0x20) > 0) {
            array_push($activeZones,6);
        }
        if (($index & 0x40) > 0) {
            array_push($activeZones,7);
        }
        if (($index & 0x80) > 0) {
            array_push($activeZones,8);
        }
        return $activeZones;
    }




    /**
     * Retreive Diagral Product Name
     * @param  integer $familyNumber Diagral Family Id
     * @param  integer $number       Diagral Product Id
     * @return string               Translated product Name
     */
    private function getProductName($familyNumber, $number) {
        if (($familyNumber == 6 || $familyNumber == 1) && $number == 81) {
            return $this->translate("logbook.logMessages.wiredCentral");
        } else {
            if ($number == 0) {
                $index = $number;
            } else {
                $index = $number - 1;
            }
            switch ($familyNumber) {
                case -1:
                    return $this->translate("defects.camera.label")." ".$number;
                    break;
                case 2:
                    if(strlen($this->DeviceMultizone["centralLearningZone"]["sensors"][$index]["customLabel"]) > 0) {
                        return $this->DeviceMultizone["centralLearningZone"]["sensors"][$index]["customLabel"];
                    } else {
                        return $this->translate("defects.sensor.label").$index;
                    }
                    break;
                case 3:
                    if(strlen($this->DeviceMultizone["centralLearningZone"]["commands"][$index]["customLabel"]) > 0) {
                        return $this->DeviceMultizone["centralLearningZone"]["commands"][$index]["customLabel"];
                    } else {
                        return $this->translate("defects.command.label").$index;
                    }
                    break;
                case 6:
                    return $this->translate("defects.central.label");
                    break;
                case 17:
                    if(strlen($this->DeviceMultizone["centralLearningZone"]["alarms"][$index]["customLabel"]) > 0) {
                        return $this->DeviceMultizone["centralLearningZone"]["alarms"][$index]["customLabel"];
                    } else {
                        return $this->translate("defects.alarm.label").$index;
                    }
                    break;
                case 5: // Dans le code e-One c'est 22 et non 5 mais ca ne semble pas bon pour reconnaitre la box
                    if(strlen($this->DeviceMultizone["centralLearningZone"]["transmitters"][$index]["customLabel"]) > 0) {
                        return $this->DeviceMultizone["centralLearningZone"]["transmitters"][$index]["customLabel"];
                    } else {
                        return $this->translate("defects.transmitter.label").$index;
                    }
                    break;
                case 24:
                    return $this->translate("logbook.genericLogProduct.".$familyNumber);
                    break;
                default:
                    return " ".$familyNumber." [".$number."]";
                    break;
            }
        }
    }




    /**
     * Retreive Diagral Group Name
     * @param  array $ids Array of Diagral group IDs
     * @return array     Array of Diagral group Names
     */
    public function getGroupsName($ids) {
        if(!isset($this->DeviceMultizone["centralLearningZone"]["groupNames"])) {
            try {
                $this->getDevicesMultizone();
            } catch (Exception $e) {
                throw $e;
            }
        }
        $GroupNames = array();
        foreach ($ids as $id) {
            array_push($GroupNames, $this->DeviceMultizone["centralLearningZone"]["groupNames"][$id]);
        }
        return $GroupNames;
    }


    /**
     * Retreive all groups with Id and Name
     * @return array Array of All Diagral Group Names
     */
    public function getAllGroups() {
        if(!isset($this->DeviceMultizone["centralLearningZone"]["groupNames"])) {
            try {
                $this->getDevicesMultizone();
            } catch (Exception $e) {
                throw $e;
            }
        }
        $nbGroups = $this->DeviceMultizone["centralSettingsZone"]["nbGroups"];
        $allGroups = $this->DeviceMultizone["centralLearningZone"]["groupNames"];
        array_splice($allGroups,$nbGroups);
        return $allGroups;
    }




    /**
     * Retreive all Diagral Devices informations
     * @param  integer $maxTry Number of tentative to retreive informations
     */
    private function getDevicesMultizone($maxTry = 100) {
        require_once('UUID.class.php');
        $v4uuid = UUID::v4();
        $GetDeviceMultizonePost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","centralId":"'.$this->centralId.'","transmitterId":"'.$this->transmitterId.'","ttmSessionId":"'.$this->ttmSessionId.'","isVideoOptional":"true","isScenariosZoneOptional":"true","boxVersion":"1.4.0"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/configuration/v2/getDevicesMultizone/".$v4uuid, $GetDeviceMultizonePost)) {
                $responsePending = True;
                $occurence = 0;
                do {
                    if(list($data,$httpRespCode) = $this->doRequest("/configuration/v2/getDevicesMultizone/".$v4uuid, "", False, "GET")) {
                        if(isset($data["status"]) && $data["status"] == "request_status_done") {
                            $responsePending = False;
                            $this->DeviceMultizone = json_decode($data["response"],True);
                        } else {
                            if ($occurence < $maxTry) {
                                if($this->verbose) {
                                    $this->addVerboseEvent("INFO", "DeviceMultizone is in generation... Pending");
                                }
                            } else {
                                throw new \Exception("Unable to get DeviceMultizone (generation in pending) after ".$maxTry, 55);
                            }
                            $occurence += 1;
                        }
                    } else {
                        throw new \Exception("Unable to get DeviceMultizone (http code : ".$httpRespCode.")... Retrying", 55);
                        $occurence += 1;
                    }
                } while ($responsePending && $occurence <= $maxTry);
            } else {
                throw new \Exception("Unable to request DeviceMultizone (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Get Scenarios
     * @param  string $search Search Filter to find match scenarios (based on name)
     * @return array Array who contain scenarios list
     */
    public function getScenarios($search = "") {
        // If DeviceMultizone don't exist yet, we launch function to retreive content values
        if(!isset($this->DeviceMultizone["boxScenariosZone"])) {
            try {
                $this->getDevicesMultizone();
            } catch (Exception $e) {
                throw $e;
            }

        }
        // Create table with scenarios informations (filtered informations)
        foreach ($this->DeviceMultizone["boxScenariosZone"] as $scenarioType => $scenarios) {
            if(is_array($scenarios)) {
                foreach ($scenarios as $scenario) {
                    $scenarioContent = array(
                        "scenarioGroup" => $scenarioType,
                        "type" => $scenario["type"],
                        "isActive" => $scenario["isActive"],
                        "id" => $scenario["id"]
                    );
                    // If search parameters, filtering on content
                    if (!empty($search)) {
                        if (preg_match("/".$search."/", $scenario["name"])) {
                            $scenarioList[$scenario["name"]][] = $scenarioContent;
                        }
                    } else {
                        $scenarioList[$scenario["name"]][] = $scenarioContent;
                    }
                }
            }
        }
        return $scenarioList;
    }



    /**
     * Launch Scenario
     * @param integer $id Launch scenario with this id
     */
    public function launchScenario($id) {
        $launchScenarioPost = '{"scenarioId":"'.$id.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/api/scenarios/launch", $launchScenarioPost)) {
                if(isset($data[0]) && $data[0] == "CMD_OK") {
                    if($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Scenario executed with success");
                    }
                } else {
                    throw new \Exception("Scenario failed to execute" . json_encode($data), 56);
                }
            } else {
                throw new \Exception("Unable to execute this scenario (http code : ".$httpRespCode.")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Disconnect session
     */
    private function disconnect($session = null) {
        // If disconnect isn't call for a specific session, we disconnect the actual session
        if(!isset($session)) {
            $session = $this->ttmSessionId;
        }
        $DisconnectPost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","ttmSessionId":"'.$session.'"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/authenticate/disconnect", $DisconnectPost)) {
                if(isset($data["status"]) && $data["status"] == "OK") {
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Disconnect completed");
                    }
                } else {
                    throw new \Exception("Disconnect Failed". json_encode($data), 68);
                }
            } else {
                throw new \Exception("Unable to request Disconnect (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Logout Session
     */
    public function logout() {
        try {
            $this->disconnect();
        } catch (Exception $e) {
            throw $e;
        }
        $LogoutPost = '{"systemId":"null"}';
        try {
            if(list($data,$httpRespCode) = $this->doRequest("/authenticate/logout", $LogoutPost))  {
                if(isset($data["status"]) && $data["status"] == "OK") {
                    if ($this->verbose) {
                        $this->addVerboseEvent("DEBUG", "Logout completed");
                    }
                } else {
                    throw new \Exception("Logout Failed" . json_encode($data), 69);
                }
            } else {
                throw new \Exception("Unable to request Logout (http code : ".$httpRespCode." with message ".$data["message"].")", 19);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * Method to add Verbose Event in array $verboseEvent
     * @param string $level     Log Level ( DEBUG / INFO / NOTICE / WARNING )
     * @param string $message   Verbose Message
     */
    private function addVerboseEvent($level, $message) {
        if (!preg_match("/^(DEBUG|INFO|NOTICE|WARNING)$/i", $level)) {
            throw new \Exception("You can only add DEBUG, INFO, NOTICE or WARNING in this array and not " . strtoupper($level), 99);
        } else {
            $log = array(strtoupper($level), $message);
            array_push($this->verboseEvent, $log);
        }
    }

    /**
     * Method to clear Verbose Event in array $verboseEvent
     */
    private function clearVerboseEvents() {
        $this->verboseEvent = array();
    }

    /**
     * Method to get Verbose Event in array $verbose
     * @return array    Return verbose event content Array
     */
    public function getVerboseEvents() {
        // Create temporary variable to be able to return content after cleaning with clearVerboseEvents()
        $memoryVerboseEvent = $this->verboseEvent;
        // Clear all event to don't have same log multiple time
        $this->clearVerboseEvents();
        // Return verbose event
        return $memoryVerboseEvent;
    }




    /**
     * Execute all http request to Diagral Cloud
     * @param  string  $endpoint Endpoint API Url
     * @param  string  $data     POST data in JSON format
     * @param  boolean $rawout   Define if you want to receive result in json or already parsed
     * @param  string  $method   Http method to use (GET or POST). Default is POST
     * @param  int     $retry    Number of retry if Diagral Cloud don't reply
     * @return array            Return a JSON content (already parsed in a array if $rawout is true)
     */
    private function doRequest($endpoint, $data, $rawout = False, $method = "POST", $retry = null) {
        // If retry isn't define in function parameters, we using doRequestAttempts value minus 1
        $retry = isset($retry) ? $retry : $this->doRequestAttempts - 1;
        $curl = curl_init();
        $curl_headers = array(
            "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 12_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148",
            "Accept: application/json, text/plain, */*",
            "Accept-Encoding: deflate",
            "X-App-Version: 1.9.1",
            "X-Identity-Provider: JANRAIN",
            "ttmSessionIdNotRequired: true",
            "X-Vendor: diagral",
            "Content-Type: application/json;charset=UTF-8",
            "Content-Length: ".strlen($data),
            "Connection: Close",
        );
        if ($endpoint != "/authenticate/login") {
            $addon = array(
            "Authorization: Bearer ".$this->sessionId,
            "X-Identity-Provider: JANRAIN",
            "ttmSessionIdNotRequired: true",
            );
            foreach ($addon as $header_to_add) {
                array_push($curl_headers, $header_to_add);
            }
        }
        curl_setopt($curl, CURLOPT_URL, $this->baseUrl.$endpoint);
        curl_setopt($curl, CURLOPT_TIMEOUT,        15);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST,  $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS,     $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_headers);
        $result = curl_exec($curl);
        $httpRespCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($this->verbose) {
            $curl_verboseEvent = "**************************************\n";
            $curl_verboseEvent = $curl_verboseEvent . "Request URL : ".$method." ". $this->baseUrl . $endpoint . "\n";
            $curl_verboseEvent = $curl_verboseEvent . "Headers : " . json_encode($curl_headers) . "\n";
            if($method == "POST") {
                $curl_verboseEvent = $curl_verboseEvent . "POST Data : " . $data . "\n";
            }
            $curl_verboseEvent = $curl_verboseEvent . "**************************************\n";
            $curl_verboseEvent = $curl_verboseEvent . "HTTP Response Code : " . $httpRespCode . "\n";
            $curl_verboseEvent = $curl_verboseEvent . "HTTP Response : " . json_encode($result) . "\n";
            $curl_verboseEvent = $curl_verboseEvent . "**************************************\n";
            $this->addVerboseEvent("DEBUG", $curl_verboseEvent);
        }
        curl_close($curl);
        // If Diagral Cloud don't reply (internet issue or Cloud issue)
        if($httpRespCode == 0) {
            while ($retry > 0) {
                --$retry;
                sleep($this->waitBetweenAttempts);
                $this->addVerboseEvent("DEBUG", "Diagral doRequest remain " . $retry . " attempts");
                $this->doRequest($endpoint, $data, $rawout, $method, $retry);
            }
            if ($retry == 0) {
                throw new \Exception("Unable to connect to Diagral Cloud after " . $this->doRequestAttempts . " attempts. Please verify your internet connection and/or retry later.", 10);
            }
        }
        if($rawout == true) {
            return array($result,$httpRespCode);
        } else {
            return array(json_decode($result, true),$httpRespCode);
        }
    }
}
