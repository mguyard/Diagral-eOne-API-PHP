<?php

/*
 * This file is part of the Diagral-eOne-API-PHP distribution (https://github.com/mguyard/Diagral-eOne-API-PHP).
 * Copyright (c) 2018 Marc GUYARD (https://github.com/mguyard).
 * Version : 1.0
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

// Chargement des classes
require_once 'class/Diagral/Diagral_eOne.class.php';
use \Mguyard\Diagral\Diagral_eOne;

// Instanciation de mon objet Alarm
$MyAlarm = new  Diagral_eOne("username@email.com","MyPassword");
// Activation/Désactivation du mode verbose
$MyAlarm->verbose = False;
$MyAlarm->login(); // On peut recuperer des information par le retour de la fonction
$MyAlarm->getSystems(); // Recupere la liste de toutes les alarmes
$MyAlarm->setSystemId(0); // Definit l'ID de son alarme
$MyAlarm->getConfiguration();
$MyAlarm->connect("1234");

// Si nous n'avons pas d'information sur l'état de l'alarme (session existante), on demande les informations
if(empty($MyAlarm->systemState)) {
  $MyAlarm->getAlarmStatus();
}

// Recupération des groupes actif de l'alarme et affichage de l'état de l'alarme
$GroupsName = $MyAlarm->getGroupsName($MyAlarm->groups);
echo "Alarme en mode :".$MyAlarm->systemState."\n";
echo "Groupes :".implode(",",$GroupsName)."\n";

// Récupération des events de l'alarme
$MyAlarm->setEventsRetry(100);
$Events = $MyAlarm->getEvents();
//$Events = $MyAlarm->getEvents("2018-01-01 00:00", "2018-01-01 23:11");

// Activation de l'alarme
//$MyAlarm->partialActivation(array(4));
//$MyAlarm->presenceActivation();
//$MyAlarm->completeActivation();
$MyAlarm->completeDesactivation();
$MyAlarm->logout();

// Debug de l'ensemble des paramètres qui sont récuperé de l'alarme
//var_dump($MyAlarm);
