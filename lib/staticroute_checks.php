<?php

################################################################################
##          >>>>>   CHECK DEVICE STATIC ROUTE CONFIGURATIONS   <<<<<          ##
################################################################################

##########
# Check default static route configuration
#
# $device_name      - Textual representation of device (for logging purposes)
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
function check_default_routes($device_name, $is_switch, $route_details, $expected_details)
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
          log_error('route', 'bad_default', $device_name);
    }
    // No need to continue, we are not expecting static defaults, if they've been found we've already logged
    // the error, if they haven't there is no error
    return;
  } 

  // Static default routes should be configured (no need to check if $expected_details is set any more)

  // We are expecting a default static route but there are no static routes at all
  if (is_null($route_details))
  {
    log_error('route', ($is_switch)?'no_default_on_switch':'no_default_on_device', $device_name);
    return;
  }

  // Static default routes should be configured, and at least one static route has been programmed (no need to check $route_details any more)
  $num_default_routes = 0;
  $num_correct_default_routes = 0;

  // Test each route one at a time
  foreach ($route_details as $route)
  {
    $route_description = "$device_name: Default gateway/route via {$route->via}";

    // Only care if it is a default route, non-default routes checked somewhere else
    if (($route->network == "0.0.0.0") and ($route->mask == "0.0.0.0"))
    {
      // It is a default route, count how many there are and whether any are incorrect
      $num_default_routes++;
      // Next hop (or exit interface is incorrect), major error
      if (in_array($route->via, $expected_details))
        $num_correct_default_routes++;
      else
        log_error('route', 'default_incorrect', $route_description);
    } 
  }

  // Fail if no default route has been found
  if ($num_default_routes == 0) log_error('route', 'no_default', $device_name);
  // Minor problem if more than one correct default route installed
  if ($num_correct_default_routes > 1) log_error('route', 'default_multiple_correct', $device_name);
}

##########
# Check default static route configuration
#
# $device_name      - Textual representation of device (for logging purposes)
# $route_details    - Database of all static routes configured on router
# $expected_details - Array storing required static route settings
#
# 1) If there should be no non-default routes, major error if there any static non-default routes configured
# 2) If there should be non-default routes, major error if there are no static routes configured
# 3) Loop through all the static routes we expect to find
#   - Check all static routes in turn, only care about possible matching routes
#     o Count number default routes programmed and number correct default routes programmed
#     o Major error if next hop IP is incorrect
#   - Major error if no matching route found
#   - Minor error if more than one correct route programmed

##########
function check_static_routes($device_name, $route_details, $expected_details)
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
          log_error('route', 'bad_static', $device_name);
    }
    // No need to continue, we are not expecting non-static defaults, if they've been found we've already logged
    // the error, if they haven't there is no error
    return;
  } 

  // Static non-default routes should be configured (no need to check if $expected_details is set any more)

  // We are expecting a non-default static route but there are no static routes at all
  if (is_null($route_details))
  {
    log_error('route', 'no_static_on_device', $device_name);
    return;
  }

  // Static non-default routes should be configured, and at least one static route has been programmed (no need to check $route_details any more)
  $num_static_routes = 0;
  $num_correct_static_routes = 0;

  // There can be multiple static routes required, thus we loop through what we want and check against what we have
  // rather than the other way around
  foreach ($expected_details as $route_wanted)
  {
    $expected_route = "$device_name: ip route {$route_wanted[0]} {$route_wanted[1]} " . implode('/', array_slice($route_wanted, 2));
    $num_matching_routes = 0;
    $num_correct_matching_routes = 0;

    foreach ($route_details as $route)
    {
      $route_description = "$device_name: ip route {$route->network} {$route->mask} {$route->via}";
  
      if (($route->network == $route_wanted[0]) and ($route->mask == $route_wanted[1]))
      {
        // Found a matching route, count how many there are and whether any are incorrect
        $num_matching_routes++;
        // Found possible match, check next hop/IP
        if (!in_array($route->via, array_slice($route_wanted, 2)))
          log_error('route', 'static_incorrect', $route_description);
        else
          $num_correct_matching_routes++;
      }
    }
    // Fail if no non-default route has been found
    if ($num_matching_routes == 0) log_error('route', 'no_static', $expected_route);
    // Minor problem if more than one correct default route installed
    if ($num_correct_matching_routes > 1) log_error('route', 'static_multiple_correct', $device_name);
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
function check_routes_static($device_name, $is_switch, $config, $expected_details)
{
  // Default Static Route Checks
  check_default_routes($device_name, $is_switch, $config->static_routes, (isset($expected_details['default_route']))?(explode(':', $expected_details['default_route'])):(null));

  // Non-default Static Route Checks
  check_static_routes($device_name, $config->static_routes, (isset($expected_details['static_routes']))?($expected_details['static_routes']):(null));
}

?>
