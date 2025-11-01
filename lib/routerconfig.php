<?php

require_once("{$cisco_configs["lib_path"]}/acl_parser.php");

################################################################################
# class VRFDefintion                                                           #
#                                                                              #
# Simple object structure to store various pieces of information about a       #
# vrf defintion                                                                #                                               
#  address_family     - ipv4 or ipv6                                           #
#  route distingutishger - not implementewd
################################################################################
class VRFDefinition
{
  public $address_family, $rds;

  function ConstructConfigString($name)
  {
    $config = "vrf definition $name\n";
    foreach ($this->rds as $as => $num) {
      $config .= "rd $as:$num\n";
      $config .= "route-target export $as:$num\n";
      $config .= "route-target import $as:$num\n";
    }
    if ($this->address_family) {
      $config .= "address-family $this->address_family\n";
      $config .= "exit-address-family\n";
    }

    return $config;
  }
}


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
    $ppp_authentication, $fr_nokeepalive, $fr_noinversearp, $fr_mapIP, $fr_mapDLCI, $fr_lmiType, $fr_subint_type, $login, $password, $aclName, $aclDir, $natDir, $vrf_name,
    $acl_in, $acl_out; # these acl vars are used to not interfere with the marking aclName and aclDir which is more restricitive.

  function ConstructConfigString($interface_name)
  {
    $config = "interface $interface_name";
    $config .= $this->fr_subint_type ? " $this->fr_subint_type\n" : "\n";
    if (isset($this->vrf_name) && strtolower($this->vrf_name) != "global") {
      $config .= "vrf forwarding $this->vrf_name\n";
    }
    if ($this->description) {
      $config .= "description $this->description\n";
    }
    if ($this->clock) {
      $config .= "clock rate $this->clock\n";
    }
    $config .= $this->shutdown ? "shutdown\n" : "no shutdown\n";
    if ($this->encapsulation) {
      $config .= "encapsulation $this->encapsulation";
      switch ($this->encapsulation) {
        case 'dot1Q':
        case 'isl':
          $config .= " $this->vlan_id";
          break;
        case 'ppp': # to be implemeneted
          break;
        case 'frame-relay':
          $config .= " $this->fr_encapsulation";
          break;
      }
      $config .= "\n";
    }
    #frame-relay
    if ($this->fr_nokeepalive) {
      $config .= "no keepalive\n";
    }
    if ($this->fr_noinversearp) {
      $config .= "no frame-relay inverse-arp\n";
    }
    if (isset($this->fr_mapDLCI)) {
      $config .= "frame-relay interface-dlci $this->fr_mapDLCI $this->fr_encapsulation protocol ip $this->fr_mapIP\n";
    }
    if ($this->loopback_ospf_good) {
      $config .= "ip ospf network point-to-point\n";
    }
    if (isset($this->acl_in)) {
      $config .= "ip access-group $this->acl_in in\n";
    }
    if (isset($this->acl_out)) {
      $config .= "ip access-group $this->acl_out out\n";
    }


    $config .= $this->address == null ? "no ip address\n" : "ip address $this->address $this->mask\n";



    #(isset($this->routemap)){$config .= "ip policy route-map $this->routemap\n";}
    # include ppp authentication constructions
    # frame-relay lmi-type
    # ip access-group
    #ip nat
    return $config;
  }

  function get_slash_mask()
  {
    $this->slash_mask = $this->slash_mask ?? strlen(preg_replace("/0/", "", decbin(ip2long($this->mask))));
    return $this->slash_mask;
  }

  function get_wildcard()
  {
    return maskInverse($this->mask);
  }

  function get_network()
  {
    $this->network = $this->network ?? long2ip(ip2long($this->address) & ip2long($this->mask));
    return $this->network;
  }
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
  public $version, $as_number, $proc_id, $auto_summary, $redistribute, $bgp_neighbors, $bgp_no_sync, $networks, $areas, $bad_network_statements, $router_id, $vrf_name;

  function ConstructConfigString($protocol_name, $interfaces)
  {
    $config = "";
    # Router configuration mode
    if ($protocol_name == "ospf") {
      $config .= "router ospf $this->proc_id ";
      $config .= (isset($this->vrf_name) && strtolower($this->vrf_name) != "global") ? "vrf $this->vrf_name\n" : "\n";
    } elseif ($protocol_name == "rip") {
      $config .= "router rip\nversion 2\n";
    } #vrf stuff to be implementd
    else {
      $config .= "router $protocol_name $this->as_number\n";
    }  #vrf stuff to be implemented for eigrp
    if ($this->router_id) {
      if ($protocol_name == "bgp") {
        $config .= "bgp ";
      }
      $config .= "router-id $this->router_id\n";
    }

    if ($protocol_name == "bgp" && isset($this->vrf_name) && strtolower($this->vrf_name) != "global") {
      $config .= "address-family ipv4 vrf $this->vrf_name\n";
    }
    if ($this->bgp_no_sync) {
      $config .= "no synchronization\n";
    }
    # Netowrk Statements
    foreach ($this->networks as $name => $empty) {
      if ($protocol_name == "bgp") {
        $mask = $empty;
        $network = $name;
      } else {
        $mask = $interfaces[$name]->mask;
        $network = $interfaces[$name]->get_network();
      }

      $wildcard = maskInverse($mask);

      switch ($protocol_name) {
        case "rip":
          break; # to be implemented conversion to classful networks
        case "ospf":
          $config .= "network $network $wildcard area $empty\n";
          break;
        case "bgp":
          $config .= "network $network mask $mask\n";
          break;
        case "eigrp":
          $config .= "network $network $wildcard\n";
          break;
      }
    }

    #redistrbute and autop summary
    if ($this->redistribute && !($protocol_name == "bgp")) {
      $config .= $protocol_name == "eigrp" ? "redistribute static\n" : "default-information originate";
    }
    if ($protocol_name != "ospf") {
      $config .= $this->auto_summary ? "auto-summary\n" : "no auto-summary\n";
    }

    # Cofnigs for neigbours (applciable to BGP)
    if (isset($this->bgp_neighbors)) {
      foreach ($this->bgp_neighbors as $address => $details) {
        $config .= $details->ConstructConfigString($address);
        if (isset($this->vrf_name) && strtolower($this->vrf_name) != "global") {
          $config .= "neighbor $address activate";
        }
      }
    }

    if ($protocol_name == "bgp" && isset($this->vrf_name) && strtolower($this->vrf_name) != "global") {
      $config .= "exit-address-family\n";
    }
    #ospf and eigrp authenticaiton
    #other bgp settings
    return $config;
  }
}

