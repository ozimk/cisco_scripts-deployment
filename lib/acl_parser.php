<?php

##################################################################################################################################################################
## class ACLRule                                                                                                                                                ##
##                                                                                                                                                              ##
## Object that represents a single ACL rule managing action (permit/deny), protocol (ip/icmp/tcp/udp), source IP address ranges, destination IP address ranges, ##
## source port ranges (0-65535 for ip and icmp) and destination port ranges (0-65535 for ip and icmp). Also can determine sort order between two ACLRule        ##
## instances, cut down an rule instance so its ranges do not overlap with an existing rule, and merge two rules together if they are adjacent                   ##
##                                                                                                                                                              ##
## The public methods on this class are:                                                                                                                        ##
##                                                                                                                                                              ##
## Methods:                                                                                                                                                     ##
##  sort_compare($a, $b):                Manage sort order to allow a list of ACLRule instances to be consistently ordered. Passed to a usort() function call   ##
##  create_nonoverlapping_rules($other): Change the current ACLRule ($this) so that it's packet matching ranges do NOT overlap with the ranges matched by an    ##
##                                       existing ACLRule ($other). Handles comparing and overlaps between protocol types as well. Used to sanitise a rule      ##
##                                       prior to adding to a list to ensure there are no overlapping rules                                                     ##
##  merge($other):                       Try to merge the ranges of an existing rule ($other) into the current rule ($this). Returns true if the rules are      ##
##                                       adjacent and a merge has been completed. Handles merges dealing with different protocols correctly. Used to see if any ##
##                                       existing rules could be merged into the new rule before the new rule is added to a list. The return value is used to   ##
##                                       determine if the existing rule needs to be removed from the list (because it was sub-sumed by the new rule ($this)     ##
##################################################################################################################################################################
class ACLRule
{
  // Private member variables
  private $src_ip1, $src_ip2, $src_port1, $src_port2, $dst_ip1, $dst_ip2, $dst_port1, $dst_port2, $protocol, $permit;

  ################################################################################################################################################################
  ## Constructor($src_ip1, $src_ip2, $src_port1, $src_port2, $dst_ip1, $dst_ip2, $dst_port1, $dst_port2, $protocol, $permit)                                    ##
  ##                                                                                                                                                            ##
  ## All internal member variables are initialised to the provided parameters                                                                                   ##
  ################################################################################################################################################################
  public function __construct($src_ip1, $src_ip2, $src_port1, $src_port2, $dst_ip1, $dst_ip2, $dst_port1, $dst_port2, $protocol, $permit)
  {
    $this->src_ip1   = $src_ip1;
    $this->src_ip2   = $src_ip2;
    $this->src_port1 = $src_port1;
    $this->src_port2 = $src_port2;
    $this->dst_ip1   = $dst_ip1;
    $this->dst_ip2   = $dst_ip2;
    $this->dst_port1 = $dst_port1;
    $this->dst_port2 = $dst_port2;

    $this->protocol  = $protocol;
    $this->permit    = $permit;
  }

  ################################################################################################################################################################
  ## sort_compare($a, $b)                                                                                                                                       ##
  ##                                                                                                                                                            ##
  ## Compare two instances of ACLRule to determine ordering, return 1 for higher and -1 for lower. Function is used in a call to usort() to sort an array of    ##
  ## ACLRule instances in a consistent order. The actual order is not overly important as rules cannot be overlapping however we try to maintain some semblance ##
  ## of obviousness. The orderting rules are:                                                                                                                   ##
  ##                                                                                                                                                            ##
  ## 1)  TCP rules are always before UDP rules which are before ICMP rules. IP rules are always last in the ordering                                            ##
  ## 2)  If the protocol matches,                     we sort based on the first source IP address in the rule                                                  ##
  ## 3)  If the first source IP address matches,      we sort on the last source IP address in the rule                                                         ##
  ## 4)  If the last source IP address matches,       we sort on the first destination IP address in the rule                                                   ##
  ## 5)  If the first destination IP address matches, we sort on the last destination IP address in the rule                                                    ##
  ## 6)  If the last destination IP address matches,  we sort on the first source port in the rule                                                              ##
  ## 7)  If the first source port matches,            we sort on the last source port in the rule                                                               ##
  ## 8)  If the last source port matches,             we sort on the first destination port in the rule                                                         ##
  ## 9)  If the first destination port matches,       we sort on the last destination port in the rule                                                          ##
  ## 10) If the last destination port matches,        we have an overlapping rule which should NEVER happer, simply return 0 and don't care about the sorting   ##
  ################################################################################################################################################################
  public static function sort_compare($a, $b)
  {
    // Protocols are not the same, sort order is tcp -> udp -> icmp -> ip
    if ($a->protocol !== $b->protocol)
    {
      switch ($a->protocol)
      {
        case 'ip':   return 1;                               // IP is always last in the list
        case 'icmp': return (($b->protocol === 'ip')?-1:1);  // ICMP is after everything except IP
        case 'udp':  return (($b->protocol === 'tcp')?1:-1); // UDP is after TCP but before everything else
        case 'tcp':  return -1;                              // TCP is always first in the list
        default:     return 0;
      }
    }

    // Protocols match, next sort keys (numerical) in order are: src_ip1, src_ip2, dst_ip1, dst_ip2, src_port1, src_port2, dst_port1, dst_port2
    if ($a->src_ip1 !== $b->src_ip1) return (($a->src_ip1 > $b->src_ip1)?1:-1);

    if ($a->src_ip2 !== $b->src_ip2) return (($a->src_ip2 > $b->src_ip2)?1:-1);

    if ($a->dst_ip1 !== $b->dst_ip1) return (($a->dst_ip1 > $b->dst_ip1)?1:-1);

    if ($a->dst_ip2 !== $b->dst_ip2) return (($a->dst_ip2 > $b->dst_ip2)?1:-1);

    if ($a->src_port1 !== $b->src_port1) return (($a->src_port1 > $b->src_port1)?1:-1);

    if ($a->src_port2 !== $b->src_port2) return (($a->src_port2 > $b->src_port2)?1:-1);

    if ($a->dst_port1 !== $b->dst_port1) return (($a->dst_port1 > $b->dst_port1)?1:-1);

    if ($a->dst_port2 !== $b->dst_port2) return (($a->dst_port2 > $b->dst_port2)?1:-1);

    // Should never get here as otherwise it would be an overlapping rule
    return 0;
  }

  ################################################################################################################################################################
  ## overlap_XXX($other)                                                                                                                                        ##
  ##                                                                                                                                                            ##
  ## We want to see if a potential new rule ($this) may partially overlap with an existing rule ($other). Each of the functions below determine whether this    ##
  ## partial overlap exists of not:                                                                                                                             ##
  ##                                                                                                                                                            ##
  ## overlap_protocol() - An IP rule overlaps all other types of rules (always true), otherwise an overlap only occurs if the protocols match                   ##
  ## overlap_src_ip()   - Return true if any source IP addresses in $this range are within any source IP address in $other range                                ##
  ## overlap_src_port() - Return true if any source ports in $this range are within any source ports in $other range. IP/ICMP store port ranges as 0-65535 and  ##
  ##                      so check will return true for these rules                                                                                             ##
  ## overlap_dst_ip()   - Return true if any source IP addresses in $this range are within any source IP address in $other range                                ##
  ## overlap_dst_port() - Return true if any source ports in $this range are within any source ports in $other range IP/ICMP store port ranges as 0-65535 and   ##
  ##                      so check will return true for these rules                                                                                             ##
  ## overlap()          - Publically callable. Returns true if all the above tests return true. For an overlap to exist, all condiions must be met. Otherwise   ##
  ##                      consider a rule that matches "host A to any" and one that matches "host B to any", these do not overlap because hosts A and B are     ##
  ##                      different, even though the destinations are the same                                                                                  ##
  ################################################################################################################################################################
  private function overlap_protocol($other)
  {
    return ($other->protocol === "ip")?(true):($this->protocol === $other->protocol);
  }

