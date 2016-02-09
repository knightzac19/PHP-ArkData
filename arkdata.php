<?php

use xPaw\SourceQuery\SourceQuery;

defined('BASEPATH') or exit('No direct script access allowed');
define('SQ_SERVER_ADDR', '192.168.1.109');
    define('SQ_SERVER_PORT', 27015);
    define('SQ_TIMEOUT',     30);
    define('SQ_ENGINE',      SourceQuery::SOURCE);
class Arkdata
{
    /// <summary>
       /// A list of all players registered on the server.
       /// </summary>
       public $Players;
       /// <summary>
       /// A list of all tribes registered on the server.
       /// </summary>
       public $Tribes;
       /// <summary>
       /// Indicates whether the steam user data has been loaded.
       /// </summary>
       private $SteamLoaded;
       private $DataLoaded;
    private $Timer;
    private $installLocation;
    private $playerFiles;
    private $tribeFiles;

    public function __construct($options = array())
    {
        if (count($options) > 0) {
            $installLocation = $options['installLocation'];
        }
        if (!isset($installLocation)) {
            die('Invalid Settings!');
        }
        $this->Players = array();
        $this->Tribes = array();
        $Rules = array();
        $playerFiles = glob("$installLocation/*.arkprofile");
        $tribeFiles = glob("$installLocation/*.arktribe");
        if (!$playerFiles && !$tribeFiles) {
            die('Invalid directory detected, no profiles or tribes found!');
        }
        $this->SteamLoaded = false;
        $this->DataLoaded = false;
        $Timer = MicroTime(true);
        foreach ($playerFiles as $key => $value) {
            $player = new Player();
            $parse = (object) $player->ParsePlayer($value);
            if (!$parse->Id) {
                continue;
            }
            $this->Players[(string) $parse->Id] = $parse;
        }
        foreach ($tribeFiles as $key => $value) {
            $tribe = new Tribe();
            $parse = $tribe->ParseTribe($value);
            if (!$parse->Id) {
                continue;
            }
            $this->Tribes[$parse->Id] = $parse;
        }

        $this->LinkPlayerTribe();
        $Timer = Number_Format(MicroTime(true) - $Timer, 4, '.', '');
        // $tribe = new Tribe();
        // $tribe->ParseTribe($installLocation."/1596232110.arktribe");
    }

    public function getArkData()
    {
        if($this->DataLoaded)
        {
            return array($this->Players,$this->Tribes);
        }
        else {
            die("Data did not load correctly!");
        }

    }

