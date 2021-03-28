<?php
declare(strict_types=1);

class HoneywellCloud extends IPSModule
{
    private const SMART_SYSTEM_BASE_URL = 'https://oauth.symcon.cloud/proxy/honeywell/v2';

    // Honeywell smart system API
    private const DEVICES = '/devices';
    private const SCHEDULE = '/devices/schedule/'; // GET	Get all devices for a location
    private const THERMOSTATS = '/devices/thermostats/'; // Get Schedule / Set Schedule
    private const CHANGE_THERMOSTAT_SETTINGS = '/devices/thermostats/'; // Get Thermostat / Change Thermostat Settings
    private const FAN = '/fan'; // Change Thermostat Settings
    private const GROUP = '/group/'; // Get current fan settings / Change fan setting for device
    private const ROOMS = '/rooms';
    private const PRIORITY = '/priority'; // 	Get rooms in a group
    private const LOCATIONS = '/locations'; //  Get Room Priority / Set Room Priority

    private $oauthIdentifer = 'honeywell';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger("UpdateInterval", 15);
        $this->RegisterTimer("Update", 0, "HONEYWELL_Update(" . $this->InstanceID . ");");
        $this->RegisterAttributeString('Token', '');
        $this->RegisterAttributeString('locations', '[]');
        $this->RegisterAttributeString('location_id', '');
        $this->RegisterAttributeString('location_name', '');
        $this->RegisterAttributeString('snapshot', '[]');
        $this->RegisterPropertyInteger("ImportCategoryID", 0);
        $this->RegisterAttributeString('websocket_url', '');
        $this->RegisterAttributeBoolean('alternative_url', false);
        $this->RegisterAttributeBoolean('limit', false);

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

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

    /** @noinspection PhpMissingParentCallCommonInspection */

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->RegisterOAuth($this->oauthIdentifer);
        $Honeywell_interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetHoneywellInterval($Honeywell_interval);

