<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
    exit(1);
}
?>
<!DOCTYPE html>
<html>

<head>
    <script src="framework.js"></script>
    <script src="topology_builder.js"></script>
    <?php
        include("webscripts/file_select.php");
    ?>
</head>

<body>
    <?php
    include("menu.inc");
    $form_class = isset($_POST["physical_select"]) ? 'file-form-min' : 'file-form';
    echo "<form class='$form_class' method='POST' action='physical.php' onkeydown='return event.key != `Enter`;'>";
        FileSelect("Physical Template", "{$cisco_configs["template_path"]}/physical", "physical_select", "Create New");
        FileSelectSubmit("physical_select");
    echo "</form>";
    ?>



    <hr>
    <form id='topology' method="POST" action="save_physical.php" onkeydown="return event.key != 'Enter';">
        <?php
        if (isset($_POST["physical_select"])) {
            FilenameInput($_POST["physical_select"]);
        }
        ?>


    </form>
    <div id="addrem_container"></div>
    <?php
    if (isset($_POST["physical_select"])) {
        if ($_POST["physical_select"] == "create") {
            $json = json_encode(null);
        } else {
            $arr = parse_ini_file($cisco_configs["physical_path"] . "/" . $_POST["physical_select"], true);
            $json = json_encode($arr);
        }

        echo "<script> build($json) </script>\n";
    }
    ?>


</body>

</html>