<?php

################################################################################
##                >>>>>   CHECK LAYER 3 CONFIGURATIONS   <<<<<                ##
################################################################################
## Contains functions to check various aspects of layer 3 configurations. The ##
## functions that should be called from the main code are:                    ##
##                                                                            ##
## check_layer3_interfaces($device, $switch, $config, $expected_details)      ##
##  $device   - String containing device name, used for logging purposes      ##
##  $switch   - Boolean, true if the device is a switch (needed to determine  ##
##              which checks to run                                           ##
##  $config   - Array containing all interface configurations for this device ##
##  $expected - Array containing list of expected interface configurations    ##
##              for this device                                               ##
##                                                                            ##
## check_layer3_link($interface_configs, $serial_link)                        ##
##  $interface_configs - Array of interface configurations that all belong to ##
##                       to the same layer 3 network/link                     ##
##  $serial_link       - Boolean indicating whether the link is a serial link ##
##                       where the clock rate needs to be set                 ##
##  Function will check whether there are any problems across the multiple    ##
##  interfaces as opposed to checking the individual interfaces in turn       ##
################################################################################

##########
# Checks that the nominated interface has been correctly configured
#
#  details          - A pointer to the NetInterface instance containing
#                     the configuration to check
#  expected_details - A pointer to the InterfaceDetails instance containing
#                     the expected configuration settings for the Interface
#
# There are a number of items to check either sequentially or as a subset of
# other conditions being met. As this function is long and covers a lot of
# tasks, please read the inline comments for descriptions on what we are checking
##########
function check_layer3_interface_config($interface_text, $interface_details, $expected_details)
{
  $long_description = (isset($expected_details['comment']))?("{$expected_details['comment']} ($interface_text):"):"$interface_text";

  //----------------------------------------
  // Check that the interface has been configured
  if ($interface_details->configured == false)
  {
    log_error('layer3', 'interface_not_configured', $long_description);
    return;
  }

  list($address, $mask) = explode("/", $expected_details['address']);
  $network = long2ip(ip2long(trim($address)) & (~(pow(2, 32 - $mask) - 1)));
  $host_id = ip2long($address) - ip2long($network);

  //----------------------------------------
  // Check if the interface is shutdown
  if ((isset($interface_details->shutdown)) && ($interface_details->shutdown))
    log_error('layer3', 'interface_shutdown', $long_description);
  else
    // If not shutdown, check the status
    if ($interface_details->status != "up up")
      log_error('layer3', 'interface_down', "$long_description Actual status (" . $interface_details->status . ")");

  //----------------------------------------
  // Check if an expected IP address has been configured
  if (isset($expected_details['address']))
  {
    // Has an IP address/mask been assigned
    if (isset($interface_details->address))
    {
      // Check that the allocated network/subnet is correct
      if ((strpos($interface_details->network, $network) !== 0) || ($interface_details->slash_mask != $mask))
        log_error('layer3', 'bad_network', "$long_description Configured ({$interface_details->network}/{$interface_details->slash_mask}) where the expected network is ($network/$mask)");

      else
        // Address/mask good, check for correct host_id
        if ($interface_details->host_id != $host_id)
        {
          if ($expected_details['strict_ip'] === true)
            log_error('layer3', 'incorrect_gateway_address', "$long_description Configured Interface IP Address ({$interface_details->address}) instead of the expected (" . long2ip(ip2long($interface_details->network) + $host_id) . ")");
          else
            log_error('layer3', 'incorrect_host_address', "$long_description You configured an Interface IP Address of ({$interface_details->address}/{$interface_details->slash_mask}) which is a host ID of ({$interface_details->host_id}). The expected host ID ($host_id) would have a configured IP address of (" . long2ip(ip2long($interface_details->network) + $host_id) . ")");
        }
    } else
      log_error('layer3', 'no_address', $long_description);
    
  } else
  {
    // Should NOT be configured with an IP address
    if (isset($interface_details->address)) log_error('layer3', 'trunk_ip_assigned', "$long_description You configured ({$interface_details->address}/{$interface_details->slash_mask})");
  }

  //----------------------------------------
  // This interface should be the DCE link, check that it is and that the clock rate is set
  if ((isset($expected_details['dce'])) && (is_null($interface_details->clock)))
    log_error('layer3', 'no_clock_dce', $long_description);  

  //----------------------------------------
  // An interface description should (or should not) exist, check what was actually done
  if (isset($expected_details['description']))
  {
    if (($expected_details['description']) && (is_null($interface_details->description)))
      log_error('layer3', 'description_not_set', $long_description);
    if (!($expected_details['description']) && (!is_null($interface_details->description)))
      log_error('layer3', 'description_set', $long_description);
  }

  //----------------------------------------
  // We expect the "ip ospf network point-to-point" on this interface, check for existance
  if (($expected_details['loopback_ospf']) && ($interface_details->loopback_ospf_good == false))
    log_error('layer3', 'ospf_point_to_point', $long_description);
}