#helper function for converting a network mask into a wildcard
function maskInverse($mask)
{
  $octets = explode(".", $mask);
  $wild = "";
  for ($i = 0; $i < 4; $i++) {
    if ($i != 0) {
      $wild .= ".";
    }
    $wild .= (255 - intval($octets[$i]));
  }
  # echo "mask $mask wild $wild\n";
  return $wild;
}

################################################################################
# class Neighbor                                                               #
#                                                                              #
# Simple object to hold data relating to a BGP Neighbor                        #
# remote_as     - The remote autonomous systems number                             #
# update-source - The interface name for the source of the updates             #
# next_hop_self - true or false if changes next hop atribute                   #
################################################################################
class Neighbor
{
  public $remote_as, $update_source, $next_hop_self, $remove_private_as, $default_originate, $route_map_in, $route_map_out;

  function ConstructConfigString($neighbor_address)
  {
    $config = "";
    if (!isset($this->remote_as)) {
      return $config;
    }
    $config .= "neighbor $neighbor_address remote-as $this->remote_as\n";
    if (isset($this->update_source)) {
      $config .= "neighbor $neighbor_address update-source $this->update_source\n";
    }
    if ($this->next_hop_self) {
      $config .= "neighbor $neighbor_address next-hop-self\n";
    }
    if ($this->remove_private_as) {
      $config .= "neighbor $neighbor_address remove-private-as\n";
    }
    if ($this->default_originate) {
      $config .= "neighbor $neighbor_address default-originate\n";
    }
    if (isset($this->route_map_in)) {
      $config .= "neighbor $neighbor_address route-map $this->route_map_in in\n";
    }
    if (isset($this->route_map_out)) {
      $config .= "neighbor $neighbor_address route-map $this->route_map_out out\n";
    }
    return $config;
  }
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

