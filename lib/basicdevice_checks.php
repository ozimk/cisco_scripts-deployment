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
function check_line_access($text_description, $line_config, $expected_settings)
{
  // This line should be configured
  if ($expected_settings !== false)
  {
    if ($line_config->login == false)
      log_error('line', 'no_login', $text_description);
    if (is_null($line_config->password))
      log_error('line', 'no_password', $text_description);
    else if ($line_config->password != $expected_settings)
      log_error('line', 'incorrect_password', "$text_description: You specified \'{$line_config->password }\', expected password is \'$expected_settings\'");
  } else
  // The line should not be configured
  {
    if (($line_config->login) || (isset($line_config->password)))
      log_error('line', 'enabled', $text_description);
  }
}

##########
# Check some basic configuration settings on the router (hostname, MOTD, console and VTY configs)
#
# $router_name      - Textual representation of router (for logging purposes)
# $config           - Database of router configuration
# $expected_details - Array storing required settings
#
# Router name:
# - Minor error if name not set or if set but first character doesn't match device name first character
#
# MOTD:
# - Minor error if MOTD not configured when you were supposed to
# - Minor error if MOTD configured when you were not supposed to
#
# Console/vty:
# - If the expected details are set for these fields, call check_line_access() to actually check the configuration
##########
function check_device_basicconfig($device_name, $config, $expected_details)
{
  // Router Name Checks
  if ($expected_details['hostname'] === true)
  {
    if (($config->hostname === "Router") or ($config->hostname === "Switch"))
      log_error('hostname', 'not_set', $device_name);
    else if (strtoupper($config->hostname[0]) !== strtoupper($device_name[0]))
      log_error('hostname', 'incorrect', "$device_name: hostname set to {$config->hostname}");
  }

  // MOTD Checks
  if ($expected_details['motd'] === true)
  {
    if (is_null($config->motd)) log_error('motd', 'not_set', $device_name);
  } else if ($expected_details['motd'] === false)
  {
    if (isset($config->motd)) log_error('motd', 'set', $device_name);
  }

  // LINE and VTY Access Checks
  if (isset($expected_details['line'])) check_line_access("$device_name(line)", $config->lines['con'], $expected_details['line']);
  if (isset($expected_details['vty'])) check_line_access("$device_name(telnet)", $config->lines['vty'], $expected_details['vty']);
}

?>
