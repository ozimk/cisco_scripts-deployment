<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false)
{
  exit(1);
}
?>
<!DOCTYPE html>
<html>
    <head>
    <?php
        include("webscripts/file_select.php");
    ?>
    </head>
    <body>
        <?php
            include("menu.inc"); 
        ?>
        <h1>Create Configurations</h1>
        <form class='file-form' method="POST" action="create_configurations.php" onkeydown="return event.key != 'Enter';">
            <?php
                FileSelect("Replication Template", "{$cisco_configs["template_path"]}/replication", "replicate_select", "Select");
                FileSelect("Physical Topology", "{$cisco_configs["template_path"]}/physical", "physical_select", "Select");
            ?>  
            <br/>
            <br/> 
            <label  class='file-label' for='jobname'>Job Name<label><input id='jobname' name='jobname' type='text' class='name-input'>
            <input type='submit' class='file-submit' value='create'>
        </form>

        <hr>
        <h1>Check Topology</h1>
        <form class='file-form' method="POST" action="web_check_topology.php" onkeydown="return event.key != 'Enter';">

            <?php
                FileSelect("Replication Template", "{$cisco_configs["template_path"]}/replication", "replicate_select", "Select");
                FileSelect("Physical Topology", "{$cisco_configs["template_path"]}/physical", "physical_select", "Select");
            ?>
            <br/>
            <br/>
            <label class='file-label' for="username">Username</label><input type="text" id="username" name="username" class='name-input'>
            <label class='file-label' for="password">Password</label><input type="password" id="password" name="password" class='name-input'>
            <input type='submit' value='check' class='file-submit'>
        </form>

        <hr>

        <h1> Wipe Devices </h1>  
        <form class='file-form' method="POST" action="web_wipe.php" onkeydown="return event.key != 'Enter';">
            <?php
                FileSelect("Replication Template", "{$cisco_configs["template_path"]}/replication", "replicate_select", "Select");
                FileSelect("Physical Topology", "{$cisco_configs["template_path"]}/physical", "physical_select", "Select");
            ?>
            <br/>
            <br/>
            <label class='file-label' for="username">Username</label><input type="text" id="username" name="username" class='name-input'>
            <label class='file-label' for="password">Password</label><input type="password" id="password" name="password" class='name-input'>
            <input  type='submit' value='wipe' class='file-submit'>
        </form>

        <hr>
        
        <h1> Upload Configurations </h1>
        <form class='file-form' id='jobs' method="POST" action="web_upload.php" onkeydown="return event.key != 'Enter';">
            <?php
                FileSelect("Job Name", "{$cisco_configs["network_path"]}", "job_select", "Select");
            ?>
            <br/>
            <br/>
            <label class='file-label' for="username">Username</label><input type="text" id="username" name="username" class='name-input'>
            <label  class='file-label' for="password">Password</label><input type="password" id="password" name="password" class='name-input'>
            <input  type='submit' value='upload' class='file-submit'>
        </form>      
    </body>
</html>