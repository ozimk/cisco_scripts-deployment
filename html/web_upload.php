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

$job = filter_var($_POST["job_select"], FILTER_SANITIZE_ADD_SLASHES);
$path = $cisco_configs["network_path"] . "/$job";
$username = '"'.filter_var($_POST["username"], FILTER_SANITIZE_ADD_SLASHES) . '"';
$password = '"'.filter_var($_POST["password"], FILTER_SANITIZE_ADD_SLASHES) . '"';
$files = array_diff(scandir($path), array('.', '..', 'config_files'));

$atc_names = array(
  "ATC328" => 8,

  "ATC329" => 9,

  "ATC320" => 0
);
$upload_processes = array();
$pipes_processes = array();
$finished_processes = array();
$descriptors = array(
  0 => array("pipe", "r"),
  1 => array("pipe", "w")
);


foreach ($files as $file) {
  $file = "$path/$file";
  $parts = explode("/", $file);
  $last = $parts[count($parts) - 1];
  $room = explode("_", $last)[0];
  $code = $atc_names[$room];
  $p = proc_open($cisco_configs["bin_path"] . "/atc_web_upload $file $code $username $password", $descriptors, $pipes);
  array_push($upload_processes, $p);
  array_push($pipes_processes, $pipes);
}


$timeout = 120;
for ($time = $timeout; $time > 0; $time--) {
  for ($i = 0; $i < count($upload_processes); $i++) {
    $proc = $upload_processes[$i];
    $pipes = $pipes_processes[$i];

    $serialized_info = "";
    while (($buffer = fread($pipes[1], 1)) !== false && $buffer !== "") {
      $serialized_info .= $buffer;
      if (@unserialize($serialized_info) !== false) {
        break;
      }
    }
    if (in_array($proc, $finished_processes) && $serialized_info === "") {
      unset($upload_processes[$i]);
    }

    if ($serialized_info !== "") {
      $info_array = unserialize($serialized_info);
      if (isset($info_array['error'])) {
        echo "<script>parent.document.getElementById('error').innerHTML +='<div style=\"text-align:center; font-weight:bold\">{$info_array['error']}</div>'</script>";
      }
      if (isset($info_array['progress'])) {
        echo "<script>parent.document.getElementById('progressbar').innerHTML='<div style=\"width:" . $info_array['progress'] . "%" . ";background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>'</script>";
      }
      if (isset($info_array['information'])) {
        echo "<script>parent.document.getElementById('information').innerHTML='<div style=\"text-align:center; font-weight:bold\">{$info_array['information']}</div>'</script>";
      }
      flush();
    }
    if (proc_get_status($proc)['running'] === false) {
      if(proc_get_status($proc)['exitcode'] !== 0) {
        echo "<script>parent.document.getElementById('error').innerHTML +='<div style=\"text-align:center; color:red;\">The Process Ended With an Erraneous ExitCode</div>'</script>";
      }
      array_push($finished_processes, $proc);
    }
  }
  if (count($upload_processes) == 0) {
    break;
  }
  sleep(1);
};
echo '<script>parent.document.getElementById("information").innerHTML="<div style=\"text-align:center; font-weight:bold\">Process completed</div>"</script>';

session_destroy();
