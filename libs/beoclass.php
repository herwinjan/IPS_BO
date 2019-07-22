<?php

/** 
 * @addtogroup BangOlufsen
 * @{
 *
 * @package       BangOlufsen BeoPlay devices
 * @file          module.php
 * @author        Herwin Jan Steehouwer <herwin@steehouwer.nu>
 * @copyright     2019 Herwin Jan Steehouwer
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       0.1
 *
 */

class BangOlufsenDeviceBase extends IPSModule
{



    public function setBeoSource($command)
    {
        $this->SendDebug(__FUNCTION__,"Command Source ".print_r($command,TRUE),0);
        $source="";
        $link="";         
        
        if (@$command["activeSources"]["primary"]=="")
        {
            $this->BeoOnline=FALSE;
            $this->__setNewValue("BOPower",FALSE);
            $this->SendDebug(__FUNCTION__,"Source Off!");
            return;
        }

        if (@$command["primaryExperience"]["source"]["friendlyName"])
            $source=$command["primaryExperience"]["source"]["friendlyName"];
        if (@$command["primaryExperience"]["source"]["product"]["friendlyName"])
            $link=$command["primaryExperience"]["source"]["product"]["friendlyName"];

        
        if ($this->jid==@$command["primaryExperience"]["source"]["product"]["jid"])
        {
            $this->__setNewValue("BOSource",$source);
        }
        else
            $this->__setNewValue("BOSource",$link." -> ".$source);
        $this->BeoOnline=TRUE;
        $this->__setNewValue("BOPower",True);

    }

    public function getDevice()
    {       
        $body=$this->__sendCommand("BeoDevice","","GET");
        $js=explode("\n",$body);
        foreach ( $js as $j)
        {
            if (@$j[0]=='{')
            {
                $source="";
                $link="";
                $command = json_decode(trim(utf8_decode($j)),TRUE);   
                if (@$command["beoDevice"]["productId"]["productFriendlyName"]["productFriendlyName"])
                    $this->BeoName=$command["primaryExperience"]["source"]["friendlyName"];
                if (@$command["beoDevice"]["productId"]["itemNumber"])
                {
                    //2702.1200268.25611490@products.bang-olufsen.com
                    $this->jid=$command["beoDevice"]["productId"]["typeNumber"].".".
                    $command["beoDevice"]["productId"]["itemNumber"].".".
                    $command["beoDevice"]["productId"]["serialNumber"]."@products.bang-olufsen.com";

                    IPS_LogMessage("B&O Device", $this->jid);
                }
                $this->__setNewValue("BOSource",$link." -> ".$source);
            }
        }      
    }

    public function getActiveSources()
    {
        $body=$this->__sendCommand("BeoZone/Zone/ActiveSources/","","GET");
        $js=explode("\n",$body);
        foreach ( $js as $j)
        {
            if (@$j[0]=='{')
            {                
                $command = json_decode(trim(utf8_decode($j)),TRUE);   
                $this->setBeoSource($command);
            }
        }        
    }

    public function getSources()
    {
        $SourcesReturn=Array();
        $body=$this->__sendCommand("BeoZone/Zone/Sources/","","GET");
        $js=explode("\n",$body);
        foreach ( $js as $j)
        {
            if ($j[0]=='{')
            {
                $this->getDevice();
                
                $command = json_decode(trim(utf8_decode($j)),TRUE);  
                $count=0;
                foreach ($command["sources"] as $source)
                {
                    $source=$source[1];
                    $id=$source["id"];
                    $friendlyName=$source["friendlyName"];
                    $pfn=$source["product"]["friendlyName"];
                    $pjid=$source["product"]["jid"];
                    if ($pjid!=$this->jid)
                        $friendlyName=$pfn."  ".$friendlyName;
                    $add= Array("count"=>$count, "id"=>$id, "name"=>$friendlyName, "jid"=>$pjid);
                    array_push($SourcesReturn,
                        $add
                    );
                    //$this->SendDebug(__FUNCTION__,"Sources ".$friendlyName."->".$id,0);
                    $count++;
                }
            }
        }
        $this->SendDebug(__FUNCTION__,"Sources List ".print_r($SourcesReturn,TRUE),0);
        return $SourcesReturn;
    }

