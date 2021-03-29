<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/ProfileHelper.php';
require_once __DIR__ . '/../libs/ConstHelper.php';

class HoneywellDevice extends IPSModule
{
    use ProfileHelper;

    // helper properties
    private $position = 0;

    private const WATER_LEAK_DETECTOR = 'Water Leak Detector';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{8B72A3DE-768D-39CC-BFF7-253501461CF8}');

        $this->RegisterPropertyString('device_class', '');
        $this->RegisterPropertyString('device_type', '');
        $this->RegisterPropertyString('device_id', '');
        $this->RegisterPropertyString('device_internal_id', '');

        $this->RegisterAttributeFloat('humidity', 0);
        $this->RegisterAttributeBoolean('humidity_enabled', false);
        $this->RegisterAttributeFloat('temperature', 0);
        $this->RegisterAttributeBoolean('temperature_enabled', false);
        $this->RegisterAttributeBoolean('alarm', false);
        $this->RegisterAttributeBoolean('alarm_enabled', false);

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $this->ValidateConfiguration();
    }

    private function ValidateConfiguration()
    {
        $id = $this->ReadPropertyString('device_internal_id');
        if ($id == '') {
            $this->SetStatus(205);
        } elseif ($id != '') {
            $this->RegisterVariables();
            $this->SetStatus(IS_ACTIVE);
        }
    }

    private function CheckRequest()
    {
        $id = $this->ReadPropertyString('device_internal_id');
        $data = false;
        if ($id == '') {
            $this->SetStatus(205);
        } elseif ($id != '') {
            $data = $this->RequestStatus('snapshot');
        }
        return $data;
    }


    /** @noinspection PhpMissingParentCallCommonInspection */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->ApplyChanges();
                }
                break;

            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;

            default:
                break;
        }
    }

    private function RegisterVariables(): void
    {
        /*
        $reachable_ass = [
            [true, $this->Translate("Online"), "", -1],
            [false, $this->Translate("Offline"), "", -1]];
        $this->RegisterProfileAssociation('Honeywell.Reachable', 'Network', '', '', 0, 1, 0, 0, VARIABLETYPE_BOOLEAN, $reachable_ass);

        $this->SetupVariable(
            'NAME', $this->Translate('name'), '', $this->_getPosition(), VARIABLETYPE_STRING, false, false
        );
        $this->SetupVariable(
            'SERIAL', $this->Translate('serial'), '', $this->_getPosition(), VARIABLETYPE_STRING, false, false
        );
        */
        $device_type = $this->ReadPropertyString('device_type');
        $this->GetDeviceStatus();
        $this->SendDebug('Honeywell device:', 'device_type: ' . $device_type, 0);



        if ($device_type == self::WATER_LEAK_DETECTOR) {
            $this->SetupVariable(
                'temperature', $this->Translate('temperature'), '~Temperature', $this->_getPosition(), VARIABLETYPE_FLOAT, false, true
            );

            $this->SetupVariable(
                'humidity', $this->Translate('humidity'), '~Humidity.F', $this->_getPosition(), VARIABLETYPE_FLOAT, false, true
            );
        }
        // $this->WriteValues();
    }

    /** Variable anlegen / löschen
     *
     * @param $ident
     * @param $name
     * @param $profile
     * @param $position
     * @param $vartype
     * @param $visible
     *
     * @return bool|int
     */
    protected function SetupVariable($ident, $name, $profile, $position, $vartype, $enableaction, $visible = false)
    {
        $objid = false;
        if ($visible) {
            $this->SendDebug('Honeywell Variable:', 'Variable with Ident ' . $ident . ' is visible', 0);
        } else {
            if ($ident == 'NAME' || $ident == 'SERIAL') {
                $ident = strtolower($ident);
            }
            $visible = $this->ReadAttributeBoolean($ident . '_enabled');
            $this->SendDebug('Honeywell Variable:', 'Variable with Ident ' . $ident . ' is shown ' . print_r($visible, true), 0);
        }
        if ($visible == true) {
            switch ($vartype) {
                case VARIABLETYPE_BOOLEAN:
                    $objid = $this->RegisterVariableBoolean($ident, $name, $profile, $position);
                    if ($ident == 'BATTERY_STATE') {
                        $string_value = $this->ReadAttributeString($ident);
                        if ($string_value == 'OK') {
                            $value = true;
                        } else {
                            $value = false;
                        }
                    }elseif($ident == 'RF_LINK_STATE')
                    {
                        $string_value = $this->ReadAttributeString($ident);
                        if ($string_value == 'ONLINE') {
                            $value = true;
                        } else {
                            $value = false;
                        }
                    }
                    else {
                        $value = $this->ReadAttributeBoolean($ident);
                    }
                    break;
                case VARIABLETYPE_INTEGER:
                    $objid = $this->RegisterVariableInteger($ident, $name, $profile, $position);
                    $value = $this->ReadAttributeInteger($ident);
                    break;
                case VARIABLETYPE_FLOAT:
                    $objid = $this->RegisterVariableFloat($ident, $name, $profile, $position);
                    $value = $this->ReadAttributeFloat($ident);
                    break;
                case VARIABLETYPE_STRING:
                    $objid = $this->RegisterVariableString($ident, $name, $profile, $position);
                    if ($ident == 'name' || $ident == 'serial') {
                        $value = $this->ReadPropertyString($ident);
                    } else {
                        $value = $this->ReadAttributeString($ident);
                    }
                    break;
            }
            $this->SetValue($ident, $value);
            if ($enableaction) {
                $this->EnableAction($ident);
            }
        } else {
            $objid = @$this->GetIDForIdent($ident);
            if ($objid > 0) {
                $this->UnregisterVariable($ident);
            }
        }
        return $objid;
    }


    /** @noinspection PhpMissingParentCallCommonInspection */
    public function RequestAction($Ident, $Value)
    {

    }


    private function CalculateTime($time_string, $subject)
    {
        $date = new DateTime($time_string);
        $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        $timestamp = $date->getTimestamp();
        $this->SendDebug('Honeywell ' . $subject . ' Timestamp', $date->format('Y-m-d H:i:sP'), 0);
        return $timestamp;
    }

    public function GetDeviceStatus()
    {
        $snapshot = $this->RequestStatus('GetSpecificDeviceByIDData');
        if ($snapshot != '[]') {
            $this->CheckDeviceData($snapshot);
        }
    }

    private function CheckDeviceData($snapshot)
    {
        $payload = json_decode($snapshot, true);
        if (!empty($payload)) {
            $device_id = $this->ReadPropertyString('device_internal_id');
            $this->SendDebug('Honeywell device id', strval($device_id), 0);
            foreach($payload as $device)
            {
                $this->SendDebug('Honeywell device id', strval($device['deviceInternalID']), 0);
                if($device_id == $device['deviceInternalID'])
                {
                    $this->SendDebug('Honeywell data', 'data for device id ' .  $device_id, 0);
                    $currentSensorReadings = $device['currentSensorReadings'];
                    $temperature = $currentSensorReadings['temperature'];
                    $humidity = $currentSensorReadings['humidity'];
                    $this->SendDebug('Honeywell temperature', $temperature, 0);
                    $this->SetValue('temperature', $temperature);
                    $this->SendDebug('Honeywell humidity', $humidity, 0);
                    $this->SetValue('humidity', $humidity);
                }
            }
        }
    }

    private function WriteEnabledValue($ident, $vartype, $enabled = false)
    {
        if ($enabled) {
            $value_enabled = true;
        } else {
            $value_enabled = $this->ReadAttributeBoolean($ident . '_enabled');
        }

        if ($value_enabled) {
            switch ($vartype) {
                case VARIABLETYPE_BOOLEAN:
                    if ($ident == 'BATTERY_STATE' || $ident == 'RF_LINK_STATE') {
                        $string_value = $this->ReadAttributeString($ident);
                        if ($string_value == 'OK' || $string_value == 'ONLINE') {
                            $value = true;
                            $debug_value = 'true';
                        } else {
                            $value = false;
                            $debug_value = 'false';
                        }
                    }
                    else {
                        $value = $this->ReadAttributeBoolean($ident);
                    }
                    $this->SendDebug('SetValue boolean', 'ident: ' . $ident . ' value: ' . $debug_value, 0);
                    $this->SetVariableValue($ident, $value);
                    break;
                case VARIABLETYPE_INTEGER:
                    $value = $this->ReadAttributeInteger($ident);
                    $this->SendDebug('SetValue integer', 'ident: ' . $ident . ' value: ' . $value, 0);
                    $this->SetVariableValue($ident, $value);
                    break;
                case VARIABLETYPE_FLOAT:
                    $value = $this->ReadAttributeFloat($ident);
                    $this->SendDebug('SetValue float', 'ident: ' . $ident . ' value: ' . $value, 0);
                    $this->SetVariableValue($ident, $value);
                    break;
                case VARIABLETYPE_STRING:
                    $value = $this->ReadAttributeString($ident);
                    $this->SendDebug('SetValue string', 'ident: ' . $ident . ' value: ' . $value, 0);
                    $this->SetVariableValue($ident, $value);
                    break;
            }
        }
    }

    private function SetVariableValue($ident, $value)
    {
        if(@$this->GetIDForIdent($ident))
        {
            $this->SetValue($ident, $value);
        }
    }

    private function WriteValues()
    {
        $model_type_instance = $this->ReadPropertyString('device_type');


        if ($model_type_instance == self::WATER_LEAK_DETECTOR) {
            $this->SendDebug('Honeywell Write Values', self::WATER_LEAK_DETECTOR, 0);
            $this->WriteEnabledValue('BATTERY_LEVEL', VARIABLETYPE_INTEGER, true);
            $this->WriteEnabledValue('BATTERY_LEVEL_TIMESTAMP', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('BATTERY_STATE', VARIABLETYPE_BOOLEAN, true);
            $this->WriteEnabledValue('BATTERY_STATE_TIMESTAMP', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('RF_LINK_LEVEL', VARIABLETYPE_INTEGER, true);
            $this->WriteEnabledValue('RF_LINK_LEVEL_TIMESTAMP', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('RF_LINK_STATE', VARIABLETYPE_BOOLEAN);
            $this->WriteEnabledValue('soil_humidity', VARIABLETYPE_INTEGER, true);
            $this->WriteEnabledValue('soil_humidity_timestamp', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('soil_temperature', VARIABLETYPE_FLOAT, true);
            $this->WriteEnabledValue('soil_temperature_timestamp', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('ambient_temperature', VARIABLETYPE_FLOAT, true);
            $this->WriteEnabledValue('ambient_temperature_timestamp', VARIABLETYPE_INTEGER);
            $this->WriteEnabledValue('light_intensity', VARIABLETYPE_INTEGER, true);
            $this->WriteEnabledValue('light_intensity_timestamp', VARIABLETYPE_INTEGER);
        }
    }

    public function RequestStatus(string $endpoint)
    {
        $this->SendDebug('Honeywell Request', 'device type: ' . $this->ReadPropertyString('device_type'), 0);
        $this->SendDebug('Honeywell Request', 'device internal id: ' . $this->ReadPropertyString('device_internal_id'), 0);
        $data = $this->SendDataToParent(json_encode([
            'DataID' => '{205C5894-9464-99C0-0921-47647DAF0BD3}',
            'Type' => 'GET',
            'Endpoint' => $endpoint,
            'device_type' => $this->ReadPropertyString('device_type'),
            'device_id' => $this->ReadPropertyString('device_internal_id'),
            'Payload' => ''
        ]));
        $this->SendDebug('Honeywell Request Response', json_encode($data), 0);
        return $data;
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $snapshot = $data->Buffer;
        $this->SendDebug('Receive Snapshot', $snapshot, 0);
        if ($snapshot != '[]') {
            $this->CheckDeviceData($snapshot);
        }
    }

    public function SendCommand(string $service_id, string $data)
    {
        $result = $this->SendDataToParent(json_encode([
            'DataID' => '{205C5894-9464-99C0-0921-47647DAF0BD3}',
            'Type' => 'PUT',
            'Endpoint' => '/command/' . $service_id,
            'Payload' => $data
        ]));
        return $result;
    }

    public function SetWebFrontVariable(string $ident, bool $value)
    {
        $this->WriteAttributeBoolean($ident, $value);
        if ($value) {
            $this->SendDebug('Honeywell Webfront Variable', $ident . ' enabled', 0);
        } else {
            $this->SendDebug('Honeywell Webfront Variable', $ident . ' disabled', 0);
        }

        $this->RegisterVariables();
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
        return json_encode([
            'elements' => $this->FormHead(),
            'actions' => $this->FormActions(),
            'status' => $this->FormStatus()
        ]);
    }

    /**
     * return form configurations on configuration step
     * @return array
     */
    protected function FormHead()
    {
        $data = $this->CheckRequest();
        if ($data != false) {
            $form = [
                [
                    'type' => 'Label',
                    'caption' => $this->Translate('device type: ') . $this->ReadPropertyString('device_type')
                ],
                [
                    'type' => 'Label',
                    'caption' => $this->Translate('device id: ') . $this->Translate($this->ReadPropertyString('device_internal_id'))
                ]
            ];
        } else {
            $form = [
                [
                    'type' => 'Label',
                    'label' => 'This device can only created by the Honeywell configurator, please use the Honeywell configurator for creating Honeywell devices.'
                ]
            ];
        }
        return $form;
    }

    /**
     * return form actions by token
     * @return array
     */
    protected function FormActions()
    {


        $model_type = $this->ReadPropertyString('device_type');
        $form = [

        ];
        /*
        $form = [
            [
                'name' => 'name_enabled',
                'type' => 'CheckBox',
                'caption' => 'name',
                'visible' => true,
                'value' => $this->ReadAttributeBoolean('name_enabled'),
                'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "name_enabled", $name_enabled);'],
            [
                'name' => 'serial_enabled',
                'type' => 'CheckBox',
                'caption' => 'serial',
                'visible' => true,
                'value' => $this->ReadAttributeBoolean('serial_enabled'),
                'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "serial_enabled", $serial_enabled);']
        ];

        if ($model_type == self::WATER_LEAK_DETECTOR) {
            $form = array_merge_recursive(
                $form, [
                    [
                        'name' => 'VALVE_1_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 1',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_1_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_1_STATE_enabled", $VALVE_1_STATE_enabled);'],
                    [
                        'name' => 'VALVE_2_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 2',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_2_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_2_STATE_enabled", $VALVE_2_STATE_enabled);'],
                    [
                        'name' => 'VALVE_3_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 3',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_3_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_3_STATE_enabled", $VALVE_3_STATE_enabled);'],
                    [
                        'name' => 'VALVE_4_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 4',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_4_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_4_STATE_enabled", $VALVE_4_STATE_enabled);'],
                    [
                        'name' => 'VALVE_5_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 5',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_5_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_5_STATE_enabled", $VALVE_5_STATE_enabled);'],
                    [
                        'name' => 'VALVE_6_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'valve 6',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('VALVE_6_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "VALVE_6_STATE_enabled", $VALVE_6_STATE_enabled);'],
                    [
                        'name' => 'RF_LINK_STATE_enabled',
                        'type' => 'CheckBox',
                        'caption' => 'rf link state',
                        'visible' => true,
                        'value' => $this->ReadAttributeBoolean('RF_LINK_STATE_enabled'),
                        'onChange' => 'HONEYWELL_SetWebFrontVariable($id, "RF_LINK_STATE_enabled", $RF_LINK_STATE_enabled);']]
            );
        }
        */
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
                'caption' => 'Honeywell device created.'
            ],
            [
                'code' => IS_INACTIVE,
                'icon' => 'inactive',
                'caption' => 'interface closed.'
            ],
            [
                'code' => 205,
                'icon' => 'error',
                'caption' => 'This device can only created by the Honeywell configurator, please use the Honeywell configurator for creating Honeywell devices.'
            ]
        ];

        return $form;
    }
}
