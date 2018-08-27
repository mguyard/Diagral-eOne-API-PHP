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

//$MyAlarm->partialActivation(array(4));
//$MyAlarm->presenceActivation();
$MyAlarm->completeActivation();
$MyAlarm->logout();
