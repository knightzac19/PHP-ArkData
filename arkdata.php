<?php
/**
  PHP-ArkData is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PHP-ArkData is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with PHP-ArkData.  If not, see <http://www.gnu.org/licenses/>.
    
**/

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
    private $installLocation;
    private $playerFiles;
    private $tribeFiles;
    private $Timers;

    public function __construct($options = array())
    {

        $enableSSH = false;
        if (count($options) > 0) {
            $this->installLocation = $options['installLocation'];
            if (isset($options['enableSSH'])) {
                $enableSSH = $options['enableSSH'];
            }

            if ($enableSSH === true) {
                if (!function_exists('ssh2_connect')) {
                    die('You must enable php_ssh2 for your PHP installation to use SSH!');
                }
            }
        }
        if (!isset($this->installLocation)) {
            die('Invalid Settings!');
        }
        $this->Players = array();
        $this->Tribes = array();
        $Rules = array();
        $this->SteamLoaded = false;
        $this->DataLoaded = false;

        if ($enableSSH === false) {
                $this->Timers['App'] = MicroTime(true);
            $this->playerFiles = glob("$this->installLocation/*.arkprofile");
            $this->tribeFiles = glob("$this->installLocation/*.arktribe");
            if (!$this->playerFiles && !$this->tribeFiles) {
                die('Invalid directory detected, no profiles or tribes found!');
            }
            foreach ($this->playerFiles as $key => $value) {
                $player = new Player();
                $parse = (object) $player->ParsePlayer($value);
                if (!$parse->Id) {
                    continue;
                }
                $this->Players[(string) $parse->Id] = $parse;
            }
            foreach ($this->tribeFiles as $key => $value) {
                $tribe = new Tribe();
                $parse = $tribe->ParseTribe($value);
                if (!$parse->Id) {
                    continue;
                }
                $this->Tribes[$parse->Id] = $parse;
            }
        } else {
            if ($this->setupSSH(
                    $options['SSHSettings']['host'],
                    @$options['SSHSettings']['port'],
                    $options['SSHSettings']['known_host'],
                    $options['SSHSettings']['pub_key_location'],
                    $options['SSHSettings']['priv_key_location'],
                    $options['SSHSettings']['key_user'],
                    $options['SSHSettings']['cache_dir'])) {
                $this->Timers['App'] = MicroTime(true);
                $this->playerFiles = glob($options['SSHSettings']['cache_dir'].'/*.arkprofile');
                $this->tribeFiles = glob($options['SSHSettings']['cache_dir'].'/*.arktribe');
                if (!$this->playerFiles && !$this->tribeFiles) {
                    die('Invalid directory detected, no profiles or tribes found!');
                }
                foreach ($this->playerFiles as $key => $value) {
                    $player = new Player();
                    $parse = (object) $player->ParsePlayer($value);
                    if (!$parse->Id) {
                        continue;
                    }
                    $this->Players[(string) $parse->Id] = $parse;
                }
                foreach ($this->tribeFiles as $key => $value) {
                    $tribe = new Tribe();
                    $parse = $tribe->ParseTribe($value);
                    if (!$parse->Id) {
                        continue;
                    }
                    $this->Tribes[$parse->Id] = $parse;
                }
            } else {
                die('SSH Failed To Process, please check your logs for any exceptions!');
            }
        }

        // $this->Timer = MicroTime(true);


        $this->LinkPlayerTribe();
        // $this->Timer = MicroTime(true) - $this->Timer;
        // $tribe = new Tribe();
        // $tribe->ParseTribe($installLocation."/1596232110.arktribe");
        $this->Timers['App'] = Number_Format(MicroTime(true) - $this->Timers['App'], 4, '.', '');
    }

    public function getArkData()
    {
        if ($this->DataLoaded) {
            return array($this->Players,$this->Tribes,$this->Timers);
        } else {
            die('Data did not load correctly!');
        }
    }

    private function setupSSH($host, $port, $known_host, $pub_key_location, $priv_key_location, $key_user, $cache_dir)
    {
        $this->Timers['SSH'] = MicroTime(true);
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir);
        } else {
            $files = array_diff(scandir($cache_dir), array('.', '..'));
            foreach ($files as $file) {
                (is_dir("$cache_dir\\$file")) ? delTree("$cache_dir\\$file") : unlink("$cache_dir\\$file");
            }
        }
        if ($port == 0 || empty($port)) {
            $port = 22;
        }
        if (!isset($host) || !isset($known_host) || !isset($pub_key_location) || !isset($priv_key_location) || !isset($key_user)) {
            throw new Exception('Please make sure to you set all variables for setting up SSH!');
        } else {
            $connection = ssh2_connect($host, $port);
            if (!$connection) {
                throw new Exception('Failed to connect to the server!');
            }
            $fingerprint = ssh2_fingerprint($connection,
                           SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
            if (empty($known_host) || !isset($known_host)) {
                die("You haven't set your SSH known host! If this is a new installation, here is your host's MD5: $fingerprint <br>Please use this key in your SSH options.");
            }
            if ($fingerprint != $known_host) {
                throw new Exception('Possible Man-In-The-Middle Attack! Check your known_host setting to make sure the key is correct!');

                return false;
            }
            ssh2_auth_pubkey_file($connection, $key_user, $pub_key_location, $priv_key_location);
            $stream = ssh2_exec($connection, "/usr/bin/rm -rf $this->installLocation/arkdata.zip && /usr/bin/zip -j $this->installLocation/arkdata.zip $this->installLocation/*.arkprofile $this->installLocation/*.arktribe");

            //forces PHP to wait for the zip file to finish before continuing.
            stream_set_blocking($stream, true);
            while ($line = fgets($stream)) {
                flush();
            }

            $sftp = ssh2_sftp($connection);
            $remotezipfile = file_get_contents("ssh2.sftp://$sftp$this->installLocation/arkdata.zip");
            if (!$remotezipfile) {
                throw new Exception('We failed to get the zip file from the server, please check your installLocation path and try again!');

                return false;
            }
            $localzipfile = file_put_contents("$cache_dir\\arkdata.zip", $remotezipfile);
            if (!$localzipfile) {
                throw new Exception('We failed to write the zip file out and cannot proceed! Please check your cache_dir path and try again.');

                return false;
            }
            $zip = new ZipArchive();
            $res = $zip->open($cache_dir.'\\arkdata.zip');
            if ($res === true) {
                $zip->extractTo($cache_dir);
                $zip->close();
            } else {
                throw new Exception('We failed to extract the zip file, this could be due to a space issue or the file failed to download!');

                return false;
            }
            $this->Timers['SSH'] = Number_Format(MicroTime(true) - $this->Timers['SSH'], 4, '.', '');
            return true;
        }
    }

    public function LoadSteam($apiKey)
    {
        $this->Timers['Steam'] = MicroTime(true);
        $steamSearchPlayers = array_chunk($this->Players,100);

        $builder = '';
        $chunks = array();
        $c = 0;
        foreach ($steamSearchPlayers as $key => $value) {
            foreach ($value as $k => $v) {
                $builder .= $v->SteamId;
                if (end($value) != $v) {
                    $builder .= ',';
                } elseif (end($value) == $v) {
                    $chunks[] = $builder;
                    $builder = '';
                }
            }

        }



        $presponses = array();
        $bresponses = array();
        foreach ($chunks as $key => $value) {
            if (empty($value)) {
                continue;
            }
            // $curl = curl_init();
            $baseUrl = 'https://api.steampowered.com/';
            // var_dump($baseUrl."ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$builder");
            // exit;
            // curl_setopt_array($curl, array(
            //             CURLOPT_RETURNTRANSFER => 1,
            //             CURLOPT_URL => $baseUrl."ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$value",
            //             CURLOPT_SSL_VERIFYPEER => false,
            //         ));
            // $profiles = curl_exec($curl);
            $profiles = Async::call('file_get_contents', array($baseUrl."ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$value"));

            $presponses = array_merge($presponses, json_decode((string) $profiles)->response->players);
            // if (!$profiles) {
            //     die('Error: "'.curl_error($curl).'" - Code: '.curl_errno($curl));
            // }
            // curl_close($curl);
            // $curl = curl_init();
            // curl_setopt_array($curl, array(
            //             CURLOPT_URL => $baseUrl."ISteamUser/GetPlayerBans/v1/?key=$apiKey&steamids=$value",
            //             CURLOPT_RETURNTRANSFER => 1,
            //             CURLOPT_SSL_VERIFYPEER => false,
            //         ));
            // $bans = curl_exec($curl);
            $bans = Async::call('file_get_contents', array($baseUrl."ISteamUser/GetPlayerBans/v1/?key=$apiKey&steamids=$value"));
            $bresponses = array_merge($bresponses, json_decode((string) $bans)->players);
            // if (!$bans) {
            //     die('Error: "'.curl_error($curl).'" - Code: '.curl_errno($curl));
            // }
            // curl_close($curl);
        }

        $this->LinkSteamProfiles($presponses);
        $this->LinkSteamBans($bresponses);
        $this->SteamLoaded = true;
        $this->Timers['Steam'] = Number_Format(MicroTime(true) - $this->Timers['Steam'], 4, '.', '');
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
                    if ($value->SteamName == $var->Name) {
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
                    $this->Players[$v->Id]->AvatarUrl = utf8_encode($value->avatarfull);
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
        $this->FileCreated = date('m/d/y g:ia', filectime($fileName));
        $this->FileUpdated = date('m/d/y g:ia', filemtime($fileName));
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

   class Async extends Thread
   {
       /**
     * Provide a passthrough to call_user_func_array.
     **/
    public function __construct($method, $params)
    {
        $this->method = $method;
        $this->params = $params;
        $this->result = null;
        $this->joined = false;
    }
    /**
     * The smallest thread in the world.
     **/
    public function run()
    {
        if (($this->result = call_user_func_array($this->method, $this->params))) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * Static method to create your threads from functions ...
     **/
    public static function call($method, $params)
    {
        $thread = new self($method, $params);
        if ($thread->start()) {
            return $thread;
        } /* else throw Nastyness **/
    }
    /**
     * Do whatever, result stored in $this->result, don't try to join twice.
     **/
    public function __toString()
    {
        if (!$this->joined) {
            $this->joined = true;
            $this->join();
        }

        return $this->result;
    }
   }
