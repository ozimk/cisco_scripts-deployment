<?php

class SwVLAN
{
  public $name, $status;
  function __construct($name, $status)
  {
    $this->name = $name;
    $this->status = $status;
  }
  function SwVLAN($name, $status)
  {
    $this->name = $name;
    $this->status = $status;
  }
}

################################################################################
# class SwFastEthernetInterface                                                #
#                                                                              #
# Simple object structure to store various pieces of information about a       #
# single interface (ethernet or vlan) on the router.                           #
#  configured  - true if any item has been configured on interface             #
#  port_mode   - string containing switch port mode: trunk/access/none         #
#  vlan        - vlan details for access port                                  #
#  security    - security configuration for the interface                      #
#  shutdown    - NULL if interface is up or true if shutdown                   #
################################################################################
class SwInterface
{
  public $configured, $port_mode, $vlans, $security, $shutdown, $address, $mask, $actual;
}


################################################################################
# class SwPortSecurity                                                         #
#                                                                              #
# Simple object structure to store varous pieces of information about a switch #
# port security function                                                       #
#  security_enable - true if switch port-security is enabled                   #
#  max_devices     - maximum number of allowed devices                         #
#  security_type   - static or dynamic using sticky approach                   #
#  violation       - violation action e.g. shutdown / protect                  #
################################################################################
class SwPortSecurity
{
  public $security_enable, $max_devices, $security_type, $violation;

  function __construct($security_enable, $max_devices, $security_type, $violation)
  {
    $this->security_enable = $security_enable;
    $this->maximum = $max_devices;
    $this->mac_address = $security_type;
	$this->violation = $violation;
  }
  function SwPortSecurity($security_enable, $max_devices, $security_type, $violation)
  {
    $this->security_enable = $security_enable;
    $this->maximum = $max_devices;
    $this->mac_address = $security_type;
	$this->violation = $violation;
  }
}

#  mode - Which STP Algorithm are we running on the switch
#  priority - Priority for this switch (NULL if default)
#  root - true if this is the root bridge, otherwise false (only set if show spanning-tree is captured
#  ports - A list mapping ports to STP algorith state
class SwSpanTree
{
  public $mode, $priority, $root, $ports;
}

################################################################################
# class LineConfig                                                             #
#                                                                              #
# Simple object structure to store various pieces of information about line    #
# access to the router                                                         #
#  login    - Boolean - has the login command been given                       #
#  password - What is the password                                             #
################################################################################
class SwLineConfig
{
  public $login, $password;

  function __construct($login)
  {
    $this->login = $login;
  }
  function LineConfig($login)
  {
    $this->login = $login;
  }
}

################################################################################
# class SwitchConfig                                                           #
#                                                                              #
# Complicated and massive multi-layered data structure to hold the contents of #
# a routers configuration basaed on collected output from a router. We pass    #
# captured output of certain commands to member functions of this class which  #
# will parse and store information in class variables.                         #
# IMPORTANT:                                                                   #
#  NOT TESTED - By renaming ParseRun() to RouterConfig() this function is now  #
#  the class constructor. This is important to stop you from making stupid     #
#  mistakes in code that calls this code. However, the constructor is not      #
#  tested properly yet.                                                        #
# CURRENTLY STORED INFORMATION:                                                #
#  interfaces    - An array of SwInterface indexed by the interface name      #
#  protocols     - An array of RoutingProtocol indexed by the protocol name    #
#  static_routes - An array of StaticRoute                                     #
#  lines         - An array of LineConfig indexed by access type (eg. vty)     #
#  hostname      - The configured router hostname                              #
#  motd          - NULL if not configured otherwise the MOTD                   #
#  pppconfig     - An array of PPPConfig 						               #
#  natconfig     - An array of NATConfig 						               #
#  aclconfig     - An array of ACLConfig
################################################################################

class SwitchConfig
{
  public $device_type, $motd, $interfaces, $defaultgateway, $static_routes, $hostname, $lineconfig, $max_fa_port, $vlans, $spanning_tree;

