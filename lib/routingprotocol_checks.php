<?php

################################################################################
##           >>>>>   CHECK ROUTING PROTOCOL CONFIGURATIONS   <<<<<            ##
################################################################################
##########
# Check routing configuration to see if any extra protocols have been configured when they should not have
#
# $routing_configs  - Array of actual routing configurations for the network
# $expected_configs - How should the routing protocols for this network be configured
#
# - Create an array ($unexpected_list) of all protocols anywhere not in the list of protocols required by
#   the solution
# - Create $extra_protocols, an array ($extra_protocols[<protocol_name>] = array of devices. Maps all protocols
#   that should not have been installed on the listed devices. Includes protocols that should not be present
#   at all, and protocols that should be present but not on those devices. Does NOT include multiple instances
#   of a correct protocol on a correct device, this will be checked later
#   o Loop through all instances of all protocols that have been configured
#     - Get a list of all devices that protocol is actually configured on (at least one network statement)
#     - If the protocol we are currently examining is in the of all protocols that shouldn't exist, add all
#       devices with this protocol to $extra_protocols
#     - Otherwise, the protocol should exist but may be installed on a device it should not be. Add this subset
#       of devices with this protocol to $extra_protocols
# - Loop through all the found extra protocols that should not exist
#   o Loop through all instances of these protocols and each device this protocol is configured on
#     - Log an error depending on whether the protocol has been programmed or just created without any
#       network statements
##########
function check_extra_irp_protocols($routing_configs, $expected_configs)
{
  // List of protocols implemented anywhere we were not expecting at all
  $unexpected_list = ($expected_configs === null)?(array_keys($routing_configs)):(array_diff(array_keys($routing_configs), array_keys($expected_configs)));

  foreach ($routing_configs as $protocol => $protocol_config)
    foreach ($protocol_config as $instance => $details)
    {
      $devices_with_protocol = array_unique(array_merge(array_keys($details['advertise']), array_keys($details['bad_network_statements'])));

      if (in_array($protocol, $unexpected_list))
        // This protocol is completely unexpected lets add to the list of bad protocols
        $extra_protocols[$protocol] = $devices_with_protocol;
      else
        // This protocol is expected, lets search if it is on any devices it should not be
        $extra_protocols[$protocol] = array_diff($devices_with_protocol, array_keys($expected_configs[$protocol]['advertise']));
    }

  if (count($extra_protocols) === 0) return;

  // Found the extra protocols, lets log errors
  foreach ($extra_protocols as $protocol => $bad_devices)
  {
    foreach ($routing_configs[$protocol] as $instance => $details)
    {
      foreach ($details['advertise'] as $device => $interfaces)
      {
        // If this device is not a bad device for this protocol, don't log an error
        if (!in_array($device, $bad_devices)) continue;

        switch ($protocol)
        {
          case 'rip':   $error_message = "$device: $protocol(version $instance)"; break;
          case 'eigrp': $error_message = "$device: $protocol(AS = $instance)"; break;
          case 'ospf':  $error_message = "$device: $protocol(ID = $instance)"; break;
        }
        if ((count($interfaces) > 0) or (count($details['bad_network_statements'][$device]) > 0))
          log_error('irp', 'bad_protocol', $error_message);
        else
          log_error('irp', 'bad_inconsequential_protocol', $error_message);
      }
    }
  }
}

##########
# Check routing configuration to see if there are any missing protocols that have not been configured
#
# $routing_configs  - Array of actual routing configurations for the network
# $expected_configs - How should the routing protocols for this network be configured
#
# - Create an array ($missing_all_devices) of all protocols that should be configured but have not been
#   on any devices in the network
# - Create $missing_protocols, an array ($missing_protocols[<protocol_name>] = array of devices. Maps all
#   protocols that should have been installed on the listed devices but aren't. Includes protocols that
#   are not present anywhere at all, and protocols that are present but are missing on a required device.
#   o Loop through all instances of all protocols that should be configured
#     - Get a list of all devices that protocol should be configured on
#     - If the protocol we are currently examining is in the of all protocols that has not been configured
#       anywhere, add all devices that should have this protocol to $missing_protocols
#     - Otherwise, the protocol does exist but may be missing on a device where it should not be. Add
#       this subset of devices missing this protocol to $missing_protocols
# - Loop through all the found missing protocols and which devices they are missing on and log an error
#   message
##########
function check_missing_irp_protocols($routing_configs, $expected_configs)
{
  // List of protocols that should be implemented but are not on any devices
  $missing_all_devices = array_diff(array_keys($expected_configs), array_keys($routing_configs));

  foreach ($expected_configs as $protocol => $protocol_config)
  {
    $devices_protocol_expected = array_keys($protocol_config['advertise']);

    if (in_array($protocol, $missing_all_devices))
      // This protocol is completely missing lets add all devices to the list of missing protocols
      $missing_protocols[$protocol] = $devices_protocol_expected;
    else
    {
      $devices_protocol_on = array();
      foreach ($routing_configs[$protocol] as $instance => $details)
        $devices_protocol_on = array_unique(array_merge($devices_protocol_on, array_keys($details['advertise']), array_keys($details['bad_network_statements'])));
      // This protocol is present, lets search if it is missing on any devices
      $missing_protocols[$protocol] = array_diff($devices_protocol_expected, $devices_protocol_on);
    } 
  }

  // Found the missing protocols, lets log errors
  foreach ($missing_protocols as $protocol => $bad_devices)
    foreach ($bad_devices as $device)
      log_error('irp', 'protocol_not_configured', "$device: $protocol");
}

