# PHP ARK Server Data Reader

This is a port of <a href="https://github.com/AuthiQ/ArkData">AuthiQ's ARK Server Data Reader</a> into PHP. It's 100% ported without anything left out and I have even fixed the 100 user limit for steam profiles. I also have imported <a href="https://github.com/xPaw/PHP-Source-Query/">xPaw's PHP-Source-Query</a> as that is the main backend of the project. Please visit the repo to make sure your PHP environment is setup properly. 

<h3>How to use it</h3>

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

<h2>Upcoming Features</h3>

<ul>
  <li>SCP Support for Remote Linux Servers
      <ul>
        <li>This will require the user setting up approved keys between servers</li>
        <li>Downsides to this is that SCP can be very slow</li>
        <li>I might create a script to zip a file for the server to download with all the profiles.</li>
      </ul>
  </li>
  <li>Possibly NFS Share Support</li>
  <li>Windows Network Share (Might actually work if mounted already)</li>
  <li>Multiple Async thread support
      <ul>
        <li>This will require PHP pthread. It is available for both windows and linux</li>
        <li>Probably will take some time to determine how I want to implement this.</li>
      </ul>
  </li>
</ul>
