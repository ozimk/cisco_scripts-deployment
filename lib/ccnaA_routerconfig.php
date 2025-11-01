<?php

################################################################################
# class NetInterface                                                           #
#                                                                              #
# Simple object structure to store various pieces of information about a       #
# single interface on the router.                                              #
#  configured  - true if any item has been configured on interface             #
#  address     - string containing assigned IP address                         #
#  mask        - string containing assigned subnet mask                        #
#  slash_mask  - integer containing assigned subnet mask is slash notation     #
#  host_id     - integer containing host id within the subnet                  #
#  clock       - NULL if not set or not serial interface, otherwise clock rate #
#  shutdown    - NULL if interface is up or true if shutdown                   #
#  status      - Summary of interface from "sh ip int brief" eg. (up up)       #
#  description - NULL if not set, otherwise string containing description      #
#  advertised  - array of routing protocols that are advertising the interface #
#  encapsulation - string containing trunking/FR/PPP encapsulation 			   #
#				that are configured on the interface 						   #
#  vlan_id     - string containing vlan ID configured on the interface 		   #
#  ppp_encapsulation - true if has been configured on interface                #
#  fr_encapsulation - string containing encapsulation type for FR			   #
#  ppp_authentication - string containing authentication type for PPP		   #
#  fr_nokeepalive - true if no keepalive has been configured for FR			   #
#  fr_mapIP 	- string containing next hop IP address for FR mapping	       #
#  fr_mapDLCI   - integer containing DLCI that maps to the next hop IP		   #
#  fr_lmiType   - string containing lmi type configured for FR interface	   #
#  aclName		- string containing name of ACL applied to the interface	   #
#  aclDir		- string containing the direction which the ACL was applied    #
################################################################################
class NetInterface
{
  public $configured, $address, $mask, $slash_mask, $network, $host_id, $clock, $shutdown, $status, $description, 
  $advertised, $loopback_ospf_good, $encapsulation, $trunk, $vlan_type, $vlan_id, $ppp_encapsulation, $ppp_username, $ppp_password, $fr_encapsulation, 
  $ppp_authentication, $fr_nokeepalive, $fr_mapIP, $fr_mapDLCI, $fr_lmiType, $login, $password, $aclName, $aclDir, $natDir ;
}

################################################################################
# class RoutingProtocol                                                        #
#                                                                              #
# Simple object structure to store varous pieces of information about a        #
# configured routing protocol                                                  #
#  type         - string containing which routing protocol has been configured #
#  version      - 1 or 2 if running RIP, otherwise NULL                        #
#  as_number    - Autonomous system number if EIGRP, otherwise NULL            #
#  proc_id      - Process ID if running OSPF, otherwise NULL                   #
#  redistribute - true if redistributing static routes, otherwise false        #
#  networks     - nothing yet, for future use                                  #
#  bad_network_statements - Routing protocol network statements that do not    #
#                           advertise any interfaces on the router             #
################################################################################
class RoutingProtocol
{
  public $version, $as_number, $proc_id, $auto_summary, $redistribute, $networks, $areas, $bad_network_statements;
}

################################################################################
# class StaticRoute                                                            #
#                                                                              #
# Simple object structure to store varous pieces of information about a static #
# route                                                                        #
#  network - Network address for this route                                    #
#  mask    - Subnet mask for this route                                        #
#  via     - Next hop IP or exit interface                                     #
################################################################################
class StaticRoute
{
  public $network, $mask, $via;

  function StaticRoute($network, $mask, $via)
  {
    $this->network = $network;
    $this->mask = $mask;
    $this->via = $via;
  }
}

################################################################################
# class LineConfig                                                             #
#                                                                              #
# Simple object structure to store various pieces of information about line    #
# access to the router                                                         #
#  login    - Boolean - has the login command been given                       #
#  password - What is the password                                             #
################################################################################
class LineConfig
{
  public $login, $password, $aclName, $aclDir;

  function LineConfig($login, $password, $aclName, $aclDir)
  {
    $this->login = $login;
	$this->password = $password;
	$this->aclName = $aclName;
	$this->aclDir = $aclDir;
  }
}

################################################################################
# class PPPConfig                                                              #
#                                                                              #
# Simple object structure to store various pieces of information about         #
# PPP configuration on a router                                                #
#  username - should be the hostname of the neighbouring router                #
#  password - should be the specified password                                 #
################################################################################
class PPPConfig
{
  public $username, $password;