  ##########
  ## Constructor
  ##
  ## Passed the directory containing the stored capture files
  ## - Loop through all files in the directory reading the contents of each one that
  ##   is an interesting file to parse to understand the configuration
  ## - Parse the "show run" output as that sets up our base data structure
  ## - Parse other interesting captures in turn
  ##########
  function __construct($capture_directory)
  {
    // List of captured commands we are/might be interested in
    $switch_commands = array();
    $switch_commands["show run"] = array("show run", "sh run", "sho run");
    $switch_commands["show ip int brief"] = array("show ip interface brief", "sh ip int brief");
    $switch_commands["sh int trunk"] = array("show interfaces trunk", "show interface trunk", "sh interfaces trunk", "sh int trunk");
    $switch_commands["sh vlan brief"] = array("show vlan brief", "sh vlan brief", "sh vlan bri");
    $switch_commands["sh vlan"] = array("show vlan", "sh vlan");
    $switch_commands["sh spanning-tree"] = array("show spanning-tree", "show span", "sh spanning-tree", "sh span");

    // Storage for what we captured that is interesting to parse
    $captured_output = array();

    if (!($handle = opendir($capture_directory)))
    {
      echo "ERROR: Directory ($capture_directory) doesn't exist\n\n";
      exit(1);
    }

    // Read input from interesting files
    while (($filename = readdir($handle)) !== false) 
      foreach ($switch_commands as $command => $possibilities)
        if (in_array(str_replace("_", " ", $filename), $possibilities)) $captured_output[$command] = file_get_contents("$capture_directory/$filename");

    closedir($handle);

    if (!isset($captured_output["show run"]))
    {
      echo "ERROR: No output from \"show run\" to parse\n\n";
      exit(1);
    }

    // Parse captured output in the correct order
    $this->ParseShowRun($captured_output["show run"]);
    if (isset($captured_output["show ip int brief"])) $this->ParseShowIntBrief($captured_output["show ip int brief"]);
    if (isset($captured_output["sh int trunk"])) $this->ParseShowIntTrunk($captured_output["sh int trunk"]);
    if (isset($captured_output["sh vlan brief"])) $this->ParseShowVLANBrief($captured_output["sh vlan brief"]);
    if (isset($captured_output["sh vlan"])) $this->ParseShowVLAN($captured_output["sh vlan"]);
    if (isset($captured_output["sh spanning-tree"])) $this->ParseShowSpanTree($captured_output["sh spanning-tree"]);
  }
  function SwitchConfig($capture_directory)
  {
    // List of captured commands we are/might be interested in
    $switch_commands = array();
    $switch_commands["show run"] = array("show run", "sh run", "sho run");
    $switch_commands["show ip int brief"] = array("show ip interface brief", "sh ip int brief");
    $switch_commands["sh int trunk"] = array("show interfaces trunk", "show interface trunk", "sh interfaces trunk", "sh int trunk");
    $switch_commands["sh vlan brief"] = array("show vlan brief", "sh vlan brief", "sh vlan bri");
    $switch_commands["sh vlan"] = array("show vlan", "sh vlan");
    $switch_commands["sh spanning-tree"] = array("show spanning-tree", "show span", "sh spanning-tree", "sh span");

    // Storage for what we captured that is interesting to parse
    $captured_output = array();

    if (!($handle = opendir($capture_directory)))
    {
      echo "ERROR: Directory ($capture_directory) doesn't exist\n\n";
      exit(1);
    }

    // Read input from interesting files
    while (($filename = readdir($handle)) !== false) 
      foreach ($switch_commands as $command => $possibilities)
        if (in_array(str_replace("_", " ", $filename), $possibilities)) $captured_output[$command] = file_get_contents("$capture_directory/$filename");

    closedir($handle);

    if (!isset($captured_output["show run"]))
    {
      echo "ERROR: No output from \"show run\" to parse\n\n";
      exit(1);
    }

    // Parse captured output in the correct order
    $this->ParseShowRun($captured_output["show run"]);
    if (isset($captured_output["show ip int brief"])) $this->ParseShowIntBrief($captured_output["show ip int brief"]);
    if (isset($captured_output["sh int trunk"])) $this->ParseShowIntTrunk($captured_output["sh int trunk"]);
    if (isset($captured_output["sh vlan brief"])) $this->ParseShowVLANBrief($captured_output["sh vlan brief"]);
    if (isset($captured_output["sh vlan"])) $this->ParseShowVLAN($captured_output["sh vlan"]);
    if (isset($captured_output["sh spanning-tree"])) $this->ParseShowSpanTree($captured_output["sh spanning-tree"]);
  }

