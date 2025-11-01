<?php
session_start();

@ini_set('max_execution_time', 0);
// @ini_set('zlib.output_compression', 0);

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

$proc = proc_open($cisco_configs["bin_path"] . "/atc_web_wipe $physical_file $replication_file $username $password", $descriptors, $pipes);

$proc_finished = false;

$timeout = 300;
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
    flush();
  }
  if (proc_get_status($proc)['running'] === false) {
    $proc_finished = true;
  }
  sleep(1);
}

echo "<script>parent.document.getElementById('progressbar').innerHTML='<div style=\"width: 100%" . ";background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>'</script>";
echo '<script>parent.document.getElementById("information").innerHTML="<div style=\"text-align:center; font-weight:bold\">Process completed</div>"</script>';



session_destroy();
