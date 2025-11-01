<?php
###############################################################
# Helepr Function that takes orgranised array of Rooms, Racks, Kits, ROuters, Switches and configs
# (output from construct_configs)
# Creates a Folder contianering the upload ini files as required by the atc_build_nework script
################################################################
function GenerateUploadINI($output_directory, $rooms){
    echo $output_directory;
    if (!file_exists("$output_directory/config_files")) {
        mkdir("$output_directory/config_files", 0777, true);
    }

    foreach ($rooms as $room_key => $racks) {
        $delay = 300 * count($rooms);
        $uploadINIstring = "inter_ssh = 100000\ndelay = $delay\n\n";
        foreach ($racks as $rack_key => $kits) {
            $uploadINIstring .= "[$rack_key]\n";
            foreach ($kits as $kit_key => $ros) {
                $uploadINIstring .= "\n";
                foreach ($ros as $router_key => $router) {

                    $config_name = "{$output_directory}/config_files/{$room_key}_{$rack_key}_{$kit_key}_{$router_key}";
                    $config_string = $router->ConstructConfigString();

                    $uploadINIstring .= "{$kit_key}[{$router_key}] = \"$config_name\"\n";

                    $config_file = fopen("$config_name", "w") or die("Unable to open file!");
                    fwrite($config_file, $config_string);
                    fclose($config_file);
                }
            }
        }
        $ini_file = fopen("$output_directory/{$room_key}_upload.ini", "w") or die("Unable to open file!");
        fwrite($ini_file, $uploadINIstring);
        fclose($ini_file);
    }

}




?>