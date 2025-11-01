<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
    exit(1);
}

include('menu.inc');

$original_name = filter_var($_POST["original_name"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$filename = trim(filter_var($_POST["filename"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

if($filename=="") {
    echo "Filename was empty, so gave it default name.";
    $filename="config";
}


if (isset($original_name) && $original_name == $filename) {
    $match_name = true;
} else {
    $match_name = false;
}

$filename = (strpos($filename, ".ini") !== false) ? $filename : $filename . ".ini";

$full_filename = $cisco_configs["template_path"] . "/config/" . $filename;

if(isset($_POST['delete']) && file_exists($full_filename) && $match_name) {
    if(file_exists($cisco_configs["template_trash"] . "/" . $filename)){
        unlink($cisco_configs["template_trash"] . "/" . $filename);
    }
    rename($full_filename, $cisco_configs["template_trash"] . "/" . $filename);
    echo "Success: $filename Deleted";
    exit;
}else if (isset($_POST['delete'])) {
    echo "File does not exist.";
    exit;
}

if (!$match_name) {
    while (file_exists($full_filename)) {
        $parts = explode(".", $full_filename);
        $full_filename = "{$parts[0]}_copy.{$parts[1]}";
    }
}

$template_file = fopen($full_filename, "w") or die("Unable to Save Settings");

foreach ($_POST["networks"] as $i => $network) {
    $network = filter_var($network, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "Networks[] = \"{$network}\"\n");
}
$masks = [];
$interfaces = [];
foreach ($_POST["model"] as $model_key => $model_details) {
    $model_key = filter_var($model_key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "Models[] = {$model_key}\n");
    $interfaces[$model_key] = [];
    foreach ($model_details["Interfaces"] as $index => $interface) {
        $interface["mask"] = filter_var($interface["mask"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $interface["network"] = filter_var($interface["network"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $interface["direction"] = filter_var($interface["direction"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);


        if (!isset($masks[$interface["mask"]]) || !isset($masks["ex"])) {
            $masks[$interface['mask']] = $interface['mask'];
            if (!isset($masks["ex"]) && isset($interface['extint']) && $interface['extint'] != "in") {
                $masks["ex"] = $interface["mask"]; // the first encoutnered external interface will set the external mask for all
            }
        }
        if (!isset($interface['device_connection'])) { // loopbacks are unconnected in web interface and connected to themselves in tempalte
            $partial_interface_string = "{$interface['direction']}:{$model_key}:{$interface['direction']}:{$interface['network']}";
        } else {
            $interface["device_connection"] = filter_var($interface["device_connection"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $interface["interface_connection"] = filter_var($interface["interface_connection"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $partial_interface_string = "{$interface['direction']}:{$interface['device_connection']}:{$interface['interface_connection']}:{$interface['network']}";
        }

        if (isset($interface['extint'])) {
            $interface["extint"] = filter_var($interface["extint"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $interface["steps"] = 1;//filter_var($interface["steps"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);


            switch ($interface['extint']) {
                case 'next':
                    $type = "ex-f";
                    break;
                case 'prev':
                    $type = "ex-b-{$interface['steps']}";
                    break;
                default:
                    $type = $interface['mask'];
                    break;
            }
        } else {
            $type = $interface['mask'];
        }


        $partial_interface_string = "$type:$partial_interface_string";
        array_push($interfaces[$model_key],  $partial_interface_string);
    }
}

foreach ($_POST["mapping"] as $id => $maps) {
    $router = filter_var($maps['router'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $model = filter_var($maps['model'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "Mappings[$router] = $model\n");
}

foreach ($masks as $name => $cidr) {
    fwrite($template_file, "Masks[$name] = $cidr\n");
}

foreach ($_POST["model"] as $model_key => $model_details) {
    $model_key = filter_var($model_key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "[$model_key]\n");
    foreach ($interfaces[$model_key] as $interface_string) {
        fwrite($template_file, "Interfaces[] = \"$interface_string\"\n");
    }
    foreach ($model_details["Routing"] as $routing_title) {
        $routing_title = filter_var($routing_title, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "Routing[] = \"$routing_title\"\n");
    }
    foreach ($model_details["Components"] as $component_title) {   
        $component_title = filter_var($component_title, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "Components[] = \"$component_title\"\n");
    }
}

// Write Details
foreach ($_POST["routing"] as $name => $protocol) {
    $name = filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    fwrite($template_file, "[{$name}]\n");
    $prot_name = filter_var($protocol['protocol'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $prot_router_id = filter_var($protocol['router_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "protocol = {$prot_name}\n");
    fwrite($template_file, "router_id = {$prot_router_id}\n");

    if ($prot_name == "bgp") {
        $as = filter_var($protocol['as'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $as_increment = filter_var($protocol['as_increment'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $update_source = filter_var($protocol['update_source'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        fwrite($template_file, "as = {$as}\n");
        fwrite($template_file, "increment_as_after = \"{$as_increment}\"\n");
        if (isset($protocol['update_source']) && trim($update_source) != "") {
            fwrite($template_file, "update_source = {$update_source}\n");
        }
    } else if ($prot_name == "ospf") {
        $proc_id = filter_var($protocol['proc_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "proc_id = {$proc_id}\n");
    }
    //Advertised
    foreach ($protocol["advert"] as $id => $advert) {
        $advert = filter_var($advert, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "Advertised[] = $advert\n");
    }
}

foreach ($_POST["component"] as $name => $details) {
    $name = filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "[{$name}]\n");
    $config = filter_var($details["config"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "config = \"$config\"\n");
    $room = filter_var($details['org']['room'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $rack = filter_var($details['org']['rack'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $kit = filter_var($details['org']['kit'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($template_file, "room = {$room}\n");
    fwrite($template_file, "rack = {$rack}\n");
    fwrite($template_file, "kit = {$kit}\n");

    if ($config == "syslog") {
        $address = filter_var($details['address'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "address = {$address}\n");
        if (isset($details['facility']) && trim($details['facility']) != "") {
            $facility = filter_var($details['facility'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            fwrite($template_file, "facility = {$facility}\n");
        }
        if (isset($details['log_level']) && trim($details['log_level']) != "") {
            $log_level = filter_var($details['log_level'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            fwrite($template_file, "log_level = {$log_level}\n");
        }
        if (isset($details['source']) && trim($details['source']) != "") {
            $source = filter_var($details['source'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            fwrite($template_file, "source_interface = {$source}\n");
        }
        if ($details['transport'] != "default") {
            $transport = filter_var($details['transport'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            fwrite($template_file, "transport_protocol = {$transport}\n");
        }
        if (isset($details['port']) && trim($details['port']) != "") {
            $port = filter_var($details['port'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            fwrite($template_file, "port = {$port}\n");
        }
    } else if ($config == "snmp") {
        $host = filter_var($details['host'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $transport = filter_var($details['transport'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $port = filter_var($details['port'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "host = {$host}\n");
        fwrite($template_file, "transport = {$transport}\n");
        fwrite($template_file, "port = {$port}\n");

        $view = filter_var($details['view'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $group = filter_var($details['group'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $user = filter_var($details['user'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $auth = filter_var($details['auth'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $enc = filter_var($details['enc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $permission = filter_var($details['permission'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "view = {$view}\n");
        fwrite($template_file, "group = {$group}\n");
        fwrite($template_file, "user = {$user}\n");
        fwrite($template_file, "auth = {$auth}\n");
        fwrite($template_file, "enc = {$enc}\n");
        fwrite($template_file, "permission = {$permission}\n");

        fwrite($template_file, "traps = ");
        if ($details["traps"]) {
            fwrite($template_file, "true\n");
        } else {
            fwrite($template_file, "false\n");
        }
        fwrite($template_file, "ifindex = ");
        if ($details["ifindex"]) {
            fwrite($template_file, "true\n");
        } else {
            fwrite($template_file, "false\n");
        }

        $options = str_replace(array("\r\n", "\n"), "#", $details['options']);
        fwrite($template_file, "options = \"$options\"\n");

    } else if ($config == "acl") {
        fwrite($template_file, "extended = ");
        if ($details["extended"]) {
            fwrite($template_file, "true\n");
        } else {
            fwrite($template_file, "false\n");
        }
        $rules = str_replace(array("\r\n", "\n"), "#", $details['rules']);
        foreach ($details["attached"] as $id => $attach) {
            fwrite($template_file, "Attached[] = $attach\n");
        }
        fwrite($template_file, "rules = \"$rules\"\n");
    } else if ($config == "prefix") {
        $rules = str_replace(array("\r\n", "\n"), "#", $details['rules']);
        fwrite($template_file, "rules = \"$rules\"\n");
    } else if ($config == "route_map") {
        fwrite($template_file, "permit = ");
        if ($details["permit"]) {
            fwrite($template_file, "true\n");
        } else {
            fwrite($template_file, "false\n");
        }
        fwrite($template_file, "number = {$details['rm_num']}\n");
        $statements = str_replace(array("\r\n", "\n"), "#", $details['statements']);
        fwrite($template_file, "statements = \"$statements\"\n");
        foreach ($details["attached"] as $id => $attach) {
            fwrite($template_file, "Attached[] = $attach\n");
        }
    } else if ($config == "ntp") {
        $ntp_server = filter_var($details['ntp_server'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        fwrite($template_file, "server = {$ntp_server}\n");
    } else if ($config == "arbitrary") {
        $lines = str_replace(array("\r\n", "\n"), "#", $details['arbitrary']);
        fwrite($template_file, "arbitrary = \"$lines\"\n");
    }
}

fclose($template_file);

echo "Success: File is Saved as $filename";
