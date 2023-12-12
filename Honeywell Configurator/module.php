<?php
declare(strict_types=1);

class HoneywellConfigurator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{8B72A3DE-768D-39CC-BFF7-253501461CF8}');
        $this->RegisterAttributeString('Snapshot', '[]');
    }

    public function Update()
    {
        $data = $this->SendDataToParent(json_encode([
            'DataID'   => '{A40D97B8-0E11-7116-4B1A-26F20039FC65}',
            'Endpoint' => '/locations',
        ]));
        $this->SendDebug('/locations', $data, 0);

        $json = json_decode($data);
        if(isset($json->code)) {
            echo $json->message;
            return;
        }

        $this->WriteAttributeString('Snapshot', $data);
        $this->ReloadForm();
    }

    public function GetConfigurationForm()
    {
        $getInstanceID = function($deviceID) {
            $ids = IPS_GetInstanceListByModuleID('{C2E1624D-B491-3162-8345-D95FE0D6F1DA}');
            foreach($ids as $id) {
                if (IPS_GetInstance($id)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    if (IPS_GetProperty($id, "DeviceID") == $deviceID) {
                        return $id;
                    }
                }
            }
            return 0;
        };

        $instanceIDs = [];

        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $json = json_decode($this->ReadAttributeString('Snapshot'), true);
        foreach($json as $site) {
            foreach ($site["devices"] as $device) {
                $instanceID = $getInstanceID($device["deviceID"]);
                $form["actions"][1]["values"][] = [
                    "ID" => $device["deviceID"],
                    "Name" => $device["userDefinedDeviceName"],
                    "Type" => $device["deviceType"],
                    "instanceID" => $instanceID,
                    "create" => [
                        "moduleID" => "{C2E1624D-B491-3162-8345-D95FE0D6F1DA}",
                        "configuration" => [
                            "DeviceID" => $device["deviceID"]
                        ],
                        "name" => $device["userDefinedDeviceName"],
                    ]
                ];
                if ($instanceID > 0) {
                    $instanceIDs[] = $instanceID;
                }
            }
        }

        $ids = IPS_GetInstanceListByModuleID('{C2E1624D-B491-3162-8345-D95FE0D6F1DA}');
        foreach($ids as $id) {
            if (IPS_GetInstance($id)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                if (!in_array($id, $instanceIDs)) {
                    $form["actions"][1]["values"][] = [
                        "ID" => IPS_GetProperty($id, "DeviceID"),
                        "Name" => IPS_GetName($id),
                        "Type" => '',
                        "instanceID" => $id,
                    ];
                }
            }
        }

        return json_encode($form);
    }
}