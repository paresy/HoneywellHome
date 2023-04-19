# IPSymconHoneywellHome
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/38222-IP-Symcon-5-0-verf%C3%BCgbar)

Modul für IP-Symcon ab Version 5. Ermöglicht die Kommunikation mit Honeywell Geräten.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)  
5. [Anhang](#5-anhang)  

## 1. Funktionsumfang

Steuerung von Honeywell Geräten über die Honeywell Home API.

## 2. Voraussetzungen

 - IP-Symcon 6.0+
 - Honeywell Benutzername and Honeywell für Honeywell Home
 - IP-Symcon Connect

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://{IP-Symcon IP}:3777/console/_ öffnen. 


Anschließend oben rechts auf das Symbol für den Modulstore klicken

![Store](img/store_icon.png?raw=true "open store")

Im Suchfeld nun

```
Honeywell Home
```  

eingeben

![Store](img/module_store_search.png?raw=true "module search")

und schließend das Modul auswählen und auf _Installieren_

![Store](img/install.png?raw=true "install")

drücken.

### b. Honeywell Home
Es wird ein Account bei Honeywell home benötigt, den man auch in der Honeywell Home App nutzt.

Um Zugriff auf die Honeywell Geräte über die Honeywell API zu erhalten muss zunächst IP-Symcon als System authentifiziert werden.
Hierzu wird ein aktives IP-Symcon Connect benötigt und den normalen Honeywell Benutzernamen und Passwort.
Zunächst wird beim installieren des Modul gefragt ob eine Konfigurator Instanz angelegt werden soll, dies beantwortet man mit _ja_, man kann aber auch die Konfigurator Instanz von Hand selber anlegen

### c. Authentifizierung bei Honeywell
Anschließend erscheint ein Fenster Schnittstelle konfigurieren, hier drückt man auf den Knopf _Registrieren_ und hält seinen Honeywell (Husqvarna) Benutzernamen und Passwort bereit.

![Schnittstelle](img/register.png?raw=true "Schnittstelle")

Es öffnet sich die Anmeldeseite von Honeywell. Hier gibt man in die Maske den  Benutzernamen und das Honeywell Passwort an und fährt mit einem Klick auf _Anmelden_ fort.

![Anmeldung](img/oauth_1.png?raw=true "Anmeldung")

Jetzt wird man von Honeywell gefragt ob IP-Symcon als System die persönlichen Geräte auslesen darf, die Honeywell Geräte steuern sowie den Status der Geräte auslesen darf.
HIer muss man nun mit _Ja_ bestätigen um IP-Symcon zu erlauben das Honeywell Smart Gateway zu steuern und damit auch die Honeywell Geräte steuern zu können.

![Genehmigung](img/oauth_2.png?raw=true "Genehmigung")

Es erscheint dann eine Bestätigung durch IP-Symcon das die Authentifizierung erfolgreich war,
 
![Success](img/oauth_3.png?raw=true "Success")
 
anschließend kann das Browser Fenster geschlossen werden und man kehrt zu IP-Symcon zurück.
Zurück beim Fenster Schnittstelle konfigurieren geht man nun auf _Weiter_

Nun öffnen wir die Konfigurator Instanz im Objekt Baum zu finden unter _Konfigurator Instanzen_. 

### d. Einrichtung des Konfigurator-Moduls

Jetzt wechseln wir im Objektbaum in die Instanz _**Honeywell**_ (Typ Honeywell Configurator) zu finden unter _Konfigurator Instanzen_.



Hier werden alle Geräte, die bei Honeywell unter dem Account registiert sind und von der Honeywell API unterstützt werden aufgeführt.

Ein einzelnes Gerät kann man durch markieren auf das Gerät und ein Druck auf den Button _Erstellen_ erzeugen. Der Konfigurator legt dann eine Geräte Instanz an.

### e. Einrichtung der Geräteinstanz
Eine manuelle Einrichtung eines Gerätemoduls ist nicht erforderlich, das erfolgt über den Konfigurator. In dem Geräte-Modul ist gegebenenfalls nur das Abfrage-Intervall anzupassen, die anderen Felder, insbesondere die Seriennummer (diese ist die Identifikation des Gerätes) und die Geräte-Typ-ID (diese steuert, welche Variablen angelegt werden) sind vom Konfigurator vorgegeben.