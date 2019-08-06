![License](https://badgen.net/github/license/mguyard/Diagral-eOne-API-PHP) ![Language](https://badgen.net/badge/Language/PHP/blue)
![Last Commit](https://badgen.net/github/last-commit/mguyard/Diagral-eOne-API-PHP)
![Open Issues](https://badgen.net/github/open-issues/mguyard/Diagral-eOne-API-PHP) ![Open Issues](https://badgen.net/github/open-prs/mguyard/Diagral-eOne-API-PHP)


# /!\ Attention : <!-- omit in toc -->

**L'utilisation de l'API Diagral dans ce code, n'est pas officielle. Elle est le résultat d'un "Reverse" sur les appels que j'ai pu voir.
Celle-ci pourrait ne plus fonctionner suite à un changement de Diagral.**

- [Commencez par cloner le repo :](#commencez-par-cloner-le-repo-)
- [Puis faites votre propre code :](#puis-faites-votre-propre-code-)
  - [Chargement des classes :](#chargement-des-classes-)
  - [Instanciation :](#instanciation-)
  - [Verbose :](#verbose-)
  - [Connexion :](#connexion-)
  - [Connaitre l'état de son alarme :](#connaitre-létat-de-son-alarme-)
  - [Evènements :](#evènements-)
  - [Activation/Désactivation de l'alarme](#activationdésactivation-de-lalarme)
  - [Recuperation/Lancement des scénarios](#recuperationlancement-des-scénarios)
  - [Gestion des erreurs](#gestion-des-erreurs)

# Comment me contacter ? <!-- omit in toc -->

[![Twitter](https://badgen.net/badge/Twitter/mguyard/cyan?icon=twitter)](https://twitter.com/mguyard)



# API PHP Alarme Diagral e-One <!-- omit in toc -->

Voici quelques lignes pour donner les exemples de démarrage de l'utilisation de l'API PHP Diagral e-One :
Une fichier [Example.php](/mguyard/Diagral-eOne-API-PHP/blob/master/Example.php) est présent dans le repos pour commencer très rapidement.


## Commencez par cloner le repo :

```
# git clone https://github.com/mguyard/Diagral-eOne-API-PHP.git
```

## Puis faites votre propre code :


### Chargement des classes :

Il faut commencer par charger les classes nécessaires
```
require_once 'class/Diagral/Diagral_eOne.class.php';
use \Mguyard\Diagral\Diagral_eOne;
```

### Instanciation :

Instanciez ensuite l'object $MyAlarm (ou le nom de votre choix) avec la classe Diagral_eOne qui prend en paramètre votre email et mot de passe Diagral.
```
$MyAlarm = new  Diagral_eOne("username@email.com","MyPassword");
```

### Verbose :

Au besoin vous pouvez activer le mode Verbose (debug) avec la commande :
```
$MyAlarm->verbose = True;
```

### Connexion :

Loggez-vous sur le cloud Diagral :
```
$MyAlarm->login();
```
*Cette commande retourne un tableau avec les informations lié à ce login tels que le nom, prénom, code postal, etc...*

Récupérez la liste des systèmes disponible. Vous pouvez avoir sur votre compte plusieures alarmes. Cette commande vous permettra de récuperer l'ensemble des systèmes disponible :
```
$MyAlarm->getSystems();
```
*Cette commande retourne un tableau avec les informations lié aux systèmes disponible sur le compte, notament, l'ID du système*

On spécifie l'ID du système que l'on veut utiliser :
```
$MyAlarm->setSystemId(0);
```

On récupère la configuration de l'alarme pour pouvoir s'y connecter :
```
$MyAlarm->getConfiguration();
```
*Cette commande retourne un tableau avec les informations lié aux systèmes sur lequel on veut se connecter*

On se connect en spécifiant son MasterCode :
```
$MyAlarm->connect("1234");
```

On se déconnecte avec :
```
$MyAlarm->logout();
```

### Connaitre l'état de son alarme :

Une fois connecté, on peut récupérer l'état de l'alarme :
```
$MyAlarm->getAlarmStatus();
```
*Cette commande, rempli les variables **systemState** et **groups** qui contiennent respectivement, l'état de l'alarme et les groupes activés.*

Pour avoir les noms des groupes et non pas seulement les ID, on peut utiliser la commande :
```
$GroupsName = $MyAlarm->getGroupsName($MyAlarm->groups);
```
*Ainsi $GroupsName est un tableau contenu les listes des groupes actif en se basant sur ce qui est présent dans $MyAlarm->groups*

```
Liste des états possibles :
* off => alarme désactivé
* group => alarme active (voir la liste des groupes actifs)
* presence => alarme en mode présence
```

### Evènements :

Utilisez cette commande pour récupérer l'ensemble des evenements :
```
$Events = $MyAlarm->getEvents()
```
*$Events est alors un tableau contenant tout les évènements suivant :
* date => Date de l'evenement
* title => Titre global de l'evenement
* details => Information plus détaillés sur l'évènement
* device => L'équipement qui à généré l'évènement
* groups => Les groupes concerné (tableau/array)
* originCode => Les codes d'origine. Diagral utilise des codes. L'API se charge donc de traduire ces codes pour une lecture humaine mais les codes d'origine sont conservés au besoin (tableau/array)

On peut définir la plage de récupération des évenements avec la commande :
```
$Events = $MyAlarm->getEvents("2018-01-01 00:00", "2018-01-01 23:11");
```
*Les formats supportés sont **YYYY-MM-DD** ou **YYYY-MM-DD HH:MM** ou **YYYY-MM-DD HH:MM:SS***
*On peut spécifier uniquement la date de début, et automatiquement la date de fin sera NOW*

### Activation/Désactivation de l'alarme

Pour activer totalement l'alarme :
```
$MyAlarm->completeActivation();
```

Pour activer partiellement l'alarme :
```
$MyAlarm->partialActivation(array(1,2));
```

Pour activer le mode MarchePresence :
```
$MyAlarm->presenceActivation();
```

Pour désactiver l'alarme :
```
$MyAlarm->completeDesactivation();
```

### Recuperation/Lancement des scénarios

Pour récuperer les scénarios :
```
$MyAlarm->getScenarios();
```

Pour récuperer les scénarios (filtré par le nom - insensible à la casse) :
```
$MyAlarm->getScenarios("Test");
```

Pour récuperer les scénarios (filtré par le nom - insensible à la casse) :
```
$MyAlarm->getScenarios(".*Test.*");
```

Pour lancer un scénarios (le paramètre correspond à l'ID du scenario) :
```
$MyAlarm->launchScenario(1);
```

Je suis pas developpeur à l'origine donc n'hésitez pas à me remonter tout :
* Problème de code
* Bug
* Demande de feature
au travers de Github

### Gestion des erreurs

Vous pouvez récupérer les erreurs API avec les try/catch

```
// Instanciation de mon objet Alarm
$MyAlarm = new  Diagral_eOne("username@email.com","MyPassword");
// Activation/Désactivation du mode verbose
$MyAlarm->verbose = True;
try {
    $MyAlarm->login(); // On peut recuperer des information par le retour de la fonction
    $MyAlarm->getSystems(); // Recupere la liste de toutes les alarmes
    $MyAlarm->setSystemId(0); // Definit l'ID de son alarme
    $MyAlarm->getConfiguration();
    $MyAlarm->connect("1234");
} catch (Exception $e) {
    echo "Exception Message : ".$e->getMessage();
    exit($e->getCode());
}
```
