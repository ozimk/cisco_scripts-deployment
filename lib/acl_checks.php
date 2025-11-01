<?php

##################################################################################################################################################################
## Need to include the ACL Parser class definitions in order to be able to mark the ACLs                                                                        ##
##################################################################################################################################################################
require_once("{$cisco_configs["lib_path"]}/acl_parser.php");

##################################################################################################################################################################
## check_single_acl($description, $configured_ACL, $expected_rules)                                                                                             ##
##                                                                                                                                                              ##
## Marks a single ACL as configured against a set of provided ACL rules                                                                                         ##
##                                                                                                                                                              ##
##   $description    - String to log in all error messages to differentiate between multiple ACLs                                                               ##
##   $configured_ACL - ACL object as parsed from the router configurations                                                                                      ##
##   $expected_rules - Array containing the individual ACL statements that $configured_ACL should be marked against                                             ##
##                                                                                                                                                              ##
## First we create a solution ACL based on the provided $expected_rules. If $expected_rules is NULL, then the solution ACL should be "permit ip any any" and we ##
## need to log the error that an ACL has been applied when it should not have been                                                                              ##
##                                                                                                                                                              ##
## The next step is to actually mark the ACL against the expected solution                                                                                      ##
##                                                                                                                                                              ##
## Finally, we need to check the results and log appropriate errors which include:                                                                              ##
##  o ACLs don't match - the ACL is incorrect                                                                                                                   ##
##  o ACL not optimal - only relevant if the ACL is correct. This means that there are too many rules in the student ACL                                        ##
##  o Pointless rules - A rule has been entered which will never be matched                                                                                     ##
##  o Pointless post-default rules - A rule has been entered which occurs after a "deny|permit ip any any" rule                                                 ##
##                                                                                                                                                              ##
## Step 2)                                                                                                                                                      ##
##   All actually configured ACLs need to be checked now, whether they should exist or not. Every ACL on the device falls into one of the following categories  ##
##   o Programmed but not used anywhere - not a major problem but should be logged as a "not applied" ACL                                                       ##
##   o Assessed elsewhere in the marking script - currently NAT ACLs, we should ignore them here as they are marked elsewhere                                   ##
##   o Should be applied - We need to mark the ACL against the expected configuration, call check_single_acl() to do so                                         ##
##   o Should not be applied - Not necessarily wrong, a "permit ip any any" ACL is equivalent to NO ACL. Calling check_single_acl() with a NULL solution will   ##
##     perform this check                                                                                                                                       ##
##################################################################################################################################################################
function check_single_acl($description, $configured_ACL, $expected_rules)
{
  // Does the configured ACL actually exist
  if (is_null($configured_ACL)) 
  {
    log_error('acl', 'not_configured', "$description: ACL does not exist");
    return;
  }
  // Create Solution ACL based on expected rules
  $acl_solution = new ACL("Extended");
  if (is_null($expected_rules))
  {
    $acl_solution->add_statement("permit ip any any");
    log_error('acl', 'applied_acl', $description);
  } else
    foreach ($expected_rules as $rule) 
    {
      if ((strpos($rule, "src_any") === 0) || (strpos($rule, "dst_any") === 0))
      {
        $acl_solution->set_any_equiv(substr($rule, 0, 3), trim(next(explode(':', $rule))));
      } else
        $acl_solution->add_statement($rule);
    }

  $acl_solution->finalise();

  // Mark configured ACL
  $acl_check = $configured_ACL->mark_acl($acl_solution);

  // Check to see if ACL is correct, optimal, and whether there are any pointless rules or not
  if ($acl_check['same'])
  {
    if ($acl_check['not_optimal']) log_error('acl', 'acl_not_optimal', "$description: " . $acl_check['not_optimal_message']);
  } else
  {
    log_error('acl', 'acl_incorrect', $description);
  }

  if ($acl_check['pointless_rules']) foreach ($acl_check['pointless_rules_list'] as $rule) log_error('acl', 'acl_pointless_normal', "$description: $rule");

  if ($acl_check['pointless_post_default']) foreach ($acl_check['pointless_post_default_list'] as $rule) log_error('acl', 'acl_pointless_default', "$description: $rule");

  if ($acl_check['not_optimal_prune'])   foreach ($acl_check['unclean_prune_summary'] as $rule) log_error('acl', 'acl_unclean_prune', "$description: $rule");
}

