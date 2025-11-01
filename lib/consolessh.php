<?php

require_once("{$cisco_configs["lib_path"]}/consolebase.php");

################################################################################
# class ConsoleSSH                                                             #
#                                                                              #
# Class instance to implement control of a router/switch via an ssh session    #
# We overload Connect() and Disconnect() from the abstract base class          #
################################################################################
class ConsoleSSH extends ConsoleBase
{
  ##########
  # If we have an existing session, disconnect from that first. Use PECL-SSH2
  # to create a new ssh session to the nominated server/user/password. We have
  # to scan input until we see "Console>" before we are actually on the router
  ##########
  function Connect($params, $devicename = "NULL")
  {
    if ($this->console !== NULL) $this->Disconnect();

    if (!array_key_exists("server", $params)) {
      echo "\033[1;31;40mERROR: ssh server information not provided\n\033[0;37;40m";
      return false;
    }
    if (!array_key_exists("username", $params)) {
      echo "\033[1;31;40mERROR: ssh username not provided for login to " . $params['server'] . "\n\033[0;37;40m";
      return false;
    }
    if (!array_key_exists("password", $params)) {
      echo "\033[1;31;40mERROR: ssh password not provided for login to " . $params['username'] . "@" . $params['server'] . "\n\033[0;37;40m";
      return false;
    }

    $server     = $params['server'] . ".ict.swin.edu.au"; // this is required when trying to resolve th hostname from vpn or swinburne's wifi network
    $username   = $params['username'];
    $password   = $params['password'];
    $devicename = $params['nickname'];

    echo "Connecting to device ($devicename)...\n";

    if (!($ssh_connection = ssh2_connect($server))) {
      echo "\033[1;31;40mERROR: Cannot start ssh\n\033[0;37;40m";
      return false;
    }
    if (!ssh2_auth_password($ssh_connection, $username, $password)) {
      echo "\033[1;31;40mERROR: Authenticating ssh session\n\033[0;37;40m";
      return false;
    }

    if (!($this->console = ssh2_shell($ssh_connection))) {
      echo "\033[1;31;40mERROR: Creating stream to capture IO\n\033[0;37;40m";
      return false;
    }
    stream_set_blocking($this->console, false);

    $this->FlushInput("Console>", 2);
  }

  ##########
  # Close the current stream, then reset the stream variables
  ##########
  function Disconnect()
  {
    if ($this->console !== NULL) fclose($this->console);
    $this->console = NULL;
  }
}