  private function overlap_src_ip($other)
  {
    return ((($other->src_ip1 <= $this->src_ip1) && ($this->src_ip1 <= $other->src_ip2)) ||
           (($this->src_ip1 <= $other->src_ip1) && ($other->src_ip1 <= $this->src_ip2)));
  }

  private function overlap_src_port($other)
  {
    return ((($other->src_port1 <= $this->src_port1) && ($this->src_port1 <= $other->src_port2)) ||
           (($this->src_port1 <= $other->src_port1) && ($other->src_port1 <= $this->src_port2)));
  }

  private function overlap_dst_ip($other)
  {
    return ((($other->dst_ip1 <= $this->dst_ip1) && ($this->dst_ip1 <= $other->dst_ip2)) ||
           (($this->dst_ip1 <= $other->dst_ip1) && ($other->dst_ip1 <= $this->dst_ip2)));
  }

  private function overlap_dst_port($other)
  {
    return ((($other->dst_port1 <= $this->dst_port1) && ($this->dst_port1 <= $other->dst_port2)) ||
           (($this->dst_port1 <= $other->dst_port1) && ($other->dst_port1 <= $this->dst_port2)));
  }

  public function overlap($other)
  {
    return (($this->overlap_protocol($other)) && ($this->overlap_src_ip($other)) && ($this->overlap_src_port($other)) && ($this->overlap_dst_ip($other)) && ($this->overlap_dst_port($other)));
  }

  ################################################################################################################################################################
  ## get_overlap_ranges($other)                                                                                                                                 ##
  ##                                                                                                                                                            ##
  ## Returns a set of ranges (IP addresses and ports) where there is no - or partial - overlap between a potential new rule ($this) and an existing rule        ##
  ## ($other). This function simply returns the ranges which can then be used to create a set of non-overlapping rules, the function is primarily to extract    ##
  ## the complexity away from the code that actually generates the non-overlapping rules. This function should not be called unless we know that there is an    ##
  ## overlap - see the overlap() function                                                                                                                       ##
  ##                                                                                                                                                            ##
  ## Break the overlap up into 5 areas (A, B, C, D, E), each of which (except E) may be non-existant. A-D are the parts of the new rule that do NOT overlap the ##
  ## IP ranges of the existing rule. These areas need to keep the entire port range of the proposed new rule. Area E is the overlapped area. In this area, some ##
  ## portions of the new rule may need to be kept depending on whether or not the port numbers overlap. In the drawings below, assume the horizontal axis       ##
  ## represents the source IP range of the rules and the vertical axis the destination IP range                                                                 ##
  ## 1) In the first drawing, the inner square is the existing rule ($other) and the outer square is the new rule ($this)                                       ##
  ##    - Areas A, B, C, D are rule ranges which do not overlap and must be kept, E is the overlapped range                                                     ##
  ## In the second drawing, the right-lower large square is the existing rule ($other) and the left-upper square is the new rule ($this)                        ##
  ##  - Areas A, B are rule ranges which do not overlap and must be kept, C and D are non-existant, E is the overlapped range                                   ##
  ##                                                                                                                                                            ##
  ##   +--------+--------+--------+         +--------+--------+                                                                                                 ##
  ##   |        |    B   |        |         |        |    B   |                                                                                                 ##
  ##   |        |        |        |         |        |        |                                                                                                 ##
  ##   |        +--------+        |         |    A   +--------+--------+                                                                                        ##
  ##   |    A   |    E   |    D   |         |        |    E   |        |                                                                                        ##
  ##   |        |        |        |         |        |        |        |                                                                                        ##
  ##   |        +--------+        |         +--------+--------+--------+                                                                                        ##
  ##   |        |    C   |        |                  |                 |                                                                                        ##
  ##   |        |        |        |                  |                 |                                                                                        ##
  ##   +--------+--------+--------+                  +-----------------+                                                                                        ##
  ##                                                                                                                                                            ##
  ## For area E, we perform a similar function to above using port ranges instead of IP ranges to come up with new areas E_a, E_b, E_c, E_d and E_e, again each ##
  ## of which (except E_e) may be non-existant. Areas E_(a-d) do not have overlapping port numbers and so rules should be created for these IP addresses and    ##
  ## sub-ranges of ports. Area E_e is a complete overlap and should not be created                                                                              ##
  ##                                                                                                                                                            ##
  ## To keep life simple, this function merely calculates the ranges of A, B, C, D, E_a, E_b, E_c and E_d and stores array elements of addresses and ports. End ##
  ## values for address/port range pairs are the first address/port OUTSIDE the range, this allows checking for an empty range by just checking if the values   ##
  ## values are equal. We don't bother with deciding if the range is empty here, this is checked after the value is returned.                                   ##
  ##                                                                                                                                                            ##
  ## NOTE: The above drawings also work if the new rule is entirely within the the existing rule, in this case A, B, C, D are empty and E is the entire area    ##
  ################################################################################################################################################################
  private function get_overlap_ranges($other)
  {
    // Store address ranges for Areas A through E - end of ranges is one greater
    $area_A_ip = array($this->src_ip1, max($this->src_ip1, $other->src_ip1), $this->dst_ip1, $this->dst_ip2 + 1);
    $area_B_ip = array(max($this->src_ip1, $other->src_ip1), min($this->src_ip2, $other->src_ip2) + 1, $this->dst_ip1, max($this->dst_ip1, $other->dst_ip1));
    $area_C_ip = array(max($this->src_ip1, $other->src_ip1), min($this->src_ip2, $other->src_ip2) + 1, min($this->dst_ip2, $other->dst_ip2) + 1, $this->dst_ip2 + 1);
    $area_D_ip = array(min($this->src_ip2, $other->src_ip2) + 1, $this->src_ip2 + 1, $this->dst_ip1, $this->dst_ip2 + 1);
    $area_E_ip = array(max($this->src_ip1, $other->src_ip1), min($this->src_ip2, $other->src_ip2) + 1, max($this->dst_ip1, $other->dst_ip1), min($this->dst_ip2, $other->dst_ip2) + 1);

    // Store port ranges for sub-areas A through D
    $area_A_port   = array($this->src_port1, max($this->src_port1, $other->src_port1), $this->dst_port1, $this->dst_port2 + 1);
    $area_B_port   = array(max($this->src_port1, $other->src_port1), min($this->src_port2, $other->src_port2) + 1, $this->dst_port1, max($this->dst_port1, $other->dst_port1));
    $area_C_port   = array(max($this->src_port1, $other->src_port1), min($this->src_port2, $other->src_port2) + 1, min($this->dst_port2, $other->dst_port2) + 1, $this->dst_port2 + 1);
    $area_D_port   = array(min($this->src_port2, $other->src_port2) + 1, $this->src_port2 + 1, $this->dst_port1, $this->dst_port2 + 1);

    // Store port ranges for all ports
    $area_all_port = array($this->src_port1, $this->src_port2 + 1, $this->dst_port1, $this->dst_port2 + 1);

    // Push the 8 potential address/port ranges to return array
    $result[] = array_merge($area_A_ip, $area_all_port);
    $result[] = array_merge($area_B_ip, $area_all_port);
    $result[] = array_merge($area_C_ip, $area_all_port);
    $result[] = array_merge($area_D_ip, $area_all_port);
    $result[] = array_merge($area_E_ip, $area_A_port);
    $result[] = array_merge($area_E_ip, $area_B_port);
    $result[] = array_merge($area_E_ip, $area_C_port);
    $result[] = array_merge($area_E_ip, $area_D_port);

    return $result;
  }

