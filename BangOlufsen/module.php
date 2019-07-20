<?php

if (@constant('IPS_BASE') == null) { //Nur wenn Konstanten noch nicht bekannt sind.
    define('IPS_BASE', 10000); //Base Message
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
    define('IM_CREATE', IPS_INSTANCEMESSAGE + 1);          //Instance created
    define('IM_DELETE', IPS_INSTANCEMESSAGE + 2);          //Instance deleted
    define('IM_CONNECT', IPS_INSTANCEMESSAGE + 3);         //Instance connectged
    define('IM_DISCONNECT', IPS_INSTANCEMESSAGE + 4);      //Instance disconncted
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
    define('IM_CHANGESEARCH', IPS_INSTANCEMESSAGE + 7);    //Searching was started/stopped
    define('IM_SEARCHUPDATE', IPS_INSTANCEMESSAGE + 8);    //Searching found new results
    define('IM_SEARCHPROGRESS', IPS_INSTANCEMESSAGE + 9);  //Searching progress in %
    define('IM_SEARCHCOMPLETE', IPS_INSTANCEMESSAGE + 10); //Searching is complete
}




/**
 * WebsocketClient Klasse implementiert das Websocket Protokoll als HTTP-Client
 * Erweitert IPSModule.
 *
 * @package BanfOlufsenDevice
 * @property int $Parent
 */
class BangOlufsenDevice extends IPSModule
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
        $this->RegisterMessage($this->InstanceID, IM_CONNECT);
        $this->RegisterMessage($this->InstanceID, IM_DISCONNECT);  

        
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