  ##########
  # Takes the output from "show run" as a parameter and parses and updates the
  # internal data structures.
  # 1) Explode the whole show run into "blocks" based on the ! lines in the output
  #    Each block in then processed independently
  # 2) Check the type of each block (the first few words) and based on the type
  #    call the appropriate subfuction to parse the block and populate the
  #    database
  #    a) ParseInterface() - block begining with "interface", this defines the
  #       configuration for a single interface
  #    b) ParseRouter() - block begining with "router", this defines the
  #       configuration for a single routing protocol instance
  #    c) ParseStaticRoute() - block begining with "ip route", this defines all
  #       programmed static routes on the router
  #    d) ParseLines() - block beginning with "line", this defines console,
  #       auxillary and telnet VTY configuration
  #    e) Extract hostname - block beginning with "hostname"
  #    f) Extract MOTD - block beginning with "banner motd"
  #    g) Extract PPP configuration - lines beginning with "username"
  #    h) Extract NAT configuration - lines beginning with "ip nat"
  ##########
  function ParseShowRun($output)
  {
    $this->device_type = "Switch";
	$this ->max_fa_port = 0;
	$config_blocks = explode("!\r\n", $output);

    for ($i = 0; $i < count($config_blocks); $i++)
    {
      # Ignore empty blocks (multiple lines of "!"
      if (strlen($config_blocks[$i]) === 0) continue;

      if (strpos($config_blocks[$i], "interface") === 0)
      {
        $this->ParseInterface($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "line") === 0)
      {
        $this->SwParseLines($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "hostname") === 0)
      {
        $section = explode(" ", $config_blocks[$i], 2);
        if (isset($section[1])) $this->hostname = trim($section[1], "\r\n");
        continue;
      }

      if (strpos($config_blocks[$i], "spanning-tree") === 0)
      {
        $this->ParseSpanningTree($config_blocks[$i]);
        continue;
      }

      # The following options are mixed in a large block, can't continue as we need to keep checking for other opsions
      if (strpos($config_blocks[$i], "ip default-gateway") !== false) $this->ParseDefaultGateway($config_blocks[$i]);

      if (strpos($config_blocks[$i], "banner motd") !== false)
      {
        $banner_block = explode("banner motd", $config_blocks[$i]);
        $banner_text = explode("^C", $banner_block[1], 3);
        if (isset($banner_text[1])) $this->motd = $banner_text[1];
      }
    }
  }