  ################################################################################################################################################################
  ## create_nonoverlapping_rules($other)                                                                                                                        ##
  ##                                                                                                                                                            ##
  ## Considering a potential new rule ($this) and an existing rule ($other), return an array consisting of a set of non-overlapping rules (a rule where a       ##
  ## a packet can match the existing AND the new rule). This function is used to sanitise a potential new rule so that it will not overlap with a set of        ##
  ## existing rules in an ACL. Uses the overlap() function to determine if overlap exists and the get_overlap_ranges() function to determine the ranges of      ##
  ## matching conditions where there is no overlap.                                                                                                             ##
  ##                                                                                                                                                            ##
  ## If there is no overlap, the rule is fine as is, return an array containing a copy of the new rule ($this). Otherwise call get_overlap_ranges() to get a    ##
  ## list of non-overlapping ranges. For each range that is not empty (meaning non-existant, see comments for get_overlap_ranges()), we need to create the      ##
  ## smaller - non-overlapping - ACLRule instance to return.                                                                                                    ##
  ################################################################################################################################################################
  public function create_nonoverlapping_rules($other)
  {
    // No overlap in rules, return an array containing a copy of this rule
    if (!($this->overlap($other))) return array($this);

    // Create null array in case the overlap causes us to dump the rule
    $result = array();

    // Find the overlap ranges
    $overlap_ranges = $this->get_overlap_ranges($other);

    // For each range, if there is not an empty range (source/dest ip/port) then add the rule to the list of non overlapping rules to return
    foreach ($overlap_ranges as $range)
    {
      if (($range[0] !== $range[1]) && ($range[2] !== $range[3]) && ($range[4] !== $range[5]) && ($range[6] !== $range[7]))
        $result[] = new ACLRule($range[0], $range[1] - 1, $range[4], $range[5] - 1, $range[2], $range[3] - 1, $range[6], $range[7] - 1, $this->protocol, $this->permit);
    }

    return $result;
  }

  ################################################################################################################################################################
  ## PRIVATE HELPER FUNCTIONS FOR merge()                                                                                                                       ##
  ##                                                                                                                                                            ##
  ## In order to merge ACLRules together, we regularly check whether IP/port ranges either match or are consecutive, we also need to check if an IP range is    ##
  ## entirely within another IP range, this set of functions extract the checks out to make the code easier to read. We are always checking to see whether a    ##
  ## condition is true when comparing one ACLRule ($this) with another ACLRule ($other):                                                                        ##
  ##                                                                                                                                                            ##
  ## ip_encompass()         - Returns true IF the source IP address ranges of $this completely encompass the source IP ranges of $other AND the destination IP  ##
  ##                          ranges of $this completely encompass the destination ranges of $other                                                             ##
  ## src_ip_match()         - Return true if any source IP address range of $this and $other are identical                                                      ##
  ## src_ip_consecutive()   - Return true if the source IP address range of $this is immediately preceeding or immediately following the source address range   ##
  ##                          of $other                                                                                                                         ##
  ## src_port_match()       - Return true if any source port range of $this and $other are identical (always true for IP/ICMP as these ranges are 0-65535)      ##
  ## src_port_consecutive() - Return true if the source port range of $this is immediately preceeding or immediately following the source port range of $other  ##
  ## dst_ip_match()         - Return true if any source IP address range of $this and $other are identical                                                      ##
  ## dst_ip_consecutive()   - Return true if the source IP address range of $this is immediately preceeding or immediately following the source address range   ##
  ##                          of $other                                                                                                                         ##
  ## dst_port_match()       - Return true if any source port range of $this and $other are identical (always true for IP/ICMP as these ranges are 0-65535)      ##
  ## dst_port_consecutive() - Return true if the source port range of $this is immediately preceeding or immediately following the source port range of $other  ##
  ################################################################################################################################################################
  private function ip_encompass($other)
  {
    return (($this->src_ip1 <= $other->src_ip1) && ($this->src_ip2 >= $other->src_ip2) && ($this->dst_ip1 <= $other->dst_ip1) && ($this->dst_ip2 >= $other->dst_ip2));
  }

  private function src_ip_match($other)
  {
    return (($this->src_ip1 == $other->src_ip1) && ($this->src_ip2 == $other->src_ip2));
  }

  private function src_ip_consecutive($other)
  {
    return ((($this->src_ip2 + 1) == $other->src_ip1) || (($other->src_ip2 + 1) == $this->src_ip1));
  }

  private function src_port_match($other)
  {
    return (($this->src_port1 == $other->src_port1) && ($this->src_port2 == $other->src_port2));
  }

  private function src_port_consecutive($other)
  {
    return ((($this->src_port2 + 1) == $other->src_port1) || (($other->src_port2 + 1) == $this->src_port1));
  }

  private function dst_ip_match($other)
  {
    return (($this->dst_ip1 == $other->dst_ip1) && ($this->dst_ip2 == $other->dst_ip2));
  }

  private function dst_ip_consecutive($other)
  {
    return ((($this->dst_ip2 + 1) == $other->dst_ip1) || (($other->dst_ip2 + 1) == $this->dst_ip1));
  }

  private function dst_port_match($other)
  {
    return (($this->dst_port1 == $other->dst_port1) && ($this->dst_port2 == $other->dst_port2));
  }

  private function dst_port_consecutive($other)
  {
    return ((($this->dst_port2 + 1) == $other->dst_port1) || (($other->dst_port2 + 1) == $this->dst_port1));
  }

