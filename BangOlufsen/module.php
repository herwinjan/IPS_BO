<?php

require_once __DIR__ . "/../libs/beoclass.php";

/**
 *
 * @package       BangOlufsen BoePlay devices
 * @author        Herwin Jan Steehouwer <herwin@steehouwer.nu>
 * @copyright     2019 Herwin Jan Steehouwer
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @release       0.1
 * @example <b>None</b>
 *
 * @property int $State Letzer Zustand
 * @property string $Buffer ID der überwachten Variable
 */
class BangOlufsenDevice extends BangOlufsenDeviceBase
{
    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $this->_RegisterProfileIntegerEx("Status.BEO", "Information", "", "", array(
            array(0, "prev", "", -1),
            array(1, "play", "", -1),
            array(2, "pause", "", -1),
            array(3, "stop", "", -1),
            array(4, "next", "", -1),
        ));

        $this->_RegisterProfileInteger("Volume.BEO", "Intensity", "", " %", 0, 100, 1);
        $this->_RegisterProfileIntegerEx("Switch.BEO", "Information", "", "", array(
            array(0, "Off", "", 0xFF0000),
            array(1, "On", "", 0x00FF00))
        );
        if (!IPS_VariableProfileExists("Sources.BEO")) {
            IPS_CreateVariableProfile("Sources.BEO", 1);
        }