    public function LoadSteam($apiKey)
    {
        $steamSearchPlayers = $this->Players;
        $chunks = array();
        $builder = '';
        $c = 0;
        while(list($key,$value) = each($steamSearchPlayers))
        {
            $builder .= $value->SteamId;
            if($c == 100)
            {
                $chunks[] = $builder;
                $builder = "";
                $c = 0;
                reset($steamSearchPlayers);
            }
            else {
                $c++;
                unset($steamSearchPlayers[$key]);
            }
            if(end($steamSearchPlayers) != $value)
            {
                $builder .= ",";
            }
            elseif(end($steamSearchPlayers) == $value)
            {
                $chunks[] = $builder;
                $builder = "";
                // $c = 0;
            }
        }
        $presponses = array();
        $bresponses = array();
        foreach ($chunks as $key => $value) {
            if(empty($value))
            {
                continue;
            }
            $curl = curl_init();
            $baseUrl = 'https://api.steampowered.com/';
            // var_dump($baseUrl."ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$builder");
            // exit;
            curl_setopt_array($curl, array(
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_URL => $baseUrl."ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$value",
                        CURLOPT_SSL_VERIFYPEER => false,
                    ));
            $profiles = curl_exec($curl);
            $presponses = array_merge($presponses,json_decode($profiles)->response->players);
            if (!$profiles) {
                die('Error: "'.curl_error($curl).'" - Code: '.curl_errno($curl));
            }
            curl_close($curl);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                        CURLOPT_URL => $baseUrl."ISteamUser/GetPlayerBans/v1/?key=$apiKey&steamids=$value",
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ));
            $bans = curl_exec($curl);
            $bresponses = array_merge($bresponses,json_decode($bans)->players);
            if (!$bans) {
                die('Error: "'.curl_error($curl).'" - Code: '.curl_errno($curl));
            }
            curl_close($curl);
        }

        $this->LinkSteamProfiles($presponses);
        $this->LinkSteamBans($bresponses);
        $this->SteamLoaded = true;
    }
    public function LoadOnlinePlayers()
    {
        if ($this->SteamLoaded) {
            $this->LinkOnlinePlayers();
        } else {
            die('The Steam user data should be loaded before the server status can be checked.');
        }
    }

    private function LinkOnlinePlayers()
    {

        // $Timer = MicroTime(true);
        //    $online = new SSQL().Players(new IPEndPoint(IPAddress.Parse(ipString), port))).ToList();
           $Query = new SourceQuery();
        try {
            $Query->Connect(SQ_SERVER_ADDR, SQ_SERVER_PORT, SQ_TIMEOUT, SQ_ENGINE);

        //$Query->SetUseOldGetChallengeMethod( true ); // Use this when players/rules retrieval fails on games like Starbound

        // $Info = $Query->GetInfo();
            $queryPlayers = $Query->GetPlayers();
            // $Rules = $Query->GetRules();
            // $Timer = Number_Format( MicroTime( true ) - $Timer, 4, '.', '' );
            foreach ($queryPlayers as $key => $var) {
                $var = (object) $var;
                if (empty($var->Name)) {
                    continue;
                }

                foreach ($this->Players as $key => $value) {
                    if($value->SteamName == $var->Name)
                    {

                        $this->Players[$value->Id]->Online = true;
                    }
                }
            }
        } catch (Exception $e) {
            $Exception = $e;
            die($Exception->getMessage());
        } finally {
            $Query->Disconnect();
        }
    }
    private function LinkPlayerTribe()
    {
        $searchPlayers = $this->Players;
        // var_dump(count($searchPlayers));
        foreach ($this->Tribes as $k => $v) {
            foreach ($searchPlayers as $key => $value) {
                if ($value->Id == $v->OwnerId) {
                    $this->Players[$value->Id]->OwnedTribe = $v->Id;
                    $this->Players[$value->Id]->Tribe = $this->Tribes[$v->Id];
                    $this->Tribes[$v->Id]->Owner = $this->Players[$value->Id];
                    $this->Tribes[$v->Id]->Players[$value->Id] = $value;
                    unset($searchPlayers[$key]);
                    // var_dump($value->CharacterName);
                    // var_dump(count($searchPlayers));
                    // var_dump($this->Players[$value->Id]);
                } elseif ($value->TribeId == $v->Id) {
                    $this->Players[$value->Id]->Tribe = $this->Tribes[$v->Id];
                    $this->Tribes[$v->Id]->Players[$value->Id] = $value;
                    unset($searchPlayers[$key]);
                }
            }
        }
        $this->DataLoaded = true;
    }

    private function LinkSteamProfiles($jsonString)
    {
        $profiles = $jsonString;

        foreach ($profiles as $key => $value) {
            foreach ($this->Players as $k => $v) {
                if ($v->SteamId == $value->steamid) {
                    $this->Players[$v->Id]->SteamName = utf8_encode($value->personaname);
                    $this->Players[$v->Id]->ProfileUrl = utf8_encode($value->profileurl);
                    $this->Players[$v->Id]->AvatarUrl = utf8_encode($value->avatar);
                    break;
                }
            }
        }
        // echo 'Profiles Link Complete'.PHP_EOL;
    }

    private function LinkSteamBans($jsonString)
    {
        $bans = $jsonString;
        foreach ($bans as $key => $value) {
            foreach ($this->Players as $k => $v) {
                if ($v->SteamId == $value->SteamId) {
                    $this->Players[$v->Id]->CommunityBanned = $value->CommunityBanned;
                    $this->Players[$v->Id]->VACBanned = $value->VACBanned;
                    $this->Players[$v->Id]->NumberOfVACBans = $value->NumberOfVACBans;
                    $this->Players[$v->Id]->NumberOfGameBans = $value->NumberOfGameBans;
                    $this->Players[$v->Id]->DaysSinceLastBan = $value->DaysSinceLastBan;
                }
            }
        }
        // echo "Ban Link Complete".PHP_EOL;
    }
}

class Tribe
{
    public $Id;
    public $Name;
    public $FileCreated;
    public $FileUpdated;
    public $OwnerId;
    public $Players;
    public $Owner;

    public function __construct()
    {
        $this->Players = array();
    }

