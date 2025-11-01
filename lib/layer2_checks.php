<?php

################################################################################
##                >>>>>   CHECK LAYER 2 CONFIGURATIONS   <<<<<                ##
################################################################################
## Contains functions to check various aspects of layer 3 configurations. The ##
## functions that should be called from the main code are:                    ##
##                                                                            ##

################################################################################
function check_layer2_accessport($interface_details, $config, $expected_details)
{
                    
  if (isset($config->shutdown) and ((is_null($expected_details['shutdown'])) or ($expected_details['shutdown'] === false)))
  {
    log_error('layer2', 'port_shutdown', $interface_details);
    return;
  }

  if ($config->actual['port_mode'] !== 'access')
  {
    log_error('access', 'not_configured', $interface_details);
  } else
  {
    if ((isset($expected_details['vlan'])) and ($expected_details['vlan'] !== $config->actual['carried_vlan']))
      log_error('access', 'wrong_vlan', "$interface_details: Should be in VLAN {$expected_details['vlan']} but is in VLAN {$config->actual['carried_vlan']}");

  }
}

function check_layer2_trunkport($interface_details, $config, $expected_details)
{
                    
  if (isset($config->shutdown) and ((is_null($expected_details['shutdown'])) or ($expected_details['shutdown'] === false)))
  {
    log_error('layer2', 'port_shutdown', $interface_details);
    return;
  }

  if (is_null($config->actual["status"])) log_error('trunk', 'not_configured', $interface_details);
  else
  switch ($config->actual["status"])
  {
    case "trunking": if ($config->actual["encapsulation"] !== '802.1q') log_error('trunk', 'wrong_encapsulation', "$interface_details: Should be 802.1q but instead is {$config->actual["encapsulation"]}");
                       $required_vlans_not_trunked = array_diff(explode(':', $expected_details["required_vlan"]), $config->actual["carried_vlan"]);
                       $extra_vlans_trunked = array_diff($config->actual["carried_vlan"], explode(':', $expected_details["required_vlan"]));

                       if (count($required_vlans_not_trunked)) 
                         log_error('trunk', 'missing_vlans', "$interface_details: VLANs (" . implode(', ', $required_vlans_not_trunked) . ") not being trunked");

                       if ((isset($expected_details["vlan_strict"])) and ($expected_details["vlan_strict"]) and (count($extra_vlans_trunked)))
                         log_error('trunk', 'extra_vlans', "$interface_details: VLANs (" . implode(', ', $extra_vlans_trunked) . ") are being trunked when they shouldn't be");

                       if (isset($expected_details["native_vlan"]))
                       {
                         // Native VLAN should be set, check value and check if it matches with other side of link
                         $pieces = explode(':', $expected_details["native_vlan"]);
                         if ($config->actual["native_vlan"] !== $pieces[0])
                           log_error('trunk', 'incorrect_native_vlan', "$interface_details: Native VLAN is {$config->actual["native_vlan"]} when it should be {$pieces[0]}");

                         echo "Need to check that native VLAN is equal to {$pieces[1]}, {$pieces[2]}\n";
                       }
                     break;
      default:          log_error('trunk', 'not_configured', "$interface_details: Configured in \'{$config->actual["status"]}\' mode");
  }
}