        if ($this->ReadAttributeString('Token') == '') {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }
    }

    private function RegisterOAuth($WebOAuth)
    {
        $ids = IPS_GetInstanceListByModuleID('{F99BF07D-CECA-438B-A497-E4B55F139D37}');
        if (count($ids) > 0) {
            $clientIDs = json_decode(IPS_GetProperty($ids[0], 'ClientIDs'), true);
            $found = false;
            foreach ($clientIDs as $index => $clientID) {
                if ($clientID['ClientID'] == $WebOAuth) {
                    if ($clientID['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $clientIDs[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $clientIDs[] = ['ClientID' => $WebOAuth, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'ClientIDs', json_encode($clientIDs));
            IPS_ApplyChanges($ids[0]);
        }
    }

    private function SetHoneywellInterval($Honeywell_interval): void
    {
        if ($Honeywell_interval < 15 && $Honeywell_interval != 0) {
            $Honeywell_interval = 15;
        }
        $interval = $Honeywell_interval * 1000 * 60; // minutes
        $this->SetTimerInterval('Update', $interval);
    }

    public function Update()
    {

    }

    public function GetToken()
    {
        $token = $this->FetchAccessToken();
        return $token;
    }

    private function FetchAccessToken($Token = '', $Expires = 0)
    {

        //Exchange our Refresh Token for a temporary Access Token
        if ($Token == '' && $Expires == 0) {

            //Check if we already have a valid Token in cache
            $data = $this->GetBuffer('AccessToken');
            if ($data != '') {
                $data = json_decode($data);
                if (time() < $data->Expires) {
                    $this->SendDebug('FetchAccessToken', 'OK! Access Token is valid until ' . date('d.m.y H:i:s', $data->Expires), 0);
                    return $data->Token;
                }
            }

            $this->SendDebug('FetchAccessToken', 'Use Refresh Token to get new Access Token!', 0);
            $options = [
                'http' => [
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query(['refresh_token' => $this->ReadAttributeString('Token')])]];
            $context = stream_context_create($options);
            $result = file_get_contents('https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer, false, $context);

            $data = json_decode($result);
            $this->SendDebug('Symcon Connect Data', $result, 0);
            if (!isset($data->token_type) || $data->token_type != 'Bearer') {
                die('Bearer Token expected');
            }

            //Update parameters to properly cache it in the next step
            $Token = $data->access_token;
            $Expires = time() + $data->expires_in;

            //Update Refresh Token if we received one! (This is optional)
            if (isset($data->refresh_token)) {
                $this->SendDebug('FetchAccessToken', "NEW! Let's save the updated Refresh Token permanently", 0);

                $this->WriteAttributeString('Token', $data->refresh_token);
            }
        }

        $this->SendDebug('FetchAccessToken', 'CACHE! New Access Token is valid until ' . date('d.m.y H:i:s', $Expires), 0);

        //Save current Token
        $this->SetBuffer('AccessToken', json_encode(['Token' => $Token, 'Expires' => $Expires]));

        //Return current Token
        return $Token;
    }

    /**
     * This function will be called by the register button on the property page!
     */
    public function Register()
    {

        //Return everything which will open the browser
        return 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
    }

    public function GetLocationsBuffer()
    {
        return $this->ReadAttributeString('locations');
    }

    public function GetConfiguration()
    {

    }

    public function ForwardData($data)
    {
        $data = json_decode($data);

        if (strlen($data->Payload) > 0) {
            $type = $data->Type;
            if ($type == 'PUT') {
                $this->SendDebug('ForwardData', $data->Endpoint . ', Payload: ' . $data->Payload, 0);
                $response = $this->PutData($data->Endpoint, $data->Payload);
            } elseif ($type == 'POST') {
                $this->SendDebug('ForwardData', $data->Endpoint . ', Payload: ' . $data->Payload, 0);
                $response = $this->PostData($data->Endpoint, $data->Payload);
            }
        } else {
            $this->SendDebug('ForwardData', $data->Endpoint, 0);
            if ($data->Endpoint == 'location_id') {
                $response = $this->ReadAttributeString('location_id');
            } elseif ($data->Endpoint == 'snapshot') {
                $response = $this->RequestSnapshot();
            } elseif ($data->Endpoint == 'GetSpecificDeviceByIDData') {
                $device_type = $data->device_type;
                $deviceid = $data->device_id;
                $response = $this->GetSpecificDeviceByIDData($device_type, $deviceid);
            } elseif ($data->Endpoint == 'snapshotbuffer') {
                $response = $this->RequestSnapshotBuffer();
            } elseif ($data->Endpoint == 'Get_All_Locations') {
                $response = $this->Get_All_Locations();
            } elseif ($data->Endpoint == 'token') {
                $response = $this->CheckToken();
            }
        }
        return $response;
    }

    private function PutData($url, $content)
    {
        $this->SendDebug("AT", $this->FetchAccessToken(), 0);
        $opts = array(
            "http" => array(
                "method" => "PUT",
                "header" => "Authorization: Bearer " . $this->FetchAccessToken() . "\r\n" . 'Content-Length: ' . strlen($content) . "\r\n",
                'content' => $content,
                "ignore_errors" => true
            )
        );
        $context = stream_context_create($opts);
        $url = $this->GetURL($url);
        $result = file_get_contents($url, false, $context);
        $http_error = $http_response_header[0];
        $result = $this->GetErrorMessage($http_error, $result);
        return $result;
    }

    private function GetURL($url)
    {
        return self::SMART_SYSTEM_BASE_URL . $url;
    }

    private function GetErrorMessage($http_error, $result)
    {
        $response = $result;
        if ((strpos($http_error, '200') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Success. Response Body: ' . $result, 0);
        } elseif ((strpos($http_error, '201') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Success. CreatedResponse Body: ' . $result, 0);
        } elseif ((strpos($http_error, '401') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Failure, user could not be authenticated. Authorization-Provider or X-Api-Key header or Beaerer Token missing or invalid. Response Body: ' . $result, 0);
            $response = false;
        } elseif ((strpos($http_error, '404') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Failure, location not found. Response Body: ' . $result, 0);
            $response = false;
        } elseif ((strpos($http_error, '500') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Failure, internal error. Response Body: ' . $result, 0);
            $response = false;
        } elseif ((strpos($http_error, '502') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Failure, backend error. Response Body: ' . $result, 0);
            $response = false;
        } elseif ((strpos($http_error, '415') > 0)) {
            $this->SendDebug('HTTP Response Header', 'Unsupported Media Type. Response Body: ' . $result, 0);
            $response = false;
        } else {
            $this->SendDebug('HTTP Response Header', $http_error . ' Response Body: ' . $result, 0);
            $response = false;
        }

        if ($result == '{"message":"Limit Exceeded"}') {
            $this->SendDebug('Honeywell API', 'Limit Exceeded', 0);
        }
        return $response;
    }

    // Honeywell API

    private function PostData($url, $content)
    {
        $this->SendDebug("AT", $this->FetchAccessToken(), 0);
        $opts = array(
            "http" => array(
                "method" => "POST",
                "header" => "Authorization: Bearer " . $this->FetchAccessToken() . "\r\n" . 'Content-Length: ' . strlen($content) . "\r\n",
                'content' => $content,
                "ignore_errors" => true
            )
        );
        $context = stream_context_create($opts);
        $url = $this->GetURL($url);
        $result = file_get_contents($url, false, $context);
        $http_error = $http_response_header[0];
        $result = $this->GetErrorMessage($http_error, $result);
        return $result;
    }

    /** Get Devices for Snapshot
     * @return bool|false|string
     */
    public function RequestSnapshot()
    {
        $location_id = $this->ReadAttributeString('location_id');
        if ($location_id != '') {
            $snapshot = $this->GetAllDevices();
        } else {
            $snapshot = '[]';
        }
        if ($snapshot === false) {
            $this->SendDebug('Honeywell Snapshot', 'Could not get snapshot', 0);
            $snapshot = '[]';
        } else {
            $this->SendDebug('Honeywell Snapshot', $snapshot, 0);
            $this->WriteAttributeString('snapshot', $snapshot);
        }
        return $snapshot;
    }

    /** Get all devices for a location
     * @return array
     */
    public function GetAllDevices()
    {
        $location_id = $this->ReadAttributeString('location_id');
        $this->SendDebug('Honeywell Location ID', $location_id, 0);
        $devices = [];
        if ($location_id != '') {
            $devices = $this->FetchData(self::DEVICES . '?locationId=' . $location_id);
        }
        return $devices;
    }

    private function FetchData($url)
    {
        $this->SendDebug("AT", $this->FetchAccessToken(), 0);
        $opts = array(
            "http" => array(
                "method" => "GET",
                "header" => "Authorization: Bearer " . $this->FetchAccessToken(),
                "ignore_errors" => true
            )
        );

        $context = stream_context_create($opts);
        $url = $this->GetURL($url);
        $result = file_get_contents($url, false, $context);
        $http_error = $http_response_header[0];
        $result = $this->GetErrorMessage($http_error, $result);
        return $result;
    }

    /**  Get a Specific Device by ID
     * @return bool
     */
    private function GetSpecificDeviceByIDData($device_type, $deviceid)
    {
        if ($device_type == 'Water Leak Detector') {
            $device_type = 'waterLeakDetectors';
        }
        if ($device_type == 'thermostats') {
            $device_type = 'thermostats';
        }
        if ($device_type == 'cameras') {
            $device_type = 'cameras';
        }

        $location_id = $this->ReadAttributeString('location_id');
        $this->SendDebug('Honeywell Location ID', $location_id, 0);
        $devices = [];
        if ($location_id != '') {
            $devices = $this->FetchData(self::DEVICES . $device_type . '/' . $deviceid . '?apikey=' . '{APIKEY}' . '&locationId=' . $location_id);
        }
        return $devices;
    }

    public function RequestSnapshotBuffer()
    {
        //$this->WriteAttributeString('snapshot', '[]');

        $snapshot = $this->ReadAttributeString('snapshot');
        $this->SendDebug('Honeywell Snapshot Buffer', $snapshot, 0);
        if ($snapshot == '[]') {
            $snapshot = $this->RequestSnapshot();
            $this->SendDebug('Honeywell Request Snapshot', $snapshot, 0);
        }
        return $snapshot;
    }

    /**  Get all Locations
     * @return bool
     */
    public function Get_All_Locations()
    {
        $locations = $this->FetchData(self::LOCATIONS);
        $locations = json_decode($locations, true);
        if ($locations === false) {
            $this->SendDebug('Honeywell Locations', 'Could not get locations', 0);
        } else {
            $this->SendDebug('Honeywell Locations', json_encode($locations), 0);
            $this->WriteAttributeString('locations', json_encode($locations));

            $location_id = $locations[0]['locationID'];
            $this->SendDebug('Honeywell Location ID', $location_id, 0);
            $this->WriteAttributeString('location_id', $location_id);
        }
        return $locations;
    }

    public function CheckToken()
    {
        $token = $this->ReadAttributeString('Token');
        return $token;
    }

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
        $visibility_register = false;
        //Check Honeywell connection
        if ($this->ReadAttributeString('Token') == '') {
            $visibility_register = true;
        }

        $form = [
            [
                'type' => 'Label',
                'visible' => $visibility_register,
                'caption' => 'Honeywell: Please register with your Honeywell account!'
            ],
            [
                'type' => 'Button',
                'visible' => true,
                'caption' => 'Register',
                'onClick' => 'echo HONEYWELL_Register($id);'
            ],
            [
                'type' => 'Label',
                'visible' => true,
                'label' => 'Update interval in minutes (minimum 15 minutes):'
            ],
            [
                'name' => 'UpdateInterval',
                'visible' => true,
                'type' => 'IntervalBox',
                'caption' => 'minutes'
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
        $location_id = $this->ReadAttributeString('location_id');
        $location_name = $this->ReadAttributeString('location_name');
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
                'caption' => $this->Translate('Honeywell Location: ') . $location_name
            ],
            [
                'type' => 'Label',
                'visible' => true,
                'caption' => 'Read Honeywell configuration:'
            ],
            [
                'type' => 'Button',
                'visible' => true,
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

    /**
     * This function will be called by the OAuth control. Visibility should be protected!
     */
    protected function ProcessOAuthData()
    {

        // <REDIRECT_URI>?code=<AUTHORIZATION_CODE>&state=<STATE>
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if (!isset($_GET['code'])) {
                die('Authorization Code expected');
            }

            $token = $this->FetchRefreshToken($_GET['code']);

            $this->SendDebug('ProcessOAuthData', "OK! Let's save the Refresh Token permanently", 0);

            $this->WriteAttributeString('Token', $token);

            //This will enforce a reload of the property page. change this in the future, when we have more dynamic forms
            IPS_ApplyChanges($this->InstanceID);
        } else {

            //Just print raw post data!
            $payload = file_get_contents('php://input');
            $this->SendDebug('OAuth Response', $payload, 0);
        }
    }

    /** Exchange our Authentication Code for a permanent Refresh Token and a temporary Access Token
     * @param $code
     *
     * @return mixed
     */
    private function FetchRefreshToken($code)
    {
        $this->SendDebug('FetchRefreshToken', 'Use Authentication Code to get our precious Refresh Token!', 0);
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query(['code' => $code])]];
        $context = stream_context_create($options);
        $result = file_get_contents('https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer, false, $context);

        $data = json_decode($result);
        $this->SendDebug('Symcon Connect Data', $result, 0);
        if (!isset($data->token_type) || $data->token_type != 'Bearer') {
            die('Bearer Token expected');
        }

        //Save temporary access token
        $this->FetchAccessToken($data->access_token, time() + $data->expires_in);

        //Return RefreshToken
        return $data->refresh_token;
    }

    /**  Set Schedule
     * @return bool
     */
    private function SetScheduleData($deviceid)
    {
        $postfields = '';
        $response = $this->PostData(self::SCHEDULE . $deviceid, $postfields);
        return $response;
    }

    /**  Get Schedule
     * @return bool
     */
    private function GetScheduleData($deviceid)
    {
        $devices = $this->FetchData(self::SCHEDULE . $deviceid);
        return $devices;
    }

    /**  Change Thermostat Settings
     * @return bool
     */
    private function ChangeThermostatSettingsData($deviceid)
    {
        $postfields = '';
        $response = $this->PostData(self::THERMOSTATS . $deviceid, $postfields);
        return $response;
    }

    /**  Get Thermostat
     * @return bool
     */
    private function GetThermostatData($deviceid)
    {
        $devices = $this->FetchData(self::THERMOSTATS . $deviceid);
        return $devices;
    }

    /** Change fan setting
     * @return bool
     */
    private function ChangeFanSettingData($deviceid)
    {
        $postfields = '';
        $response = $this->PostData(self::THERMOSTATS . $deviceid . self::FAN, $postfields);
        return $response;
    }

    /**  Get current fan settings
     * @return bool
     */
    private function GetCurrentFanSettingsData($deviceid)
    {
        $devices = $this->FetchData(self::THERMOSTATS . $deviceid . self::FAN);
        return $devices;
    }

    /**  Get rooms in a group
     * @return bool
     */
    private function GetRoomsData($deviceid, $groupid)
    {
        $devices = $this->FetchData(self::THERMOSTATS . $deviceid . self::GROUP . $groupid . self::ROOMS);
        return $devices;
    }


    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /** Set Room Priority
     * @return bool
     */
    private function SetRoomPriorityData($deviceid)
    {
        $postfields = '';
        $response = $this->PostData(self::THERMOSTATS . $deviceid . self::PRIORITY, $postfields);
        return $response;
    }

    /**  Get Room Priority
     * @return bool
     */
    private function GetRoomPriorityData($deviceid)
    {
        $devices = $this->FetchData(self::THERMOSTATS . $deviceid . self::PRIORITY);
        return $devices;
    }

    /**  Get all Devices by Type
     * @return bool
     */
    private function GetAllDevicesByTypeData($device_type)
    {
        $devices = $this->FetchData(self::DEVICES . $device_type);
        return $devices;
    }

    /** List Locations
     * @return bool|false|string
     */
    private function RequestLocations()
    {
        $location_id = false;
        $state_location = $this->Get_All_Locations();
        if ($state_location === false) {
            $this->SendDebug('Honeywell Locations', 'Could not get location', 0);
        } else {
            $this->SendDebug('Honeywell Locations', strval($state_location), 0);
            $location_data = json_decode($state_location, true);
            $location_id = $location_data[0]['locationID'];
            $location_name = $location_data[0]['name'];
            $this->SendDebug('Honeywell Location Name', $location_name, 0);
            $this->WriteAttributeString('location_name', $location_name);
            $this->SendDebug('Honeywell Location ID', $location_id, 0);
            $this->WriteAttributeString('location_id', $location_id);
        }
        return $location_id;
    }
}