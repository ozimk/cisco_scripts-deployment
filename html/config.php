<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
    exit(1);
}
?>
<!DOCTYPE html>
<html>

<head>
    <script src="framework.js"></script>
    <script src="template_builder.js"></script>
    <?php
        include("webscripts/file_select.php");
    ?>
</head>

<body>
    <?php
    include("menu.inc");
    $form_class = isset($_POST["template_select"]) ? 'file-form-min' : 'file-form';
    echo "<form class='$form_class' method='POST' action='config.php' onkeydown='return event.key != `Enter`;'>";
        FileSelect("Config Template", "{$cisco_configs["template_path"]}/config", "template_select", "Create New");
        FileSelect("Physical Topology", "{$cisco_configs["template_path"]}/physical", "physical_select", "Select");
        FileSelectSubmit("template_select");
   
    echo "</form>";
    ?>

    <hr>
    <form id='topology' method="POST" action="save_template.php">
        <?php
        
        if (isset($_POST["template_select"]) && isset($_POST["physical_select"])) {
            FilenameInput($_POST["template_select"]);
        }
        ?>


    </form>
    <?php
    if (isset($_POST["template_select"]) && isset($_POST["physical_select"])) {
        if ($_POST["template_select"] == "create") {
            $json = json_encode(null);
        } else {
            $arr = parse_ini_file("{$cisco_configs["template_path"]}/config" . "/" . $_POST["template_select"], true);
            $json = json_encode($arr);
        }
        $physical_arr = parse_ini_file($cisco_configs["physical_path"] . "/" . $_POST["physical_select"], true);
        $physical_context = json_encode($physical_arr);
        echo "<script> build($json, $physical_context) </script>\n";
    }
    ?>


</body>

</html>