function check_layer2_interface($interface_details, $config, $expected_details)
{
  if ((isset($expected_details['shutdown'])) and ($expected_details['shutdown'] === true) and (is_null($config->shutdown)))
    log_error('layer2', 'not_shutdown',  $interface_details);


  switch ($expected_details['mode'])
  {
    // Should be no switchport mode configuration, if any is set then it is a minor error
    case 'default': if (isset($config->port_mode)) log_error('layer2', 'port_mode_configured', $interface_details);
                    break;

    // Should be an access port, major error if not set OR set to different mode OR access vlan is incorrect
    case 'access':  
                    if (isset($config->shutdown) and ((is_null($expected_details['shutdown'])) or ($expected_details['shutdown'] === false)))
                    {
//                      var_dump($config->shutdown); var_dump($expected_details['shutdown']);
                      log_error('layer2', 'port_shutdown', $interface_details);
                      return;
                    }
                    if (is_null($config->actual['port_mode'] !== 'access')) log_error('access', 'not_configured', $interface_details);
                    else
                      switch ($config->actual['port_mode'])
                      {
                        case 'access': if ((isset($expected_details['vlan'])) and ($expected_details['vlan'] !== $config->actual['carried_vlan']))
                                         log_error('access', 'wrong_vlan', "$interface_details: Should be in VLAN {$expected_details['vlan']} but is in VLAN {$config->actual['carried_vlan']}");
                                       break;
                        default:       log_error('access', 'not_configured', "$interface_details: Configured in \'{$config->actual['port_mode']}\' mode");
                                       break;                    
                    }
                    break;

    case 'trunk':   
                    if (isset($config->shutdown) and ((is_null($expected_details['shutdown'])) or ($expected_details['shutdown'] === false)))
                    {
//                      var_dump($config->shutdown); var_dump($expected_details['shutdown']);
                      log_error('layer2', 'port_shutdown', $interface_details);
                      return;
                    }
                    if (is_null($config->actual["status"])) log_error('trunk', 'not_configured', $interface_details);
                    else
                    switch ($config->actual["status"])
                    {
                      case "trunking": if ($config->actual["encapsulation"] !== '802.1q') log_error('trunk', 'wrong_encapsulation', "$interface_details: Should be 802.1q but instead is {$config->actual["encapsulation"]}");
                                       $required_vlans_not_trunked = array_diff(explode(':', $expected_details["required_vlan"]), $config->actual["carried_vlan"]);
                                       $extra_vlans_trunked = array_diff($config->actual["carried_vlan"], explode(':', $expected_details["required_vlan"]));

                                       if (count($required_vlans_not_trunked)) 
                                         log_error('trunk', 'missing_vlans', "$interface_details: VLANs (" . implode(', ', $required_vlans_not_trunked) . ") not being trunked");

                                       if ((isset($expected_details["vlan_strict"])) and ($expected_details["vlan_strict"]) and (count($extra_vlans_trunked)))
                                         log_error('trunk', 'extra_vlans', "$interface_details: VLANs (" . implode(', ', $extra_vlans_trunked) . ") are being trunked when they shouldn't be");

                                       if (isset($expected_details["native_vlan"]))
                                       {
                                         // Native VLAN should be set, check value and check if it matches with other side of link
                                         $pieces = explode(':', $expected_details["native_vlan"]);
                                         if ($config->actual["native_vlan"] !== $pieces[0])
                                           log_error('trunk', 'incorrect_native_vlan', "$interface_details: Native VLAN is {$config->actual["native_vlan"]} when it should be {$pieces[0]}");

                                         //echo "Need to check that native VLAN is equal to {$pieces[1]}, {$pieces[2]}\n";
                                       }
                                       break;
                      default:          log_error('trunk', 'not_configured', "$interface_details: Configured in \'{$config->actual["status"]}\' mode");
                    }
                    break;
  }
}


function check_layer2_interface_security($interface_details, $config, $expected_details)
{
  if (is_null($expected_details['security'])) return;

  // Is port-security enabled 
  if ($config->security_enable != true)
  {
    log_error('switch_security', 'not_configured', "$interface_details");
    return;
  }

  // Security is set, lets check the individual settings
  //$required_settings = explode(":", $expected_details['security']);
  $actual_settings = (array) $config;

  foreach (explode('::', $expected_details['security']) as $security_option) 
  {
    list($option, $setting) = explode(':', $security_option, 2);

    if ((strlen($setting)) and ($actual_settings[$option] !== $setting))
      log_error('switch_security', 'incorrect_setting', "$interface_details: Security option \'" . str_replace('_', '-', $option) . "\' set to ({$actual_settings[$option]}) instead of the expected ($setting)");
  }
}

##########
# Checks all layer 2 ports on the nominated device
#
# $device_name      - Name of device for logging
# $config           - DB storing all interface configurations for the device
# $expected_details - How should the interfaces for this device be configured
#
# - Loop through all actual interfaces on the device
#   o If the interface doesn't have 'Ethernet' in the name, it is a virtual interface,
#     skip the check
#   o Call check_layer2_interface() to check the interface configuration. If there is
#     no actual expected configuration for the nominated interface, provide the default
#     expected configuration
##########
function check_layer2ports($device_name, $config, $expected_details)
{
  foreach ($config as $interface => $interface_config)
  {
    if (strpos($interface, "Ethernet") === false) continue;

    check_layer2_interface("$device_name($interface)", $interface_config, (isset($expected_details[$interface]))?$expected_details[$interface]:$expected_details['default']);
    check_layer2_interface_security("$device_name($interface)", $interface_config->security, (isset($expected_details[$interface]))?$expected_details[$interface]:$expected_details['default']);
  }
}