  function PPPConfig($username, $password)
  {
    $this->username = $username;
    $this->password = $password;
  }
}

################################################################################
# class NATConfig and NATBinding                                               #
#                                                                              #
# Simple object structure to store varous pieces of information about NAT      #
# configuration																   #
#  pool 	- Pool name for public address pool                                #
#  startIP  - 1st IP address of the public pool     						   #
#  lastIP   - Last IP address of the public pool     						   #
#  mask     - Subnet mask for this route                                       #
#  binding  - true if there was binding inside source list <-> pool			   #
#  NATAcl   - NAT ACL for internal addresses								   # 
#  poolBound - Name of the public pool for the binding						   #
################################################################################
class NATConfig
{
  public $pool, $startIP, $lastIP, $mask;
  function NATConfig($pool, $startIP, $lastIP, $mask)
  {
    $this->pool = $pool;
    $this->mask = $mask;
    $this->startIP = $startIP;
	$this->lastIP  = $lastIP;
 }
}
class NATBinding
{
  public $binding, $NATAcl, $poolBound;

  function NATBinding($binding, $NATAcl, $poolBound)
  {
 	$this->binding = $binding;
	$this->NATAcl  = $NATAcl;
	$this->poolBound = $poolBound;
  }
}

class ACLConfig
{
  public $acltype, $name, $rule;
  function ACLConfig( $acltype, $name, $rule)
  {
    $this->acltype = $acltype;
    $this->name = $name;
	$this->rule = $rule;

  }
}

class DHCPConfig
{
  public $pool_name, $default_route, $dns_server, $domain_name, $excluded;

  function DHCPConfig($pool, $route, $dns, $domain)
  {
    $this->pool_name = $pool;
    $this->default_route = $route;
    $this->dns_server = $dns;
    $this->domain_name = $domain;
    $this->excluded = array();    
  }
}

################################################################################
# class RouterConfig                                                           #
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
#  interfaces    - An array of NetInterface indexed by the interface name      #
#  protocols     - An array of RoutingProtocol indexed by the protocol name    #
#  static_routes - An array of StaticRoute                                     #
#  lines         - An array of LineConfig indexed by access type (eg. vty)     #
#  hostname      - The configured router hostname                              #
#  motd          - NULL if not configured otherwise the MOTD                   #
#  pppconfig     - An array of PPPConfig 						               #
#  natconfig     - An array of NATConfig 						               #
#  aclconfig     - An array of ACLConfig
################################################################################
class RouterConfig
{
  public $device_type, $interfaces, $routing_itp, $static_routes, $lines, $hostname, $motd, $users, $natconfig, $natbinding, $aclconfig, $dhcp, $lineconfig;

  ##########
  ## Constructor
  ##
  ## Passed the directory containing the stored capture files
  ## - Loop through all files in the directory reading the contents of each one that
  ##   is an interesting file to parse to understand the configuration
  ## - Parse the "show run" output as that sets up our base data structure
  ## - Parse other interesting captures in turn
  ##########
  function RouterConfig($capture_directory)
  {
    // List of captured commands we are/might be interested in
    $router_commands = array();
    $router_commands["show run"] = array("show run", "sh run", "sho run");
    $router_commands["show ip int brief"] = array("show ip interface brief", "sh ip int brief");

    // Storage for what we captured that is interesting to parse
    $captured_output = array();

    if (!($handle = opendir($capture_directory)))
    {
      echo "ERROR: Directory ($capture_directory) doesn't exist\n\n";
      exit(1);
    }

    // Read input from interesting files
    while (($filename = readdir($handle)) !== false) 
      foreach ($router_commands as $command => $possibilities)
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
    $this->device_type = "Router";
    $dhcp_excluded = array();
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

      if (strpos($config_blocks[$i], "router") === 0)
      {
        $this->ParseRouter($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "ip route") !== false)
      {
        $this->ParseStaticRoute($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "line") === 0)
      {
        $this->ParseLines($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "hostname") === 0)
      {
        $section = explode(" ", $config_blocks[$i], 2);
        if (isset($section[1])) $this->hostname = trim($section[1], "\r\n");
        continue;
      }

      if (strpos($config_blocks[$i], "banner motd") === 0)
      {
        $section = explode("^C", $config_blocks[$i], 3);
        if (isset($section[1])) $this->motd = $section[1];
        continue;
      }
	  if (strpos($config_blocks[$i], "username") === 0)
      {
        $this->ParseUsernameConfig($config_blocks[$i]);
        continue;
      }
	  if (strpos($config_blocks[$i], "ip nat pool") === 0)
      {
        $this->ParseNATConfig($config_blocks[$i]);
        continue;
      }
	  if (strpos($config_blocks[$i], "ip access-list") === 0)
      {
		$this->ParseACLConfig($config_blocks[$i]);
        continue;
      }
	  if (strpos($config_blocks[$i], "ip dhcp") === 0)
      {
		$this->ParseDHCP($config_blocks[$i]);
        continue;
      }
      if (strpos($config_blocks[$i], "ip cef") === 0)
      {
        $dhcp_excluded = array_merge($dhcp_excluded, $this->ParseDHCPExclude($config_blocks[$i]));
        continue;
      }
    }