  ################################################################################################################################################################
  ## merge($other)                                                                                                                                              ##
  ##                                                                                                                                                            ##
  ## We are trying to see if an existing ACLRule ($other) can be merged and combined with a potentially new ACLRule ($this). If a merge is not possible, $this  ##
  ## remains unchanged and we return false. If a merge is possible, the address/port ranges of the two rules are merged and the updated details are stored in   ##
  ## $this. We return true to indicate that the rules have been merged and that the original rule ($other) can/should now be discarded. The basic algorithm is: ##
  ##                                                                                                                                                            ##
  ## 1) Rules CANNOT be merged unless they are both "permit" or both "deny"                                                                                     ##
  ## 2) If protocols don't match, a merge can only occur if the new rule ($this) is an IP rule AND the IP Address ranges of the new rule completely encompass   ##
  ##    the address ranges of the OLD rule.                                                                                                                     ##
  ## --- At this point we know that the protocols actually match ---                                                                                            ##
  ## 3) If the rules have the exact same source and destination IP address ranges (not possible for IP or ICMP as they would overlap and be previously removed) ##
  ##    then we may be able to merge the rules if the source or destination ports are consecutive                                                               ##
  ## --- At this point we know that both the source and destination addresses do not match ---                                                                  ##
  ## 4) If the rules have the exact same source and destination port ranges (always true for IP or ICMP as they are stored as 0-65535) then we may be able to   ##
  ##    merge the rules if the source or destination IP addresses are consecutive                                                                               ##
  ################################################################################################################################################################
  public function merge($other)
  {
    // Different rule types, cannot be merged
    if ($this->permit !== $other->permit) return false;

    // Different protocol types, cannot be merged
    if ($this->protocol !== $other->protocol)
    {
      // If the new rule ($this) is "ip" and the existing rule ($other) is a sub-protocol (not "ip") then there is a possibility that the IP rule may encompass
      // the sub-protocol. If the range of old addresses are inside the range of new addresses, then the old rule is now simply irrelevant and we can return
      // true. If it doesn't encompass, or this protocol is NOT "ip", then the protocols are different so there is no overlap and we return false
      return (($this->protocol === "ip")?($this->ip_encompass($other)):false);      
    }

    // Protocol is same, source and dest IPs match. This is not possible for IP or ICMP (would be overlapping). Need to check if port ranges are consecutive
    if ($this->src_ip_match($other) && $this->dst_ip_match($other))
    {
      // Source ports same, if destination ports are consecutive then rules are adjacent
      if (($this->src_port_match($other)) && ($this->dst_port_consecutive($other)))
      {
        // Merging destination ports
        $this->dst_port1 = min($this->dst_port1, $other->dst_port1);
        $this->dst_port2 = max($this->dst_port2, $other->dst_port2);
        return true;
      }

      // Destination ports same, if source ports are consecutive then rules are adjacent
      if (($this->dst_port_match($other)) && ($this->src_port_consecutive($other)))
      {
        // Merging source ports
        $this->src_port1 = min($this->src_port1, $other->src_port1);
        $this->src_port2 = max($this->src_port2, $other->src_port2);
        return true;
      }

      // Neither source nor destination ports match, not adjacent so return false
      return false;
    }

    // Protocol is same, source and dest ports match. For IP or ICMP this is always true (0-65535). Need to check if IP addresses are consecutive
    if ($this->src_port_match($other) && $this->dst_port_match($other))
    {
      // Source IPs same, if destination IPs are consecutive then rules are adjacent
      if (($this->src_ip_match($other)) && ($this->dst_ip_consecutive($other)))
      {
        // Merging destination IP addresses
        $this->dst_ip1 = min($this->dst_ip1, $other->dst_ip1);
        $this->dst_ip2 = max($this->dst_ip2, $other->dst_ip2);
        return true;
      }

      // Destination IPs same, if source IPs are consecutive then rules are adjacent
      if (($this->dst_ip_match($other)) && ($this->src_ip_consecutive($other)))
      {
        // Merging source IP addresses
        $this->src_ip1 = min($this->src_ip1, $other->src_ip1);
        $this->src_ip2 = max($this->src_ip2, $other->src_ip2);
        return true;
      }

      // Neither source nor destination IPs match, not adjacent so return false
      return false;
    }

    // Source or destination don't match, never going to be adjacent
    return false;
  }

  ################################################################################################################################################################
  ## new_range($first, $last, $min_first, $max_last)                                                                                                            ##
  ##                                                                                                                                                            ##
  ## Given an IP address range ($first->$last) that needs to be pruned to the range ($min_first->$max_last) return an array of pruned address ranges            ##
  ##                                                                                                                                                            ##
  ## - If $last < $min_first, whole range preceeds pruning area, change range to (0->0) effectively deleting the rule                                           ##
  ## - If $first < $max_last, whole range follows pruning area, change range to (0->0) effectively deleting the rule                                            ##
  ## - Return pruned range:                                                                                                                                     ##
  ##   o Start is larger value of current $first or $min_first (keep $first if already within range otherwise increase it to lowest allowed value)              ##
  ##   o End is smaller value of current $last or $max_last (keep $last if already within range otherwise decrease it to largest allowed value)                 ##
  ################################################################################################################################################################
  private function new_range(&$first, &$last, $min_first, $max_last)
  {
    if (($last < $min_first) || ($first > $max_last))
    {
      $first = 0; $last = 0; return True;
    }

    $was_any = (($first == ip2long('0.0.0.0')) && ($last == ip2long('255.255.255.255')));

    // Default setting is no error
    $result = True;

    // Required range wholly inside original range. Error (false) if original range is not "any"
    if (($first < $min_first) && ($last > $max_last)) $result = $was_any;

    // Original range splits required range (partially in and partially out), this is poorly specified, return error (false)
    if ((($first >= $min_first) && ($last > $max_last)) || (($first < $min_first) && ($last <= $max_last))) $result = False;

    $first = max($first, $min_first);
    $last  = min($last, $max_last);

    return $result;

  }

  ################################################################################################################################################################
  ## prune($keep_ranges)                                                                                                                                        ##
  ##                                                                                                                                                            ##
  ## Prune this ACL rule to a range of source and destination IP address ranges. $keep_ranges is an array that maps 'src' and 'dst' to an array of two elements ##
  ## equaling the first and last IP address of the range we consider equivalent to 'any. After pruning the address ranges, we return False if we actually       ##
  ## performed the prune and the original range prior to pruning was NOT 'any'. This way we can identify intellegence ('any' instead of the exact range) and    ##
  ## blind luck (covers the range but not 'any')                                                                                                                ##
  ##                                                                                                                                                            ##
  ## - Initialise result                                                                                                                                        ##
  ## - Check to see if source or destination were 'any' and store in variables                                                                                  ##
  ## - If the source range exceeds the prune range, prune down to the ideal range, modify the result if $src_any was false                                      ##
  ## - If the desitnation range exceeds the prune range, prune down to the ideal range, modify the result if $dst_any was false                                 ##
  ## - Return outcome                                                                                                                                           ##
  ################################################################################################################################################################
  public function prune($keep_ranges)
  {
    $result = true;

    $src_any = (($this->src_ip1 == ip2long('0.0.0.0')) && ($this->src_ip2 == ip2long('255.255.255.255')));
    $dst_any = (($this->dst_ip1 == ip2long('0.0.0.0')) && ($this->dst_ip2 == ip2long('255.255.255.255')));

    $result = $result & $this->new_range($this->src_ip1, $this->src_ip2, $keep_ranges['src'][0], $keep_ranges['src'][1]);

    $result = $result & $this->new_range($this->dst_ip1, $this->dst_ip2, $keep_ranges['dst'][0], $keep_ranges['dst'][1]);

    return $result;
  }

  ################################################################################################################################################################
  ## is_empty_rule()                                                                                                                                            ##
  ##                                                                                                                                                            ##
  ## After pruning, empty rules have a source or destination range (0.0.0.0-0.0.0.0). Return true of this is the case (allows deleting empty rules from a list  ##
  ################################################################################################################################################################
  public function is_empty_rule()
  {
    return ((($this->src_ip1 === 0) && ($this->src_ip2 === 0)) || (($this->dst_ip1 === 0) && ($this->dst_ip2 === 0)));
  }