  function ConstructConfigString()
  {
    return "ip route $this->network $this->mask $this->via\n";
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

  function __construct($login, $password, $aclName, $aclDir)
  {
    $this->login = $login;
    $this->password = $password;
    $this->aclName = $aclName;
    $this->aclDir = $aclDir;
  }
  function LineConfig($login, $password, $aclName, $aclDir)
  {
    $this->login = $login;
    $this->password = $password;
    $this->aclName = $aclName;
    $this->aclDir = $aclDir;
  }

  function ConstructConfigString()
  {
    $config = "";
    if (isset($this->password)) {
      $config .= "password $this->password\n";
    }
    if ($this->login) {
      $config .= "login";
    }
    # acl stuff
    return $config;
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

  function __construct($username, $password)
  {
    $this->username = $username;
    $this->password = $password;
  }
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

class DHCPConfig
{
  public $pool_name, $default_route, $dns_server, $domain_name, $excluded;

  function __construct($pool, $route, $dns, $domain)
  {
    $this->pool_name = $pool;
    $this->default_route = $route;
    $this->dns_server = $dns;
    $this->domain_name = $domain;
    $this->excluded = array();
  }
  function DHCPConfig($pool, $route, $dns, $domain)
  {
    $this->pool_name = $pool;
    $this->default_route = $route;
    $this->dns_server = $dns;
    $this->domain_name = $domain;
    $this->excluded = array();
  }
}

class SyslogConfig
{
  public $address, $facility, $log_level, $transport_protocol, $port, $source_interface;

  function ConstructConfigString()
  {
    $config = "logging {$this->address}\n";
    $config .= "logging on\n";
    if (isset($this->facility)) {
      $config .= "logging facility local{$this->facility}\n";
    }
    if (isset($this->log_level)) {
      $config .= "logging trap {$this->log_level}\n";
    }
    if (isset($this->transport_protocol) && isset($this->port)) {
      $config .= "logging trap {$this->address} transport {$this->transport_protocol} port {$this->port}\n";
    }
    if (isset($this->source_interface)) {
      $config .= "logging source-interface {$this->source_interface}\n";
    }

    return $config;
  }
}

class NTPConfig
{
  public $server_address;
  function ConstructConfigString()
  {
    $config = "ntp server $this->server_address\n";
    return $config;
  }
}

class SNMPConfig
{
  public $host, $transport, $port, $view, $group, $user, $permission, $authpass, $encpass, $location, $contact, $traps, $ifindex_persist, $options;

  function ConstructConfigString()
  {
    $config = "";
    if ($this->location) {
      $config .= "snmp-server location $this->location\n";
    }
    if ($this->contact) {
      $config .= "snmp-server contact $this->contact\n";
    }
    if ($this->ifindex_persist) {
      $config .= "snmp-server ifindex persist\n";
    }
    if ($this->traps) {
      $config .= "snmp-server enable traps\n";
    }

    foreach ($this->options as $option) {
      $config .= "snmp-server view $this->view $option\n"; // e.g. iso included, internet excluded, etc.
    }

    $config .= "snmp-server group $this->group v3 priv $this->permission $this->view\n"; # change permissions
    $config .= "snmp-server user $this->user $this->group v3 auth sha $this->authpass priv aes 128 $this->encpass\n";

    $config .= "snmp-server host $this->transport/$this->port $this->host version 3 $this->user\n";
    return $config;
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
# vrfs           - an array of vrf defintions indexed by name
# config         - a seires of abitrary configuration lines
################################################################################
class RouterConfig
{
  public $device_type, $interfaces, $routing_itp, $static_routes, $lines, $hostname, $motd, $users, $nat_instances, $aclconfig, $dhcp, $lineconfig, $ACLs, $prefixes, $routemaps, $syslog, $snmp, $ntp, $vrfs, $arbitrarys;

  ##########
  ## Constructor
  ##
  ## Passed the directory containing the stored capture files
  ## - Loop through all files in the directory reading the contents of each one that
  ##   is an interesting file to parse to understand the configuration
  ## - Parse the "show run" output as that sets up our base data structure
  ## - Parse other interesting captures in turn
  ##########
  function __construct($capture_directory = "")
  {
    if ($capture_directory != "") {
      $this->LoadFromCapture($capture_directory);
    } else {
      $this->device_type = "Router";
      $this->interfaces = [];
      $this->routing_itp = [];
      $this->static_routes = [];
      $this->vrfs = [];
      $this->aclconfig = [];
      $this->routemaps = [];
      $this->prefixes = [];
      $this->arbitrarys = [];
    }
  }
  function RouterConfig($capture_directory = "")
  {
    if ($capture_directory != "") {
      $this->LoadFromCapture($capture_directory);
    } else {
      $this->device_type = "Router";
      $this->interfaces = [];
      $this->routing_itp = [];
      $this->static_routes = [];
      $this->vrfs = [];
      $this->aclconfig = [];
      $this->routemaps = [];
      $this->prefixes = [];
      $this->arbitrarys = [];
    }
  }

  function LoadFromCapture($capture_directory)
  {
    // List of captured commands we are/might be interested in
    $router_commands = array();
    $router_commands["show run"] = array("show run", "sh run", "sho run");
    $router_commands["show ip int brief"] = array("show ip interface brief", "sh ip int brief");

    // Storage for what we captured that is interesting to parse
    $captured_output = array();

    if (!($handle = opendir($capture_directory))) {
      echo "ERROR: Directory ($capture_directory) doesn't exist\n\n";
      exit(1);
    }

    // Read input from interesting files
    while (($filename = readdir($handle)) !== false)
      foreach ($router_commands as $command => $possibilities)
        if (in_array(str_replace("_", " ", $filename), $possibilities))
          $captured_output[$command] = file_get_contents("$capture_directory/$filename");

    closedir($handle);

    if (!isset($captured_output["show run"])) {
      echo "ERROR: No output from \"show run\" to parse\n\n";
      exit(1);
    }

    // Parse captured output in the correct order
    $this->ParseShowRun($captured_output["show run"]);
    if (isset($captured_output["show ip int brief"]))
      $this->ParseShowIntBrief($captured_output["show ip int brief"]);
  }


  function ConstructConfigString()
  {
    $config = "!\n";
    if (isset($this->hostname)) {
      $config .= "hostname $this->hostname\n";
    }
    $config .= "!\n";

    if (isset($this->vrfs)) {
      foreach ($this->vrfs as $name => $definition) {
        $config .= "!\n";
        $config .= $definition->ConstructConfigString($name);
      }
    }
    $config .= "!\n";

    if (isset($this->ntp)) {
      $config .= $this->ntp->ConstructConfigString();
    }

    $config .= "!\n";

    if (isset($this->aclconfig)) {
      foreach ($this->aclconfig as $acl) {
        $config .= "!\n";
        $config .= $acl->ConstructConfigString();
      }
    }

    $config .= "!\n";

    if (isset($this->prefixes)) {
      foreach ($this->prefixes as $prefix) {
        $config .= "!\n";
        $config .= $prefix->ConstructConfigString();
      }
    }

    $config .= "!\n";

    if (isset($this->routemaps)) {
      foreach ($this->routemaps as $routemap) {
        $config .= "!\n";
        $config .= $routemap->ConstructConfigString();
      }
    }

    $config .= "!\n";

    # Interfaces
    if (isset($this->interfaces)) {
      foreach ($this->interfaces as $name => $details) {
        $config .= "!\n";
        $config .= $details->ConstructConfigString($name);
      }
    }
    $config .= "!\n";
    # Routing Protocols
    //$num = count($this->routing_itp);
    if (isset($this->routing_itp)) {
      foreach ($this->routing_itp as $name => $details) {
        if (is_array($details)) {
          foreach ($details as $protocol) {
            $config .= "!\n";
            $config .= $protocol->ConstructConfigString($name, $this->interfaces);
          }
        } else {
          $config .= "!\n";
          $config .= $details->ConstructConfigString($name, $this->interfaces);
        }
      }
    }
    $config .= "!\n";
    # Static Routes
    if (isset($this->static_routes)) {
      foreach ($this->static_routes as $static_route) {
        $config .= "!\n";
        $config .= $static_route->ConstructConfigString();
      }
    }
    $config .= "!\n";

    #syslog
    if (isset($this->syslog)) {
      $config .= $this->syslog->ConstructConfigString();
    }
    $config .= "!\n";

    #snmp
    if (isset($this->snmp)) {
      $config .= $this->snmp->ConstructConfigString();
    }
    $config .= "!\n";

    # Line
    #foreach($this->lines as $access_type => $line){ this is not what i am expecting
    #  $config .= "!\n";
    #  $config .= $line->ConstructConfigString();
    #}

    foreach ($this->arbitrarys as $arbitrary) {
      $config .= "!\n";
      $config .= $arbitrary->ConstructConfigString();
    }

    return $config;
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

    for ($i = 0; $i < count($config_blocks); $i++) {
      # Ignore empty blocks (multiple lines of "!"
      if (strlen($config_blocks[$i]) === 0)
        continue;

      if (strpos($config_blocks[$i], "interface") === 0) {
        $this->ParseInterface($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "router") === 0) {
        $this->ParseRouter($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "ip route") !== false) {
        var_dump($config_blocks[$i]);
        $this->ParseStaticRoute($config_blocks[$i]);
        //continue;
      }

      if (strpos($config_blocks[$i], "line") === 0) {
        $this->ParseLines($config_blocks[$i]);
        continue;
      }

      if (strpos($config_blocks[$i], "hostname") === 0) {
        $section = explode(" ", $config_blocks[$i], 2);
        if (isset($section[1]))
          $this->hostname = trim($section[1], "\r\n");
        continue;
      }

      if (strpos($config_blocks[$i], "banner motd") === 0) {
        $section = explode("^C", $config_blocks[$i], 3);
        if (isset($section[1]))
          $this->motd = $section[1];
        continue;
      }
      if ((strpos($config_blocks[$i], "username") === 0) || (strpos($config_blocks[$i], "license udi pid") === 0)) {
        $this->ParseUsernameConfig($config_blocks[$i]);
        continue;
      }
      if (strpos($config_blocks[$i], "ip nat") === 0) {
        var_dump($config_blocks[$i]);
        $this->ParseNATConfig($config_blocks[$i]);
        continue;
      }
      if (strpos($config_blocks[$i], "ip access-list") === 0) {
        $this->ParseACLConfig($config_blocks[$i]);
        continue;
      }
      if ((strpos($config_blocks[$i], "ip cef") === 0) || (strpos($config_blocks[$i], "ip dhcp excluded-address") === 0)) {
        $dhcp_excluded = array_merge($dhcp_excluded, $this->ParseDHCPExclude($config_blocks[$i]));
        continue;
      }
      //if (strpos($config_blocks[$i], "ip dhcp") !== false)
      if (strpos($config_blocks[$i], "ip dhcp") === 0) {
        $this->ParseDHCP($config_blocks[$i]);
        continue;
      }
    }

    // DCHP excluded blocks come before the DHCP pools, need to loop through and see which pool each address is excluded from
    $this->dhcp['bad_exclusions']['excluded'] = array();

    foreach ($dhcp_excluded as $address) {
      $matched_network = 'bad_exclusions';
      if (isset($this->dhcp['networks']))
        foreach (array_keys($this->dhcp['networks']) as $network) {
          $net_add = substr($network, 0, strpos($network, '/'));
          $check_add = long2ip(ip2long(trim($address)) & (~(pow(2, 32 - substr($network, strpos($network, '/') + 1)) - 1)));

          if ($net_add === $check_add)
            $matched_network = $network;
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

    for ($i = 1; $i < count($lines); $i++) {
      $pieces = explode(" ", trim($lines[$i], " "));

      if (strpos($lines[$i], "no ip address"))
        continue;

      # Parse the IP address for this interface (address, mask, slash notation, network address and host_id
      if (strpos($lines[$i], "ip address")) {
        $this->interfaces[$interface_name]->address = $pieces[2];
        $this->interfaces[$interface_name]->mask = $pieces[3];
        $this->interfaces[$interface_name]->slash_mask = strlen(preg_replace("/0/", "", decbin(ip2long($pieces[3]))));
        $this->interfaces[$interface_name]->network = long2ip(ip2long($pieces[2]) & ip2long($pieces[3]));
        $this->interfaces[$interface_name]->host_id = ip2long($pieces[2]) - ip2long($this->interfaces[$interface_name]->network);
        continue;
      }

      # Parse the configured clock rate for this interface
      if (strpos($lines[$i], "clock rate")) {
        $this->interfaces[$interface_name]->clock = $pieces[2];
        continue;
      }

      # Check if interface is shutdown
      if (strpos($lines[$i], "shutdown")) {
        $this->interfaces[$interface_name]->shutdown = true;
        continue;
      }

      # Extract interface description
      if ($pieces[0] == "description") {
        $temp = explode(" ", trim($lines[$i], " "), 2);
        $this->interfaces[$interface_name]->description = $temp[1];
        continue;
      }

      # Check if the "ip ospf network point-to-point" command has been specified
      if (strpos($lines[$i], "ip ospf network point-to-point")) {
        $this->interfaces[$interface_name]->loopback_ospf_good = true;
        continue;
      }

      # Encapsulation on this interface, store encaptulation type
      if (strpos($lines[$i], "encapsulation")) {
        $this->interfaces[$interface_name]->encapsulation = $pieces[1];
        switch ($this->interfaces[$interface_name]->encapsulation) {
          case 'dot1Q':
          case 'isl':
            $this->interfaces[$interface_name]->vlan_id = $pieces[2];

            # Element 0 is parent interface, element 1 is sub-interface number
            $interface_breakdown = explode(".", $interface_name);
            $parent_interface = $interface_breakdown[0];
            $sub_interface = $interface_breakdown[1];

            if (is_null($this->interfaces[$parent_interface]->trunk))
              $this->interfaces[$parent_interface]->trunk = array();
            $this->interfaces[$parent_interface]->trunk[$pieces[2]] = $sub_interface;
            break;
          case 'ppp':
            $this->interfaces[$interface_name]->ppp_encapsulation = true;
            break;
          case 'frame-relay':
            $this->interfaces[$interface_name]->fr_encapsulation = (isset($pieces[2])) ? ($pieces[2]) : ("default");
            break;
        }
        continue;
      }

      if (strpos($lines[$i], "ppp authentication")) {
        $this->interfaces[$interface_name]->ppp_authentication = $pieces[2];
        if ($pieces[2] === 'chap')
          $this->interfaces[$interface_name]->ppp_username = strtolower($this->hostname);
        continue;
      }

      if (strpos($lines[$i], "ppp pap sent-username")) {
        $this->interfaces[$interface_name]->ppp_username = strtolower($pieces[3]);
        $this->interfaces[$interface_name]->ppp_password = $pieces[5];
        continue;
      }

      if (strpos($lines[$i], "no keepalive")) {
        $this->interfaces[$interface_name]->fr_nokeepalive = true;
        continue;
      }

      if (strpos($lines[$i], "frame-relay map")) {
        $this->interfaces[$interface_name]->fr_mapIP = $pieces[3];
        $this->interfaces[$interface_name]->fr_mapDLCI = intval($pieces[4]);
        continue;
      }

      if (strpos($lines[$i], "frame-relay interface-dlci")) {
        $this->interfaces[$interface_name]->fr_mapIP = "InverseArp";
        $this->interfaces[$interface_name]->fr_mapDLCI = intval($pieces[2]);
        continue;
      }
      if (strpos($lines[$i], "frame-relay lmi-type")) {
        $this->interfaces[$interface_name]->fr_lmiType = $pieces[2];
        continue;
      }
      if (strpos($lines[$i], "ip access-group")) {
        if (!isset($this->ACLs[$pieces[2]]))
          $this->ACLs[$pieces[2]]['applied'] = "$interface_name:{$pieces[3]}";
        $this->interfaces[$interface_name]->aclName = $pieces[2];
        $this->interfaces[$interface_name]->aclDir = $pieces[3];
        continue;
      }
      if (strpos($lines[$i], "ip nat")) {
        if (($pieces[2] === "inside") or ($pieces[2] === "outside"))
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

    if ($protocol == "rip")
      $this->routing_itp[$protocol]->version = 1;
    if ($protocol == "eigrp")
      $this->routing_itp[$protocol]->as_number = $information[2];
    if ($protocol == "ospf") {
      $this->routing_itp[$protocol]->proc_id = $information[2];
      $this->routing_itp[$protocol]->auto_summary = false;
    } else
      $this->routing_itp[$protocol]->auto_summary = true;

    for ($i = 1; $i < count($lines); $i++) {
      $pieces = explode(" ", trim($lines[$i], " "));

      // Parse a network statement
      if (strpos($lines[$i], "network")) {
        $interfaces_matching = 0;

        // Calculate the network subnet mask, if not provided (RIP or EIGRP), calculate from the network address
        $octets = explode(".", $pieces[1]);
        $mask = (isset($pieces[2])) ? (long2ip(~ip2long($pieces[2]))) : ("255." . (($octets[0] < 128) ? ("0.") : ("255.")) . (($octets[0] < 192) ? ("0.0") : ("255.0")));

        // Loop through all interfaces on this device
        foreach ($this->interfaces as $name => $details) {
          // If this interface is advertised by this network statement
          if (($details->configured) && ((ip2long($details->network) & ip2long($mask)) == ip2long($pieces[1]))) {
            // Add routing protocol to array routing protocols this interface is advertised by
            array_push($details->advertised, $protocol);

            // Update array of interfaces advertised by this protocol
            // If OSPF, the interface is an array element mapping to an area number
            if ($protocol == "ospf") {
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
        if ($interfaces_matching == 0)
          array_push($this->routing_itp[$protocol]->bad_network_statements, $lines[$i]);
        continue;
      }
      if (strpos($lines[$i], "version")) {
        $this->routing_itp[$protocol]->version = $pieces[1];
        continue;
      }
      if (strpos($lines[$i], "no auto-summary")) {
        $this->routing_itp[$protocol]->auto_summary = false;
        continue;
      }
      if ((strpos($lines[$i], "redistribute static")) || (strpos($lines[$i], "default-information originate"))) {
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
    if (is_null($this->static_routes))
      $this->static_routes = array();

    $lines = explode("\r\n", $config);

    foreach ($lines as $line) {
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
    for ($i = 0; $i < count($lines); $i++) {
      $pieces = explode(" ", trim($lines[$i], " "));
      if ($pieces[0] == "password")
        $password = $pieces[1];
      if ($pieces[0] == "login")
        $login = true;
      if ($pieces[0] == "access-class") {
        $this->ACLs[$pieces[1]]['applied'] = "vty:{$pieces[2]}";
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
    if (is_null($this->users))
      $this_static_routes = array();

    $lines = explode("\r\n", $config);

    foreach ($lines as $line) {
      $pieces = explode(" ", trim($line, " "));

      if (($pieces[0] == "username") && ($pieces[2] == "password"))
        $this->users[strtolower($pieces[1])] = $pieces[4];
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
    $lines = explode("\r\n", $config);

    foreach ($lines as $line) {
      $pieces = explode(" ", trim($line, " "));

      // Ignore non-NAT configurations
      if (($pieces[0] !== "ip") || ($pieces[1] !== "nat"))
        continue;

      // NAT Pool specified, record details
      if ($pieces[2] == "pool") {
        $poolname = $pieces[3];
        switch ($pieces[6]) {
          case 'netmask':
            $prefix = strlen(preg_replace("/0/", "", decbin(ip2long($pieces[7]))));
            break;
          case 'prefix-length':
            $prefix = (int) $pieces[7];
            break;
          default:
            continue 2;
        }
        $this->nat_instances[$poolname]['public']['first'] = $pieces[4];
        $this->nat_instances[$poolname]['public']['last'] = $pieces[5];
        $this->nat_instances[$poolname]['public']['prefix'] = $prefix;
      }

      // NAT Inside Source List specified, record details
      if (($pieces[2] == "inside") && ($pieces[3] == "source") && ($pieces[4] == "list")) {
        switch ($pieces[6]) {
          case 'pool':
            $poolname = $pieces[7];
            break;
          case 'interface':
            $poolname = $this->interfaces[$pieces[7]]->address;
            $this->nat_instances[$poolname]['public']['first'] = $poolname;
            $this->nat_instances[$poolname]['public']['last'] = $poolname;
            $this->nat_instances[$poolname]['public']['prefix'] = 32;
            break;
          default:
            continue 2;
        }
        $this->nat_instances[$poolname]['private']['acl'] = $pieces[5];
        if (!isset($this->ACLs[$current_name]))
          $this->ACLs[$pieces[5]]['applied'] = "nat:in";

        $this->nat_instances[$poolname]['overload'] = ($pieces[8] === 'overload') ? True : False;

        // Append interface NAT details to dataset
        $this->nat_instances[$poolname]['interfaces']['inside'] = array();
        $this->nat_instances[$poolname]['interfaces']['outside'] = array();
        foreach ($this->interfaces as $interface_name => $details) {
          if (isset($details->natDir))
            $this->nat_instances[$poolname]['interfaces'][$details->natDir][] = $interface_name;
        }
      }
    }
  }

  function ParseACLConfig($config)
  {
    if (!isset($this->ACLs))
      $this->ACLs = array();

    $lines = explode("\r\n", $config);

    foreach ($lines as $line) {
      $pieces = explode(" ", trim($line, " "));
      if (($pieces[0] === "ip") && ($pieces[1] === "access-list")) {
        $current_name = $pieces[3];

        if (!isset($this->ACLs[$current_name]))
          $this->ACLs[$current_name]['applied'] = NULL;
        $this->ACLs[$current_name]['ACL'] = new ACL($pieces[2]);
      }

      if (($pieces[0] == "permit") || ($pieces[0] == "deny"))
        $this->ACLs[$current_name]['ACL']->add_statement(trim($line));
    }

    $this->ACLs[$current_name]['ACL']->finalise();
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
      $this->dhcp = array();

    if (!isset($this->dhcp['empty_pools']))
      $this->dhcp['empty_pools'] = array();


    $lines = explode("\r\n", $config);
    $information = explode(" ", $lines[0], 4);

    if (($information[0] !== "ip") and ($information[1] !== "dhcp") and ($information[2] !== "pool"))
      return;

    $pool_name = $information[3];

    for ($i = 1; $i < count($lines); $i++) {
      $pieces = explode(" ", trim($lines[$i], " "));

      // Parse the network addresses advertised	  
      if ($pieces[0] == "network")
        $network = $pieces[1] . '/' . strlen(preg_replace("/0/", "", decbin(ip2long($pieces[2]))));

      // Parse the default-router option
      if ($pieces[0] == "default-router")
        $default_route = $pieces[1];

      // Parse the dns-server option
      if ($pieces[0] == "dns-server")
        $dns_server = $pieces[1];

      // Parse the domain-name option
      if ($pieces[0] == "domain-name")
        $domain_name = $pieces[1];

      // not parsing lease [days [hours [minutes]]] | infinite
      // also not parsing ip helper address assigned on interface to forward DHCP requests
    }

    if (isset($network)) {
      if (isset($pool_name))
        $this->dhcp['networks'][$network]['pool'] = $pool_name;
      if (isset($default_route))
        $this->dhcp['networks'][$network]['gateway'] = $default_route;
      if (isset($dns_server))
        $this->dhcp['networks'][$network]['dns'] = $dns_server;
      if (isset($domain_name))
        $this->dhcp['networks'][$network]['domain'] = $domain_name;
    } else {
      if (isset($pool_name))
        $this->dhcp['empty_pools'][]['pool'] = $pool_name;
      if (isset($default_route))
        $this->dhcp['empty_pools'][]['gateway'] = $default_route;
      if (isset($dns_server))
        $this->dhcp['empty_pools'][]['dns'] = $dns_server;
      if (isset($domain_name))
        $this->dhcp['empty_pools'][]['domain'] = $domain_name;
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
    foreach ($lines as $line) {
      if (strpos($line, "ip dhcp excluded-address") === false)
        continue;

      $pieces = explode(" ", trim($line, " "));
      $low_IP = $pieces[3];
      $high_IP = (isset($pieces[4])) ? ($pieces[4]) : ($low_IP);
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
    foreach ($this->interfaces as $name => $details) {
      $search = strstr($output, $name);
      // If the search failed we need to check shortened version of name since if it is too long GigebitEthernet will be cut fo Gi
      if (($search === FALSE) && (strstr($name, "GigabitEthernet") === $name))
        $search = strstr($output, str_replace("GigabitEthernet", "Gi", $name));

      $line = substr($search, 0, strpos($search, "\n"));
      $line = preg_replace('/\s+/', ' ', $line);
      $pieces = explode(" ", $line, 5);

      $details->status = trim($pieces[4], " ");
    }
  }
}
