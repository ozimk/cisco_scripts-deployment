<?php

##################################################################################################################################################################
## check_service_dhcp($device, $dhcp_config, $expected_details)                                                                                                 ##
##                                                                                                                                                              ##
## Checks the NAT configuration on a single device against an expected configuration                                                                            ##
##                                                                                                                                                              ##
##   $device           - Device name, for logging purposes                                                                                                      ##
##   $dhcp_config      - A copy of the actual DHCP configuration on the device                                                                                  ##
##   $expected_details - An array containing the expected DHCP configurations  indexed by address/mask pools                                                    ##
##                                                                                                                                                              ##
## Step 1) Perform some basic checks:                                                                                                                           ##
##         a) If there is no DHCP configuration at all just return as this is the end of the check. However log an error if there should have been a            ##
##            configuration                                                                                                                                     ##
##         b) Now we know there is a configuration, however if there should not be then log an error and return                                                 ##
## Step 2) For any configured excluded addresses that do not belong to an actual pool, log an error                                                             ##
## Step 3) For any existing pools that don't provide any addresses, log an error                                                                                ##
## Step 4) Determine all pools that should exist but don't, pools that shouldn't exist but do, and pools that should exist AND do exist                         ##
## Step 5) Log an error for each pool that should exist but doesn't                                                                                             ##
## Step 6) Log an error for each pool that shouldn't exist but does                                                                                             ##
## Step 7) For each pool that should and does exist, check the settings an log errors for                                                                       ##
##         a) Excluded IP addresses that shouldn't be excluded                                                                                                  ##
##         b) Non-excluded IP addresses that should be excluded                                                                                                 ##
##         c) Any other setting that should be set but either isn't or is incorrect                                                                             ##
##################################################################################################################################################################
function check_service_dhcp($device, $dhcp_config, $expected_details)
{
  // No DHCP config exists, if there should be log error, either way no point to continue checking, return
  if (is_null($dhcp_config['networks'])) 
  {
    if (isset($expected_details)) log_error('dhcp', 'not_configured', $device);
    return;
  }

  // Config exists but it shouldn't, log error and return
  if (is_null($expected_details))
  {
    log_error('dhcp', 'configured', $device);
    return;
  }

  // Log errors for any excluded IP addresses that don't belong to any pool
  foreach ($dhcp_config['bad_exclusions']['excluded'] as $address)
    log_error('dhcp', 'bad_exclusion', "$device: $address");

  // Log errors for DHCP pools that don't include any IP addresses
  if (isset($dhcp_config['empty_pools']))
    foreach ($dhcp_config['empty_pools'] as $pool)
      log_error('dhcp', 'empty_pool', "$device: {$pool->pool_name}");

  // Find pools that are required but don't exist, pools that exist but are not required, and pools that exist and are required
  $required_networks_not_advertised = array_diff(array_keys($expected_details), array_keys($dhcp_config['networks']));
  $extra_networks_advertised = array_diff(array_keys($dhcp_config['networks']), array_keys($expected_details));
  $required_networks_existing = array_intersect(array_keys($dhcp_config['networks']), array_keys($expected_details));

  // The following required DHCP pools do not exist but should
  foreach ($required_networks_not_advertised as $network)
    log_error('dhcp', 'missing_pool', "$device($network)");

  // The following DHCP pools exist but shouldn't
  foreach ($extra_networks_advertised as $network)
    log_error('dhcp', 'extra_pool', "$device($network)");

  // The following pools do exist and should exist, need to check the details
  foreach ($required_networks_existing as $network)
  {
    // Only check for what should be configured, ignore the rest
    foreach ($expected_details[$network] as $param => $value) 
    {
      // Excluded addresses need to be treated differently as it is an array of values instead of a single value
      if ($param === 'excluded')
      {
        // Log errors for addresses that should be excluded but aren't, and vice-versa
        if (isset($dhcp_config['networks'][$network]['excluded']))
        {
          $missing_exclusions = array_diff($value, $dhcp_config['networks'][$network]['excluded']);
          $extra_exclusions = array_diff($dhcp_config['networks'][$network]['excluded'], $value);
        } else
        {
          $missing_exclusions = $value;
          $extra_exclusions = array();
        }
        if (count($missing_exclusions)) log_error('dhcp', 'pool_property', "$device($network): The following IP addresses should be excluded but aren't - " . implode(', ', $missing_exclusions));
        if (count($extra_exclusions)) log_error("dhcp", 'pool_property', "$device($network): The following IP addresses should NOT be excluded but are - " . implode(', ', $extra_exclusions));
      } else
        // Not excluded addresses, check the value and log error if incorrect
        if ($dhcp_config['networks'][$network][$param] !== $value) log_error('dhcp', 'pool_property', "$device($network): Property \'$param\' should be set to \'$value\' but instead is set to {$dhcp_config['networks'][$network][$param]}");
    }
  }
}

