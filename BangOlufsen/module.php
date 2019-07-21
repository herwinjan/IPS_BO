<?php

require_once(__DIR__ . "/../libs/beoclass.php");

/**
 *
 * @package       BangOlufsen BoePlay devices
 * @author        Herwin Jan Steehouwer <herwin@steehouwer.nu>
 * @copyright     2019 Herwin Jan Steehouwer
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       0.1
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

        $this->RegisterProfileIntegerEx("Status.BO", "Information", "", "",   Array( 
            Array(0, "prev",       "", -1),
            Array(1, "play",       "", -1),
            Array(2, "pause",      "", -1),
            Array(3, "stop",       "", -1),
            Array(4, "next",       "", -1)
        ));

        $this->RegisterProfileInteger("Volume.BO",   "Intensity",   "", " %",    0, 100, 1);
        $this->RegisterProfileIntegerEx("Switch.BO", "Information", "",   "", Array( 
            Array(0, "Off", "", 0xFF0000),
            Array(1, "On",  "", 0x00FF00) )
        );
        if(!IPS_VariableProfileExists("Sources.BO"))
            IPS_CreateVariableProfile("Sources.BO", 1);

        $id = $this->__CreateVariable("Status", 1, 0, "BOStatus", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Status.BO");
        $id = $this->__CreateVariable("Source", 3, "", "BOSource", $this->InstanceID);
        $id = $this->__CreateVariable("Sources", 1, 0, "BOSources", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Sources.BO");
        $id = $this->__CreateVariable("Volume", 1, 0, "BOVolume", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "Volume.BO");
        $id = $this->__CreateVariable("Song", 3, "", "BOSong", $this->InstanceID);
        $id = $this->__CreateVariable("Artiest", 3, "", "BOArtist", $this->InstanceID);
        $id = $this->__CreateVariable("Voorgang", 3, "", "BOLoc", $this->InstanceID);
       
        $this->EnableAction("BOVolume");
        $this->EnableAction("BOStatus");

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
        $this->online=FALSE;
        $this->closeConnection();
        $this->State=10;
        parent::ApplyChanges();
      
        $this->State=10;
        $this->Buffer="";
        $this->VolumeMin=0;
        $this->VolumeMax=100;
        $this->Type=0;
        $this->jid="";
        $this->BeoName="";        

        if ((float) IPS_GetKernelVersion() < 4.2) {
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        } else {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
        }

        $data = IPS_GetInstance($this->InstanceID);
        $this->SendDebug('ID', $data['ConnectionID'], 0);
        $this->SendDebug('ID2', $this->InstanceID, 0);
        $this->RegisterMessage($data['ConnectionID'], 10505);

        if(IPS_VariableProfileExists("Sources.BO"))
            IPS_DeleteVariableProfile("Sources.BO"); 
        $this->Sources=$this->getSources();
        IPS_CreateVariableProfile("Sources.BO", 1);
        foreach ($this->Sources as $source)
        {           
            $this->SendDebug(__FUNCTION__, "Add Profile ({$source["id"]}) ".$source["name"],0);
            IPS_SetVariableProfileAssociation("Sources.BO", $source["count"], $source["name"], "", -1);

        }
        $this->EnableAction("BOSources");
        $this->__SetVariable("BOSources",1,1);

       //$this->getDevice();
       $this->getActiveSources();
       $this->getVolume();

       $this->online=TRUE;
       $this->openConnection();  
       parent::ApplyChanges();
    }

    public function RequestAction($ident, $value)
    { 
        switch ($ident) {
            case "BOVolume":
                $max=$this->VolumeMax;
                $min=$this->VolumeMin;
                $vols=round((($value*($max-$min)/100)+$min));

                $this->__sendCommand('BeoZone/Zone/Sound/Volume/Speaker/Level', json_encode(Array("level"=>$vols)), "PUT");
            break;
            case "BOStatus":
                switch($value)
                {
                    case 0: // prev
                    $this->__sendCommand("BeoZone/Zone/Stream/Backward","");
                    $this->__sendCommand("BeoZone/Zone/Stream/Backward/Release","");
                    break;
             
                    case 1: // play
                        $this->__sendCommand("BeoZone/Zone/Stream/Play","");
                        $this->__sendCommand("BeoZone/Zone/Stream/Play/Release","");
                        break;
                    case 2: // pause
                        $this->__sendCommand("BeoZone/Zone/Stream/Pause","");
                        $this->__sendCommand("BeoZone/Zone/Stream/Pause/Release","");
                        break;
                    case 3: // stop
                        $this->__sendCommand("BeoZone/Zone/Stream/Stop","");
                        $this->__sendCommand("BeoZone/Zone/Stream/Stop/Release","");
                        break;
                        case 4: // next
                        $this->__sendCommand("BeoZone/Zone/Stream/Forward","");
                        $this->__sendCommand("BeoZone/Zone/Stream/Forward/Release","");
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
        if ($Message==10505)
        {
            if ($Data[0]==102)
            {
                $sendData="GET /BeoNotify/Notifications?list=recursive+features HTTP/1.1\r\n".
                "Connection: Keep-Alive\r\n\r\n";
                $JSON['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
                $JSON['Buffer'] = utf8_encode($sendData);
                $JsonString = json_encode($JSON);
                $this->SendDataToParent($JsonString);
            }
            if ($Data[0]==200)
            {
                $this->__setNewValue("BOStatus",3);
                if ($this->online)
                    $this->restartConnection();
            }
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug(__FUNCTION__, "RAW: ".print_r($data->Buffer, true),0);

        if (strlen($this->Buffer)>0) 
        {
            $data->Buffer=$this->Buffer.$data->Buffer;
            $this->Buffer="";           
        }
       
        if ( ord(substr($data->Buffer,-1))==10 )
        {
           
        }
        else
        {
            $this->Buffer=$data->Buffer;            
            return;
            
        }

        $js=explode("\n",$data->Buffer);
        foreach ( $js as $j)
        {
            if (strlen(trim($j))<=1) continue;            
            $command = json_decode(trim(utf8_decode($j)),TRUE);               

            if ($j[0] == '{' )
            {
                $this->SendDebug(__FUNCTION__, "COMMAND: ".$command["notification"]["type"],0);
                
                switch($command["notification"]["type"])
                {
                    case "SOURCE":
                        if (@count($command["notification"]["data"])>0)
                        {
                           $this->setBeoSource($command["notification"]["data"]);
                        }
                        else    
                            $this->__setNewValue("BOSource","-");
                        break;
                    case "NOW_PLAYING_STORED_MUSIC":
                        if (@count($command["notification"]["data"])>0)
                        {
                            $this->Type=1;
                            $this->__setNewValue("BOSong",$command["notification"]["data"]["name"]);
                            $this->__setNewValue("BOArtist",$command["notification"]["data"]["artist"]);
                        }
                        else
                        {
                            $this->__setNewValue("BOSong","");
                            $this->__setNewValue("BOArtist","");
                        }
                        break;
                    case "NOW_PLAYING_NET_RADIO":
                        if (@count($command["notification"]["data"])>0)
                        {
                            $this->Type=2;
                            $this->__setNewValue("BOSong",$command["notification"]["data"]["name"]);
                            $this->__setNewValue("BOArtist",$command["notification"]["data"]["liveDescription"]);
                        }
                        else
                        {
                            $this->__setNewValue("BOSong","");
                            $this->__setNewValue("BOArtist","");
                        }
                        break;
                    case "NOW_PLAYING_ENDED":
                       
                        $this->Type=0;
                            
                        $this->__setNewValue("BOSong","");
                        $this->__setNewValue("BOArtist","");
                       
                        break;
                    case "SHUTDOWN":
                        if ($command["notification"]["data"]["reason"]=="standby")
                        {
                            $this->Type=0;
                            $this->__setNewValue("BOSong","");
                            $this->__setNewValue("BOArtist","");
                            $this->__setNewValue("BOLoc","");
                            $this->__setNewValue("BOSource","Shutdown");
                            $this->__setStatus("stop");
                        }
                        break;
                    case "NUMBER_AND_NAME":
                        $this->__setNewValue("BOSong",$command["notification"]["data"]["name"]);
                    break;
                    case "PROGRESS_INFORMATION";
                        $this->__setStatus($command["notification"]["data"]["state"]);
                        if ($this->Type==1)
                        {
                            
                            $pos=$command["notification"]["data"]["position"];
                            $tot=$command["notification"]["data"]["totalDuration"];
                            $this->__setNewValue("BOLoc",date('i:s',$pos)."/".date('i:s',$tot));
                        }
                        else
                        {
                            $this->__setNewValue("BOLoc","");
                        }
                       
                        break;
                    case "VOLUME":
                        $level=$command["notification"]["data"]["speaker"]["level"];
                        $min=$command["notification"]["data"]["speaker"]["range"]["minimum"];
                        $max=$command["notification"]["data"]["speaker"]["range"]["maximum"];
                        $this->setVolume($level,$min,$max);
                        break;

                }

            }
        }
    }   

    private function __setStatus($state)
    {
        $st=3;
        switch ($state)
        {
            case "stop":
                $st=3;
                break;
                case "pause":
                $st=2;
                break;
                case "play":
                $st=1;
                break;
        }
        $this->__setNewValue("BOStatus",$st);
    }

    public function closeConnection()
    {      
        $this->online=FALSE;  
        IPS_SetProperty($this->GetParentID(), "Open", false);
        IPS_ApplyChanges($this->GetParentID());	
    }
    public function openConnection()
    {
        $this->online=TRUE;        
        IPS_SetProperty($this->GetParentID(), "Open", true);
        IPS_ApplyChanges($this->GetParentID());	
    }
    
    public function restartConnection()
    {
        $this->closeConnection();
        IPS_Sleep(2000);
        IPS_Sleep(2000);
        $this->openConnection();        
    }
}
