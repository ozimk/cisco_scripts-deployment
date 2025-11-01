<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false)
{
  exit(1);
}

$physical_file = filter_var($_POST["physical_select"], FILTER_SANITIZE_ADD_SLASHES);
$replication_file = filter_var($_POST["replicate_select"], FILTER_SANITIZE_ADD_SLASHES);
$jobname = filter_var($_POST["jobname"], FILTER_SANITIZE_ADD_SLASHES);
$create_config = $cisco_configs["bin_path"] . "/construct_config";

if(file_exists($cisco_configs["network_path"] . "/$jobname")){
    $trash_jobname = $jobname;
    while (file_exists($cisco_configs["template_trash"] . "/$trash_jobname")) {
        $trash_jobname = "{$trash_jobname}_copy";
    }
    rename($cisco_configs["network_path"] . "/$jobname", $cisco_configs["template_trash"]. "/$trash_jobname");
}

$command = "{$create_config} '{$replication_file}' '{$physical_file}' '{$jobname}'";
$cisco_path = getenv("CISCO_PATH");
putenv("CISCO_PATH={$cisco_path}");
$info = passthru($command, $result_code);







?>
<!DOCTYPE html>
<html>
    <head>
    </head>
    <body>
        <?php
            include("menu.inc");
            
            if($result_code == 0){
            echo "Successfuly Created $jobname";
            }else{
            echo "Could Not Create See Above Errors (above the title bar)";
            }
        ?>




    </body>
</html>