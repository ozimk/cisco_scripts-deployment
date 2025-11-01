<?php

require_once("{$cisco_configs["lib_path"]}/consolebase.php");

################################################################################
# class ConsoleSerial                                                          #
#                                                                              #
# Class instance to implement control of a router/switch via a serial port     #
# We overload Connect() and Disconnect() from the abstract base class          #
################################################################################
class ConsoleSerial extends ConsoleBase
{
  ##########
  # Prompt the user to connect the cable and press enter to continue. If we have
  # already connected, we can just return as the older file handle is still OK
  ##########
  function Connect($params, $devicename="NULL")
  {
    if (!array_key_exists("port", $params))
    {
      echo "\033[1;31;40mERROR: serial port information not provided\n\033[0;37;40m";
      return false;
    }
    if (!array_key_exists("speed", $params))
    {
      echo "\033[1;31;40mERROR: serial port speed not provided\n\033[0;37;40m";
      return false;
    }

    $devicename = $params['nickname'];

    echo "\033[7;32;40mPlug console cable into ($devicename) now, press Enter when ready\n\033[0;37;40m";
    trim(fgets(STDIN));

    if ($this->console !== NULL) return;

    $this->console = fopen($params['port'], "w+");
    stream_set_blocking($this->console, false);

    $cmd = "stty -F " . $params['port'] . " clocal crtscts";
    exec($cmd);

    $cmd = "stty ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke < " . $params['port'];
    exec($cmd);

    $cmd = "stty speed " . $params['speed'] . " < " . $params['port'];
    exec($cmd);
  }

  ##########
  # Close the current stream, then reset the stream variables
  ##########
  function Disconnect()
  {
    fclose($this->console);
    $this->console = NULL;
  }
}

?>