##################################################################################################################################################################
## check_acls($device, $acl_config, $expected_details)                                                                                                          ##
##                                                                                                                                                              ##
## Mark all ACLs installed on a given device against the expected ACLs on that device                                                                           ##
##                                                                                                                                                              ##
##   $device           - Device name for logging purposes                                                                                                       ##
##   $acl_config       - Array containing actual ACL configurations on the device                                                                               ##
##   $expected_details - Array containing the ACL rules that should be installed on the device                                                                  ##
##                                                                                                                                                              ##
## Two basic checks need to be done, we need to look for ACLs that should be installed but aren't, and we need to confirm that all all actually configured ACLs ##
## are correct:                                                                                                                                                 ##
##                                                                                                                                                              ##
## Step 1)                                                                                                                                                      ##
##   If we expect ACLs to be installed, then we first loop through each expected ACL and check to see if it has actually been installed (in $acl_config). If    ##
##   the search for the ACL fails we log an error. At this stage, all ACLs which should be on the device but are not have been dealt with                       ##
##                                                                                                                                                              ##
## Step 2)                                                                                                                                                      ##
##   All actually configured ACLs need to be checked now, whether they should exist or not. Every ACL on the device falls into one of the following categories  ##
##   o Programmed but not used anywhere - not a major problem but should be logged as a "not applied" ACL                                                       ##
##   o Assessed elsewhere in the marking script - currently NAT ACLs, we should ignore them here as they are marked elsewhere                                   ##
##   o Should be applied - We need to mark the ACL against the expected configuration, call check_single_acl() to do so                                         ##
##   o Should not be applied - Not necessarily wrong, a "permit ip any any" ACL is equivalent to NO ACL. Calling check_single_acl() with a NULL solution will   ##
##     perform this check                                                                                                                                       ##
##################################################################################################################################################################
function check_acls($device, $acl_config, $expected_details)
{
  // Search for any ACLs which should exist but don't and log errors.
  // If no ACLs should be configured but are, those will be checked in the next part
  if (isset($expected_details))
    foreach (array_keys($expected_details) as $placement)
    {
      list($applied_interface, $applied_direction) = explode(":", $placement);
      $found = false;
      if (isset($acl_config))
        foreach ($acl_config as $information)
          if ($information['applied'] === $placement) $found = true;

      if (!$found) log_error('acl', 'not_configured', "$device: On $applied_interface, direction ($applied_direction)");
    }

  // Check all actually configured ACLs on the device, determine if they are applied/not-applied, and if they are correct.
  // If no ACLs are configured, skip loop, a check for their existance (if required is done above)
  if (isset($acl_config))
    foreach ($acl_config as $name => $acl_information)
    {
      $applied_to = $acl_information['applied'];

      list($applied_interface, $applied_direction) = explode(":", $applied_to);

      // This ACL has not been applied on the device, log the error and move to next ACL
      if (is_null($acl_information['applied']))
      {
        log_error('acl', 'non_applied_acl', "$device: ACL $name not applied to any interfaces");
        continue;
      }

      // This ACL is a NAT private pool ACL. This is checked elsewhere, move to the next ACL
      if (strpos($applied_to, "nat") === 0) continue;

      // Mark the ACL.
      // If there should not be an ACL on this interface, then $expected_details[$applied_to] will be NULL and the function will check it against a "permit ip any any" rule
      check_single_acl("Device - $device, ACL - $name (applied on '$applied_interface', direction '$applied_direction')", $acl_information['ACL'], $expected_details[$applied_to]);
    }
}

?>
