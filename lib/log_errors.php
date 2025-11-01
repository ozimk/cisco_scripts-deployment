<?php

################################################################################
##                >>>>>   CHECK LAYER 2 CONFIGURATIONS   <<<<<                ##
################################################################################
## Contains functions to check various aspects of layer 3 configurations. The ##
## functions that should be called from the main code are:                    ##
##                                                                            ##

################################################################################
##########
# Store the nominated error class/details with the associated description
#
# - All errors are stored in the global $error_log variable. The structure of
#   the array is:
#
#   $error_log[CLASS][ERROR] = array of error messages for this type of error
##########
function log_error($error_class, $error_details, $error_message)
{
  global $error_log;

  $error_log[$error_class][$error_details][] = $error_message;
}

##########
# Saves all logged errors in $error_log to the specified file in INI format
#
# - If there are no errors, just return
# - Content of file will be constructed in the variable $content, start with a
#   a comment for the INI file
# - Loop through each error class in the log
#   o Append a comment for the error class and create the class section in $content
#   o Loop through each error within the error class
#     - Loop through each error message for the error and append to $content in 
#       the form of an array value in the INI file
#     - Append a new line to $content for readability
# - Save the contents of $content to the nominated file
##########
function save_error_log($error_ini_file)
{
  global $error_log;

  if (file_exists($error_ini_file)) unlink($error_ini_file);

  if (is_null($error_log)) return false;

  $content = ";------------------------------\n; NOTE: Auto generated file, do not edit\n;------------------------------\n\n";

  foreach ($error_log as $class => $details)
  {
    $content .= ";------------------------------\n; Errors detected in error class $class\n";
    $content .= "[$class]\n";
    foreach ($details as $detail => $messages)
    {
      foreach ($messages as $message) $content .= $detail . "[] = \"$message\"\n";
      $content .= "\n";
    }
  }

  file_put_contents($error_ini_file, $content);

  return true;
}

?>
