<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
    exit(1);
}

include('menu.inc');

$original_name = filter_var($_POST["original_name"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$filename = trim(filter_var($_POST["filename"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

if($filename=="") {
    echo "Filename was empty, so gave it default name.";
    $filename="replication";
}


if (isset($original_name) && $original_name == $filename) {
    $match_name = true;
} else {
    $match_name = false;
}

$filename = (strpos($filename, ".ini") !== false) ? $filename : $filename . ".ini";


$full_filename = $cisco_configs["template_path"] . "/replication/" . $filename;

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

$replication_file = fopen($full_filename, "w") or die("Unable to Save Settings");


foreach ($_POST["vrf"] as $id => $vrf) {
    $name = filter_var($vrf['vrf'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $file = filter_var($vrf['file'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    fwrite($replication_file, "VRF[{$name}] = \"{$file}\"\n");
}
foreach ($_POST["replicate"] as $container => $details) {
    $container = filter_var($container, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $numbered = is_int(array_key_first($details));
    if ($numbered) { // These ones are just the switches and routers as their conenctions should not change between kit/rack/room
        $title = $container == "Switch" ? "Switches" : "{$container}s";

        fwrite($replication_file, "[{$title}]\n");
        ksort($details);
        for ($i = 0; $i <= array_key_last($details); $i++) {
            if (isset($details[$i])) {
                $item = filter_var($details[$i], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                fwrite($replication_file, "{$container}[] = {$item}\n");
            }
        }
    } else {
        $title = $container == "Switch" ? "Switches" : "{$container}s";
        fwrite($replication_file, "[{$title}]\n");
        foreach ($details as $category => $values) {
            $category = filter_var($category, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            ksort($values);
            for ($i = 0; $i <= array_key_last($values); $i++) {
                if (isset($values[$i])) {
                    $item = filter_var($values[$i], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    fwrite($replication_file, "{$category}[] = {$item}\n");
                }
            }
        }
    }
}

fclose($replication_file);

echo "Success: File is Saved as $filename";
