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
require_once("arkdata.php");
$arkdata = new Arkdata(array("installLocation"=>"DIRECT LOCATION TO STEAM FILES"));
$arkdata->LoadSteam("STEAM API KEY HERE");
$arkdata->LoadOnlinePlayers();
$arkdataresults = $arkdata->getArkData();
```

## Enabling SSH (Beta)

**Note: I take no responsibility if you somehow end up corrupting your saves or your web server dies from the stress of too many player files!**


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

###Using Cygwin on Windows
The library supports using Cygwin out of the box without any extra config needed on the library side, but you just need to make sure your Cygwin SSH server is setup properly.

Please visit this tutorial page, http://docs.oracle.com/cd/E24628_01/install.121/e22624/preinstall_req_cygwin_ssh.htm#EMBSC281 to learn more on how to setup an SSH server in Cygwin. Disregard any specific things for Oracle on that page. To setup authorized key access to your cygwin server, you can just use the ssh-copy-id command from your web server (Linux can just use the ssh-copy-id command, if you have a windows web server, you can either put Cygwin on that server or just use the other method.) or you can just copy the contents of your public key into your cygwin user's authorized_keys file. (Ex. C:\Cygwin64\home\USER\.ssh\authorized_keys). 

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

* All
    * Steam will load 400ish profiles in about 4.8s. Not much I can do about that other than look into more Async commands, but it's doing about 100 a second.
    * SSH is taking on average about 11 seconds to connect,zip,download, and process files.
        * This might go faster once I implement Async for processing player files.
    * Warnings/Errors have not been fully implemented so you might get invalid results if you provide bad variables.
* Windows
    * XAMPP Apache with PHP 5.6 will crash if you provide an invalid installLocation (affects SSH only).
        * Not much I can do about that, it's a known bug in XAMPP.
* Linux (SSH Server **Not** Client)
    * You might run into issues with SSH because I had to hard code command paths. If it becomes an issue, I will put some more checks in to make sure the commands exist.

## Upcoming Features

* Add more avatar picture sizes
    * Currently I have the largest one set.

* Standard FTP Support
  * The only feasible way to do this is if you can run a script on your server side to update profiles into a zip.
  * Otherwise just pulling all the files off could be a tad slow. (although FTP supports multi file downloads)
* Multiple Async thread support
  * This will require PHP pthread. It is available for both windows and linux
  * Probably will take some time to determine how I want to implement this.
  * I might not continue much with this as using AJAX on the client side might be a better option.
* Possible Node.js support (Stretch Goal)
  * This will let a client have realtime updates between the arkdata library and the browser.
  * Until this is implemented, I will be implementing smaller functions to the library to support AJAX calls.
* ~~Cygwin support for remote windows servers. (More or less needs testing, should fit like a glove with current SSH setup)~~ **Done**
    * ~~This will be the easiest way for people to implement the library.~~
* ~~SCP/SSH Support for Remote Linux Servers~~  **Done**
* ~~This will require the user setting up approved keys between servers~~
* ~~Downsides to this is that SCP can be very slow~~
* ~~I might create a script to zip a file for the server to download with all the profiles.~~
