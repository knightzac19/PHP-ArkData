# PHP ARK Server Data Reader

This is a port of <a href="https://github.com/AuthiQ/ArkData">AuthiQ's ARK Server Data Reader</a> into PHP. It's 100% ported without anything left out and I have even fixed the 100 user limit for steam profiles. I also have imported <a href="https://github.com/xPaw/PHP-Source-Query/">xPaw's PHP-Source-Query</a> as that is the main backend of the project. Please visit the repo to make sure your PHP environment is setup properly.



## How to use it

If you use codeigniter then you need to use the ssq.php file that is included.
```PHP
$this->load->library("ssq");
$this->load->library("arkdata",array("installLocation"=>"DIRECT LOCATION TO STEAM FILES"));
$this->arkdata->LoadSteam("STEAM API KEY HERE");
$this->arkdata->LoadOnlinePlayers();
$data['arkdata'] = $this->arkdata->getArkData();
$this->load->view('ark',$data);
```

To use it on a non-codeigniter instance, you can initialize it like any other class:
```PHP
require_once("SourceQuery/bootstrap.php");
$arkdata = new Arkdata(array("installLocation"=>"DIRECT LOCATION TO STEAM FILES"));
$arkdata->LoadSteam("STEAM API KEY HERE");
$arkdata->LoadOnlinePlayers();
$arkdataresults = $arkdata->getArkData();
```

## Enabling SSH (Beta)

**Note: I take no responsibility if you somehow end up deleting your entire ark folder with this or something worse. Please TRIPLE check your cache directory before you run the script. It is set to delete everything in that folder on startup. YOU HAVE BEEN WARNED**


If you want to enable SSH you must have php_ssh2 installed and you have to have PHP 5.5+ or at least have ZipArchive support in your PHP installation. On the server that hosts ARK, you must have the zip application installed. Please run **which zip** and make sure it returns something.

To Enable SSH do add the following items to the array for the arkdata class:
```PHP
"enableSSH" => true,
"SSHSettings" => array("host"=>'<HOST>',
                        "port"=> 22,
                        "known_host"=>"<HOST MD5 HASH>",
                        "pub_key_location"=>"<FULL PATH TO PUBLIC KEY>",
                        "priv_key_location"=>"<FULL PATH TO PRIVATE KEY>",
                        "key_user"=>"<USER KEY IS FOR>",
                        "cache_dir" => "<FULL PATH ON WEB SERVER FOR CACHE>"
                        )
```
Once you have it enabled and you have your SSH key all setup with your server, you will have to load the library at least once so you can get your host's MD5 HASH. It's not something you can just pull from the server, PHP will md5 it for you and you can put it in the known_host array option. If the key matches after you refresh, you will probably have to wait at least 2-3 minutes depending on the userbase you have and the connection speed between you and the server.

## Timers
Timers are stored in the 3rd array item from the getArkData() function. They will be in an array format as shown below.
```
array(
    'App' => 0.0143,
    'Steam' => 4.4331,
    'SSH' => 11.6214 //Only set if enabled...
    )
```

## Known Issues

* Windows
    * XAMPP Apache with PHP 5.6 will crash if you provide an invalid installLocation (affects SSH only).
        * Not much I can do about that, it's a known bug in XAMPP.
* Linux
    * You might run into issues because I had to hard code command paths. If it becomes an issue, I will put some more checks in to make sure the commands exist.

## Upcoming Features

* ~~SCP/SSH Support for Remote Linux Servers~~  **Done**
  * ~~This will require the user setting up approved keys between servers~~
  * ~~Downsides to this is that SCP can be very slow~~
  * ~~I might create a script to zip a file for the server to download with all the profiles.~~
* Possibly NFS Share Support
* Windows Network Share (Might actually work if mounted already)
* Multiple Async thread support
  * This will require PHP pthread. It is available for both windows and linux
  * Probably will take some time to determine how I want to implement this.