##########
# Check a single instance of a routing protocol configuration across multiple devices
#
# $Protocol        - Protocol name for logging
# $device_list     - Array of device names running a particular instance of the routing protocol
# $instance_config - Routing protocol instance configuration across multiple devices to check
# $expected_config - How should the routing protocol be configured
#
# Other functions check for extra protocols configured, or devices that should not have a particular
# protocol, we only care about correct protocols configured on the correct devices
# - Loop through all the device names
#   o Get a list of interfaces advertised on that device that should not be
#   o Get a list of interfaces not advertised on that device that should be
#   o Log errors for all extra advertised interfaces or missingnetwork interfaces
# - Check automatic summarisation, if it should be disabled, count how many devices have it enabled
#   o Log different errors for (1 - partially broken) and (>1 - completely broked)
# - Check static route redistribution
#   o Determine devices where route redistribution was done where it should not be and devices where
#     it was not done where it should be
#   o Log errors for missing and extra redistributed devices
# - Loop through all bad network statements (those that don't advertise any interfaces) and log errors
##########
function check_protocol_instance($protocol, $device_list, $instance_config, $expected_config)
{
  // Special checks for OSPF
  if ($protocol === 'ospf')
  {
    // Log errors for single area networks not using area 0, multi area networks missing an area 0, or single/multi area networks that should be multi/single
    switch (count($instance_config['ospf_areas']))
    {
      case 0:  // No advertised networks in any area, will pick up the error later on
               break;
      case 1:  if (key($instance_config['ospf_areas']) !== 0) log_error('ospf', 'single_no_area_0', "Your single-area OSPF network is running in area " . key($instance_config['ospf_areas']));
               if (count($expected_config['areas']) > 1) log_error('ospf', 'not_multi_area', "Your single-area OSPF network should be a " . count($expected_config['areas']) . "-area network");
               break;
      default: if (!array_key_exists(0, $instance_config['ospf_areas'])) log_error('ospf', 'multi_no_area_0', "No default (area 0) networks are being advertised");
               if (count($expected_config['areas']) == 1) log_error('ospf', 'not_single_area', "Your multi-area OSPF network should be a single-area network");
               break;
    }

    // Log errors for OSPF links that are not advertised in the same area at both ends of the link
    foreach ($expected_config['links'] as $link)
    {
      $areas = array();

      // For each interface for this link in this OSPF network
      foreach ($link as $interface_details)
      {
        // This interface is not advertised, error logged elsewhere, move to the next interface
        if (is_null($instance_config['advertise'][$interface_details[0]][$interface_details[1]])) continue;

        // What area is this interface it, create an array storing strings describing all interfaces in all areas
        $area = $instance_config['advertise'][$interface_details[0]][$interface_details[1]];
        $areas[$area][] .= "{$interface_details[0]}:{$interface_details[1]}";
      }

      // Log error if number of areas on this link is more than one
      if (count($areas) > 1)
      {
        foreach ($areas as $area => $interface_list) $error_messages[] = "Area $area (" . implode(', ', $interface_list) . ")"; 
        log_error('ospf', 'area_mismatch', implode(" ", $error_messages));
      }
    }

    // Check that all interfaces in one expected group are advertised in the same group
    foreach ($expected_config['areas'] as $area_name => $area_details)
    {
      $areas = array();
      if (isset($area_details['number'])) $area_name = "Area {$area_details['number']}(" . $area_name . ")";

      foreach ($area_details['advertise'] as $device => $interfaces)
        foreach ($interfaces as $interface)
        {
          // This interface is not advertised, error logged elsewhere, move to the next interface
          if (is_null($instance_config['advertise'][$device][$interface])) continue;

          // What area is this interface it, create an array storing strings describing all interfaces in all areas
          $area = $instance_config['advertise'][$device][$interface];
          $areas[$area][] .= "$device:$interface";
        }

      // Log error if number of areas advertising this group is more than one or the actual area is not the required area
      switch (count($areas))
      {
        case 0:  // No interfaces in group advertised, will pick up the error later on
                 break;
        case 1:  if ((isset($area_details['number'])) and (key($areas) != $area_details['number']))
                   log_error('ospf', 'wrong_area', "$area_name: Instead advertised in area " . key($areas));
                 break;
        default: foreach ($areas as $area => $interface_list) $error_messages[] = "Area $area (" . implode(', ', $interface_list) . ")"; 
                 log_error('ospf', 'group_multi_area', "$area_name: You advertised the following areas - " . implode(" ", $error_messages));
                 break;
      }
    }
  }

  foreach ($device_list as $device)
  {
    $extra_networks = array_diff(array_keys($instance_config['advertise'][$device]), $expected_config['advertise'][$device]);
    $missing_networks = array_diff($expected_config['advertise'][$device], array_keys($instance_config['advertise'][$device]));

    foreach ($missing_networks as $interface) log_error('irp', 'net_not_advertised', "$device($interface) using protocol $protocol");
    foreach ($extra_networks as $interface) log_error('irp', 'net_advertised', "$device($interface) using protocol $protocol");
  }

  // Check if split network support is required
  if ((isset($expected_config['split_network'])) and ($expected_config['split_network'] === true))
  {
    switch (count($instance_config['auto_summary_devices']))
    {
      case 0:  break;
      case 1:  log_error('irp', 'auto_summary_enable_inconsequential', "$protocol: " . implode(", ", $instance_config['auto_summary_devices'])); break;
      default: log_error('irp', 'auto_summary_enable', "$protocol: " . implode(", ", $instance_config['auto_summary_devices'])); break;
    }
  }

  // Check for default route redistribution
  if (isset($expected_config['redistribute']))
  {
    if (isset($instance_config['redistribute']))
    {
      $extra_redistributes = array_diff($instance_config['redistribute'], $expected_config['redistribute']);
      $missing_redistributes = array_diff($expected_config['redistribute'], $instance_config['redistribute']);
    } else
    {
      $extra_redistributes = array();
      $missing_redistributes = $expected_config['redistribute'];
    }
  } else
  {
    $extra_redistributes = $instance_config['redistribute'];
    $missing_redistributes = array();
  }

  if (count($extra_redistributes) > 0) log_error('irp', 'redistribute', "$protocol: " . implode(", ", $extra_redistributes));
  if (count($missing_redistributes) > 0) log_error('irp', 'no_redistribute', "$protocol: " . implode(", ", $missing_redistributes));

  // Check for any bad network statesments in our set of instances to check
  foreach ($instance_config['bad_network_statements'] as $device => $statements)
    foreach ($statements as $statement) 
      log_error('irp', 'bad_network_statements', "$device($protocol): $statement");
}

