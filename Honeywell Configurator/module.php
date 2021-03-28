<?php
declare(strict_types=1);

class HoneywellConfigurator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{8B72A3DE-768D-39CC-BFF7-253501461CF8}');
        $this->RegisterPropertyInteger("ImportCategoryID", 0);
        $this->RegisterAttributeString('devices_snapshot', '[]');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $import_category = $this->ReadPropertyInteger('ImportCategoryID');
        if($import_category == 0)
        {
            $this->SetStatus(202);
        }
        $token = $this->GetHoneywellToken();
        if ($token == '') {
            $this->SendDebug('Honeywell Token', $token, 0);
            $this->SendDebug('Honeywell Token', 'Instance set inactive', 0);
            $this->SetStatus(IS_INACTIVE);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function GetHoneywellToken()
    {
        $token = $this->RequestDataFromParent('token');
        return $token;
    }

    /** Get Snapshot
     * @return bool|false|string
     */
    public function RequestSnapshot()
    {
        $location_id = $this->RequestDataFromParent('location_id');
        if ($location_id != '') {
            $snapshot = $this->RequestDataFromParent('snapshotbuffer');
        } else {
            $snapshot = '[]';
        }
        $this->SendDebug('Honeywell Request Response', $snapshot, 0);
        $this->WriteAttributeString('devices_snapshot', $snapshot);
        return $snapshot;
    }

    /** Get Snapshot
     * @return bool|false|string
     */
    public function RequestSnapshotBuffer()
    {
        //$this->WriteAttributeString('devices_snapshot', '[]');
        return $this->ReadAttributeString('devices_snapshot');
    }

    /** Get Locations
     * @return bool|false|string
     */
    public function RequestLocations()
    {
        $location_id = $this->RequestDataFromParent('request_location_id');
        return $location_id;
    }

    public function GetConfiguration()
    {
        $location_id = $this->RequestDataFromParent('location_id');
        if ($location_id != '') {
            $snapshot = $this->RequestSnapshot();
        } else {
            $locations = $this->RequestLocations();
            if(!$locations === false)
            {
                $snapshot = $this->RequestSnapshot();
            }
        }
        return $snapshot;
    }

    public function RequestDataFromParent(string $endpoint)
    {
        $data = $this->SendDataToParent(json_encode([
            'DataID'   => '{A40D97B8-0E11-7116-4B1A-26F20039FC65}',
            'Type' => 'GET',
            'Endpoint' => $endpoint,
            'Payload'  => ''
        ]));
        $this->SendDebug('Honeywell Request Response', $endpoint . ": " . $data, 0);
        return $data;
    }

    /**
     * Liefert alle Geräte.
     *
     * @return array configlist all devices
     */
    private function Get_ListConfiguration()
    {
        $config_list = [];
        $location_id = $this->RequestDataFromParent('location_id');
        if ($location_id != '') {
            $HoneywellInstanceIDList = IPS_GetInstanceListByModuleID('{C2E1624D-B491-3162-8345-D95FE0D6F1DA}'); // Honeywell Devices
            $snapshot = $this->RequestSnapshotBuffer(); // Get Snapshot
            $this->SendDebug('Honeywell Config', $snapshot, 0);
            if(strpos($snapshot, '"') == 0)
            {
                $snapshot = json_decode($snapshot, true);
            }
            $payload = json_decode($snapshot, true);
            $counter = count($payload);
            if ($counter > 0) {
                foreach ($payload as $device) {
                    $device_class = $device['deviceClass'];
                    $device_type = $device['deviceType'];
                    $device_id = $device['deviceID'];
                    $device_internal_id = $device['deviceInternalID'];
                    $device_name = $device['userDefinedDeviceName'];

                    $instanceID = 0;
                    if ($device_type == 'Water Leak Detector') {
                        foreach ($HoneywellInstanceIDList as $HoneywellInstanceID) {
                            if (IPS_GetProperty($HoneywellInstanceID, 'device_internal_id') == $device_internal_id) { // todo  InstanceInterface is not available
                                $instanceID = $HoneywellInstanceID;
                            }
                        }
                        $config_list[] = ["instanceID" => $instanceID,
                            "name" => $device_name,
                            "device_type" => $this->Translate($device_type),
                            "device_class" => $this->Translate($device_class),
                            "create" => [
                                [
                                    "moduleID" => "{C2E1624D-B491-3162-8345-D95FE0D6F1DA}",
                                    "configuration" => [
                                        "device_class" => $device_class,
                                        "device_type" => $device_type,
                                        "device_id" => $device_id,
                                        "device_internal_id" => $device_internal_id,
                                    ],
                                    "location" => $this->SetLocation()
                                ]
                            ]
                        ];
                    }
                }
            }
        }
        return $config_list;
    }

    private function SetLocation()
    {
        $category = $this->ReadPropertyInteger("ImportCategoryID");
        $tree_position[] = IPS_GetName($category);
        $parent = IPS_GetObject($category)['ParentID'];
        $tree_position[] = IPS_GetName($parent);
        do {
            $parent = IPS_GetObject($parent)['ParentID'];
            $tree_position[] = IPS_GetName($parent);
        } while ($parent > 0);
        // delete last key
        end($tree_position);
        $lastkey = key($tree_position);
        unset($tree_position[$lastkey]);
        // reverse array
        $tree_position = array_reverse($tree_position);
        return $tree_position;
    }

    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form
     * @return string
     */
    public function GetConfigurationForm()
    {
        // return current form
        $Form = json_encode([
            'elements' => $this->FormHead(),
            'actions' => $this->FormActions(),
            'status' => $this->FormStatus()
        ]);
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return $Form;
    }

    /**
     * return form configurations on configuration step
     * @return array
     */
    protected function FormHead()
    {
        $location_id = $this->RequestDataFromParent('location_id');
        if ($location_id == '') {
            $show_config = false;
        } else {
            $show_config = true;
        }
        $visibility_register = false;
        //Check Honeywell connection
        $token = $this->GetHoneywellToken();
        if ($token == '') {
            $this->SendDebug('Token', $token, 0);
            $visibility_register = true;
        }

        $form = [
            [
                'type' => 'Label',
                'visible' => $visibility_register,
                'caption' => 'Honeywell: Please switch to the I/O instance and register with your Honeywell account!'
            ],
            [
                'type' => 'Label',
                'caption' => 'category for Honeywell devices'
            ],
            [
                'name' => 'ImportCategoryID',
                'type' => 'SelectCategory',
                'caption' => 'category Honeywell devices'
            ],
            [
                'name' => 'HoneywellConfiguration',
                'type' => 'Configurator',
                'visible' => $show_config,
                'rowCount' => 20,
                'add' => false,
                'delete' => true,
                'sort' => [
                    'column' => 'name',
                    'direction' => 'ascending'
                ],
                'columns' => [
                    [
                        'caption' => 'ID',
                        'name' => 'id',
                        'width' => '200px',
                        'visible' => false
                    ],
                    [
                        'name' => 'name',
                        'caption' => 'name',
                        'width' => 'auto'
                    ],
                    [
                        'name' => 'device_type',
                        'caption' => 'device type',
                        'width' => '250px'
                    ],
                    [
                        'name' => 'device_class',
                        'caption' => 'device class',
                        'width' => '250px'
                    ]
                ],
                'values' => $this->Get_ListConfiguration()
            ]
        ];
        return $form;
    }

    /**
     * return form actions by token
     * @return array
     */
    protected function FormActions()
    {
        //Check Connect availability
        $ids = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}');
        if (IPS_GetInstance($ids[0])['InstanceStatus'] != IS_ACTIVE) {
            $visibility_label1 = true;
            $visibility_label2 = false;
        } else {
            $visibility_label1 = false;
            $visibility_label2 = true;
        }

        $location_id = $this->RequestDataFromParent('location_id');
        if ($location_id != '') {
            $visibility_config = true;
        } else {
            $visibility_config = false;
        }
        $form = [
            [
                'type' => 'Label',
                'visible' => $visibility_label1,
                'caption' => 'Error: Symcon Connect is not active!'
            ],
            [
                'type' => 'Label',
                'visible' => $visibility_label2,
                'caption' => 'Status: Symcon Connect is OK!'
            ],
            [
                'type' => 'Label',
                'visible' => $visibility_config,
                'caption' => 'Read Honeywell configuration:'
            ],
            [
                'type' => 'Button',
                'visible' => $visibility_config,
                'caption' => 'Read configuration',
                'onClick' => 'HONEYWELL_GetConfiguration($id);'
            ]
        ];
        return $form;
    }

    /**
     * return from status
     * @return array
     */
    protected function FormStatus()
    {
        $form = [
            [
                'code' => IS_CREATING,
                'icon' => 'inactive',
                'caption' => 'Creating instance.'
            ],
            [
                'code' => IS_ACTIVE,
                'icon' => 'active',
                'caption' => 'configuration valid.'
            ],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => 'interface closed.'
            ],
            [
                'code' => 201,
                'icon' => 'inactive',
                'caption' => 'Please follow the instructions.'
            ],
            [
                'code' => 202,
                'icon' => 'error',
                'caption' => 'no category selected.'
            ]
        ];

        return $form;
    }
}