        $id = $this->_CreateVariable("Status", 1, 0, "BEOStatus", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Status.BEO");
        $id = $this->_CreateVariable("Source", 3, "", "BEOSource", $this->InstanceID);
        $id = $this->_CreateVariable("Sources", 1, 0, "BEOSources", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Sources.BEO");
        $id = $this->_CreateVariable("Volume", 1, 0, "BEOVolume", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Volume.BEO");
        $id = $this->_CreateVariable("Song", 3, "", "BEOSong", $this->InstanceID);
        $id = $this->_CreateVariable("Artiest", 3, "", "BEOArtist", $this->InstanceID);
        $id = $this->_CreateVariable("Voorgang", 3, "", "BEOLoc", $this->InstanceID);
        $id = $this->_CreateVariable("Power", 0, false, "BEOPower", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "~Switch");
        $id = $this->_CreateVariable("Mute", 0, false, "BEOMuted", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "~Switch");

        $this->EnableAction("BEOVolume");
        $this->EnableAction("BEOStatus");
        $this->EnableAction("BEOPower");
        $this->EnableAction("BEOMuted");

        $this->RegisterPropertyString('IP', '');
        $this->RegisterPropertyInteger('Port', 8080);

        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");

    }

    public function GetConfigurationForParent()
    {
        $JsonArray = array('Host' => $this->ReadPropertyString('IP'), 'Port' => $this->ReadPropertyInteger('Port'));
        $Json = json_encode($JsonArray);
        return $Json;
    }

    public function ApplyChanges()
    {
        $this->online = false;
        $this->_closeConnection();
        $this->State = 10;
        parent::ApplyChanges();

        $this->State = 10;
        $this->Buffer = "";
        $this->VolumeMin = 0;
        $this->VolumeMax = 100;
        $this->Type = 0;
        $this->jid = "";
        $this->BeoName = "";
        $this->BeoOnline = false;
        $this->BeoMuted = false;

        if ((float)IPS_GetKernelVersion() < 4.2) {
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        } else {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
        }

        $data = IPS_GetInstance($this->InstanceID);
        //$this->SendDebug('ID', $data['ConnectionID'], 0);
        //$this->SendDebug('ID2', $this->InstanceID, 0);
        $this->RegisterMessage($data['ConnectionID'], 10505);

        if (strlen($this->ReadPropertyString('IP')) > 0) {
            if (IPS_VariableProfileExists("Sources.BEO")) {
                IPS_DeleteVariableProfile("Sources.BEO");
            }

            $this->Sources = $this->_getSources();
            IPS_CreateVariableProfile("Sources.BEO", 1);
            foreach ($this->Sources as $source) {
                $this->SendDebug(__FUNCTION__, "Add Profile ({$source["id"]}) " . $source["name"], 0);
                IPS_SetVariableProfileAssociation("Sources.BEO", $source["count"], $source["name"], "", -1);
            }

            $this->EnableAction("BEOSources");
            //$this->__SetVariable("BEOSources",1,1);

            //$this->getDevice();
            $this->_getActiveSources();
            $this->_getVolume();
            parent::ApplyChanges();

            $this->online = true;
            $this->_openConnection();
        }

        $this->RegisterTimerNow('Ping', 5000, 'BEO_KeepAlive(' . $this->InstanceID . ');');

    }

    public function KeepAlive()
    {
        if (IPS_GetProperty($this->__GetParentID(), "Open")) {
            $sendData = "2\r\n\r\n\r\n";
            $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
            $JSON['Buffer'] = utf8_encode($sendData);
            $JsonString = json_encode($JSON);
            $this->SendDataToParent($JsonString);
            $this->SendDebug(__FUNCTION__, "Send KeepAlive", 0);
        } else {
            $this->_openConnection();
            $this->SendDebug(__FUNCTION__, "Session closed?? reopen!", 0);
        }
    }

    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case "BEOVolume":
                $max = $this->VolumeMax;
                $min = $this->VolumeMin;
                $vols = round((($value * ($max - $min) / 100) + $min));

                $this->_sendCommand('BeoZone/Zone/Sound/Volume/Speaker/Level', json_encode(array("level" => $vols)), "PUT");
                break;
            case "BEOMuted":
                $this->_sendCommand('BeoZone/Zone/Sound/Volume/Speaker/Muted', json_encode(array("muted" => $value)), "PUT");
                $this->BeoMuted = $value;
                $this->__setNewValue("BEOMuted", $value);
                break;
            case "BEOPower":
                if ($value == false) {
                    $str = array("standby" => array("powerState" => "standby"));

                    $this->_sendCommand("BeoDevice/powerManagement/standby", json_encode($str), "PUT");
                    $this->BeoOnline = false;
                    $this->_setNewValue("BEOPower", false);
                } else {
                    $this->_sendCommand("BeoZone/Zone/Stream/Play", array(), "POST");
                    $this->_setNewValue("BEOPower", true);
                    $this->BeoOnline = true;
                }
                break;
            case "BEOSources":
                $this->SendDebug(__FUNCTION__, "Select Source: " . $value, 0);

                foreach ($this->Sources as $c) {
                    if ($c["count"] == $value) {
                        $this->_sendCommand("BeoZone/Zone/ActiveSources", json_encode(array("primaryExperience" => array("source" => array("id" => $c["id"])))), "POST");
                        $this->SendDebug(__FUNCTION__, "Select Source: " . $c["name"] . " => " . json_encode(array("primaryExperience" => array("source" => array("id" => $c["id"])))), 0);

                        //self._postReq('POST','', {"primaryExperience":{"source":{"id":chosenSource}}})
                    }
                }

                //BeoZone/Zone/ActiveSources
                break;
            case "BEOStatus":
                switch ($value) {
                    case 0: // prev
                        $this->_sendCommand("BeoZone/Zone/Stream/Backward", "");
                        $this->_sendCommand("BeoZone/Zone/Stream/Backward/Release", "");
                        break;

                    case 1: // play
                        $this->_sendCommand("BeoZone/Zone/Stream/Play", "");
                        $this->_sendCommand("BeoZone/Zone/Stream/Play/Release", "");
                        break;
                    case 2: // pause
                        $this->_sendCommand("BeoZone/Zone/Stream/Pause", "");
                        $this->_sendCommand("BeoZone/Zone/Stream/Pause/Release", "");
                        break;
                    case 3: // stop
                        $this->_sendCommand("BeoZone/Zone/Stream/Stop", "");
                        $this->_sendCommand("BeoZone/Zone/Stream/Stop/Release", "");
                        break;
                    case 4: // next
                        $this->_sendCommand("BeoZone/Zone/Stream/Forward", "");
                        $this->_sendCommand("BeoZone/Zone/Stream/Forward/Release", "");
                        break;
                }
                break;
            default:
                throw new Exception("Invalid ID");
        }

    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, "TS: $TimeStamp SenderID " . $SenderID . ' with MessageID ' . $Message . ' Data: ' . print_r($Data, true), 0);
        if ($Message == 10505) {
            if ($Data[0] == 102) {
                $sendData = "GET /BeoNotify/Notifications?list=recursive+features HTTP/1.1\r\n" .
                    "Connection: Keep-Alive\r\n\r\n";
                $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
                $JSON['Buffer'] = utf8_encode($sendData);
                $JsonString = json_encode($JSON);
                $this->SendDataToParent($JsonString);
            }
            if ($Data[0] == 200) {
                $this->_setNewValue("BEOStatus", 3);
                if ($this->online) {
                    $this->restartConnection();
                }

            }
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug(__FUNCTION__, "RAW: " . print_r($data->Buffer, true), 0);

        if (strlen($this->Buffer) > 0) {
            $data->Buffer = $this->Buffer . $data->Buffer;
            $this->Buffer = "";
        }

        if (ord(substr($data->Buffer, -1)) == 10) {

        } else {
            $this->Buffer = $data->Buffer;
            return;

        }

        $js = explode("\n", $data->Buffer);
        foreach ($js as $j) {
            if (strlen(trim($j)) <= 1) {
                continue;
            }

            $command = json_decode(trim(utf8_decode($j)), true);

            if ($j[0] == '{') {
                $this->SendDebug(__FUNCTION__, "COMMAND: " . $command["notification"]["type"], 0);

                switch ($command["notification"]["type"]) {
                    case "SOURCE":
                        $this->_setBeoSource($command["notification"]["data"]);

                        break;
                    case "NOW_PLAYING_STORED_MUSIC":
                        if (@count($command["notification"]["data"]) > 0) {
                            $this->Type = 1;
                            $this->_setNewValue("BEOSong", $command["notification"]["data"]["name"]);
                            $this->_setNewValue("BEOArtist", $command["notification"]["data"]["artist"]);
                        } else {
                            $this->_setNewValue("BEOSong", "");
                            $this->_setNewValue("BEOArtist", "");
                        }
                        break;
                    case "NOW_PLAYING_NET_RADIO":
                        if (@count($command["notification"]["data"]) > 0) {
                            $this->Type = 2;
                            $this->_setNewValue("BEOSong", $command["notification"]["data"]["name"]);
                            $this->_setNewValue("BEOArtist", $command["notification"]["data"]["liveDescription"]);
                        } else {
                            $this->_setNewValue("BEOSong", "");
                            $this->_setNewValue("BEOArtist", "");
                        }
                        break;
                    case "NOW_PLAYING_ENDED":

                        $this->Type = 0;

                        $this->_setNewValue("BEOSong", "");
                        $this->_setNewValue("BEOArtist", "");

                        break;
                    case "SHUTDOWN":
                        if ($command["notification"]["data"]["reason"] == "standby") {
                            $this->Type = 0;
                            $this->_setNewValue("BEOSong", "");
                            $this->_setNewValue("BEOArtist", "");
                            $this->_setNewValue("BEOLoc", "");
                            $this->_setNewValue("BEOSource", "Shutdown");
                            $this->_setStatus("stop");
                            $this->_setNewValue("BEOPower", false);
                        }
                        break;
                    case "NUMBER_AND_NAME":
                        $this->_setNewValue("BEOSong", $command["notification"]["data"]["name"]);
                        break;
                    case "PROGRESS_INFORMATION";
                        $this->_setStatus($command["notification"]["data"]["state"]);
                        if ($this->Type == 1) {
                            $pos = 0;
                            $tot = 0;
                            if (@$command["notification"]["data"]["position"]) {
                                $pos = $command["notification"]["data"]["position"];
                            }

                            if (@$command["notification"]["data"]["totalDuration"]) {
                                $tot = $command["notification"]["data"]["totalDuration"];
                            }

                            if ($tot > 0) {
                                $this->_setNewValue("BEOLoc", date('i:s', $pos) . "/" . date('i:s', $tot));
                            }

                        } else {
                            $this->_setNewValue("BEOLoc", "");
                        }

                        break;
                    case "VOLUME":
                        $level = $command["notification"]["data"]["speaker"]["level"];
                        $min = $command["notification"]["data"]["speaker"]["range"]["minimum"];
                        $max = $command["notification"]["data"]["speaker"]["range"]["maximum"];
                        $mute = $command["notification"]["data"]["speaker"]["muted"];
                        $this->_setVolume($level, $min, $max, $mute);
                        break;

                }

            }
        }
    }

    protected function _setStatus($state)
    {
        $st = 3;
        switch ($state) {
            case "stop":
                $st = 3;
                break;
            case "pause":
                $st = 2;
                break;
            case "play":
                $st = 1;
                break;
        }
        $this->_setNewValue("BEOStatus", $st);
    }

    protected function _closeConnection()
    {
        if (strlen($this->ReadPropertyString('IP')) > 0) {
            $this->online = false;
            IPS_SetProperty($this->__GetParentID(), "Open", false);
            IPS_ApplyChanges($this->__GetParentID());
        }
    }
    protected function _openConnection()
    {
        if (strlen($this->ReadPropertyString('IP')) > 0) {
            $this->online = true;
            IPS_SetProperty($this->__GetParentID(), "Open", true);
            IPS_ApplyChanges($this->__GetParentID());

        }
    }

    protected function _restartConnection()
    {
        $this->_closeConnection();
        IPS_Sleep(2000);
        IPS_Sleep(2000);
        $this->_openConnection();
    }

    private function __GetParentID()
    {
        $ParentID = (IPS_GetInstance($this->InstanceID)['ConnectionID']);
        return $ParentID;
    }
}