##########
# Checks that the nominated interface has NOT been configured
#
# - If the interface has not been configured, no error and return
# - Otherwise an error, check and log one of the three types of error:
#   o The interface is configured but shutdown
#   o The interface has no IP address but is not shutdown
#   o The interface has an IP address and is not shutdown
##########
function check_layer3_interface_unconfigured($interface_text, $interface_details)
{
  // Check that the interface exists and has been configured
  if ($interface_details->configured == false) return;

  if ((isset($interface_details->shutdown)) && ($interface_details->shutdown))
  {
    log_error('layer3', 'configured_if_shutdown', $interface_text);
    return;
  }

  // Check an IP address has been configured
  if (isset($interface_details->address))
    log_error('layer3', 'iface_enabled', $interface_text);
  else
    log_error('layer3', 'unconfigured_if_enabled', $interface_text);
}

##########
# Check all interfaces on a given device for layer 3 configuration
#
# $device_name      - Name of device for logging
# $is_switch        - Is the device a switch (important as switch physical interfaces cannot be configured at layer 3
# $config           - DB storing all interface configurations for the device
# $expected_details - How should the interfaces for this device be configured
#
# - Create an array ($required_interfaces_not_existing) of all interfaces that we need (in $expected_details) but
#   don't exist in the config (not in $config). This should only be true of virtual interfaces (Loopback, Vlan, etc.)
#   as physical interfaces are always present in the config
# - Create an array of interfaces ($extra_interfaces_existing) that exist in the config but are NOT required (not in
#   $expected_details). This will include physical interfaces that are not used and incorrectly created virtual
#   interfaces
# - Create an array of interfaces ($required_interfaces_existing) that are both needed and configured (in both
#   $config and in $expected_details)
# - Loop through $required_interfaces_not_existing
#   o If the required interface is sub-interface AND the actual parent interface configuration denotes there are
#     sub-interfaces AND a sub-interface exists with the correct VLAN ID then:
#     - Call check_layer3_interface_config() with the actual sub-interface configured on this VLAN
#     - Log an error (bad-practice) where VLAN ID and sub-interface number do not match
#     - Remove the actual sub-interface from the list $extra_interfaces_existing as the interface is not extra and
#       has now been checked
#   o Otherwise we log an unconfigured interface error
# - Loop through $extra_interfaces_existing
#   o If the device is a switch AND it is a physical interface do not check the configuration. The extra interface
#     is present and can't be configured for layer 3 anyway. All extra interfaces on a router and all extra non-virtual
#     interfaces on a switch should be checked that they are not configured - check_layer3_interface_unconfigured()
# - Loop through $required_interfaces_existing and call check_layer3_interface_config() to check that the interface
#   has been correctly configured
##########
function check_layer3_interfaces($device_name, $is_switch, $config, $expected_details)
{
  $required_interfaces_not_existing = array_diff(array_keys($expected_details), array_keys($config));
  $extra_interfaces_existing = array_diff(array_keys($config), array_keys($expected_details));
  $required_interfaces_existing = array_intersect(array_keys($config), array_keys($expected_details));

  // The following required (sub-)interfaces do not exist on the device, major error
  foreach ($required_interfaces_not_existing as $interface)
  {
    // The missing interface is a sub-interface, perhaps it exists but has the wrong name
    if (strpos($interface, '.') !== false)
    {
      $interface_pieces = explode('.', $interface);

      // Does the parent have any sub-interfaces with the VLAN we are expecting, then this is not a missing interface so let's check it
      // and remove interface from list of interfaces that shouldn't exist
      if ((isset($config[$interface_pieces[0]]->trunk)) and (isset($config[$interface_pieces[0]]->trunk[$interface_pieces[1]])))
      {
        $correct_interface = $interface_pieces[0] . '.' . $config[$interface_pieces[0]]->trunk[$interface_pieces[1]];
        check_layer3_interface_config("$device_name: $interface", $config[$correct_interface], $expected_details[$interface]);
        log_error('layer3', 'sub_int_vlan_mismatch', "$device_name($correct_interface): Configured with VLAN {$interface_pieces[1]}");
        $extra_interfaces_existing = array_diff($extra_interfaces_existing, array($correct_interface));
        continue;
      }
    } 
    log_error('layer3', 'iface_not_configured', "{$expected_details[$interface]['comment']} ($device_name: $interface):");
  }

  // The following interfaces should not exist but do (only check if not a switch physical interface)
  foreach ($extra_interfaces_existing as $interface)
    if ((!$is_switch) or (strpos($interface, "Ethernet") === false)) check_layer3_interface_unconfigured("{$expected_details[$interface]['comment']} ($device_name: $interface):", $config[$interface]);

  // The following interfaces do exist and should exist
  foreach ($required_interfaces_existing as $interface)
    check_layer3_interface_config("$device_name: $interface", $config[$interface], $expected_details[$interface]);
}

