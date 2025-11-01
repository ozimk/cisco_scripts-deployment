<?php

################################################################################
##               >>>>>   CHECK INTERFACE CONFIGURATIONS   <<<<<               ##
################################################################################
##########
# Checks the configuration of a single interface, parameters are:
#  details          - A pointer to the NetInterface instance containing
#                     the configuration to check
#  expected_details - A pointer to the InterfaceDetails instance containing
#                     the expected configuration settings for the Interface
##########
function check_interface_config($interface_text, $interface_details, $expected_details)
{
  // Check that the interface exists and has been configured
  if ((is_null($interface_details)) || ($interface_details->configured == false))
  {
    LogError("major", "Interfaces not configured for the following networks", $long_description);
    return;
  }

  list($address, $mask) = explode("/", $expected_details['address']);
  $network = long2ip(ip2long(trim($address)) & (~(pow(2, 32 - $mask) - 1)));
  $host_id = ip2long($address) - ip2long($network);
  $long_description = "{$expected_details['comment']} ($interface_text):";

  // Check if the interface is shutdown
  if ((isset($interface_details->shutdown)) && ($interface_details->shutdown))
    LogError("major", "Interfaces still \"shutdown\"", $long_description);
  else
    // If not shutdown, check the status
    if ($interface_details->status != "up up")
      LogError("major", "Interfaces not up", "$long_description Actual status (" . $interface_details->status . ")");

  // Check an IP address has been configured
  if (isset($interface_details->address))
  {
    // Check that the allocated network/subnet is correct
    if ((strpos($interface_details->network, $network) !== 0) || ($interface_details->slash_mask != $mask))
      LogError("major", "Incorrect network address/mask allocated for the following networks", "$long_description Configured ({$interface_details->network}/{$interface_details->slash_mask}) where the expected network is ($network/$mask)");
    else
      // Network OK, now lets check the host_id
      if ($interface_details->host_id != $host_id)
      {
        if ($expected_details['strict_ip'] === true)
          LogError("major", "Incorrect IP Addresses configured on a shared network link", "$long_description Configured Interface IP Address ({$interface_details->address}) instead of the expected (" . long2ip(ip2long($interface_details->network) + $host_id) . ")");
        else
          LogError("minor", "Incorrect IP Addresses configured for the following networks", "$long_description You configured an Interface IP Address of ({$interface_details->address}/{$interface_details->slash_mask}) which is a host ID of ({$interface_details->host_id}). The expected host ID ($host_id) would have a configured IP address of (" . long2ip(ip2long($interface_details->network) + $host_id) . ")");
      }
  } else
    LogError("major", "No IP Addresses configured for the following networks", $long_description);

  // Serial DCE link
  if ((isset($expected_details['dce'])) && (is_null($interface_details->clock)))
    LogError("major", "Clock rate not set on DCE interface of serial Link", $long_description);  

  // Confirm that an interface description has been set (if required)
  if (isset($expected_details['description']))
  {
    if (($expected_details['description']) && (is_null($interface_details->description)))
      LogError("minor", "No Interface decription set (required) for the following networks", $long_description);
    if (!($expected_details['description']) && (!is_null($interface_details->description)))
      LogError("minor", "Interface description set (when it should not be) for the following networks", $long_description);
  }

  // Confirm that if we are using OSPF, the correct advertising is done for loopbacks
  if (($expected_details['loopback_ospf']) && ($interface_details->loopback_ospf_good == false))
    LogError("minor", "You did not type in the required command (ip ospf network point-to-point) for the following networks", $long_description);
}

##########
# Checks that the nominated interface has NOT been configured
# - Major error if an address is configured
# - Minor error if it is up/partially configured but no address has been configured
##########
function check_interface_unconfigured($interface_text, $interface_details)
{
  // Check that the interface exists and has been configured
  if ((is_null($interface_details)) || ($interface_details->configured == false)) return;

  if ((isset($interface_details->shutdown)) && ($interface_details->shutdown))
  {
    LogError("minor", "The following interfaces are \"shutdown\" but have been configured", $interface_text);
    return;
  }

  // Check an IP address has been configured
  if (isset($interface_details->address))
    LogError("major", "You have configured the following interfaces when you should not have", $interface_text);
  else
    LogError("minor", "You have incorrectly enabled the following (unconfigured) interfaces", $interface_text);
}

##########
# The two nominated interface strings (format - "router_name:interface_name") 
# form a network link, so we need to check two more issues:
#  1) IP Addresses cannot clash
#  2) If it is a serial link, the clock rate must be set on at least one
#     interface
##########
function check_link_config($description, $interface_details1, $interface_details2, $serial_link)
{
  // Only need to check link if IP addresses have been configured on both sides
  if ((is_null($interface_details1->address)) || (is_null($interface_details2->address))) return;

  // Check for clashing IP addresses on link
  if ($interface_details1->address == $interface_details2->address)
    LogError("major", "IP Addresses on either side of network link are the same", "$description: Both set to " . $interface_details1->address);  

  // Serial link, check for clock rate
  if (($serial_link) && (is_null($interface_details1->clock)) && (is_null($interface_details2->clock)))
    LogError("major", "Clock rate not set on DCE interface of serial Link", $description);  
}

?>
