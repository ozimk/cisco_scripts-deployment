<?php
if (($cisco_configs = parse_ini_file(getenv("CISCO_PATH") . "/config.ini", true)) === false) {
    exit(1);
}
?>
<!DOCTYPE html>
<html>

<head>
    <script src="framework.js"></script>
    <script src="replicate_builder.js"></script>
    <?php
        include("webscripts/file_select.php");
    ?>
</head>

<body>
    <?php
        include("menu.inc");
        $form_class = isset($_POST["replicate_select"]) ? 'file-form-min' : 'file-form';
        echo "<form class='$form_class' method='POST' action='replicate.php' onkeydown='return event.key != `Enter`;'>";
        FileSelect("Replication Template", "{$cisco_configs["template_path"]}/replication", "replicate_select", "Create New");
        FileSelectSubmit("replicate_select");
        echo '</form>';
    ?>
    
    <hr>
    <form id='topology' method="POST" action="save_replication.php" onkeydown="return event.key != 'Enter';">
        <?php
        if (isset($_POST["replicate_select"])) {
            FilenameInput($_POST["replicate_select"]);
        }
        ?>


    </form>
    <div id="addrem_container"></div>
    <?php
    if (isset($_POST["replicate_select"])) {
        if ($_POST["replicate_select"] == "create") {
            $json = json_encode(null);
        } else {
            $arr = parse_ini_file($cisco_configs["template_path"] . "/replication/" . $_POST["replicate_select"], true);
            $json = json_encode($arr);
        }
        $template_files = scandir($cisco_configs["template_path"] . "/config", SCANDIR_SORT_DESCENDING);
        $template_files_json = json_encode($template_files);
        $organisation = parse_ini_file($cisco_configs["template_path"] . "/details.ini");
        $organisation_json = json_encode($organisation);
        echo "<script> build($json, $template_files_json, $organisation_json) </script>\n";
    }
    ?>


</body>

</html>