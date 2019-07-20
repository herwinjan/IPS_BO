<?php

/*
 * @addtogroup BangOlufsen
 * @{
 *
 * @package       BangOlufsen
 * @file          module.php
 * @author        Herwin Jan Steehouwer <herwin@steehouwer.nu>
 * @copyright     2019 Herwin Jan Steehouwer
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 *
 */

class BangOlufsenDeviceBase extends IPSModule
{

}

/**
 * NoTrigger Klasse für die die Überwachung einer Variable auf fehlende Änderung/Aktualisierung.
 * Erweitert BangOlufsenDevice.
 *
 * @package       BangOlufsen
 * @author        Herwin Jan Steehouwer <herwin@steehouwer.nu>
 * @copyright     2019 Herwin Jan Steehouwer
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.0
 * @example <b>None</b>
 *
 * @property int $State Letzer Zustand
 * @property string $Buffer ID der überwachten Variable
 */
class BangOlufsenDevice extends BangOlufsenDeviceBase
{
    
        /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     *
     * @access public
     * @param string $name Propertyname
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        return unserialize($this->GetBuffer($name));
    }
    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     *
     * @access public
     * @param string $name Propertyname
     * @param mixed Value of Name
     */
    public function __set($name, $value)
    {
        $this->SetBuffer($name, serialize($value));
    }


    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);
        
        //You can add custom code below.
        
        
    }

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        $id = $this->__CreateVariable("Status", 3, "", "BOStatus", $this->InstanceID);
        $id = $this->__CreateVariable("Source", 3, "", "BOSource", $this->InstanceID);
        $id = $this->__CreateVariable("Status", 3, "", "BOStatus", $this->InstanceID);
        $id = $this->__CreateVariable("Volume", 1, 0, "BOVolume", $this->InstanceID);
        $id = $this->__CreateVariable("Song", 3, "", "BOSong", $this->InstanceID);
        $id = $this->__CreateVariable("Artiest", 3, "", "BOArtist", $this->InstanceID);

        $this->RegisterPropertyString('IP', '');
        $this->RegisterPropertyString('Port', '');

        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");   
           
    }
    public function GetConfigurationForParent()
    {
        $JsonArray = array('Host' => $this->ReadPropertyString('IP'), 'Port' => $this->ReadPropertyString('Port'), 'Open' => IPS_GetProperty(IPS_GetInstance($this->InstanceID)['ConnectionID'], 'Open'));
        $Json = json_encode($JsonArray);
        return $Json;
    }


   public function ApplyChanges()
    {
        $this->State=10;
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        $this->State=10;
        $this->Buffer="";
        
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
       // $this->RegisterMessage($data['ConnectionID'], IM_DISCONNECT);
       // $this->RegisterMessage($data['ConnectionID'], IM_CHANGESTATUS);

       
       parent::ApplyChanges();
        

    }

    public function RequestAction($ident, $value)
    {

    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        IPS_LogMessage("BO RECV", $Message);
        IPS_LogMessage("BO RECV", $this->test);
         
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
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        //$this->SendDebug(__FUNCTION__, "RAW: ".print_r($data->Buffer, true),0);

        if (strlen($this->Buffer)>0) 
        {
            $data->Buffer=$this->Buffer.$data->Buffer;
            $this->Buffer="";
           // $this->SendDebug(__FUNCTION__, "append buffer -> ".strlen($data->Buffer),0);
        }
        //$this->SendDebug(__FUNCTION__, "Last: ".ord(substr($data->Buffer,-1)),0);
        

        if ( ord(substr($data->Buffer,-1))==10 )
        {
           // $this->SendDebug(__FUNCTION__, "FULL: ".print_r($data->Buffer, true),0);
        }
        else
        {
            $this->Buffer=$data->Buffer;
            //$this->SendDebug(__FUNCTION__, "full buffer",0);
            return;
            
        }

        $js=explode("\n",$data->Buffer);
        foreach ( $js as $j)
        {
            if (strlen(trim($j))<=1) continue;
            //$this->SendDebug(__FUNCTION__, "FE: ".trim($j),0);
            $command = json_decode(trim(utf8_decode($j)),TRUE);   
            

            if ($j[0] == '{' )
            {
                //$this->SendDebug(__FUNCTION__, "RAWCOMMAND: ".print_r($command, true),0);
                //$this->SendDebug(__FUNCTION__, "COMMAND: ".$command["notification"]["type"],0);
                
                switch($command["notification"]["type"])
                {
                    case "SOURCE":
                        if (@count($command["notification"]["data"])>0)
                            $this->__setNewValue("BOSource",$command["notification"]["data"]["primaryExperience"]["source"]["friendlyName"]);
                        else    
                            $this->__setNewValue("BOSource","-");
                            break;
                    case "NOW_PLAYING_STORED_MUSIC":
                        if (@count($command["notification"]["data"])>0)
                        {
                            $this->__setNewValue("BOSong",$command["notification"]["data"]["name"]);
                            $this->__setNewValue("BOArtist",$command["notification"]["data"]["artist"]);
                        }
                        else
                        {
                            $this->__setNewValue("BOSong","");
                            $this->__setNewValue("BOArtist","");
                        }
                        break;
                    case "PROGRESS_INFORMATION";
                        $this->__setNewValue("BOStatus",$command["notification"]["data"]["state"]);
                        break;
                    case "VOLUME":
                        $this->__setNewValue("BOVolume",$command["notification"]["data"]["speaker"]["level"]);
                        break;

                }

            }
        }
    }   

    private function __setNewValue($name, $value)
    {
        $sid = @IPS_GetObjectIDByIdent($name, $this->InstanceID);
       // $this->SendDebug(__FUNCTION__, "SV: ".$name." -> ".$sid." -> ".$value,0);
        if ($sid) SetValue($sid, $value);
    }
    
    private function __CreateCategory($Name, $Ident = '', $ParentID = 0)
    {
        $RootCategoryID = $this->InstanceID;
        IPS_LogMessage("Visonic DEBUG", "CreateCategory: ( $Name, $Ident, $ParentID ) \n");
        if ('' != $Ident) {
            $CatID = @IPS_GetObjectIDByIdent($Ident, $ParentID);
            if (false !== $CatID) {
                $Obj = IPS_GetObject($CatID);
                if (0 == $Obj['ObjectType']) { // is category?
                    return $CatID;
                }
            }
        }
        $CatID = IPS_CreateCategory();
        IPS_SetName($CatID, $Name);
        IPS_SetIdent($CatID, $Ident);
        if (0 == $ParentID) {
            if (IPS_ObjectExists($RootCategoryID)) {
                $ParentID = $RootCategoryID;
            }
        }
        IPS_SetParent($CatID, $ParentID);
        return $CatID;
    }

    private function __CreateVariable($Name, $Type, $Value, $Ident = '', $ParentID = 0)
    {
        IPS_LogMessage("Visonic DEBUG", "CreateVariable: ( $Name, $Type, $Value, $Ident, $ParentID ) \n");
        if ('' != $Ident) {
            $VarID = @IPS_GetObjectIDByIdent($Ident, $ParentID);
            if (false !== $VarID) {
                $this->__SetVariable($VarID, $Type, $Value);
                return $VarID;
            }
        }
        $VarID = @IPS_GetObjectIDByName($Name, $ParentID);
        if (false !== $VarID) { // exists?
            $Obj = IPS_GetObject($VarID);
            if (2 == $Obj['ObjectType']) { // is variable?
                $Var = IPS_GetVariable($VarID);
                if ($Type == $Var['VariableValue']['ValueType']) {
                    $this->__SetVariable($VarID, $Type, $Value);
                    return $VarID;
                }
            }
        }
        $VarID = IPS_CreateVariable($Type);
        IPS_SetParent($VarID, $ParentID);
        IPS_SetName($VarID, $Name);
        if ('' != $Ident) {
            IPS_SetIdent($VarID, $Ident);
        }
        $this->__SetVariable($VarID, $Type, $Value);
        return $VarID;
    }

    private function __SetVariable($VarID, $Type, $Value)
    {
        switch ($Type) {
            case 0: // boolean
                SetValueBoolean($VarID, $Value);
                break;
            case 1: // integer
                SetValueInteger($VarID, $Value);
                break;
            case 2: // float
                SetValueFloat($VarID, $Value);
                break;
            case 3: // string
                SetValueString($VarID, $Value);
                break;
        }
    }
   
}
/** @} */