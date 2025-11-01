<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false)
{
    echo "Environment Variable CISCO_PATH not found or no config.ini";
  exit(1);
}

include('menu.inc');


function SaveInterface($interface, $router){
    global $template_file;
    $interface_string = "Interfaces[{$interface['direction']}] = {$interface['name']}\n";
    fwrite($template_file, $interface_string);
}

function SaveConnections($connections){
    global $template_file;
    fwrite($template_file, "[Connections]\n");
    foreach($connections as $con_string){
        fwrite($template_file, "Con[] = $con_string");
    }
}

$original_name = filter_var($_POST["original_name"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$filename = trim(filter_var($_POST["filename"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

if($filename=="") {
    echo "Filename was empty, so gave it default name.";
    $filename="physical";
}


if (isset($original_name) && $original_name == $filename) {
    $match_name = true;
} else {
    $match_name = false;
}
$filename = (strpos($filename, ".ini") !== false) ? $filename : $filename . ".ini";

$full_filename = $cisco_configs["physical_path"] . "/" . $filename;

if(isset($_POST['delete']) && file_exists($full_filename) && $match_name) {
    if(file_exists($cisco_configs["template_trash"] . "/" . $filename)){
        unlink($cisco_configs["template_trash"] . "/" . $filename);
    }
    rename($full_filename, $cisco_configs["template_trash"] . "/" . $filename);
    echo "Success: $filename Deleted";
    exit;
} else if (isset($_POST['delete'])) {
    echo "File does not exist.";
    exit;
}

if (!$match_name) {
    while (file_exists($full_filename)) {
        $parts = explode(".", $full_filename);
        $full_filename = "{$parts[0]}_copy.{$parts[1]}";
    }
}
 



// save
$template_file = fopen($full_filename, "w") or die("Unable to Save Settings");
$connections = [];
foreach($_POST["device"] as $model_key => $model_details){
    $model_key = filter_var($model_key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file,"[$model_key]\n");
    foreach($model_details["Interfaces"] as $index => $interface){
        $interface['direction'] = filter_var($interface['direction'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $interface['name'] = filter_var($interface['name'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        SaveInterface($interface, $model_key);
        if($interface['device_connection'] != null && $interface['interface_connection'] != null){
            $interface['interface_connection'] = filter_var($interface['interface_connection'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $interface['name'] = filter_var($interface['name'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $interface['extint'] = filter_var($interface['extint'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS); 
            $connection_string = "\"{$model_key}:{$interface['direction']}:{$interface['device_connection']}:{$interface['interface_connection']}:{$interface['extint']}\"\n";
            array_push($connections, $connection_string);
        }
     
    }
}

SaveConnections($connections);

fclose($template_file);

echo "Success: File is Saved as $filename";



?>