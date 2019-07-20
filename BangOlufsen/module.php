<?php

if (@constant('IPS_BASE') == null) { //Nur wenn Konstanten noch nicht bekannt sind.
    define('IPS_BASE', 10000); //Base Message
    define('IPS_DATAMESSAGE', IPS_BASE + 1100); //Data Handler Message
    define('DM_CONNECT', IPS_DATAMESSAGE + 1); //On Instance Connect
    define('DM_DISCONNECT', IPS_DATAMESSAGE + 2); //On Instance Disconnect
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500); //Instance Manager Message
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5); //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6); //Settings were Changed
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600); //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1); //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2); //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3); //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4); //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5); //On Profile Action Change
}




/**
 * WebsocketClient Klasse implementiert das Websocket Protokoll als HTTP-Client
 * Erweitert IPSModule.
 *
 * @package BanfOlufsenDevice
 * @property int $Parent
 */
class BanfOlufsenDevice extends IPSModule
{
 
    public function __construct($InstanceID)
    {
        // Do not delete this row
        parent::__construct($InstanceID);

        // Self-service code
    }

    
    public function Create()
    {
        // Do not delete this row.
        parent::Create();

        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $this->ConnectParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, DM_CONNECT);
        $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);  

        
        return true;
    }

    
    public function ApplyChanges()
    {
       
    }

    public function RequestAction($ident, $value)
    {

    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        IPS_LogMessage("BO RECV", $Message); 
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        IPS_LogMessage("BO RECV", utf8_decode($data->Buffer));

    }

    

   
}