    public function getVolume()
    {
        $body=$this->__sendCommand("BeoZone/Zone/Sound/Volume","","GET");
        $js=explode("\n",$body);
        foreach ( $js as $j)
        {
            if ($j[0]=='{')
            {
                $max=0;
                $min=0;
                $level=0;
                $command = json_decode(trim(utf8_decode($j)),TRUE);   
                if (@$command["volume"]["speaker"]["range"]["maximum"])
                    $max=$command["volume"]["speaker"]["range"]["maximum"];
                if (@$command["volume"]["speaker"]["range"]["minimum"])
                    $min=$command["volume"]["speaker"]["range"]["minimum"];
                if (@$command["volume"]["speaker"]["level"])
                    $level=$command["volume"]["speaker"]["level"];
                $this->setVolume($level, $min, $max);                
            }
        }        
    }
    public function setVolume($level, $min, $max)
    {
        $this->VolumeMin=$min;
        $this->VolumeMax=$max;
                               
        $new=round(($level - $min) * 100) / ($max - $min);
                                 
        $this->__setNewValue("BOVolume",$new);
    }

    

    /**     
     * @access public
     * @param string $name Propertyname
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        return unserialize($this->GetBuffer($name));
    }
    /**     
     * @access public
     * @param string $name Propertyname
     * @param mixed Value of Name
     */
    public function __set($name, $value)
    {
        $this->SetBuffer($name, serialize($value));
    }

    protected function __sendCommand($url, $data,$type="POST")
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, "http://".$this->ReadPropertyString('IP').":".$this->ReadPropertyInteger('Port')."/". $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.A.B.C Safari/525.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));

        if ($type=="POST") curl_setopt($ch, CURLOPT_POST, true);
        if ($type=="PUT") { 

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        }
        
        $file_content = curl_exec($ch);
        $this->SendDebug(__FUNCTION__,"CURL URL: http://".$this->ReadPropertyString('IP').":".$this->ReadPropertyInteger('Port')."/". $url,0);
        $this->SendDebug(__FUNCTION__,"CURL return: ".$file_content,0);
        if($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            $this->SendDebug(__FUNCTION__,"CURL error: ({$errno}):\n {$error_message}",0);
        }
        curl_close($ch);
        return $file_content;
    }

    protected function __setNewValue($name, $value)
    {
        $sid = @IPS_GetObjectIDByIdent($name, $this->InstanceID);
       // $this->SendDebug(__FUNCTION__, "SV: ".$name." -> ".$sid." -> ".$value,0);
        if ($sid) SetValue($sid, $value);
    }
    
    protected function __CreateCategory($Name, $Ident = '', $ParentID = 0)
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

    protected function __CreateVariable($Name, $Type, $Value, $Ident = '', $ParentID = 0)
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

    protected function __SetVariable($VarID, $Type, $Value)
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
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 1)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
        
    }
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
        
    }
    protected function RegisterTimerNow($Ident, $Milliseconds, $Action) {
        //search for already available scripts with proper ident
        $eid = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        //properly update eventID
        if($eid === false) {
                $eid = 0;
        } else if(IPS_GetEvent($eid)['EventType'] <> 1) {
                IPS_DeleteEvent($eid);
                $eid = 0;
        }
        //we need to create one
        if ($eid == 0) {
                $eid = IPS_CreateEvent(1);
                IPS_SetParent($eid, $this->InstanceID);
                IPS_SetIdent($eid, $Ident);
                IPS_SetName($eid, $Ident);
                IPS_SetHidden($eid, true);
                IPS_SetEventScript($eid, $Action);
        } else {
                if(IPS_GetEvent($eid)['EventScript'] != $Action) {
                        IPS_SetEventScript($eid, $Action);
                }
        }
        if($Milliseconds > 0) {
                $now = time();
                $Hour = date("H",$now);
                $Minute = date("i",$now);
                $Second = date("s",$now);                    
                IPS_SetEventCyclicTimeFrom($eid, $Hour, $Minute, $Second);
                IPS_SetEventCyclic($eid, 0, 0, 0, 0, 1, round($Milliseconds/1000));
                IPS_SetEventActive($eid, true);
        } else {
                IPS_SetEventActive($eid, false);
        }
}

}
/** @} */