    // DCHP excluded blocks come before the DHCP pools, need to loop through and see which pool each address is excluded from
    $this->dhcp['bad_exclusions']['excluded'] = array();

    foreach ($dhcp_excluded as $address)
    {
      $matched_network = 'bad_exclusions';
      foreach (array_keys($this->dhcp['networks']) as $network)
      {
        $net_add = substr($network, 0, strpos($network, '/'));
        $check_add = long2ip(ip2long(trim($address)) & (~(pow(2, 32 - substr($network, strpos($network, '/') + 1)) - 1)));

        if ($net_add === $check_add) $matched_network = $network;
      }
      $this->dhcp['networks'][$matched_network]['excluded'][] = $address;
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
    if (!isset($information[1])) 
      return;
      
    $interface_name = $information[1];
    $this->interfaces[$interface_name] = new NetInterface();
    $this->interfaces[$interface_name]->advertised = array();
    $this->interfaces[$interface_name]->loopback_ospf_good = (strpos($interface_name, "Loopback") === false);

    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (strpos($lines[$i], "no ip address")) continue;

      # Parse the IP address for this interface (address, mask, slash notation, network address and host_id
      if (strpos($lines[$i], "ip address"))
      {
        $this->interfaces[$interface_name]->address = $pieces[2];
        $this->interfaces[$interface_name]->mask = $pieces[3];
        $this->interfaces[$interface_name]->slash_mask = strlen(preg_replace("/0/", "", decbin(ip2long($pieces[3]))));
        $this->interfaces[$interface_name]->network = long2ip(ip2long($pieces[2]) & ip2long($pieces[3]));
        $this->interfaces[$interface_name]->host_id = ip2long($pieces[2]) - ip2long($this->interfaces[$interface_name]->network);
        continue;
      }

      # Parse the configured clock rate for this interface
      if (strpos($lines[$i], "clock rate"))
      {
        $this->interfaces[$interface_name]->clock = $pieces[2];
        continue;
      }

      # Check if interface is shutdown
      if (strpos($lines[$i], "shutdown"))
      {
        $this->interfaces[$interface_name]->shutdown = true;
        continue;
      }

      # Extract interface description
      if ($pieces[0] == "description")
      {
        $temp = explode(" ", trim($lines[$i], " "), 2);
        $this->interfaces[$interface_name]->description = $temp[1];
        continue;
      }

      # Check if the "ip ospf network point-to-point" command has been specified
      if (strpos($lines[$i], "ip ospf network point-to-point"))
      {
        $this->interfaces[$interface_name]->loopback_ospf_good = true;
        continue;
      }

      # Encapsulation on this interface, store encaptulation type
	  if (strpos($lines[$i], "encapsulation"))
      {
        $this->interfaces[$interface_name]->encapsulation = $pieces[1];
        switch ($this->interfaces[$interface_name]->encapsulation)
        {
          case 'dot1Q':
          case 'isl':         $this->interfaces[$interface_name]->vlan_id = $pieces[2];

                              # Element 0 is parent interface, element 1 is sub-interface number
                              $interface_breakdown = explode(".", $interface_name);
                              $parent_interface = $interface_breakdown[0];
                              $sub_interface = $interface_breakdown[1];

                              if (is_null($this->interfaces[$parent_interface]->trunk)) $this->interfaces[$parent_interface]->trunk = array();
                              $this->interfaces[$parent_interface]->trunk[$pieces[2]] = $sub_interface;
                              break;
          case 'ppp':         $this->interfaces[$interface_name]->ppp_encapsulation = true;
                              break;
          case 'frame-relay': $this->interfaces[$interface_name]->fr_encapsulation = (isset($pieces[2]))?($pieces[2]):("default");
                              break;
        }
        continue;
      }
	
      if (strpos($lines[$i], "ppp authentication")) 
		{
			$this->interfaces[$interface_name]->ppp_authentication = $pieces[2];
            if ($pieces[2] === 'chap') $this->interfaces[$interface_name]->ppp_username = strtolower($this->hostname);
			continue;
		}	
        
      if (strpos($lines[$i], "ppp pap sent-username")) 
		{
			$this->interfaces[$interface_name]->ppp_username = strtolower($pieces[3]);
			$this->interfaces[$interface_name]->ppp_password = $pieces[5];
			continue;
		}	
        
	  if (strpos($lines[$i], "no keepalive")) 
		{
			$this->interfaces[$interface_name]->fr_nokeepalive = true;
			continue;
		} 
		
	  if (strpos($lines[$i], "frame-relay map")) 
		{
			$this->interfaces[$interface_name]->fr_mapIP = $pieces[3];
			$this->interfaces[$interface_name]->fr_mapDLCI = intval($pieces[4]);
			continue;
		}
		
	  if (strpos($lines[$i], "frame-relay interface-dlci")) 
		{
			$this->interfaces[$interface_name]->fr_mapIP = "InverseArp";
			$this->interfaces[$interface_name]->fr_mapDLCI = intval($pieces[2]);
			continue;
		}
	  if (strpos($lines[$i], "frame-relay lmi-type")) 
		{
			$this->interfaces[$interface_name]->fr_lmiType = $pieces[2];
			continue;
		}
	  if (strpos($lines[$i], "ip access-group"))
      {
        $this->interfaces[$interface_name]->aclName = $pieces[2];
		$this->interfaces[$interface_name]->aclDir = $pieces[3];
        continue;
      }
	  if (strpos($lines[$i], "ip nat"))
      {
        $this->interfaces[$interface_name]->natDir = $pieces[2];
		continue;
      }
    }
    $this->interfaces[$interface_name]->configured = is_null($this->interfaces[$interface_name]->shutdown) ||
                                                     isset($this->interfaces[$interface_name]->address); 
  }

  ##########
  # Takes a string containing configuration for one routing protocol and creates
  # and fills in the data structure within the protocols array
  # - The config is broken up into a series of individual lines
  # - The protocol name is taken from the first line, a new array entry is created
  # - Default values are set for the RoutingProtocol instance, some defaults are
  #   based on the actual routing protocol
  # - Subsequent lines are broken up into seperate words and parsed for relevant
  #   configuration options. This data is then stored in the newly created object
  # - Extra work is done for parsing the "network" statements
  ##########
  function ParseRouter($config)
  {
    $lines = explode("\r\n", $config);
    $information = explode(" ", $lines[0], 3);
    $protocol = trim($information[1], "\r");
    $this->routing_itp[$protocol] = new RoutingProtocol();
    //$this->protocols[$protocol]->type = $protocol;
    $this->routing_itp[$protocol]->redistribute = false;
    $this->routing_itp[$protocol]->networks = array();
    $this->routing_itp[$protocol]->areas = array();
    $this->routing_itp[$protocol]->bad_network_statements = array();

    if ($protocol == "rip") $this->routing_itp[$protocol]->version = 1;
    if ($protocol == "eigrp") $this->routing_itp[$protocol]->as_number = $information[2];
    if ($protocol == "ospf")
    {
      $this->routing_itp[$protocol]->proc_id = $information[2];
      $this->routing_itp[$protocol]->auto_summary = false;
    } else
      $this->routing_itp[$protocol]->auto_summary = true;

    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      // Parse a network statement
      if (strpos($lines[$i], "network"))
      {
        $interfaces_matching = 0;

        // Calculate the network subnet mask, if not provided (RIP or EIGRP), calculate from the network address
        $octets = explode(".", $pieces[1]);
        $mask = (isset($pieces[2]))?(long2ip(~ip2long($pieces[2]))):("255." . (($octets[0] < 128)?("0."):("255.")) . (($octets[0] < 192)?("0.0"):("255.0")));

        // Loop through all interfaces on this device
        foreach ($this->interfaces as $name => $details)
        {
          // If this interface is advertised by this network statement
          if (($details->configured) && ((ip2long($details->network) & ip2long($mask)) == ip2long($pieces[1])))
          {
            // Add routing protocol to array routing protocols this interface is advertised by
            array_push($details->advertised, $protocol);

            // Update array of interfaces advertised by this protocol
            // If OSPF, the interface is an array element mapping to an area number
            if ($protocol == "ospf")
            {
$this->routing_itp[$protocol]->networks[$name] = $pieces[4];
$this->routing_itp[$protocol]->areas[$pieces[4]][] = $name;
//              if (!isset($this->routing_itp[$protocol]->networks[$pieces[4]])) $this->routing_itp[$protocol]->networks[$pieces[4]] = array();
//              array_push($this->routing_itp[$protocol]->networks[$pieces[4]], $name);
            } else
$this->routing_itp[$protocol]->networks[$name] = null;
//              array_push($this->routing_itp[$protocol]->networks, $name);

            // Increment count of how many interfaces matched this network statement
            $interfaces_matching++;
          }
        }
        if ($interfaces_matching == 0) array_push($this->routing_itp[$protocol]->bad_network_statements, $lines[$i]);
        continue;
      }
      if (strpos($lines[$i], "version"))
      {
        $this->routing_itp[$protocol]->version = $pieces[1];
        continue;
      }
      if (strpos($lines[$i], "no auto-summary"))
      {
        $this->routing_itp[$protocol]->auto_summary = false;
        continue;
      }
      if ((strpos($lines[$i], "redistribute static")) || (strpos($lines[$i], "default-information originate")))
      {
        $this->routing_itp[$protocol]->redistribute = true;
        continue;
      }
    }
  }

  ##########
  # Takes a list of configured static routes as a parameter and parses and
  # fills in the $static_routes array within the class instance
  # - The config is broken up into a series of individual lines
  # - Lines are broken up into seperate words and parsed for routing 
  #   configurations. We check that the line is actually an "ip route" command
  #   and push the route onto the $static_routes array
  ##########
  function ParseStaticRoute($config)
  {
    if (is_null($this->static_routes)) $this->static_routes = array();

    $lines = explode("\r\n", $config);

    foreach ($lines as $line)
    {
      $pieces = explode(" ", trim($line, " "));

      if (($pieces[0] == "ip") && ($pieces[1] == "route"))
        array_push($this->static_routes, new StaticRoute($pieces[2], $pieces[3], $pieces[4]));
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
  function ParseLines($config)
  {
    $lines = explode("\r\n", $config);
    $login = NULL;
	$password = NULL;
	$aclName = NULL;
	$aclDir = NULL;
    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));
      if ($pieces[0] == "password") $password = $pieces[1];
	  if ($pieces[0] == "login") $login = true;
	  if ($pieces[0] == "access-class")
      {
        $aclName = $pieces[1];
		$aclDir = $pieces[2];
        //continue;
      }
    }	
    $this->lineconfig = new LineConfig($login, $password, $aclName, $aclDir); 
  }
  
  ##########
  # Takes all username configuration options and create a user/password database within the class
  # - The config is broken up into a series of individual lines
  # - Lines are broken up into seperate words
  # - Lines with format "username XXX password 0 XXX" have the username and password extracted
  #   and stored in $this->users. Usernames are stored in lowercase as they are case-insensitive
  ##########
  function ParseUsernameConfig($config)
  {
    if (is_null($this->users)) $this_static_routes = array();

    $lines = explode("\r\n", $config);

    foreach ($lines as $line)
    {
      $pieces = explode(" ", trim($line, " "));

      if (($pieces[0] == "username") && ($pieces[2] == "password")) $this->users[strtolower($pieces[1])] = $pieces[4];
    }
  }

  ##########
  # Takes all configuration options for NAT as a parameter and
  # parses and fills in the $natconfig array within the class instance
  # - The config is broken up into a series of individual lines
  # - Lines are broken up into seperate words and parsed for configurations
  # - Lines are broken up into seperate words and parsed for routing 
  #   configurations. We check that the line is actually an "ip nat pool..." and "ip nat inside source list ..." commands
  #   and push the route onto the $natconfig array
  ##########

  function ParseNATConfig($config)
  {
    $this->natconfig = array();
	$this->natbinding = array();
	
    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (($pieces[0] == "ip") && ($pieces[1] == "nat") && ($pieces[2] == "pool"))
		{	$poolname = $pieces[3];
			$startIP = $pieces[4];
			$lastIP = $pieces[5];
			$mask = $pieces[7];
			array_push($this->natconfig, new NATConfig($poolname, $startIP, $lastIP, $mask ));
			
		}
	  if (($pieces[0] == "ip") && ($pieces[1] == "nat") && ($pieces[2] == "inside") && ($pieces[3] == "source") && ($pieces[4] == "list") )
		{	$binding = true;
			$NATAcl = $pieces[5];
			$poolBound = $pieces[7];
			array_push($this->natbinding, new NATBinding($binding, $NATAcl,$poolBound ));
		}
    }
  }
  
   function ParseACLConfig($config)
  {
    if (!isset($this->aclconfig))
      $this->aclconfig = array();
		
    $lines = explode("\r\n", $config);
    $acl_rules = array();
    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (isset($pieces[2]) && (($pieces[2] == "standard") || ($pieces[2] == "extended")))
		{	
          $acltype = $pieces[2];
          $name = $pieces[3];
		}
	  if (($pieces[0] == "permit") || ($pieces[0] == "deny"))
		{	
          $rule = implode(" ",$pieces);
          array_push($acl_rules, $rule);
		}
    }
    array_push($this->aclconfig, new ACLConfig($acltype, $name, $acl_rules));
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
  function ParseDHCP($config)
  {
    if (!isset($this->dhcp)) 
    {
      $this->dhcp = array();
      $this->dhcp['empty_pools'] = array();
    }

    $lines = explode("\r\n", $config);
    $information = explode(" ", $lines[0], 4);

    if (($information[0] !== "ip") and ($information[1] !== "dhcp") and ($information[2] !== "pool")) return;

    $pool_name = $information[3];

    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      // Parse the network addresses advertised	  
      if ($pieces[0] == "network") $network = $pieces[1] . '/' . strlen(preg_replace("/0/", "", decbin(ip2long($pieces[2]))));

      // Parse the default-router option
      if ($pieces[0] == "default-router") $default_route = $pieces[1];

      // Parse the dns-server option
      if ($pieces[0] == "dns-server") $dns_server = $pieces[1];

      // Parse the domain-name option
      if ($pieces[0] == "domain-name") $domain_name = $pieces[1];

      // not parsing lease [days [hours [minutes]]] | infinite
      // also not parsing ip helper address assigned on interface to forward DHCP requests
    }

    if (isset($network)) 
    {
      if (isset($pool_name))     $this->dhcp['networks'][$network]['pool'] = $pool_name;
      if (isset($default_route)) $this->dhcp['networks'][$network]['gateway'] = $default_route;
      if (isset($dns_server))    $this->dhcp['networks'][$network]['dns'] = $dns_server;
      if (isset($domain_name))   $this->dhcp['networks'][$network]['domain'] = $domain_name;
    } else
    {
      if (isset($pool_name))     $this->dhcp['empty_pools'][]['pool'] = $pool_name;
      if (isset($default_route)) $this->dhcp['empty_pools'][]['gateway'] = $default_route;
      if (isset($dns_server))    $this->dhcp['empty_pools'][]['dns'] = $dns_server;
      if (isset($domain_name))   $this->dhcp['empty_pools'][]['domain'] = $domain_name;
    }
  }

  ##########
  # Parses the "ip cef" block which more importantly includes the "ip dhcp excluded-address"
  # lines. As the excluded addresses are before the actual DHCP pools, we need to return an
  # array of all excluded addresses which will later be allocated to the individual pools
  # - Create an empty array to store excluded addresses
  # - Loop through each individual line
  #   o Only process the "ip dhcp excluded-address" lines
  #   o Extract the 3rd and 4th word (low and high IP addresses in range)
  #   o If the high address is not set, set it to the low (only one address)
  #   o Create an array of all the IP addresses in the range and merge it with any other
  #     addresses in $excluded
  # - Return the array of excluded addresses
  ##########
  function ParseDHCPExclude($config)
  {
    $excluded = array();

    $lines = explode("\r\n", $config);
    foreach ($lines as $line)
    {
      if (strpos($line, "ip dhcp excluded-address") === false) continue;

      $pieces = explode(" ", trim($line, " "));
      $low_IP = $pieces[3];
      $high_IP = (isset($pieces[4]))?($pieces[4]):($low_IP);
      $excluded = array_merge($excluded, array_map('long2ip', range(ip2long($low_IP), ip2long($high_IP))));
    }
    return $excluded;
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
}

?>
