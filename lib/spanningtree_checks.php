
<?php

################################################################################
##             >>>>>   CHECK SPANNING TREE CONFIGURATIONS   <<<<<             ##
################################################################################
## Contains functions to check spanning tree configurations on a nominated    ##
## set of switches. The function that should be called from the main code is: ##
##                                                                            ##
## check_spanning_tree($STP_configs, $expected)                               ##
##  $STP_configs - Array containing all STP configurations for a set of       ##
##                 switches. The array is indexed by the device name          ##
##  $expected - Array containing list of expected STP configuration options   ##
################################################################################

##########
# Check whether a nominated device for a nominated VLAN is the root bridge
#
#  spanning_tree_configs - Array of STP configurations for a set of switches to check
#  expected_root         - Name of device that should be the root bridge
#  vlan                  - The VLAN ID we are checking for
#
# Since we are expecting a root bridge, that means it should be a forced (not lucky)
# configuration, we also need to check there is intent in setting the bridge.
# - Determine the priority for the root bridge for this VLAN (we could use any
#   switch here as this value should be the same for all devices
# - Count how many switches are set to the root priority (determine intent) and
#   locate the name of the actual root bridge (loop through each STP config)
#   o Increment count of root priorities if necessary
#   o If the current switch is the root, store its name
# - If more than one switch has the root priority, the root bridge is not forced
#   so we have an error. Log a different error message detail depending on if the
#   student was lucky enough to get the root bridge correct
# - Otherwise a root bridge has been forced, check that it is the correct one
##########
function check_spanningtree_rootbridge($spanning_tree_configs, $expected_root, $vlan)
{
  // Get the actual priority of the root bridge so we can later find what was set
  $root_priority = $spanning_tree_configs[$expected_root]->root_priority[$vlan];
  $num_switches_with_root_priority = 0;

  foreach ($spanning_tree_configs as $name => $device_config)
  {
    // Count how many switches have the root priority (should only be one for no errors), if this device is the root, let's save the name for later
    if ($device_config->priority[$vlan] === $root_priority) $num_switches_with_root_priority++;
    if ($device_config->root[$vlan]) $actual_root = $name;
  }

  // Check for errors, if more than one switch has the root priority, major error as root switch has not been forced
  if ($num_switches_with_root_priority > 1)
  {
    if ($actual_root === $expected_root)
      log_error('stp', 'root_not_forced', "VLAN $vlan: $expected_root is correctly the root bridge, but " . ($num_switches_with_root_priority - 1) . " other switches have been configured with the same priority");
    else
      log_error('stp', 'root_not_forced', "VLAN $vlan: $expected_root should be the root bridge, but $actual_root is the root bridge AND " . ($num_switches_with_root_priority - 1) . " other switches have been configured with the same priority");
  } else
    // A root switch has been forced, but it is the wrong one
    if ($actual_root !== $expected_root)
      log_error('stp', 'root_incorrect', "VLAN $vlan: $expected_root should be the root bridge but you configured $actual_root");
}

##########
# Check the spanning tree configuration of a set of switches
#
#  spanning_tree_configs - Array of STP configurations for a set of switches to check
#  expected_details      - Array containing list of expected STP configuration options
#
# - If the 'mode' entry is set in $expected_details, we require a particular STP
#   algorithm. Loop through each config and check that it has been correctly set
# - If the 'root' entry is set we require a particular switch to be the root bridge
#   for all or some VLANs
#   o Explode the list of root bridges into list elements separated by '::'
#   o Loop through each nominated set of root bridges
#     - Explode the set into a list (separated by ':'
#     - The first element is the root bridge name, extract it
#     - The rest of the list is an array of VLAN IDs
#     - If there are no VLAN IDs default is all VLANs. Extract all the VLANs from the
#       actual switch STP config for the nominated root bridge
#     - For each VLAN ID in the list, call check_spanningtree_rootbridge() to check
#       the root bridge configuration for that VLAN
##########
function check_spanning_tree($spanning_tree_configs, $expected_details)
{
  // If a particular spanning tree protocol is required, let's check for it
  if (isset($expected_details['mode']))
    foreach ($spanning_tree_configs as $device_name => $details)
      if ($details->mode !== $expected_details['mode'])
        log_error('stp', 'bad_protocol', "$device_name: You configured STP protocol {$details->mode} instead of {$expected_details['mode']}");

  // If a root bridge is to be checked, we need to check that it is forced
  if (isset($expected_details['root']))
  {
    // Root bridge details are stored in a '::' separated list where each element is a ':' separated pair. The pair is "device:vlan:vlan:vlan...", if only one element then it means all VLANs
    $root_list = explode('::', $expected_details['root']);
    foreach ($root_list as $root_info)
    {
      // Extract the list of VLANs and device name from $root_info
      $vlan_list = explode(':', $root_info);
      $device_name = array_shift($vlan_list);

      // If no VLANs, set VLAN list to all VLANs on one device
      if (count($vlan_list) === 0)
      {
        foreach ($spanning_tree_configs as $details)
        {
          $vlan_list = $vlan_list + array_diff(array_keys($details->priority), array(1));
//          $vlan_list = array_diff(array_keys($details->priority), array(1));
        }
      }

      foreach ($vlan_list as $vlan) check_spanningtree_rootbridge($spanning_tree_configs, $device_name, $vlan);
    }
  }
}

?>
