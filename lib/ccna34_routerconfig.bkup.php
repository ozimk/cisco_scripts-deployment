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
  $advertised, $loopback_ospf_good, $encapsulation, $vlan_id, $ppp_encapsulation, $fr_encapsulation, 
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
  public $type, $version, $as_number, $proc_id, $auto_summary, $redistribute, $networks, $bad_network_statements;
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
#  username - should be the hostname of the neigboring router                  #
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
  public $poolname, $network, $netmask, $default_route;
  function DHCPConfig($poolname, $network, $netmask, $default_route)
  { 
    $this->poolname = $poolname;
	$this->network = $network;
	$this->netmask = $netmask;
	$this->default_route = $default_route;
  }
}
class DHCPExclude
{
  public $excludedIPlow, $excludedIPhigh;
  function  DHCPExclude($excludedIPlow, $excludedIPhigh)
  {
    $this->excludedIPlow = $excludedIPlow;
	$this->excludedIPhigh = $excludedIPhigh;
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
  public $interfaces, $protocols, $static_routes, $lines, $hostname, $motd, $pppconfig, $natconfig, $natbinding, $aclconfig, $dhcpconfig, $dhcpexclude, $lineconfig;

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
  function RouterConfig($output)
  {
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
        $this->ParsePPPConfig($config_blocks[$i]);
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
		$this->ParseDHCPConfig($config_blocks[$i]);
        continue;
      }
      if (strpos($config_blocks[$i], "ip cef") === 0)
      {
        $this->ParseDHCPConfig_Exclude($config_blocks[$i]);
        continue;
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
      if (strpos($lines[$i], "ip address"))
      {
        $this->interfaces[$interface_name]->address = $pieces[2];
        $this->interfaces[$interface_name]->mask = $pieces[3];
        $this->interfaces[$interface_name]->slash_mask = strlen(preg_replace("/0/", "", decbin(ip2long($pieces[3]))));
        $this->interfaces[$interface_name]->network = long2ip(ip2long($pieces[2]) & ip2long($pieces[3]));
        $this->interfaces[$interface_name]->host_id = ip2long($pieces[2]) - ip2long($this->interfaces[$interface_name]->network);
        continue;
      }
      if (strpos($lines[$i], "clock rate"))
      {
        $this->interfaces[$interface_name]->clock = $pieces[2];
        continue;
      }
      if (strpos($lines[$i], "shutdown"))
      {
        $this->interfaces[$interface_name]->shutdown = true;
        continue;
      }
      if ($pieces[0] == "description")
      {
        $temp = explode(" ", trim($lines[$i], " "), 2);
        $this->interfaces[$interface_name]->description = $temp[1];
        continue;
      }
      if (strpos($lines[$i], "ip ospf network point-to-point"))
      {
        $this->interfaces[$interface_name]->loopback_ospf_good = true;
        continue;
      }
	  if (strpos($lines[$i], "encapsulation"))
      {
        $this->interfaces[$interface_name]->encapsulation = $pieces[1];
		if ($pieces[1] == "dot1Q" || $pieces[1] == "isl")
		{	
			$this->interfaces[$interface_name]->vlan_id = $pieces[2];
			continue;
		}
		else if ($pieces[1] == "ppp")
		{	
			$this->interfaces[$interface_name]->ppp_encapsulation = true;
			continue;
		}
		else if ($pieces[1] == "frame-relay" )
		{	
			$this->interfaces[$interface_name]->fr_encapsulation = $pieces[2];
			continue;
		}
      }
	
      if (strpos($lines[$i], "ppp authentication")) 
		{
			$this->interfaces[$interface_name]->ppp_authentication = $pieces[2];
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
    $this->protocols[$protocol] = new RoutingProtocol();
    $this->protocols[$protocol]->type = $protocol;
    $this->protocols[$protocol]->redistribute = false;
    $this->protocols[$protocol]->networks = array();
    $this->protocols[$protocol]->bad_network_statements = array();

    if ($protocol == "rip") $this->protocols[$protocol]->version = 1;
    if ($protocol == "eigrp") $this->protocols[$protocol]->as_number = $information[2];
    if ($protocol == "ospf")
    {
      $this->protocols[$protocol]->proc_id = $information[2];
      $this->protocols[$protocol]->auto_summary = false;
    } else
      $this->protocols[$protocol]->auto_summary = true;

    for ($i = 1; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (strpos($lines[$i], "network"))
      {
        $interfaces_matching = 0;
        if (!isset($pieces[2]))
        {
          // Running RIP or EIGRP with no wildcard mask
          $octets = explode(".", $pieces[1]);
          $mask = "255.";
          $mask.= ($octets[0] < 128)?"0.":"255.";
          $mask.= ($octets[0] < 192)?"0.0":"255.0";
          foreach ($this->interfaces as $name => $details)
          {
            if (($details->configured) && ((ip2long($details->network) & ip2long($mask)) == ip2long($pieces[1])))
            {
              // array of which routing protocols this interface is advertised with
              array_push($details->advertised, $protocol);
              // array of interfaces advertised by this protocol
              array_push($this->protocols[$protocol]->networks, $name);
              $interfaces_matching++;
            }
          }
        } 
        else if (isset($this->interfaces))
        {
          // Running OSPF or EIGRP with a wildcard mask
          foreach ($this->interfaces as $name => $details)
          {
            $mask = long2ip(~ip2long($pieces[2]));
#            if (($details->network == $pieces[1]) && ($details->mask == long2ip(~ip2long($pieces[2]))))
            if (($details->configured) && ((ip2long($details->network) & ip2long($mask)) == ip2long($pieces[1])))
            {
              // array of which routing protocols this interface is advertised with
              array_push($details->advertised, $protocol);
              if ($protocol == "ospf")
              {
                // array of interfaces advertised by this protocol
                if (!isset($this->protocols[$protocol]->networks[$pieces[4]])) $this->protocols[$protocol]->networks[$pieces[4]] = array();
                array_push($this->protocols[$protocol]->networks[$pieces[4]], $name);
              } else
                // array of interfaces advertised within each OSPF area
                array_push($this->protocols[$protocol]->networks, $name);
              $interfaces_matching++;
            }
          }
        }
        if ($interfaces_matching == 0) array_push($this->protocols[$protocol]->bad_network_statements, $lines[$i]);
        continue;
      }
      if (strpos($lines[$i], "version"))
      {
        $this->protocols[$protocol]->version = $pieces[1];
        continue;
      }
      if (strpos($lines[$i], "no auto-summary"))
      {
        $this->protocols[$protocol]->auto_summary = false;
        continue;
      }
      if ((strpos($lines[$i], "redistribute static")) || (strpos($lines[$i], "default-information originate")))
      {
        $this->protocols[$protocol]->redistribute = true;
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
    $this->static_routes = array();

    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

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
  # Takes all configuration options for PPP configuration with a neighbour router as a parameter and
  # parses and fills in the $pppconfig array within the class instance
  # - The config is broken up into a series of individual lines
  # - Lines are broken up into seperate words and parsed for configurations
  # - Lines are broken up into seperate words and parsed for routing 
  #   configurations. We check that the line is actually an "username XXX password 0 XXX" command
  #   and push the route onto the $pppconfig array
  ##########

  function ParsePPPConfig($config)
  {
    $this->pppconfig = array();

    $lines = explode("\r\n", $config);

    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (($pieces[0] == "username") && ($pieces[2] == "password"))
        array_push($this->pppconfig, new PPPConfig($pieces[1], $pieces[4]));
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

function ParseDHCPConfig($config)
{
    if (!isset($this->dhcpconfig))
      $this->dhcpconfig = array();
      
	$lines = explode("\r\n", $config);
    for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));
      if (($pieces[0] == "ip") && ($pieces[1] == "dhcp") && ($pieces[2] == "pool"))
		{	
		   $poolname = $pieces[3];
		}
	  
	  if ($pieces[0] == "network") 
		{	
		   $network = $pieces[1];
		   $netmask = $pieces[2];
		}
	  if ($pieces[0] == "default-router") 
		   $default_router = $pieces[1];
		
	}
	array_push($this->dhcpconfig, new DHCPconfig($poolname, $network , $netmask, $default_router ));
}

function ParseDHCPConfig_Exclude($config)
{
    if (!isset($this->dhcpexclude))
      $this->dhcpexclude = array();
    
    $lines = explode("\r\n", $config);
	for ($i = 0; $i < count($lines); $i++)
    {
      $pieces = explode(" ", trim($lines[$i], " "));
      if (($pieces[0] == "ip") && ($pieces[1] == "dhcp") && ($pieces[2] == "excluded-address"))
		{ 
		  $excludedIPlow = $pieces[3];
		  if (isset($pieces[4]))
		    $excludedIPhigh = $pieces[4];
		  else 
		    $excludedIPhigh ="";
            
		  array_push($this->dhcpexclude, new DHCPExclude($excludedIPlow, $excludedIPhigh));
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
}

?>