##########
# Check all VLAN configuration on a switch
#
# $device_name      - Name of device for logging
# $config           - DB storing all VLAN configurations for the device
# $expected_details - How should the VLANs for this device be configured
#
# - Create an array with a list of expected VLANs that have not been created in the switch
# - Create an array with a list of VLANs that have been created that are not required
# - Create an array with a list of all created VLANs that should be created
# - Loop through array 1, log an error for all non-existing required VLANs
# - Loop through array 2, log an error for all extra, unrequired VLANs, ignoring VLAN1 and VLAN>1001
# - Loop through array 3, these VLANs should exist and do, check for other issues
#   o If the VLAN is inactive, log an error
#   o If the VLAN has not been named, log an error
##########
function check_layer2vlans($device_name, $config, $expected_details)
{
//  if (is_null($config)) { LogError("major", "VLAN Information not captured on the following switches", $device_name); return; }
  if (is_null($config)) { log_error('vlan', 'information_not_captured', $device_name); return; }

  if (is_null($expected_details))
  {
    $required_vlans_not_created = array();
    $extra_vlans_created = array_keys($config);
    $required_vlans_created = array();
  } else
  {
    $required_vlans_not_created = array_diff(array_keys($expected_details), array_keys($config));
    $extra_vlans_created = array_diff(array_keys($config), array_keys($expected_details));
    $required_vlans_created = array_intersect(array_keys($config), array_keys($expected_details));
  }

  foreach ($required_vlans_not_created as $vlan_id)
    log_error('vlan', 'not_created', "$device_name: VLAN($vlan_id:{$expected_details[$vlan_id]}) has not been created");

  foreach ($extra_vlans_created as $vlan_id)
  {
    if (($vlan_id == '1') or ($vlan_id > 1001)) continue;
    log_error('vlan', 'extra_vlan', "$device_name: VLAN($vlan_id:{$config[$vlan_id]->name}) has been created when it should not have been");
  }

  foreach ($required_vlans_created as $vlan_id)
  {
    if ($config[$vlan_id]->status !== "active") log_error('vlan', 'not_active', "$device_name: VLAN($vlan_id:{$config[$vlan_id]->name}) should be active");

    if (strtoupper($config[$vlan_id]->name[0]) !== strtoupper($expected_details[$vlan_id][0]))
      log_error('vlan', 'bad_name', "$device_name: VLAN($vlan_id:{$config[$vlan_id]->name}) should be named {$expected_details[$vlan_id]}");
  }
}

##########
# Check both ends of the provided trunking link
#
# $interface_configs - Array of interface configurations (from various devices) at
#                      each end of a trunking link (should be only two devices)
#
# There should only be two interfaces on the trunk link, the user may specify more
# in the config but we don't check for that.
# - Loop through all (both) interfaces in the link
#   o If the status is not set, then trunking is not configured on this link so
#     abort the test (unconfigured trunk is marked elsewhere)
#   o Create arrays of all trunking statuses, all encapsulation methods and all
#     native VLANs (indexed by device_name:interface)
#   o Assemble potential error messages from the interface details in case we
#     detect an error later
# - If there is more than one status type in the interfaces, the trunking link is not
#   up, log error
# - If there is more than one encapsulation method in the interfaces, the trunking
#   link broken, log error
# - If the native VLAN not the same on all interfaces, log error (actual native VLAN
#   is checked when the interface is individually checked)
##########
function check_layer2_link($interface_configs)
{
  foreach ($interface_configs as $description => $config)
  {
    if (is_null($config->actual['status'])) return;

    $trunk_status[$description]  = $config->actual["status"];
    $encapsulation[$description] = $config->actual["encapsulation"];
    $native_vlans[$description]  = $config->actual["native_vlan"];

    $error_message['encapsulation'] .= "$description (encapsulation = {$config->actual["encapsulation"]})  ";
    $error_message['native_vlans']  .= "$description (native VLAN = {$config->actual["native_vlan"]})  ";
  }

  // Check that both ends of link are actually trunking
  if (count(array_count_values($trunk_status)) > 1)
    log_error('layer2_link', 'not_trunk_both_ends', implode(' <-> ', array_keys($interface_configs)));

  // Check that encapsulation method is same at both ends of link
  if (count(array_count_values($encapsulation)) > 1)
    log_error('layer2_link', 'encapsulation_mismatch', $error_message['encapsulation']);

  // Check that native VLAN is same at both ends of link
  if (count(array_count_values($native_vlans)) > 1)
    log_error('layer2_link', 'native_vlan_mismatch', $error_message['native_vlans']);
}

?>