  ################################################################################################################################################################
  ## to_string()                                                                                                                                                ##
  ##                                                                                                                                                            ##
  ## Return a (standard format) string representation of this particular ACLRule instance for display or logging purposes                                       ##
  ################################################################################################################################################################
  public function to_string()
  {
    $src_ip = long2ip($this->src_ip1) . "-" . long2ip($this->src_ip2);
    $src_port = "{$this->src_port1}-{$this->src_port2}";
    $dst_ip = long2ip($this->dst_ip1) . "-" . long2ip($this->dst_ip2);
    $dst_port = "{$this->dst_port1}-{$this->dst_port2}";

    $result = (($this->permit)?"permit ":"deny   ") . str_pad($this->protocol, 5);

    switch ($this->protocol)
    {
      case "ip":
      case "icmp": return $result . "source($src_ip) dest($dst_ip)";
      case "tcp":
      case "udp":  return $result . "source($src_ip:$src_port) dest($dst_ip:$dst_port)";
      default:     return "massive stuff-up";
    }
  }

  ################################################################################################################################################################
  ## print_rule()                                                                                                                                               ##
  ##                                                                                                                                                            ##
  ## FOR DEBUGGING/DEVELOPMENT PURPOSES ONLY                                                                                                                    ##
  ##                                                                                                                                                            ##
  ## Print out the rule defined by this ACLRule instance in a standard way                                                                                      ##
  ################################################################################################################################################################
  public function print_rule()
  {
    echo "      " . $this->back_to_string() . "\n";
  }
}

##################################################################################################################################################################
## class ACL                                                                                                                                                    ##
##                                                                                                                                                              ##
## Object that can parse a set of ACL statements and generate a non-overlapping set of rules to implement the ACL. Can also be used to compare against another  ##
## ACL object to determine equality of ACL outcomes. Finally the class will also determine which statements result in no matches for any packets as well as     ##
## differentiate between statements before and after a "permit|deny ip any any" rule in the list.                                                               ##
##                                                                                                                                                              ##
## The public variables and methods on this class are:                                                                                                          ##
##                                                                                                                                                              ##
## Variables:                                                                                                                                                   ##
##  $type                 Is this a Standard or Extended ACL, mainly for informational purposes                                                                 ##
##  $raw_statements:      Array containing the actual ACL statements from the original ACL statements as supplied to add_statement(). Statements are stored in  ##
##                        order of addition                                                                                                                     ##
##  $rulelist:            Array containing ACLRule instances of the effective ACL after all overlapping components have been removed. This is what we use to    ##
##                        compare against a "solution" ACL to see if the ACL has the same outcome                                                               ##
##  $no_match_rules:      Array containing original ACL statements as strings (as supplied to add_statement()) for rules that will never be matched because     ##
##                        they were matched by earlier rules                                                                                                    ##
##  $after_default_rules: Array containing original ACL statements as strings (as supplied to add_statement()) for rules were applied afer a "deny ip any any"  ##
##                        or "permit ip any any" was seen                                                                                                       ##
##                                                                                                                                                              ##
## Methods:                                                                                                                                                     ##
##  set_any_equiv($dir, $list): Set the equivalent sets of IP addresses to ip "any" so we can prune the rule list to this set. $dir is set to "src" or "dst"    ##
##  add_statement($statement):  Add the ACL statement ($statement) as a string to this ACL                                                                      ##
##  finalise():                 Call method when there are no more rules to add to the ACL, this will apply the default "deny ip any any" rule as long as a     ##
##                              previous "deny ip any any or "permit ip any any" rule has not been added                                                        ##
##  mark_acl($solution):        Mark the effective ACL of $this against the solution in $solution. Return an array of responses indicating any problems and/or  ##
##                              errors                                                                                                                          ##
##################################################################################################################################################################
class ACL
{
  // Private member variables
  private $finalised, $prune, $clean_prune;

  // Public member variables
  public $type, $raw_statements, $rulelist, $no_match_rules, $after_default_rules, $unclean_prune_summary;

  ################################################################################################################################################################
  ## Constructor($type)                                                                                                                                         ##
  ##                                                                                                                                                            ##
  ## Set the ACL type variable and initialise all other internal class variables ready to add the first rule                                                    ##
  ################################################################################################################################################################
  public function __construct($type)
  {
    $this->finalised = false;
    $this->type = $type;
    $this->raw_statements = array();
    $this->rulelist = array();
    $this->no_match_rules = array();
    $this->after_default_rules = array();
    $this->prune['src'] = array(ip2long("0.0.0.0"), ip2long("255.255.255.255"));
    $this->prune['dst'] = array(ip2long("0.0.0.0"), ip2long("255.255.255.255"));
    $this->clean_prune = true;
  }

  ################################################################################################################################################################
  ## ip_ranges($ip_add, $wildcard)                                                                                                                              ##
  ##                                                                                                                                                            ##
  ## Set the equivalent set of IP addresses for 'any' for either src or destination IPs for this ACL. $dir is either 'src' or 'dst'. $any_ip_range is a string  ##
  ## containing subnet information if (address/mask) format                                                                                                     ##
  ##                                                                                                                                                            ##
  ## - Split string into address and mask values                                                                                                                ##
  ## - Store address as first IP address in prune range for $dir                                                                                                ##
  ## - Calculate and store the last IP address in prune range for $dir                                                                                          ##
  ################################################################################################################################################################
  public function set_any_equiv($dir, $any_ip_range)
  {
    $ranges = explode('/', $any_ip_range);

    $this->prune[$dir][0] = ip2long($ranges[0]);
    $this->prune[$dir][1] = $this->prune[$dir][0] + pow(2, 32 - intval($ranges[1])) - 1;
  }

  ################################################################################################################################################################
  ## ip_ranges($ip_add, $wildcard)                                                                                                                              ##
  ##                                                                                                                                                            ##
  ## Given an input IP address and wildcard mask in Cisco form, return an array of two-element arrays. Each two element arrany indicates a continuous range of  ##
  ## IP addresses array(first, last) that match IP addresses defined by the ACL IP address/wildcard. If the wildcard specifies a complete continuous range,     ##
  ## the returned array will consist of a single entry which contains a simple continuous range.                                                                ##
  ##                                                                                                                                                            ##
  ## Examples:                                                                                                                                                  ##
  ##  ip_range('196.168.0.0', '0.0.0.127') => array(array(192.168.0.0, 192.168.0.127))                                                                          ##
  ##  ip_range('196.168.26.32', '0.0.0.5') => array(array(192.168.26.32, 192.168.26.33), array(192.168.26.36, 192.168.26.37))                                   ##
  ################################################################################################################################################################
  private function ip_ranges($ip_add, $wildcard)
  {
    // last possible address in range
    $ip_last = $ip_add | $wildcard;

    // initialise variables, the first range will always start at $ip_add and we are within that range
    $current_start = $ip_add;
    $in_range = true;

    // loop through all potential IP addresses in matching rule
    for ($i = $ip_add; $i <= $ip_last; $i++)
    {
      // This address ($i) is NOT matched
      if (($i & (~ $wildcard)) !== $ip_add)
      {
        // We are in a range, we are now outside of it. Append range to $result array and update status flag to being out of a valid range
        // If we have used ~half the allocated memory, return an empty range, it will be too dificult to fit everything in RAM
        if ($in_range)
        {
          $in_range = false;
          $result[] = array($current_start, $i - 1);
          if (memory_get_usage(true) > 40000000) return array();
        }
      } else
      // This address ($i) IS matched
      {
        // We are not in a range, we have now started a new range. Reset $current_start an update status flag to indicate we are within the range
        if (!$in_range) { $current_start = $i; $in_range = true; }
      }
    }

    // Last valid range has not been appended to the array (we always finish within the range), add it now
    $result[] = array($current_start, $i - 1);

    return $result;
  }