##########
# Checks a layer 3 link to make sure all configurations do not break layer 3 connectivity across the link
#
# The array of interface configurations are all on the same layer 3 network, so we need to check the following:
#  1) IP Addresses cannot clash for any interfaces
#  2) If it is a serial link, the clock rate must be set on at least one
#     interface
#
# - Loop through all interfaces to check and create some arrays
#   $networks  - array of interface names mapping to allocated network/mask strings
#   $addresses - array of interface names mapping to allocated IP address strings
#   $clock     - array of interface names where the clock was set
# - Check if all interfaces have the same allocated network address
#   o Remove all interfaces from $networks where no address has been configured (this will already by
#     flagged as an error when checking interfaces)
#   o If the number of assigned networks/masks across all interfaces is >1, then the allocated
#     networks are not common across the layer 3 link and we have an error
#     - Create a string of all the mismatching interfaces/networks and log the error
# - Check if all interfaces have been allocated unique IP addresses
#   o Count the instances of each value in $addresses, if correct it should be 1 unless an address
#     has not been configured at all
#   o Ignore unset addresses (already flagged earlier when checking interfaces)
#   o Ignore if the address is unique
#   o If not unique, log the error of a conflicting address
# - If we are asked to check a serial link ($serial_link === true)
#   o Check that the clock has been set on at least one side of the link, if not log an error
# - If PPP should not be set on this link we should return as there is no PPP specifics. Before
#   doing so, if PPP is set on any interfaces, log the errors prior to returning
# - If PPP should be set on this link but isn't on any interface, log error and return without
#   further checks
# - If more than one actual PPP authentication scheme has been set, we have a protocol mismatch,
#   log the error and return ($ppp_protocols) = array mapping protocol name to number of instances
# - If we get here, only one PPP authentication scheme has been set, so we have protocol comonality
# - Determine the actual protocol used and create a string to describe the link (for logging)
# - If PPP has not been configured at both ends of the link, log an error for the unconfigured
#   interface
# - If a particular PPP protocol was requested but the wrong one selected, log an error
# - Loop through each PPP configuration interface on the PPP link
#   o If the password for this interface is OK, the account on the remote device is valid and
#     functional, however there may still be other issues
#     - If a particular username was specified but not used, log an error for the device
#     - If a particular password was specified but not used, log an error for the device
#   o Otherwise the account on the remote device is invalid, however we have to determine HOW it
#     is invalid
#     - If the password is not found, the account does not exist on the remote device, log error
#     - Otherwise, the account exists but has an incorrect password set, log error
##########
function check_layer3_link($interface_configs, $serial_link, $ppp_configs, $ppp_requirements)
{
  foreach ($interface_configs as $description => $config)
  {
    $networks[$description] = (isset($config->network))?("{$config->network}/{$config->slash_mask}"):"no address";
    $addresses[$description] = (isset($config->address))?("{$config->address}"):"no address";
    $clock[$description] = isset($config->clock);
  }

  // Check for mismatch in network addresses
  $unique_networks = array_count_values($networks);
  unset($unique_networks['no address']);
  if (count($unique_networks) > 1)
  {
    $bad_network_list = "";
    foreach (array_keys($unique_networks) as $network) $bad_network_list .= "$network configured on (" . implode(', ', array_keys($networks, $network)) . ")  ";
    log_error('layer3', 'link_network_mismatch', $bad_network_list);
  }

  // Check for clashing addresses on link
  foreach (array_count_values($addresses) as $address => $count)
  {
    if ($address === "no address") continue;
    if ($count === 1) continue;
    log_error('layer3', 'link_conflict_address', "IP Address ($address) is shared by - " . implode(', ', array_keys($addresses, $address)));
  }

  // Serial link, check for clock rate
  if (($serial_link) and (count(array_keys($clock, true)) === 0)) log_error('layer3', 'no_clock_set', "Serial interfaces - " . implode(', ', array_keys($clock)));

  // PPP should not be set, if set log error, either way return, no more checking
  if (is_null($ppp_requirements))
  {
    if (isset($ppp_configs)) log_error('ppp', 'enabled', "Device on link: " . implode(', ', array_keys($ppp_configs)));
    return;
  }

  // PPP should be set, if not, log error and return, no further checking
  if (is_null($ppp_configs))
  {
    foreach (array_keys($interface_configs) as $description) log_error('ppp', 'not_enabled', $description);
    return;
  }
  
  // Check which actual PPP protocols have been configured, if more than one, log error and return
  $ppp_protocols = array_count_values( array_map(function($p) { return $p['protocol']; }, $ppp_configs));
  if (count($ppp_protocols) > 1)
  {
    $message = "";
    foreach ($ppp_configs as $device => $details) $message .= "$device(configured with PPP {$details['protocol']}) ";
    log_error('ppp', 'protocol_conflict', $message);
    return;
  }

  // One PPP protocol configured at both ends of link, let's check what was actually configured
  $actual_protocol = key($ppp_protocols);
  $ppp_link_description = implode(' <-> ', array_keys($interface_configs));

  // PPP is point-to-point, if there are not two devices configured, log an error for the missing interface
  if (count($ppp_configs) !== 2)
    foreach (array_keys($interface_configs) as $description) 
      if (strpos($description, $ppp_configs[key($ppp_configs)]['remote_device']) === 0) log_error('ppp', 'not_enabled', $description);

  // Incorrect PPP protocol, but at least the same at both ends
  if ((isset($ppp_requirements['authentication'])) and ($actual_protocol !== $ppp_requirements['authentication']))
    log_error('ppp', 'incorrect_protocol', "$ppp_link_description: Configured PPP $actual_protocol (expecting PPP {$ppp_requirements['authentication']})");

  // Loop through each interface on PPP link
  foreach ($ppp_configs as $device => $details)
  {
    if ((isset($details['password_ok'])) and ($details['password_ok']))
    {
      // account valid, check if student used specified username and/or password
      if ((isset($ppp_requirements['username'])) and ($ppp_requirements['username']) !== $details['username'])
        log_error('ppp', 'incorrect_username', "$device: You configured username ({$details['username']}) instead of the expected ({$ppp_requirements['username']})");

      if ((isset($ppp_requirements['password'])) and ($ppp_requirements['password']) !== $details['password'])
        log_error('ppp', 'incorrect_password', "$device: You configured password ({$details['password']}) instead of the expected ({$ppp_requirements['password']})");
    } else
    {
      // account is not valid
      if ($details['password'] === null) log_error('ppp', 'no_account', "{$details['remote_device']}: Account for {$details['username']} does not exist");
      else                               log_error('ppp', 'account_invalid_password', "{$details['remote_device']}: Password for {$details['username']} is invalid");
    }
  }
}

?>
