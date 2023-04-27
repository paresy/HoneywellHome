<?php
declare(strict_types=1);

class HoneywellDevice extends IPSModule
{
    private $variables = [
        ["waterPresent", VARIABLETYPE_BOOLEAN, "~Alert"],
        ["temperature", VARIABLETYPE_FLOAT, "~Temperature"],
        ["humidity", VARIABLETYPE_FLOAT, "~Humidity.F"],
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{8B72A3DE-768D-39CC-BFF7-253501461CF8}');

        $this->RegisterPropertyString('DeviceID', '');
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $result = $data->Result;

        $json = json_decode($result, true);

        // Go through all sites and devices
        foreach($json as $site) {
            foreach ($site["devices"] as $device) {
                if ($device["deviceID"] == $this->ReadPropertyString("DeviceID")) {
                    $this->ParseData($device);
                }
            }
        }
    }

    private function ParseData($json)
    {
        $parse = function($json) {
            foreach($json as $key => $value) {
                foreach($this->variables as $variable) {
                    if ($variable[0] == $key) {
                        $this->MaintainVariable($key, $key, $variable[1], $variable[2], 0, true);
                        $this->SetValue($key, $value);
                    }
                }
            }
        };

        $this->SendDebug("Received", json_encode($json), 0);

        if (isset($json["time"])) {
            $this->RegisterVariableInteger("time", "time", "~UnixTimestamp", 0);
            $utc = new DateTimeZone('UTC');
            $dt = new DateTime($json["time"], $utc);
            $this->SetValue("time", $dt->format('U'));
        }

        $parse($json);
        if (isset($json["currentSensorReadings"])) {
            $parse($json["currentSensorReadings"]);
        }
    }
}