  ################################################################################################################################################################
  ## port_map($port)                                                                                                                                            ##
  ##                                                                                                                                                            ##
  ## Given an port number in string form or a port application type as allowed in Cisco ACL rules, return an integer representation of the port. If the port    ##
  ## provided is a number is string form, we convert it to integer form. Otherwise, we use a direct mapping. Not all service names are coded below, only the    ##
  ## the most likely ones.                                                                                                                                       ##
  ################################################################################################################################################################
  private function port_map($port)
  {
    switch ($port)
    {
      case 'echo':   return 7;
      case 'ftp':    return 21;
      case 'ssh':    return 22;
      case 'telnet': return 23;
      case 'smtp':   return 25;
      case 'domain': return 53;
      case 'www':    return 80;
      case 'ntp':    return 123;
      default:       return intval($port);
    }
  }

  ################################################################################################################################################################
  ## convert_statement($statement)                                                                                                                              ##
  ##                                                                                                                                                            ##
  ## Given a single ACL statement in string form, convert it into an array of instances of ACLRule that directly maps to the provided statement. If the source  ##
  ## and destination address/port ranges are all continuous, this will result in a single ACLRule instance, otherwise there may be more. The ACLRule instances  ##
  ## created here are not ready to be inserted into $this->rulelist, they need to be cleaned up prior to addition, this function merely creates the initial set ##
  ## of ACLRule's to consider for addition to $this->rulelist. The basic algorithm is:                                                                          ##
  ##                                                                                                                                                            ##
  ## - Break the rule up into individual words                                                                                                                  ##
  ## - Perform a sanity check on the rule, does it start with "permit" or "deny"                                                                                ##
  ## - Store basic information about the rule (permit/deny, protocol), also pre-initialise variables indicating that it is NOT an "ip any any" rule             ##
  ## - Determine the index (in the $words[] array) where the source IP and destination IP information is. Also extract the source and destination port ranges   ##
  ##   from the rule:                                                                                                                                           ##
  ##   - For IP/ICMP, port ranges are always (0-65535)                                                                                                          ##
  ##   - For TCP/UDP, port ranges could be a single range or two ranges ("neq" option), or not specified (0-65535).                                             ##
  ##   - For no protocol specified, this is a Basic ACL, so the protocol is "ip" and the destination ip address index is set to the sentinal value of 0         ##
  ## - Calculate the source and destination IP ranges                                                                                                           ##
  ##   - If the range coded as any, calculate the range (0.0.0.0-255.255.255.255) and set the corresponding $XXX_any flag                                       ##
  ##   - If the range coded as host x.x.x.x, calculate the range (x.x.x.x-x.x.x.x) and set the corresponding $XXX_any flag                                      ##
  ##   - If the range coded with a wildcard mask, call ip_ranges() to calculate the ranges                                                                      ##
  ##   - If the destination index was 0 (matched by case statements "permit" or "deny" - basic ACL rule), do the same as for as an "any" destination            ##
  ## - If the protocol is IP and it is an "any any" rule, then set the $this->finalised flag to indicate that any future rules are pointless                    ##
  ## - For all combinations of src/dest ip/port ranges, create an ACLRule instance to return                                                                    ##
  ################################################################################################################################################################
  private function convert_statement($statement)
  {
    // Split the rule statement into individual words
    $words = preg_split("/\s+/", trim($statement));

    if (($words[0] !== "permit") && ($words[0] !== "deny"))
    {
      echo "ERROR: ACL statement does not begin with \"permit\" or \"deny\"\n";
      exit(1);
    }

    // Set initial variables to maintain information about this set of rules
    $permit = ($words[0] === "permit");
    $protocol = $words[1];
    $source_any = false;
    $dest_any = false;

    switch ($words[1])
    {
      case "icmp":
      case "ip":   // Extended ACL IP or ICMP rule
                   // Both types have source and desination IP
                   // Index of destination IP depends on whether source is "any" or not
                   $source_ip_index = 2;
                   $dest_ip_index = ($words[$source_ip_index] === "any")?3:4;
                   $source_port_ranges = array(array(0, 65535));
                   $dest_port_ranges = array(array(0, 65535));
                   break;

      case "tcp":
      case "udp":  // Extended ACL TCP or UDP rule
                   // Both types have source and desination IP + source and destination ports (optional)
                   // Lots of conditional stuff here
                   $source_ip_index = 2;
                   // Source port information index depends on whether source is "any" or not
                   $source_port_index = ($words[$source_ip_index] === "any")?3:4;
                   // Depending on port match rule, set the source port ranges, set destination IP index to point to after source port rule
                   // Note if no rule match, then there is no source port specified, we need to match all ports
                   switch ($words[$source_port_index])
                   {
                     case "lt":    $source_port_ranges = array(array(0, $this->port_map($words[$source_port_index + 1] - 1))); 
                                   $dest_ip_index = $source_port_index + 2; break;
                     case "gt":    $source_port_ranges = array(array($this->port_map($words[$source_port_index + 1] + 1), 65535));
                                   $dest_ip_index = $source_port_index + 2; break;
                     case "eq":    $source_port_ranges = array(array($this->port_map($words[$source_port_index + 1]), $this->port_map($words[$source_port_index + 1])));
                                   $dest_ip_index = $source_port_index + 2; break;
                     case "neq":   $source_port_ranges = array(array(0, $this->port_map($words[$source_port_index + 1] - 1)), array($this->port_map($words[$source_port_index + 1] + 1), 65535));
                                   $dest_ip_index = $source_port_index + 2; break;
                     case "range": $source_port_ranges = array(array($this->port_map($words[$source_port_index + 1]), $this->port_map($words[$source_port_index + 2])));
                                   $dest_ip_index = $source_port_index + 3; break;
                     default:      $source_port_ranges = array(array(0, 65535));
                                   $dest_ip_index = $source_port_index; break;
                   }
                   // We know the destination IP location, now we need to parse the destination ports
                   $dest_port_index = $dest_ip_index + (($words[$dest_ip_index] === "any")?1:2);

                   // Same rules as source ports
                   switch ($words[$dest_port_index])
                   {
                     case "lt":    $dest_port_ranges = array(array(0, $this->port_map($words[$dest_port_index + 1] - 1))); break;
                     case "gt":    $dest_port_ranges = array(array($this->port_map($words[$dest_port_index + 1] + 1), 65535)); break;
                     case "eq":    $dest_port_ranges = array(array($this->port_map($words[$dest_port_index + 1]), $this->port_map($words[$dest_port_index + 1]))); break;
                     case "neq":   $dest_port_ranges = array(array(0, $this->port_map($words[$dest_port_index + 1] - 1)), array($this->port_map($words[$dest_port_index + 1] + 1), 65535)); break;
                     case "range": $dest_port_ranges = array(array($this->port_map($words[$dest_port_index + 1]), $this->port_map($words[$dest_port_index + 2]))); break;
                     default:      $dest_port_ranges = array(array(0, 65535)); break;
                   }
                   break;

      default:     // Basic ACL, no protocol type specified, set the protocol type to "IP", set the source IP location and use 0 for destination IP index
                   $protocol = "ip";
                   $source_ip_index = 1;
                   $source_port_ranges = array(array(0, 65535));
                   $dest_ip_index = 0;
                   $dest_port_ranges = array(array(0, 65535)); 
                   break;
    }

    // Calculate source IP ranges
    switch ($words[$source_ip_index])
    {
      case "any":  $source_ip_ranges = array(array(ip2long("0.0.0.0"), ip2long("255.255.255.255"))); $source_any = true; break;
      case "host": $source_ip_ranges = array(array(ip2long($words[$source_ip_index + 1]), ip2long($words[$source_ip_index + 1]))); break;
      default:     $source_ip_ranges = $this->ip_ranges(ip2long($words[$source_ip_index]), ip2long($words[$source_ip_index + 1]));
                   $source_any       = (($words[$source_ip_index] === "0.0.0.0") && ($words[$source_ip_index + 1] === "255.255.255.255"));
                   break;
    }

    // Calculate destination IP ranges. If keyword is "permit" or "deny", then we have a basic ACL so we set destination ranges to "any"
    switch ($words[$dest_ip_index])
    {
      case "permit":
      case "deny":
      case "any":    $dest_ip_ranges = array(array(ip2long("0.0.0.0"), ip2long("255.255.255.255"))); $dest_any = true; break;
      case "host":   $dest_ip_ranges = array(array(ip2long($words[$dest_ip_index + 1]), ip2long($words[$dest_ip_index + 1]))); break;
      default:       $dest_ip_ranges = $this->ip_ranges(ip2long($words[$dest_ip_index]), ip2long($words[$dest_ip_index + 1]));
                     $dest_any       = (($words[$dest_ip_index] === "0.0.0.0") && ($words[$dest_ip_index + 1] === "255.255.255.255"));
                     break;
    }

    // Rule is a "permit|deny ip any any" rule, the ACL list is now finalised
    if (($protocol === "ip") && ($source_any) && ($dest_any)) $this->finalised = true;

    // For all sets of ranges, create the ACLRule instance to return
    foreach ($source_ip_ranges as $source_ip_range)
      foreach ($dest_ip_ranges as $dest_ip_range)
        foreach ($source_port_ranges as $source_port_range)
          foreach ($dest_port_ranges as $dest_port_range)
          {
            $rules[] = new ACLRule($source_ip_range[0], $source_ip_range[1], $source_port_range[0], $source_port_range[1],
                                   $dest_ip_range[0], $dest_ip_range[1], $dest_port_range[0], $dest_port_range[1],
                                   $protocol, $permit);
          }

    // Prune rules down to "any" equivalent IP ranges (smallest rule)
    foreach ($rules as $rule) $rule->prune($this->prune);

    return $rules;
  }

