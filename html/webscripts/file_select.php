<?php
function FileSelect($label, $dir_path, $id, $default_option) {
    echo "<div class='file-input'><label class='file-label' for='$id'>$label</label>
            <select class='file-dropdown' name='$id' id='$id'>
            <option value='create'>$default_option</option>";
            $template_files = scandir($dir_path, SCANDIR_SORT_DESCENDING);
            for ($i = 0; $i < count($template_files) - 2; $i++) {
                $name = $template_files[$i];
                echo "<option value='$name'";
                if (isset($_POST["$id"]) && $name == $_POST["$id"]) {
                    echo "selected ";
                }
                echo ">$name</option>\n";
            }
    echo "</select></div>";
}


function FileSelectSubmit($id) {
    if (isset($_POST[$id])) {
        echo "<input class='file-submit' type='submit' onclick='return confirm(`Are you sure you want to open a new template? Your current changes are not saved!`)'>";
    } else {
        echo "<input class='file-submit' type='submit'>";
    }
}

function FilenameInput($value) {
    if ($value == "create") {
        echo "<input class='filename-input' type='text' name='filename' id='filename' placeholder='filename'>";
    } else {
        $filename = explode(".", $value)[0];
        echo "<input class='filename-input' type='text' name='filename' id='filename' value='{$filename}' placeholder='filename'><input type='text' name='original_name' value='{$filename}'hidden>";
    }
    echo "<input class='save-button' type='submit' value='Save'><input class='delete-button' type='submit' name='delete' value='Delete' onclick='return confirm(`Are you sure you want to delete project?`)'>";
}
?>