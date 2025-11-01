<?php

################################################################################
##             >>>>>   CHECK BASIC ROUTER CONFIGURATIONS   <<<<<              ##
################################################################################

##########
# Check default static route configuration
#
# $router_name      - Textual representation of router (for logging purposes)
# $route_details    - Database of all static routes configured on router
# $expected_details - Array storing required static route settings
#
# 1) If there should be no default routes, major error if there any static default routes configured
# 2) If there should be default routes, major error if there are no static routes configured
# 3) Check all static routes in turn, only care about default routes
#   - Count number default routes programmed and number correct default routes programmed
#   - Major error if next hop IP is incorrect
# 4) Major error if no default statics programmed
# 5) Minor error if more than one correct default route programmed
##########
function check_default_routes($router_name, $route_details, $expected_details)
{
  // Not expecting any static default routes
  if (is_null($expected_details))
  {
    // Static routes configured, check to see if any are default routes
    if (isset($route_details))
    {
      foreach ($route_details as $route)
        // Default route, not expected, major error
        if (($route->network == "0.0.0.0") and ($route->mask == "0.0.0.0"))
          LogError("major", "Static default routes have been installed (when there should be none) on the following devices", $router_name);
    }
    // No need to continue, we are not expecting static defaults, if they've been found we've already logged
    // the error, if they haven't there is no error
    return;
  } 

  // Static default routes should be configured (no need to check if $expected_details is set any more)

  // We are expecting a default static route but there are no static routes at all
  if (is_null($route_details))
  {
    LogError("major", "No static default routes have been installed (when there should be) on the following devices", $router_name);
    return;
  }

  // Static default routes should be configured, and at least one static route has been programmed (no need to check $route_details any more)
  $num_default_routes = 0;
  $num_correct_default_routes = 0;

  // Test each route one at a time
  foreach ($route_details as $route)
  {
    $route_description = "$router_name: ip route {$route->network} {$route->mask} {$route->via}";

    // Only care if it is a default route, non-default routes checked somewhere else
    if (($route->network == "0.0.0.0") and ($route->mask == "0.0.0.0"))
    {
      // It is a default route, count how many there are and whether any are incorrect
      $num_default_routes++;
      // Next hop (or exit interface is incorrect), major error
      if (in_array($route->via, $expected_details))
        $num_correct_default_routes++;
      else
        LogError("major", "At least one default static route has the incorrect next hop or exit interface programmed", $route_description);
    } 
  }

  // Fail if no default route has been found
  if ($num_default_routes == 0) LogError("major", "No default static route has been installed on the following devices", $router_name);
  // Minor problem if more than one correct default route installed
  if ($num_correct_default_routes > 1)  LogError("minor", "Multiple (correct) default static routes configured on the following devices", $router_name);
}