  ################################################################################################################################################################
  ## sanitise_rules(&$rule_list)                                                                                                                                ##
  ##                                                                                                                                                            ##
  ## Given an array containing a set of ACLRules to consider for addition to $this->rulelist, sanitise these ACL rules in place to remove any packet matches    ##
  ## with any existing rules in $this->rulelist. $rule_list is passed by reference, so the original array is modified by this function. The final set of rules  ##
  ## contained in the array can now be added to $this->rulelist as they will not clash with any existing rules.                                                 ##
  ##                                                                                                                                                            ##
  ## All existing rules need to be compared with all potential new rules, and sanitising a rule always makes it smaller (removes some matching conditions). The ##
  ## code outer loop goes through the existing rather than new rules as once each possible new rule has been checked against an existing rule, no further       ##
  ## changes to an potential new rule will require re-sanitisation against that particular old rule. The basic algorithm is outlined below:                     ##
  ## new rule is sanitised against a single existing rule,                                                                                                      ##
  ##                                                                                                                                                            ##
  ## - We need to loop through all existing rules to sanitise the new rule list against                                                                         ##
  ##   - We then loop through each new rule. As sanitisation can make $rule_list grow OR shrink, we need to loop against a counter rather than an iterator      ##
  ##     - $new_nonoverlap is the replacement set of rules for $rule_list[$i], calling create_nonoverlapping_rules() on the rule to be sanitised will create    ##
  ##       this replacement set. If there is no overlap, then create_non_overlapping_rules() will return an array holding only a copy of the unsanitised rule   ##
  ##     - Replace $rule_list[$i] with the non-overlapped rules previously determined                                                                           ##
  ##     - Increment $i by the size of the replacement rules (possibly zero) to move to the next yet-to-be-sanitised rule                                       ##
  ################################################################################################################################################################
  private function sanitise_rules(&$rule_list)
  {
    // Check against each existing rule in ACL in turn
    foreach ($this->rulelist as $old_rule)
    {
      // Check each possible rule in list of potential new rules against the current existing rule we are checking
      for ($i = 0; $i < count($rule_list);)
      {
        // Create an array consisting of the sanitised version (compared to $old_rule) of $rule_list[$i] - could be itself if no overlap exists
        $new_nonoverlap = $rule_list[$i]->create_nonoverlapping_rules($old_rule);

        // Then replace the un-sanitised rule with the sanitised version
        array_splice($rule_list, $i, 1, $new_nonoverlap);

        // Increment $i by the number of rules we have put into the list, for example:
        // - if replacing one rule with one rule, $i increments by one to go to the next $rule_list
        // - if replacing one rule with no rules, $i does not change so $rule_list[$i] will be what was the next rule
        // - if replacing one rule with X rules, $i increments by X to point after the inserted rules as we have already ensured these rules don't overlap
        $i+= count($new_nonoverlap);
      }
    }
  }

  ################################################################################################################################################################
  ## merge_rules($rule_list)                                                                                                                                    ##
  ##                                                                                                                                                            ##
  ## Given an array containing a set of sanitised ACLRules to add into $this->rulelist, add these rules into the list and merge them with any existing rules    ##
  ## if they can be joined together into a single rule. This is important as it allows non-ideal solutions to still be properly parsed and checked against an   ##
  ## optimal set of ACL rules.                                                                                                                                  ##
  ##                                                                                                                                                            ##
  ## The basic algorithm is simple, for each new rule we wish to add to $this->rulelist, we try to merge it with as many existing rules as possible. If it can  ##
  ## be merged with an existing rule in $this->rulelist, the new rule contains the merged details and the rule it has merged with is removed from               ##
  ## $this->rulelist. When no further merging is possible, the new (larger, merged) rule is simply added to $this->rulelist and becomes an existing rule for    ##
  ## the next new rule to be merged into the list.                                                                                                              ##
  ################################################################################################################################################################
  private function merge_rules($rule_list)
  {
    // Loop through each new rule we wish to add to the ACL
    foreach ($rule_list as $new_rule)
    {
      // We loop until we decide we cannot merge any more existing rule to this new rule, then the "continue" will break the "while" loop
      while (true)
      {
        // Loop through each existing rule to try to merge it with the new rule before adding the new rule
        // This may run multiple times, after we merge everything, we try again in case the new - bigger - merged rule can now merge with a
        // rule it could not merge with before
        for ($i = 0; $i < count($this->rulelist); $i++)
        {
          // Try to merge the current existing rule with the new rule, if it succeeds:
          // - Delete the existing rule from the list of existing rules (it is merged and contained within the new rule
          // - Call continue to restart the check of the now changed new rule against all existing rules (restart the "for ($i = 0..." loop)
          if ($new_rule->merge($this->rulelist[$i]))
          {
            array_splice($this->rulelist, $i, 1);
            continue 2;
          }
        }
        // If we get here, the for loop successfully completed which means that on this iteration no existing rule was able to be merged
        // with the new rule. The "break" will break the while loop, allowing the new rule to be added to the list of existing rules and
        // to move onto the next new rule to try to merge into the ACL
        break;
      }
      $this->rulelist[] = $new_rule;
    }
  }