##########
# Check a single instance of a routing protocol configuration across multiple devices
#
# $Protocol        - Protocol name for logging
# $device_list     - Array of device names running a particular instance of the routing protocol
# $instance_config - Routing protocol instance configuration across multiple devices to check
# $expected_config - How should the routing protocol be configured
#
# Other functions check for extra protocols configured, or devices that should not have a particular
# protocol, we only care about correct protocols configured on the correct devices
# - Loop through each routing protocol that should be configured
#   o If not configured on any device, just continue, error logged by check_missing_irp_protocols()
#   o Create a temporary database ($per_device_config) to help determine which instance of the
#     protocol (in case of multiple instances) we should be assessing
#     - If a device has no advertised interfaces, log an error message
#     - Otherwise add device/interface to database
#   o Create $instances_to_check, which protocol instance should we actually assess
#     - Ignore devices where protocol should not exist, error logged by check_extra_irp_protocols()
#     - If multiple - functional - routing protocol instances exist, log an error
#     - Otherwise add the device name and instance number (AS/PID) to $instances_to_check
#   o If there are no valid instances of the protocol to assess, skip to next protocol
#   o Protocol specific checks
#     - If RIP, check the version number is as required, log appropriate errors
#     - If EIGRP, there is more than one device (AS number mismatch) and that the AS number is as
#       required, log appropriate errors
#   o Loop through each configured protocol instance, call check_protocol_instance() to check
#     if it is configured properly
##########
function check_required_irp_protocols($routing_configs, $expected_configs)
{
  // Loop through each protocol that should be configured
  foreach ($expected_configs as $protocol => $protocol_config)
  {
    // Protocol not configured on any device, move onto next protocol
    if (is_null($routing_configs[$protocol])) continue;

    // Create per-device config database, log errors for protocol instances with no advertised networks
    $per_device_config = array();
    foreach ($routing_configs[$protocol] as $instance => $instance_config)
    {
      foreach ($instance_config['advertise'] as $device => $interfaces)
      {
        if (count($interfaces) === 0)
        {
          switch ($protocol)
          {
            case 'rip':   $error_message = "$device: $protocol(version $instance)"; break;
            case 'eigrp': $error_message = "$device: $protocol(AS = $instance)"; break;
            case 'ospf':  $error_message = "$device: $protocol(ID = $instance)"; break;
          }
          log_error('irp', 'bad_inconsequential_protocol', $error_message);
        } else
          $per_device_config[$device][$instance]['interfaces'] = $interfaces;
      }
    }

    // Check for multiple routing protocol instances on each device, find which one to check
    $instances_to_check = array();
    foreach ($per_device_config as $device => $instance_details)
    {
      // Ignore devices where this protocol should not be configured, already checked elsewhere
      if (is_null($expected_configs[$protocol]['advertise'][$device])) continue;

      if (count($instance_details) > 1)
      {
        switch ($protocol)
        {
          // No RIP, can't have multiple copies of RIP
          case 'eigrp': $error_message = "$device: $protocol(AS's configured = "; break;
          case 'ospf':  $error_message = "$device: $protocol(ID's configured = "; break;
        }
        log_error('irp', 'multiple_instances', $error_message . implode(", ", array_keys($instance_details)) . ")");
      } else
        $instances_to_check[key($instance_details)][] = $device;
    }

    // No valid instances, errors already logged move onto next protocol
    if (count($instances_to_check) === 0) continue;

    // RIP specific checks
    if ($protocol === 'rip')
    {
      if ((isset($protocol_config['version'])) and ($protocol_config['version'] != key($instances_to_check)))
        log_error('irp', 'rip_version', "Configured version " . key($instances_to_check) . " instead of required AS {$protocol_config['version']}");
    }

    // EIGRP specific checks
    if ($protocol === 'eigrp')
    {
      if (count($instances_to_check) !== 1)
        foreach ($instances_to_check as $instance => $devices) log_error('irp', 'eigrp_as_mismatch', "AS $instance configured on " . implode(", ", $devices));
      else
        if ((isset($protocol_config['as_number'])) and ($protocol_config['as_number'] != key($instances_to_check)))
          log_error('irp', 'incorrect_eigrp_as', "Configured AS " . key($instances_to_check) . " instead of required AS {$protocol_config['as_number']}");
    }

    // We now have a list of instances and devices to check, let's check them in turn
    foreach ($instances_to_check as $instance => $devices)
      check_protocol_instance($protocol, $devices, $routing_configs[$protocol][$instance], $protocol_config);
  }
}


##########
### OLD REFERENCE CODE FOR OSPF AREA CHECKS
# Checks the configuration of a single interface, parameters are:
#  details          - A pointer to the NetInterface instance containing
#                     the configuration to check
#  expected_details - A pointer to the InterfaceDetails instance containing
#                     the expected configuration settings for the Interface
##########
function check_interface_advertisement($description, $interface_name, $should_be_advertised, $protocol, $advertised_interfaces)
{
  $advertised = false;
  $advertised_areas = Array();

  if ($protocol == "ospf")
  {
    foreach ($advertised_interfaces as $area => $list)
    {
      if (in_array($interface_name, $list))
      {
        $advertised = true;
        $advertised_areas[] = $area;

        if ($area !== 0) LogError("minor", "OSPF - The following networks are not being advertised in area 0", "$description is being advertised in area $area");
      }
    }

  } else
  {
    $advertised = in_array($interface_name, $advertised_interfaces);
  }

  switch ($should_be_advertised)
  {
    case true:  if ($advertised) return $advertised_areas;
                LogError("major", "The following networks are not being advertised using $protocol", $description);
                break;
    case false: if ($advertised) LogError("minor", "You are advertising the following networks using $protocol when you should not be", $description);
  }
}

?>
