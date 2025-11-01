<?php
session_start();

@ini_set('max_execution_time', 0);
// @ini_set('zlib.output_compression', 0);

echo getenv("CISCO_PATH");

include("menu.inc");

if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
  echo "\n\033[1;31;40mERROR: \033[0;37;40m Reading Cisco configuration file: " . getenv("CISCO_PATH") . "/config.ini\n";
  exit(1);
}
$cisco_path = getenv("CISCO_PATH");
putenv("CISCO_PATH=$cisco_path");

echo "<div id='file'></div>";
echo "<div id='information' ></div>";
echo "<div id='progressbar' style='border:1px solid #ccc; border-radius: 5px; '></div>";
echo "<div id='error' ></div>";

echo "<script>parent.document.getElementById('progressbar').innerHTML='<div style=\"width: 0%" . ";background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>'</script>";
echo "<script>parent.document.getElementById('information').innerHTML='<div style=\"text-align:center; font-weight:bold\">Contacting Server</div>'</script>";

flush();

$physical_file = '"'.filter_var($_POST["physical_select"], FILTER_SANITIZE_ADD_SLASHES) . '"';
$replication_file = '"'.filter_var($_POST["replicate_select"], FILTER_SANITIZE_ADD_SLASHES) . '"';
$username = '"'.filter_var($_POST["username"], FILTER_SANITIZE_ADD_SLASHES) . '"';
$password = '"'.filter_var($_POST["password"], FILTER_SANITIZE_ADD_SLASHES) . '"';


$descriptors = array(
  0 => array("pipe", "r"),
  1 => array("pipe", "w")
);

$proc = proc_open($cisco_configs["bin_path"] . "/check_topology $physical_file $replication_file $username $password", $descriptors, $pipes);

$proc_finished = false;

$timeout = 1000;
$disconnection = false;

for ($time = $timeout; $time > 0; $time--) {
  $serialized_info = "";
  while (($buffer = fread($pipes[1], 1)) !== false && $buffer !== "") {
    $serialized_info .= $buffer;
    if (@unserialize($serialized_info) !== false) {
      break;
    }
    flush();
  }
  if ($proc_finished && $serialized_info === "") {
    break;
  }
  if ($serialized_info !== "") {
    $info_array = unserialize($serialized_info);
    if (isset($info_array['error'])) {
      echo "<script>parent.document.getElementById('error').innerHTML +='<div style=\"text-align:center; color:red;\">" . $info_array['error'] . "</div>'</script>";
    }
    if (isset($info_array['progress'])) {
      echo "<script>parent.document.getElementById('progressbar').innerHTML='<div style=\"width:" . $info_array['progress'] . "%" . ";background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>'</script>";
    }
    if (isset($info_array['information'])) {
      echo "<script>parent.document.getElementById('information').innerHTML='<div style=\"text-align:center; font-weight:bold\">" . $info_array['information'] . "</div>'</script>";
    }
    if (isset($info_array['results'])) {
      $current_room = "";
      $current_rack = "";
      $current_kit = "";
      $current_device = "";
      foreach ($info_array['results'] as $device => $details) {
        $title_parts = explode("-", $device);

        if($title_parts[0] != $current_room) {
          $current_room = $title_parts[0];
          echo  "<script>parent.document.getElementById('error').innerHTML +=`<h2>$current_room</h2>`</script>";
        }
        if($title_parts[1] != $current_rack) {
          $current_rack = $title_parts[1];
          echo  "<script>parent.document.getElementById('error').innerHTML +=`<h3>$current_rack</h3>`</script>";
        }
        if($title_parts[2] != $current_kit) {
          $current_kit = $title_parts[2];
          echo  "<script>parent.document.getElementById('error').innerHTML +=`<h4>$current_kit</h4>`</script>";
        }
        if($title_parts[3] != $current_device) {
          $current_device = $title_parts[3];
          echo  "<script>parent.document.getElementById('error').innerHTML +=`<h5>$current_device</h5>`</script>";
        }

        if (is_string($details)) {
          echo "<script>parent.document.getElementById('error').innerHTML +=`<div><label><input type='checkbox'/><span>$details</span></label></div>`</script>";
          $disconnection = true;
          continue;
        }
        foreach ($details as $interface => $reason) {
          $disconnection = true;
          echo "<script>parent.document.getElementById('error').innerHTML +=`<div><label><input type='checkbox'/><span>$reason</span></label></div>`</script>";
        }
      }
    }
    flush();
  }
  if (proc_get_status($proc)['running'] === false) {
    if(proc_get_status($proc)['exitcode'] !== 0) {
      echo "<script>parent.document.getElementById('error').innerHTML +='<div style=\"text-align:center; color:red;\">The Process Ended With an Erraneous ExitCode</div>'</script>";
    }
    $proc_finished = true;
  }
  sleep(1);
}

//echo "<script>parent.document.getElementById('progressbar').innerHTML='<div style=\"width: 100%" . ";background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>'</script>";

if ($disconnection) {
  echo '<script>parent.document.getElementById("information").innerHTML="<div style=\"text-align:center; font-weight:bold\">Process completed (Disconnections Found)</div>"</script>';
} else {
  echo '<script>parent.document.getElementById("information").innerHTML="<div style=\"text-align:center; font-weight:bold\">Process completed (No Disconnections)</div>"</script>';
}


session_destroy();