  ################################################################################################################################################################
  ## sort_rules($rule_list)                                                                                                                                     ##
  ##                                                                                                                                                            ##
  ## To compare with other ACLs for marking, the order of rules in $this->rulelist need to be consistent. We use the usort() function and the ACLRule class     ##
  ## comparison function to resort the rules in $this->rulelist to ensure this is the case.                                                                     ##
  ################################################################################################################################################################
  private function sort_rules()
  {
    usort($this->rulelist, array("ACLRule", "sort_compare"));
  }

  ################################################################################################################################################################
  ## add_statement($statement)                                                                                                                                  ##
  ##                                                                                                                                                            ##
  ## Add an ACL statement to the ACL, remove any overlaps and then simplify the rules. The basic algorithm is:                                                  ##
  ##                                                                                                                                                            ##
  ## - Add statement to $this->raw_statements                                                                                                                   ##
  ## - If the list is finalised, the statement is pointless, also add statement to $this->after_default_rules and return (no more work to do)                   ##
  ## - Call convert_statement() to convert the statement into an array of ACLRule's to potentially add to $this->rulelist                                       ##
  ## - Call sanitise_rules() to sanitise the returned rules to remove any overlaps with existing rules                                                          ##
  ## - If there are no rules to add (after sanitisation), that means this statement is pointless as it will never be matched. Add the statement to              ##
  ##   $this->no_match_rules and return (no more work to do                                                                                                     ##
  ## - Call merge_rules() to merge the sanitised rules into the ACL rule list ($this->rulelist)                                                                 ##
  ## - Call sort_rules() to ensure consistent ordering of rules within $this->rulelist                                                                          ##
  ################################################################################################################################################################
  function add_statement($statement)
  {
//echo "--------------------------------------------------------------------------------\n";
//echo "Processing ACL Statement: $statement\n";

    $this->raw_statements[] = $statement;

    // Step 1: If the ACL has already been finalised, this rule will have no effect, mark the error
    if ($this->finalised)
    {
      // This ACL already has a default deny/permit rule, this statement will never be processed
      $this->after_default_rules[] = $statement;
      return;
    }

    // Step 2: Convert from wildvard rule to IP-ranges rule(s)
    $rules_to_add = $this->convert_statement($statement);

    // Step 3: Sanitise rules to remove any overlaps with existing rules
    $this->sanitise_rules($rules_to_add);

    // Step 4: If rules to add set is empty, this statement has complete overlap and is a rule with zero effect, mark the error
    if (count($rules_to_add) === 0)
    {
      // This rule will never match any packets as they are matched by previous rule(s) in the ACL
      $this->no_match_rules[] = $statement;
      return;
    }

    // Step 5: Merge sanitised rules into existing ACL ruleset
    $this->merge_rules($rules_to_add);

    // Step 6: Re-sort the ACL ruleset to provide consistent ordering
    $this->sort_rules();

    //echo "  Step 7: Displaying - current - ACL\n";
    //$this->print_acl();
  }

  ################################################################################################################################################################
  ## prune($prune_list)                                                                                                                                         ##
  ##                                                                                                                                                            ##
  ## Prune all rules such that the source and destination IPs fall within the $prune_list range. If called with NULL, the prune list is stored in the internal  ##
  ## $this->prune_list parameter. Alternatively called when marking with the internal ($this->prune_list) parameter of the solution ACL                         ##
  ##                                                                                                                                                            ##
  ## - Loop through each rule in $this->rulelist calling prune() on that rule. Update the $this->clean_prune variable to indicate whether the rule is outside   ##
  ##   the bounds of $prune_list BUT not an 'any' rule                                                                                                          ##
  ## - If pruning created an empty rule (source or destination IP range is 0.0.0.0->0.0.0.0), then delete it from the list of rules. We use array_filter() to   ##
  ##   delete these rules and array_values() to re-index the array for comparison reasons                                                                       ##
  ## - Resort rules in case pruning changed the order                                                                                                           ##
  ################################################################################################################################################################
  private function prune_rules($prune_list = NULL)
  {
    if ($prune_list == NULL) $prune_list = $this->prune;

    foreach ($this->rulelist as $rule)
    {
      $orig_rule_string = $rule->to_string();
      if (! $rule->prune($prune_list))
      {
        $this->clean_prune = False;
        $this->unclean_prune_summary[] = $orig_rule_string;
      }
    }

    $this->rulelist = array_values(array_filter($this->rulelist, function($rule) { return ! ($rule->is_empty_rule()); } ));

    $this->sort_rules();
  }

  ################################################################################################################################################################
  ## finalise()                                                                                                                                                 ##
  ##                                                                                                                                                            ##
  ## Called when there are no more rules to add. Checks to see if the ACL Rule has been finalised and if not, adds the default "deny ip any any" rule that      ##
  ## would normally exist at the end of an ACL. If equivalent "any" rules have been set, call $this->prune() to prune the rule set to a minimal range.          ##
  ################################################################################################################################################################
  public function finalise()
  {
    if ($this->finalised === false) $this->add_statement("deny ip any any");

    $this->prune_rules();
  }

  ################################################################################################################################################################
  ## mark_acl($solution, $use_solution_prune)                                                                                                                   ##
  ##                                                                                                                                                            ##
  ## Called when there are no more rules to add. Checks to see if the ACL Rule has been finalised and if not, adds the default "deny ip any any" rule that      ##
  ## would normally exist at the end of an ACL.                                                                                                                 ##
  ################################################################################################################################################################
  public function mark_acl($solution, $use_solution_prune = True)
  {
    if ($use_solution_prune) $this->prune_rules($solution->prune);

    $effective_rule_count                  = count($this->raw_statements) - count($this->no_match_rules) - count($this->after_default_rules);

    $result['same']                        = ($this->rulelist == $solution->rulelist);

    $result['not_optimal']                 = ($effective_rule_count > count($solution->raw_statements));
    $result['not_optimal_message']         = "ACL consists of $effective_rule_count effective rules, optimal solution contains " . count($solution->raw_statements) . " rules";

    $result['pointless_rules']             = (count($this->no_match_rules) > 0);
    $result['pointless_rules_list']        = $this->no_match_rules;

    $result['pointless_post_default']      = (count($this->after_default_rules) > 0);
    $result['pointless_post_default_list'] = $this->after_default_rules;

    $result['not_optimal_prune']           = ! ($this->clean_prune);
    $result['unclean_prune_summary']       = $this->unclean_prune_summary;

    return $result;
  }

  ################################################################################################################################################################
  ## print_acl()                                                                                                                                                ##
  ##                                                                                                                                                            ##
  ## FOR DEBUGGING?DEVELOPMENT PURPOSES ONLY                                                                                                                    ##
  ##                                                                                                                                                            ##
  ## Print out the effective ACL rules. Loops through each ACLRule in $this->rulelist and prints it to the screen.                                              ##
  ################################################################################################################################################################
  public function print_acl()
  {
    foreach ($this->rulelist as $rule) $rule->print_rule();
  }
}

?>
