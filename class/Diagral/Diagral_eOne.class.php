<?php

/*
 * This file is part of the Diagral-eOne-API-PHP distribution (https://github.com/mguyard/Diagral-eOne-API-PHP).
 * Copyright (c) 2018 Marc GUYARD (https://github.com/mguyard).
 * Version : 0.1
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
   * eventsRetry retreive by setEventsRetry() method.
   * Default : 100
   * @var int
   */
  private $eventsRetry = 100;
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
  public $MarchePresenceZone = array();





  /**
   * Object construct initialisation
   * @param string $username Diagral Cloud Username
   * @param string $password Diagral Cloud Password
   */
  public function __construct($username,$password) {
    $this->username = $username;
    $this->password = $password;
  }




  /**
   * Login to Diagral Cloud
   * @return array All user informations like Firstname, Lastname, CGU, etc...
   */
  public function login() {
  	// Login Sequence
  	$LoginPost = '{"username":"'.$this->username.'","password":"'.$this->password.'"}';
  	if(list($data,$httpRespCode) = $this->doRequest("/authenticate/login", $LoginPost)) {
      if(isset($data["sessionId"])) {
        $this->sessionId = $data["sessionId"];
        return $data;
      } else {
        if ($data["message"] == "error.connect.mydiagralusernotfound") {
          $this->showErrors("crit", "User not found.");
        } else {
          $this->showErrors("crit","sessionId is not in the response",$data);
        }
      }
    } else {
      $this->showErrors("crit", "Unable to login to Diagral Cloud (http code : ".$httpRespCode.")");
    }
  }




  /**
   * Retreive all Diagral systems
   * @return array All systems informations like name, if installation is complete
   */
  public function getSystems() {
    // Get System Sequence
    $GetSystemPost = '{"sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/configuration/getSystems", $GetSystemPost)) {
      if(isset($data["diagralId"])) {
        $this->diagralId = $data["diagralId"];
        $this->systems  = $data["systems"];
        return $this->systems;
      } else {
        $this->showErrors("crit","diagralId is not in the response",$data);
      }
    } else {
      $this->showErrors("crit", "Unable to retrieve systems (http code : ".$httpRespCode.")");
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
          $this->showErrors("crit", "Installation of this SystemID isn't complete. Please finish your installation before using this API.");
      }
    } else {
      $this->showErrors("crit", "This systemID don't exist.");
    }
  }




  /**
   * Retreive TransmetterID and centralId
   * @return array All configuration informations about a Diagral System
   */
  public function getConfiguration() {
    // Get Configuration Sequence
    $GetConfPost = '{"systemId":'.$this->systems[$this->systemId]["id"].',"role":'.$this->systems[$this->systemId]["role"].',"sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/configuration/getConfiguration", $GetConfPost)) {
      if(isset($data["transmitterId"],$data["centralId"])) {
        $this->transmitterId = $data["transmitterId"];
				$this->centralId = $data["centralId"];
        // Verify if user (not master user) are able to manage alarm system
        if ($this->systems[$this->systemId]["role"] == 0 && !$data["rights"]["UNIVERSE_ALARMS"]) {
          $this->showErrors("crit", "This account don't have alarm rights.");
        } else {
          return $data;
        }
      } else {
        $this->showErrors("crit","transmitterId and/or centralId is not in the response",$data);
      }
    } else {
      $this->showErrors("crit", "Unable to retrieve configuration (http code : ".$httpRespCode.")");
    }
  }



  /**
   * Verify if eOne is connected to Internet
   */
  public function isConnected() {
    $IsConnectedPost = '{"transmitterId":"'.$this->transmitterId.'","sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/installation/isConnected", $IsConnectedPost)) {
      if (isset($data["isConnected"]) && $data["isConnected"]) {
        if($this->verbose) {
          $this->showErrors("info", "eOne Status : Connected to Internet");
        }
      } else {
        $this->showErrors("crit","Your eOne isn't connected to Internet");
      }
    } else {
      $this->showErrors("crit", "Unable to know if eOne is connected (http code : ".$httpRespCode.")");
    }
  }



  /**
   * Retreive last session ID
   */
  private function getLastTtmSessionId() {
    // Try to find a existing session
    $FindOldSessionPost = '{"systemId":'.$this->systems[$this->systemId]["id"].',"sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/authenticate/getLastTtmSessionId", $FindOldSessionPost, true)) {
      if(strlen($data) == 32) {
        // A valid session already exist.
        return $data;
      } else {
        // No valid session exist. Need to create a new session
        if ($this->verbose) {
          $this->showErrors("info","ttmSessionId and/or centralId is not in the response. Need to create a new session.",$data);
        }
      }
    } else {
      $this->showErrors("crit", "Unable to request old session (http code : ".$httpRespCode.")");
    }
  }



  /**
   * Connect to a Diagral System
   * @param string $masterCode MasterCode use to enter in Diagral System
   */
  public function connect($masterCode) {
    $this->isConnected();
    if (preg_match("/^[0-9]*$/", $masterCode)) {
      $this->masterCode = $masterCode;
    } else {
      $this->showErrors("crit","masterCode only support numbers. Need to change it in configuration.");
    }
    $this->createNewSession();
  }




  /**
   * Create a new session
   */
  private function createNewSession() {
    $ConnectPost = '{"masterCode":"'.$this->masterCode.'","transmitterId":"'.$this->transmitterId.'","systemId":'.$this->systems[$this->systemId]["id"].',"role":'.$this->systems[$this->systemId]["role"].',"sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/authenticate/connect", $ConnectPost)) {
      if(isset($data["ttmSessionId"])) {
        $this->ttmSessionId = $data["ttmSessionId"];
        $this->systemState = $data["systemState"];
        $this->groups = $data["groups"];
      } else {
        switch ($data["message"]) {
          case 'transmitter.connection.badpincode':
            $this->showErrors("crit", "masterCode invalid. Please verify your configuration.");
            break;
          case "transmitter.connection.sessionalreadyopen":
            // If user is not master user, so we are unable to reuse a previous session. We need to create a new one.
            if ($this->systems[$this->systemId]["role"] == 1) {
              $lastTtmSessionId = $this->getLastTtmSessionId();
              $this->disconnect($lastTtmSessionId);
              $this->createNewSession();
            } else {
              $this->showErrors("crit", "Another session is already open. ".$data["details"]);
            }
            break;
          default:
            $this->showErrors("crit","ttmSessionId is not in the response. Please retry later.",$data);
            break;
        }
      }
    } else {
      $this->showErrors("crit", "Unable to get new session (http code : ".$httpRespCode.")");
    }
  }




  /**
   * Get Alarm status
   * @return array Array who contain system state and activate groups
   */
  public function getAlarmStatus() {
    $GetAlarmStatusPost = '{"sessionId":"'.$this->sessionId.'","centralId":"'.$this->centralId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/status/getSystemState", $GetAlarmStatusPost)) {
      if(isset($data["systemState"])) {
        $this->systemState = $data["systemState"];
        $this->groups = $data["groups"];
        return array($this->systemState, $this->groups);
      } else {
        if ($this->verbose) {
          $this->showErrors("warn","systemState is not in the response",$data);
        }
        switch ($data["message"]) {
          case "transmitter.error.invalidsessionid":
            $this->createNewSession();
            break;
				default:
          $this->showErrors("info", "Unknown error (http code ".$httpRespCode."). Please contact developper");
          break;
        }
      }
    } else {
      $this->showErrors("crit", "Unable to request Alarm Status (http code : ".$httpRespCode." with message ".$data["message"].")");
    }
  }




  /**
   * Partial Alarm Activation
   * @param  array $groups Groups to activate
   */
  public function partialActivation($groups) {
    $groups = implode(",", $groups);
    $partialActivationPost = '{"systemState":"group","group": ['.$groups.'],"currentGroup":[],"nbGroups":"4","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $partialActivationPost)) {
      if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
        if ($this->verbose) {
          $this->showErrors("info", "Partial activation completed");
        }
      } else {
        $this->showErrors("crit", "Partial Activation Failed", $data);
      }
    } else {
      $this->showErrors("crit", "Unable to request Partial Alarm Activation (http code : ".$httpRespCode." with message ".$data["message"].")");
    }
  }




  public function presenceActivation() {
    $this->getDevicesMultizone();
    foreach ($this->DeviceMultizone["centralSettingsZone"]["groupesMarchePresence"] as $zone => $activation) {
      if ($activation) {
          array_push($this->MarchePresenceZone, $zone);
      }
    }
    $this->partialActivation($this->MarchePresenceZone);
  }



  /**
   * Complete Alarm Activation
   */
    public function completeActivation() {
      $CompleteActivationPost = '{"systemState":"on","group": [],"currentGroup":[],"nbGroups":"4","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
      if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $CompleteActivationPost)) {
        if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
          if ($this->verbose) {
            $this->showErrors("info", "Complete activation completed");
          }
          //sleep(5);
        } else {
          $this->showErrors("crit", "Complete Activation Failed", $data);
        }
      } else {
        $this->showErrors("crit", "Unable to request Complete Alarm Activation (http code : ".$httpRespCode." with message ".$data["message"].")");
      }
    }




  /**
   * Complete Alarm Desactivation
   */
    public function completeDesactivation() {
      list($status,$zones) = $this->getAlarmStatus();
      if ($status != "off") {
        $CompleteDesactivationPost = '{"systemState":"off","group": [],"currentGroup":[],"nbGroups":"4","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
        if(list($data,$httpRespCode) = $this->doRequest("/action/stateCommand", $CompleteDesactivationPost)) {
          if(isset($data["commandStatus"]) && $data["commandStatus"] == "CMD_OK") {
            if ($this->verbose) {
              $this->showErrors("info", "Complete desactivation completed");
            }
            //sleep(5);
          } else {
            $this->showErrors("crit", "Complete desctivation Failed", $data);
          }
        } else {
          $this->showErrors("crit", "Unable to request Complete Alarm Desactivation (http code : ".$httpRespCode." with message ".$data["message"].")");
        }
      } else {
        $this->showErrors("info", "Alarm isn't active. Unable to desactive alarm");
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
      $this->showErrors("crit", "Number of retry need to be a integer. Please update your configuration.");
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
    $GetEventsPost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","centralId":"'.$this->centralId.'","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'"}';
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
                $this->showErrors("info", "History is in generation... Pending");
              }
  					} else {
  						$this->showErrors("crit", "Unable to get History (generation in pending) after ".$this->eventsRetry." try. Please to to increase with calling setEventsRetry() method");
  					}
  					$occurence += 1;
  				}
  			} else {
  				$this->showErrors("crit", "Unable to get History (http code : ".$httpRespCode.")... Retrying");
  				$occurence += 1;
  			}
		  } while ($responsePending && $occurence <= $this->eventsRetry);
    } else {
      $this->showErrors("crit", "Unable to request History (http code : ".$httpRespCode.")");
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
    $this->getDevicesMultizone();
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
      $this->getDevicesMultizone();
    }
    $GroupNames = array();
    foreach ($ids as $id) {
      array_push($GroupNames, $this->DeviceMultizone["centralLearningZone"]["groupNames"][$id]);
    }
    return $GroupNames;
  }




  /**
   * Retreive all Diagral Devices informations
   * @param  integer $maxTry Number of tentative to retreive informations
   */
  private function getDevicesMultizone($maxTry = 100) {
    require_once('UUID.class.php');
    $v4uuid = UUID::v4();
    $GetDeviceMultizonePost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","centralId":"'.$this->centralId.'","transmitterId":"'.$this->transmitterId.'","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$this->ttmSessionId.'","isVideoOptional":"true","isScenariosZoneOptional":"true","boxVersion":"1.3.0"}';
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
              $this->showErrors("info", "DeviceMultizone is in generation... Pending");
            } else {
              $this->showErrors("crit", "Unable to get DeviceMultizone (generation in pending) after ".$maxTry);
            }
            $occurence += 1;
          }
        } else {
          $this->showErrors("crit", "Unable to get DeviceMultizone (http code : ".$httpRespCode.")... Retrying");
          $occurence += 1;
        }
      } while ($responsePending && $occurence <= $maxTry);
    } else {
      $this->showErrors("crit", "Unable to request DeviceMultizone (http code : ".$httpRespCode.")");
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
    $DisconnectPost = '{"systemId":"'.$this->systems[$this->systemId]["id"].'","sessionId":"'.$this->sessionId.'","ttmSessionId":"'.$session.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/authenticate/disconnect", $DisconnectPost)) {
      if(isset($data["status"]) && $data["status"] == "OK") {
        if ($this->verbose) {
          $this->showErrors("info", "Disconnect completed");
        }
      } else {
        $this->showErrors("crit", "Disconnect Failed", $data);
      }
    } else {
      $this->showErrors("crit", "Unable to request Disconnect (http code : ".$httpRespCode." with message ".$data["message"].")");
    }
  }



  /**
   * Logout Session
   */
  public function logout() {
    $this->disconnect();
    $LogoutPost = '{"systemId":"null","sessionId":"'.$this->sessionId.'"}';
    if(list($data,$httpRespCode) = $this->doRequest("/authenticate/logout", $LogoutPost))  {
      if(isset($data["status"]) && $data["status"] == "OK") {
        if ($this->verbose) {
          $this->showErrors("info", "Logout completed");
        }
      } else {
        $this->showErrors("crit", "Logout Failed", $data);
      }
    } else {
      $this->showErrors("crit", "Unable to request Logout (http code : ".$httpRespCode." with message ".$data["message"].")");
    }
  }



  /**
   * Method to standardize and show errors
   * @param  string $criticity Which type of criticity. All is possible but only crit force a exit code
   * @param  string $text      Error text
   * @param  string $raw       If you need to add some content to better understant error like result array or something else
   */
  private function showErrors($criticity,$text,$raw = "") {
    echo "[".strtoupper($criticity)."] - ".$text."\n";
    if (!empty($raw)) {
		    echo "Data Content :\n\n";
		    print_r($raw);
    }
    if ($criticity == "crit") {
      exit(10);
    }
  }




  /**
   * Execute all http request to Diagral Cloud
   * @param  string  $endpoint Endpoint API Url
   * @param  string  $data     POST data in JSON format
   * @param  boolean $rawout   Define if you want to receive result in json or already parsed
   * @param  string  $method   Http method to use (GET or POST). Default is POST
   * @return array            Return a JSON content (already parsed in a array if $rawout is true)
   */
  private function doRequest($endpoint, $data, $rawout = False, $method = "POST") {
  	$curl = curl_init();
  	curl_setopt($curl, CURLOPT_URL, "https://appv3.tt-monitor.com/topaze".$endpoint);
  	curl_setopt($curl, CURLOPT_TIMEOUT,        15);
  	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
  	curl_setopt($curl, CURLOPT_CUSTOMREQUEST,  $method);
  	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  	curl_setopt($curl, CURLOPT_POSTFIELDS,     $data);
  	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  		"User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 10_2 like Mac OS X) AppleWebKit/602.3.12 (KHTML, like Gecko) Version/10.0 Mobile/14C92 Safari/602.1",
  		"Accept: application/json, text/plain, */*",
  		"Accept-Encoding: deflate",
  		"X-App-Version: 1.5.0",
  		"X-Vendor: diagral",
  		"Content-Type: application/json;charset=UTF-8",
  		"Content-Length: ".strlen($data),
  		"Connection: Close",
  	));
  	$result = curl_exec($curl);
  	$httpRespCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($this->verbose) {
      echo "**************************************\n";
      echo "Request URL : ".$method." https://appv3.tt-monitor.com/topaze".$endpoint."\n";
      if($method == "POST") {
        echo "Post Data : ".$data."\n";
      }
      echo "**************************************\n";
    	echo "HTTP Code : ".$httpRespCode."\n";
    	var_dump($result);
    }
  	curl_close($curl);
    if($httpRespCode == 0) {
      $this->showErrors("crit", "Unable to connect to Diagral Cloud. Please verify your internet connection and/or retry later.");
    }
    if($rawout == true) {
  		return array($result,$httpRespCode);
  	} else {
  		return array(json_decode($result, true),$httpRespCode);
  	}
  }

}