  ##########
  # Takes a string containing configuration for a single interface and creates
  # and fills in the data structure within the interfaces array
  # - The config is broken up into a series of individual lines
  # - The interface name is taken from the first line, a new array entry is created
  # - Subsequent lines are broken up into seperate words and parsed for
  #   interesting configuration options. This data is then stored in the newly
  #   created object
  # - Set the configured variable for quick reference
  ##########
  function ParseInterface($config)
  {
    $lines = explode("\r\n", $config);
    $information = explode(" ", $lines[0], 2);
	
	//security parameters
    $security_enable = false;
	$max_devices = null;
	$security_type = null;
	$violation = null;
	
    if (!isset($information[1])) 
      return;
      
    $interface_name = $information[1];
    $this->interfaces[$interface_name] = new SwInterface();
    $this->interfaces[$interface_name]->vlans = array();
    $this->interfaces[$interface_name]->actual = array();

	//count the number of fast ethernet ports (e.g. switches with 12 or 24 ports)
	
	if (stripos($interface_name,'FastEthernet') === 0)
    {
        $interface_breakdown = explode("/", $interface_name);
		$this->max_fa_port = max($this->max_fa_port, $interface_breakdown[1]);
	}
	
    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (strpos($lines[$i], "no ip address")) continue;
      if (strpos($lines[$i], "ip address"))
      {
        $this->interfaces[$interface_name]->address = $pieces[2];
        $this->interfaces[$interface_name]->mask = $pieces[3];
        $this->interfaces[$interface_name]->slash_mask = strlen(preg_replace("/0/", "", decbin(ip2long($pieces[3]))));
        $this->interfaces[$interface_name]->network = long2ip(ip2long($pieces[2]) & ip2long($pieces[3]));
        $this->interfaces[$interface_name]->host_id = ip2long($pieces[2]) - ip2long($this->interfaces[$interface_name]->network);
        continue;
      }

      if (strpos($lines[$i], "shutdown"))
      {
        $this->interfaces[$interface_name]->shutdown = true;
        continue;
      }
	  if (strpos($lines[$i], "switchport mode"))
      {
        $this->interfaces[$interface_name]->port_mode = $pieces[2];
		continue;
      }
	  
	  if (strpos($lines[$i], "switchport access vlan"))
      {
        $this->interfaces[$interface_name]->vlans[] = $pieces[3];
		continue;
      }
	  
	  if (strpos($lines[$i], "switchport port-security") && (sizeof($pieces) == 2))
      {    
       	$security_enable = true;
		continue;
      }
	  
	  if (strpos($lines[$i], "switchport port-security maximum") && (sizeof($pieces) == 4))
      {    
       	$max_devices= $pieces[3];
		continue;
      }
	  
	  if (strpos($lines[$i], "switchport port-security mac-address") && (sizeof($pieces) == 4))
      {    
       	$security_type= $pieces[3];
		continue;
      }
	  
	  if (strpos($lines[$i], "switchport port-security violation") && (sizeof($pieces) == 4))
      {    
       	$violation= $pieces[3];
		continue;
      }
	  
    }
	$this->interfaces[$interface_name]->security = new SwPortSecurity($security_enable, $max_devices, $security_type, $violation);	
    $this->interfaces[$interface_name]->configured = $this->interfaces[$interface_name]->port_mode || $this->interfaces[$interface_name]->vlan || $this->interfaces[$interface_name]->address; 
  }

 
  ##########
  # The default-gateway command is embedded in a block of other configs. We need to loop
  # through each line of the block and only take the default-gateway
  # - We know there is a default-gateway when the function is called, create the array to store
  #   if it doesn't exist
  # - Break the block into lines
  #   o If the line is the default-gateway line, explode into pieces and take the last element (next-hop address)
  ##########
  function ParseDefaultGateway($config)
  {
    if (is_null($this->defaultgateway)) $this->defaultgateway = array();
    if (is_null($this->static_routes)) $this->static_routes = array();

    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
        if (strpos($lines[$i], "ip default-gateway") !== false)
        {
            $pieces = explode(" ", trim($lines[$i], " "));
            array_push($this->defaultgateway, $pieces[2]);
            array_push($this->static_routes, new StaticRoute("0.0.0.0", "0.0.0.0", $pieces[2]));
        }
    }
  }
  
  ##########
  function ParseSpanningTree($config)
  {
    $this->spanning_tree = new SwSpanTree;

    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));
      if ($pieces[0] !== "spanning-tree") continue;

      switch ($pieces[1])
      {
        case "mode":     // STP Algorithm
                         $this->spanning_tree->mode = $pieces[2];
                         break;
        default:         break;
      }
    }
  }

   ##########
  # Takes the output from "show ip int brief" as a parameter and parses and
  # updates the internal data structures. You must call ParseRun() first
  # to create the NetInterface instances for this function to call
  # - Loop through all interfaces we know about (created by ParseRun()
  # - Find the line from "show ip int brief" for this interface
  # - Get the status entries and put into corresponding data structure
  ##########
  function ParseShowIntBrief($output)
  {
    foreach ($this->interfaces as $name => $details)
    {
      $search = strstr($output, $name);
      $line = substr($search, 0, strpos($search, "\n"));
      $line = preg_replace('/\s+/', ' ', $line);
      $pieces = explode(" ", $line, 5);

      $details->status = trim($pieces[4], " ");
    }
  }
  
  ##########
  # Takes all configuration options for access to the router as a parameter and
  # parses and fills in the $lines array within the class instance
  # - The config is broken up into a series of individual lines
  # - Lines are broken up into seperate words and parsed for configurations
  # - A line beginning with "line" defines a new section and we set the
  #   $current_line variable and create the instance in the $lines array
  # - For other settings we update values within the class instance
  ##########
  function SwParseLines($config)
  {
    $this->lines = array();

    $current_line = "ERROR";
    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if ($pieces[0] == "line")
      {
        $current_line = $pieces[1];
        $this->lines[$current_line] = new SwLineConfig(false);
        continue;
      }

      if ($pieces[0] == "password") $this->lines[$current_line]->password = $pieces[1];

      if ($pieces[0] == "login") $this->lines[$current_line]->login = true;
    }
  }

  ################################################################################
  ################################################################################
  function ParseShowIntTrunk($output)
  {
	$config_blocks = explode("\r\n\r\n", $output);

    // Config Block #1, interfaces, trunk mode, encapsulation, status and native VLAN
    $lines = explode("\r\n", $config_blocks[1]);
    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", preg_replace("/\s+/", " ", $lines[$i]));

      $interface = $this->GetLongInterfaceName($pieces[0]);

      $this->interfaces[$interface]->actual["port_mode"] = $pieces[1];
      $this->interfaces[$interface]->actual["encapsulation"] = $pieces[2];
      $this->interfaces[$interface]->actual["status"] = $pieces[3];
      $this->interfaces[$interface]->actual["native_vlan"] = $pieces[4];
    }

    // Config block #2, allowed VLANs
    $lines = explode("\r\n", $config_blocks[2]);
    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", preg_replace("/\s+/", " ", $lines[$i]));

      $interface = $this->GetLongInterfaceName($pieces[0]);

      $this->interfaces[$interface]->actual["allowed_vlan"] = $this->ExpandVLANLists($pieces[1]);
    }

    // Config block #3, carried VLANs
    $lines = explode("\r\n", $config_blocks[3]);
    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", preg_replace("/\s+/", " ", $lines[$i]));

      $interface = $this->GetLongInterfaceName($pieces[0]);
      if (is_null($interface)) continue;

      $this->interfaces[$interface]->actual["carried_vlan"] = $this->ExpandVLANLists($pieces[1]);
    }
  }

  ################################################################################
  # Convert a short interface name used in many outputs (eg. Fa0/9) to the full
  # name.
  # - Search the list of actual interfaces (populated by parsing "show run") for
  #   a matching interface using a regular expression
  # - Return the matching full interface name
  ################################################################################
  function GetLongInterfaceName($short_name)
  {
    // Search for string starting with first two chars of $pieces[0] and ending with the rest of $pieces[0]
    $reg_exp = '@^' . substr($short_name, 0, 2) . '.*' . substr($short_name, 2) . '$@';
    $search_int = array_values(preg_grep($reg_exp, array_keys($this->interfaces)));
    return $search_int[0];
  }

  ################################################################################
  # Called when parsing trunk information, expand a list of VLANs as output into
  # an array of actual values
  # - Create an empty array to store the value
  # - Split the string on commas, each element is now a single VLAN ID or a range
  #   of VLANs defined by "<start_vlan>-<end_vlan>". For each range
  #   o Call ExpandRange() to return an array of all VLANs defined by the range
  #   o Merge the result with the partially constructed $result array
  ################################################################################
  function ExpandVLANLists($list_string)
  {
    $result = array();
    foreach (explode(',', $list_string) as $sub_range) $result = array_merge($result, $this->ExpandRange($sub_range));
    return $result;
  }

  ################################################################################
  # Called when parsing trunk information, expand a range of VLANs as output by
  # "<start_vlan>-<end_vlan>" into an array of the actual VLAN IDs.
  # - Explode the string around the '-'
  #   o If there is one result, there was no '-' and the range consisted of a 
  #     single VLAN ID. Return that value in an array
  #   o If there are two results, return an array generated by the range() 
  #     command from the start and end values
  #   o There should not be more than 2 results, return an error just in case
  ################################################################################
  function ExpandRange($range_string)
  {
    $delimiters = explode('-', $range_string);

    switch (count($delimiters))
    {
      case 1: return array($range_string); break;
      case 2: return range($delimiters[0], $delimiters[1]); break;
      default: echo "ERROR: Unable to expand $range_string\n\n"; exit(1);
    }
  }

  ################################################################################
  # Parse the output of "show vlan brief" and populate the vlans variable of the
  # class.
  # - Split the output into blocks starting with a number at the start of the
  #   line. The output array has odd elements resolving to the VLAN ID and even
  #   elements with the rest of the output for that VLAN ID
  # - For each VLAN
  #   o Split the VLAN information into three pieces, the first two fields and the
  #     rest. The rest contains the list of interfaces, the first two pieces
  #     contain the VLAN name and it's status
  #   o Create the VLAN array entry storing the name/status
  #   o Explode the list of interfaces into separate interfaces, then strip any
  #     commas, finally convert from a short name (Fa0/5) into the full form 
  #    interface name.
  #   o Set the details for the interface stating that the interface has actually
  #     been configured in "access" mode and store the access VLAN ID
  #
  # vlans[<vlan_id> = SwLAN(<vlan_name>, <vlan_status>)
  # interfaces[<iface>]->actual['port_mode'] = access
  # interfaces[<iface>]->actual['carried_vlan'] = <vlan_id>
  ################################################################################
  function ParseShowVLANBrief($output)
  {
    $this->vlans = array();

    $sections = preg_split("@\n(\d+)@", $output, NULL, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 1; $i < count($sections); $i+=2)
    {
      $vlan_id = $sections[$i];

      if (($vlan_id >= 1002) and ($vlan_id <= 1005)) continue;

      $pieces = explode(" ", preg_replace("/\s+/", " ", trim($sections[$i + 1])), 3);

      $this->vlans[$vlan_id] = new SwVLAN($pieces[0], $pieces[1]);      

      if (is_null($pieces[2])) continue;

      foreach (explode(' ', $pieces[2]) as $short_name)
      {
        $interface = $this->GetLongInterfaceName(trim($short_name, ','));
        if (is_null($interface)) continue;

        $this->interfaces[$interface]->actual["port_mode"] = "access";
        $this->interfaces[$interface]->actual["carried_vlan"] = $vlan_id;
      }
    }
  }

  function ParseShowVLAN($output)
  {
    $sections = explode("\r\n\r\n", trim($output));
    $this->ParseShowVLANBrief($sections[0]);
  }

  ################################################################################
  # Parse the output of "show spanning-tree" and populate the spanning_tree
  # variable of the class.
  # - Parsing "show run" will determine which spanning-tree algorithm is running,
  #   we are mainly interested in storing information about the root bridge,
  #   bridge and root bridge IDs, port roles, and port/path costs
  # - The $port_type array maps port roles in the text output to actual basic STP
  #   roles. This allows flexibility in case some versions of the algorithm have
  #   different output text for port roles
  # - Split the output into blocks for each per-VLAN information. The resultant
  #   array has each odd element being the VLAN ID and even element being the STP
  #   configuration for that VLAN
  #   o Each block consists of three sections (separated by blank lines), split
  #     VLAN information into these blocks
  #   o Extract root bridge priority and root bridge information from block 0
  #   o Extract this bridge priority from block 1
  #   o Block 3 contains port information, split into lines for each interface
  #     - Extract interface name, role, cost and priority
  # - Store all extracted information in array:
  #
  # spanning_tree->root[<vlan_id>] = true if this switch is the root for <vlan_id>
  # spanning_tree->root_priority[<vlan_id>] = bridge priority for the root bridge
  #                                           for <vlan_id>
  # spanning_tree->priority[<vlan_id>] = bridge priority for this switch for
  #                                           <vlan_id>
  # spanning_tree->ports[<vlan_id>]['root'][<iface>]['cost']
  #   <iface> is a root port for STP on <vlan_id> with the nominated path cost
  # spanning_tree->ports[<vlan_id>]['root'][<iface>]['priority']
  #   <iface> is a root port for STP on <vlan_id> with the nominated priority
  # spanning_tree->ports[<vlan_id>]['designated'][<iface>]['cost'|'priority']
  #   <iface> is a designated port for STP on <vlan_id> with cost/priority
  # spanning_tree->ports[<vlan_id>]['blocked'][<iface>]['cost'|'priority']
  #   <iface> is a blocked port for STP on <vlan_id> with cost/priority
  ################################################################################
  function ParseShowSpanTree($output)
  {
    // Map output of port information to actual port role for consistent storage in DB (add for other STP protocols)
    $port_type = array();
    $port_type['Root'] = 'root';
    $port_type['Desg'] = 'designated';
    $port_type['Altn'] = 'blocked';

    // Break output into per-VLAN output
    $vlan_trees = preg_split("@VLAN(\d+)\r\n@", $output, NULL, PREG_SPLIT_DELIM_CAPTURE);

    // Loop through span tree configuration for each VLAN
    for ($i = 1; $i < count($vlan_trees); $i+=2)
    {
      $vlan_id = (int)$vlan_trees[$i];

      // Break into the three blocks, root-config, bridge-config, port-config
      $sections = explode("\r\n\r\n", trim($vlan_trees[$i + 1]));

      // Extract root bridge information, ID + whether we are the root or not
      preg_match('/Priority[ ]+(\d+)/', $sections[0], $matches);
      $this->spanning_tree->root_priority[$vlan_id]  = $matches[1];
      $is_root = (strpos($sections[0], "This bridge is the root") !== false);
      if (strpos($sections[0], "This bridge is the root") !== false) $this->spanning_tree->root[$vlan_id] = true;

      // Extract this bridge ID
      preg_match('/Priority[ ]+(\d+)/', $sections[1], $matches);
      $this->spanning_tree->priority[$vlan_id]  = $matches[1];

      // Break port information into individual ports
      $port_details = explode("\n", trim($sections[2]));

      // Loop through ports (first two lines no information)
      for ($j = 2; $j < count($port_details); $j++)
      {
        // Break into fields and obtain the proper interface name
        $pieces = explode(" ", preg_replace("/\s+/", " ", trim($port_details[$j])));

        $interface = $this->GetLongInterfaceName($pieces[0]);

        // If it is a weird state, continue
        if (($port_type[$pieces[1]] === 'blocked') and ($pieces[2] !== 'BLK')) continue;

        // Store the port cost and priority information
        $this->spanning_tree->ports[$vlan_id][$port_type[$pieces[1]]][$interface]['cost'] = $pieces[3];
        $this->spanning_tree->ports[$vlan_id][$port_type[$pieces[1]]][$interface]['priority'] = $pieces[4];
      }
    }
  }
}

?>
