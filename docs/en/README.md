# IPSymconHoneywellHome
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/37412-IP-Symcon-5-0-%28Testing%29)

Module for IP-Symcon from version 5. Allows communication with Honeywell devices.

## Documentation

**Table of Contents**

1. [Features](#1-features)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Function reference](#4-functionreference)
5. [Configuration](#5-configuration)
6. [Annex](#6-annex)

## 1. Features

Control Honeywell devices via Honeywell Home API. 
	  
## 2. Requirements

 - IP-Symcon 6.0+
 - Honeywell Home Account
 - IP-Symcon Connect

## 3. Installation

### a. Loading the module

Open the IP Console's web console with _http://{IP-Symcon IP}:3777/console/_.

Then click on the module store icon in the upper right corner.

![Store](img/store_icon.png?raw=true "open store")

In the search field type

```
Honeywell Home
```  


![Store](img/module_store_search_en.png?raw=true "module search")

Then select the module and click _Install_

![Store](img/install_en.png?raw=true "install")

### b. Honeywell Home
An account with Honeywell Home is required, which is used for the Honeywell home App.

To get access to the Honeywell devices via the Honeywell API, IP-Symcon must first be authenticated as a system.
This requires an active IP-Symcon Connect and the normal Honeywell user name and password.
First, when installing the module, you are asked whether you want to create a configurator instance, you answer this with _yes_, but you can also create the configurator instance yourself

### c. Authentication to Honeywell
Then a Configure Interface window appears, here you press the _Register_ button and have your Honeywell (Husqvarna) user name and password ready.

![Interface](img/register.png?raw=true "interface")

Honeywell's login page opens. Here you enter the Honeywell user name and the Honeywell password in the mask and continue by clicking on _Login_.

![Login](img/oauth_1.png?raw=true "Login")

Honeywell now asks if IP-Symcon as a system can read out personal devices, control Honeywell devices and read out the status of the devices.
Here you have to confirm with _Yes_ to allow IP-Symcon to control the Honeywell Smart Gateway and thus also to control the Honeywell devices.

![Terms](img/oauth_2.png?raw=true "Terms")

A confirmation by IP-Symcon appears that the authentication was successful,
 
![Success](img/oauth_3.png?raw=true "Success")

then the browser window can be closed and you return to IP-Symcon.
Back at the Configure Interface window, go to _Next_

Now we open the configurator instance in the object tree under _configurator instances_.


### d. Setup of the configurator module

Now we switch to the instance _**Honeywell**_ (type Honeywell Configurator) in the object tree under _Configurator Instances_.



All devices that are registered with Honeywell under the account and supported by the Honeywell API are listed here.

A single device can be created by marking the device and pressing the _Create_ button. The configurator then creates a device instance.

### e. Device instance setup
A manual setup of a device module is not necessary, this is done via the configurator. If necessary, only the query interval has to be adjusted in the device module; the other fields, in particular the serial number (this is the identification of the device) and the device type ID (which controls which variables are created) are specified by the configurator.
