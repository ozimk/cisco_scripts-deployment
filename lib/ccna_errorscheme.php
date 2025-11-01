<?php

################################################################################
##             >>>>>   CHECK BASIC DEVICE CONFIGURATIONS   <<<<<              ##
################################################################################

##########
# Checks the configuration of either line or vty access to the router. Input is:
#  $text_description  - Description of router and interface for logging purposes
#  $line_config       - Router configuration for the line/vty access to check
#  $expected_settings - false, should not be enabled
#                       otherwise string set to expected password
#
# - If it supposed to be configured ($expected_settings != false) we need to
#   check that:
#   o login is enabled
#   o a password has been specified AND that it is correct
# - Otherwise an error if any configuration has been performed
##########

Error_Categories = array();

Error_Categories["login"]["not_enabled"]        = "minor";
Error_Categories["login"]["no_password"]        = "minor";
Error_Categories["login"]["incorrect_password"] = "minor";
Error_Categories["login"]["enabled"]            = "minor";

Error_Categories["hostname"]["not_configured"]  = "minor";
Error_Categories["hostname"]["wrong_name"]      = "minor";

Error_Categories["motd"]["not_configured"]      = "minor";
Error_Categories["motd"]["configured"]          = "minor";


?>