##################################################################################################################################################################
## check_service_nat($device, $nat_config, $acl_config, $expected_details)                                                                                      ##
##                                                                                                                                                              ##
## Checks the NAT configuration on a single device against an expected configuration                                                                            ##
##                                                                                                                                                              ##
##   $description    - String to log in all error messages to differentiate between multiple ACLs                                                               ##
##   $configured_ACL - ACL object as parsed from the router configurations                                                                                      ##
##   $expected_rules - Array containing the individual ACL statements that $configured_ACL should be marked against                                             ##
##                                                                                                                                                              ##
## Step 1) If NAT is *NOT* configured but should be, then simply log the appropriate error and return (not returning means NAT has been configured              ##
## Step 2) If NAT should not be configured (but has), then simply log the appropriate error and return                                                          ##
## Step 3) Check each individually configured NAT pool on the device in turn:                                                                                   ##
##         a) If no ACL has been configured for the NAT, there is no inside range of addresses. Log error and continue to next configured pool                  ##
##         b) A NAT ACL is configured, but the actual ACL does not exist. Log error and continue to the next configured pool                                    ##
##         c) Flag that we have found at least one valid pool                                                                                                   ##
##         d) Check the private(inside) side configuration of the NAT                                                                                           ##
##            The inside is managed by an ACL, we construct a solution ACL instance based on the private address ranges and compare it with the assigned ACL to ##
##            for the pool. Appropriate errors are logged based on the return values when checking the ACL                                                      ##
##         e) Check the public(outside) side configuration of the NAT                                                                                           ##
##            The configured public address range is checked to see if it is smaller than the required range, or outside the required range. The public side    ##
##            prefix is checked. Whether or not we require "overloading" is checked                                                                             ##
##         f) Check the NAT interface configurations                                                                                                            ##
##            Check all interfaces that have been configured with NAT on the device and log errors if they have been configured when they shouldn't, the have   ##
##            not been configured when they should, or are configured in the wrong direction                                                                    ##
## Step 4) If no valid pool was configured, log the error                                                                                                       ##
##################################################################################################################################################################
function check_service_nat($device, $nat_config, $acl_config, $expected_details)
{
  // No NAT config exists, if there should be log error, either way no point to continue checking, return
  if (is_null($nat_config)) 
  {
    if (isset($expected_details)) log_error('nat', 'not_configured', $device);
    return;
  }

  // Config exists but it shouldn't, log error and return
  if (is_null($expected_details))
  {
    log_error('nat', 'configured', $device);
    return;
  }

  $valid_pool_found = false;

  // Search each nat pool configured on the router trying to find at least one valid pool
  foreach ($nat_config as $poolname => $configured_details)
  {
    // The nat pool exists, but there was no "ip nat inside source list" command, as such there is no inside_ACL configured on this pool, log error and continue to next pool
    if (!array_key_exists('private', $configured_details))
    {
      log_error('nat', 'no_inside_acl', "$device: $poolname");
      continue;
    }

    // The nat pool exists, and an ACL has been configured on the pool, but the actual ACL itself was not configured on the router, log error and continue to next pool
    if (!array_key_exists('ACL', $acl_config[$configured_details['private']['acl']]))
    {
      log_error('nat', 'no_inside_acl', "$device: ACL ({$configured_details['private']['acl']}) configured for $poolname does not exist");
      continue;
    }

    // We found at least one valid nat pool, set flag
    $valid_pool_found = true;

    // CHECK THE PRIVATE SIDE OF THE NAT CONFIGURATION
    // Copy some variables to aid readability
    $private_settings = $configured_details['private'];
    $private_expected = $expected_details['private'];

    // Create Solution ACL based on configured private range of addresses
    $nat_acl_solution = new ACL("Extended");
    foreach ($private_expected as $private_range)
    {
      list($address, $slash) = explode("/", $private_range);
      $nat_acl_solution->add_statement("permit $address " . long2ip(pow(2, 32 - $slash) - 1));
    }
    $nat_acl_solution->finalise();

    $acl_check = $acl_config[$private_settings['acl']]['ACL']->mark_acl($nat_acl_solution);

    // Check to see if ACL is correct, optimal, and whether there are any pointless rules or not
    if ($acl_check['same'])
    {
      if ($acl_check['not_optimal']) log_error('nat', 'acl_not_optimal', $private_settings['acl'] . ": " . $acl_check['not_optimal_message']);
    } else
    {
      log_error('nat', 'acl_incorrect', $private_settings['acl']);
    }

    if ($acl_check['pointless_rules']) foreach ($acl_check['pointless_rules_list'] as $rule) log_error('nat', 'acl_pointless_normal', $rule);

    if ($acl_check['pointless_post_default']) foreach ($acl_check['pointless_post_default_list'] as $rule) log_error('nat', 'acl_pointless_default', $rule);

    // CHECK THE PUBLIC SIDE OF THE NAT CONFIGURATION
    // Copy some variables to aid readability
    $public_settings = $configured_details['public'];
    $public_expected = $expected_details['public'];
  
    $range_error_message = "Configured a pool range of ({$public_settings['first']}-{$public_settings['last']}), required range was ({$public_expected[0]}-{$public_expected[1]})";

    // Check to see if the range of public IP addresses are too small or too big and log appropriate error message
    if ((ip2long($public_settings['first']) < ip2long($public_expected[0])) || ip2long($public_settings['last']) > ip2long($public_expected[1]))
      log_error('nat', 'pool_range_outside', $range_error_message);
    else if ((ip2long($public_settings['first']) !== ip2long($public_expected[0])) || ip2long($public_settings['last']) !== ip2long($public_expected[1]))
      log_error('nat', 'pool_range_inside', $range_error_message);

    // Check to see if the public IP prefix is incorrect and log appropriate error message
    if ($public_settings['prefix'] != $public_expected[2])
      log_error('nat', 'pool_prefix', "Configured a pool prefix of /{$public_settings['prefix']}, required prefix was /{$public_expected[2]}");

    // Check to see if the public IP addresses should/should not be overloaded and log appropriate error message
    if ((array_key_exists('overload', $expected_details)) &&  ($expected_details['overload'] !== $configured_details['overload']))
      log_error('nat', ($expected_details['overload'])?'pool_not_overloaded':'pool_overloaded', "$device: $poolname");

    // CHECK THE PER-INTERFACE CONFIGURATION FOR NAT INFORMATION
    foreach ($configured_details['interfaces'] as $direction => $configs)
    {
      $required_not_configured = array_diff($expected_details['interfaces'][$direction], $configs);
      $not_required_configured = array_diff($configs, $expected_details['interfaces'][$direction]);

      foreach ($required_not_configured as $interface) log_error('nat', 'interface_not_configured', "$interface should be configured as NAT $direction");
      foreach ($not_required_configured as $interface) log_error('nat', 'interface_configured', "$interface has been configured as NAT $direction");
    }

  }

  // We've checked all pools, if no valid pool was ever found, log the error
  if (!$valid_pool_found) log_error('nat', 'no_valid_pool', $device);
}

?>