##########
# Check default static route configuration
#
# $router_name      - Textual representation of router (for logging purposes)
# $route_details    - Database of all static routes configured on router
# $expected_details - Array storing required static route settings
#
# 1) If there should be no default routes, major error if there any static default routes configured
# 2) If there should be default routes, major error if there are no static routes configured
# 3) Check all static routes in turn, only care about default routes
#   - Count number default routes programmed and number correct default routes programmed
#   - Major error if next hop IP is incorrect
# 4) Major error if no default statics programmed
# 5) Minor error if more than one correct default route programmed
##########
############## TO BE FIXED
function check_static_routes($router_name, $route_details, $expected_details)
{
  // Not expecting any static (non-default) routes
  if (is_null($expected_details))
  {
    // Static routes configured, check to see if any are non-default routes
    if (isset($route_details))
    {
      foreach ($route_details as $route)
        // Non-default route, not expected, major error
        if (($route->network != "0.0.0.0") and ($route->mask != "0.0.0.0"))
          LogError("major", "Static routes have been installed (when there should be none) on the following devices", $router_name);
    }
    // No need to continue, we are not expecting non-static defaults, if they've been found we've already logged
    // the error, if they haven't there is no error
    return;
  } 

  // Static non-default routes should be configured (no need to check if $expected_details is set any more)

  // We are expecting a non-default static route but there are no static routes at all
  if (is_null($route_details))
  {
    LogError("major", "No static routes have been installed (when there should be) on the following devices", $router_name);
    return;
  }

  // Static non-default routes should be configured, and at least one static route has been programmed (no need to check $route_details any more)
  $num_static_routes = 0;
  $num_correct_static_routes = 0;

  // Test each route one at a time
  foreach ($route_details as $route)
  {
    $route_description = "$router_name: ip route {$route->network} {$route->mask} {$route->via}";

    // Only care if it is a non-default route, non-default routes checked somewhere else
    if (($route->network != "0.0.0.0") and ($route->mask != "0.0.0.0"))
    {
      // It is a non-default route, count how many there are and whether any are incorrect
      $num_static_routes++;
      // Next hop (or exit interface is incorrect), major error
      if (!in_array($route->via, $expected_details)) 
        LogError("major", "At least one static route has the incorrect next hop or exit interface programmed", $route_description);
      else
        $num_correct_static_routes++;
    } 
  }

  // Fail if no non-default route has been found
  if ($num_static_routes == 0) LogError("major", "No static route has been installed on the following devices", $router_name);
  // Fail if the incorrect number of static non-default route has been found
  if ($num_static_routes == 0) LogError("major", "No default static route has been installed on the following devices", $router_name);
  // Minor problem if more than one correct default route installed
  if ($num_default_routes > 1)  LogError("minor", "Multiple (correct) default static routes configured on the following devices", $router_name);
}

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
  global $configs, $major_error, $bestpractice_error;

  // This line should be configured
  if ($expected_settings !== false)
  {
    if ($line_config->login == false)
      LogError("minor", "You have not enabled 'login' on the following devices/lines", $text_description);
    if (is_null($line_config->password))
      LogError("minor", "You have not specified any password on the following devices/lines", $text_description);
    else if ($line_config->password != $expected_settings)
      LogError("minor", "You have specified the incorrect password on the following devices/lines", "$text_description: You specified \"{$line_config->password }\", expected password is \"$expected_settings\"");
  } else
  // The line should not be configured
  {
    if (($line_config->login) || (isset($line_config->password)))
      LogError("minor", "You have enabled access of the following devices/lines when you were not supposed to", $text_description);
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
#
# Static Routes:
# - Call check_default_routes() to check configuration of default static routes
# - Call check_static_routes() to check configuration of non-default static routes
##########
function check_router_basicconfig($router_name, $config, $expected_details)
{
  // Router Name Checks
  if ($expected_details['hostname'] === true)
  {
    if ($config->hostname === "Router")
      LogError("minor", "You have not configured the hostname on the following devices:", $router_name);
    else if (strtoupper($config->hostname[0]) !== strtoupper($router_name[0]))
      LogError("minor", "You have incorrectly configured the hostname on the following devices:", "$router_name: hostname set to {$config->hostname}");
  }

  // MOTD Checks
  if ($expected_details['motd'] === true)
  {
    if (is_null($config->motd)) LogError("minor", "You have not configured the required MOTD on the following devices:", $router_name);
  } else if ($expected_details['motd'] === false)
  {
    if (isset($config->motd)) LogError("minor", "You configured a MOTD (when you were told NOT to) on the following devices:", $router_name);
  }*/

  // LINE and VTY Access Checks
  if (isset($expected_details['line'])) check_line_access("$router_name(line)", $config->lines['con'], $expected_details['line']);
  if (isset($expected_details['vty'])) check_line_access("$router_name(telnet)", $config->lines['vty'], $expected_details['vty']);

  // Default Static Route Checks
  check_default_routes($router_name, $config->static_routes, (isset($expected_details['default_route']))?(explode(':', $expected_details['default_route'])):(null));

  // Non-default Static Route Checks
}

?>