    public function ParseTribe($fileName)
    {
        $data = file_get_contents($fileName);

        if (!$data) {
            return false;
        }
        $this->Id = GetInt($data, 'TribeID')[1];
        $this->Name = utf8_encode(GetString($data, 'TribeName'));
        $this->OwnerId = GetUInt32($data, 'OwnerPlayerDataID')[1];
        $this->FileCreated = filectime($fileName);
        $this->FileUpdated = filemtime($fileName);

        return $this;
    }
}

class Player
{
    public $Id;
    public $SteamId;
    public $SteamName;
    public $AvatarUrl;
    public $CharacterName;
    public $Online;
    public $FileCreated;
    public $FileUpdated;
    public $TribeId;
    public $Level;
    public $ProfileUrl;
    public $CommunityBanned;
    public $VACBanned;
    public $NumberOfVACBans;
    public $DaysSinceLastBan;
    public $NumberOfGameBans;
    public $Tribe;
    public $OwnedTribe;

    public function __construct()
    {
    }
    private function GetId($data)
    {
        return GetUInt64($data, 'PlayerDataID');
    }

    private function GetSteamId($data)
    {
        $bytes1 = strpos($data, 'UniqueNetIdRepl');
        if ($bytes1 === false) {
            return false;
        }

        return substr($data, $bytes1 + strlen('UniqueNetIdRepl') + 9, 17);
        //    byte[] bytes1 = Encoding.Default.GetBytes("UniqueNetIdRepl");
        //    int num = Extensions.LocateFirst(data, bytes1, 0);
        //    byte[] bytes2 = new byte[17];
           //
        //    Array.Copy((Array)data, num + bytes1.Length + 9, (Array)bytes2, 0, 17);
        //    return Encoding.Default.GetString(bytes2);
    }

    public function ParsePlayer($fileName)
    {
        $data = file_get_contents($fileName);
        if (!$fileName) {
            return false;
        }
        $this->Id = $this->GetId($data)[1];
        $this->SteamId = $this->GetSteamId($data);
        $this->SteamName = utf8_encode(GetString($data, 'PlayerName'));
        $this->CharacterName = GetString($data, 'PlayerCharacterName');
        $this->TribeId = GetInt($data, 'TribeID')[1];
        if (strlen((string) $this->TribeId) < 2) {
            $this->TribeId = false;
        }
        $this->Level = GetUInt16($data, 'CharacterStatusComponent_Extra_CharacterLevel') + 1;
        $this->FileCreated = date("m/d/y g:ia",filectime($fileName));
        $this->FileUpdated = date("m/d/y g:ia",filemtime($fileName));
        $this->Online = false;

        return $this;
    }
}

function GetInt($data, $name)
{
    $bytes1 = strpos($data, $name);
    $bytes2 = strpos($data, 'IntProperty', $bytes1);
    if ($bytes2 === false) {
        return false;
    }
    $end = $bytes2 + strlen('IntProperty') + 9;

    return unpack('I', (substr($data, $end, 8)));
}

  function GetUInt16($data, $name)
  {
      $bytes1 = strpos($data, $name);
      $bytes2 = strpos($data, 'UInt16Property', $bytes1);
      if ($bytes2 === false) {
          return false;
      }
      $end = $bytes2 + strlen('UInt16Property') + 9;

      return unpack('S', (substr($data, $end, 8)))[1];
  }

   function GetUInt32($data, $name)
   {
       $bytes1 = strpos($data, $name);
       $bytes2 = strpos($data, 'UInt32Property', $bytes1);
       if ($bytes2 === false) {
           return false;
       }
       $end = $bytes2 + strlen('UInt32Property') + 9;

       return unpack('I', (substr($data, $end, 8)));
   }

   function GetUInt64($data, $name)
   {
       $bytes1 = strpos($data, $name);
       $bytes2 = strpos($data, 'UInt64Property', $bytes1);
       if ($bytes2 === false) {
           return false;
       }
       $end = $bytes2 + strlen('UInt64Property') + 9;

       return unpack('V', (substr($data, $end, 8)));
   }

   function GetString($data, $name)
   {
       $bytes1 = strpos($data, $name);
       $bytes2 = strpos($data, 'StrProperty', $bytes1);

       if ($bytes1 === false) {
           return false;
       }
       $num = $bytes2;
       $mid = unpack('C*', (substr($data, $num + strlen('StrProperty') + 1, 1)));
       $midlength = (int) $mid[1] - ($num + strlen('StrProperty') + 12 == 255 ? 6 : 5);
       $start = $num + strlen('StrProperty') + 13;

       return substr($data, $start, $midlength);
   }
