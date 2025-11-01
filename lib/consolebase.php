<?php

################################################################################
# class ConsoleBase                                                            #
#                                                                              #
# This is an abstract base to allow support for multiple means to connect to   #
# the console port of a router/switch. Main functions are:                     #
#                                                                              #
#  Connect()     - Connect to the router/switch                                #
#  Disconnect()  - Disconnect from the router/switch, release resources        #
#  SendText()    - Send the specified text to the router/switch                #
#  ReadAllText() - Return all text output by the router/switch                 #
#  FlushInput()  - Read (and discard) all text output by the router/switc      #
#                  until a specified string is seen                            #
################################################################################
abstract class ConsoleBase
{
  public $console;

  ##########
  # Constructor, default constructor initialises public members
  ##########
  function __construct()
  {
    $this->console = NULL;
  }

  ##########
  # Destructor, clean up, disconnect first if required
  ##########
  function __destruct()
  {
    $this->Disconnect();
  }

  ##########
  # Abstract functions to be overloaded
  # Connect()    - Initialise a new connection to the router
  # Disconnect() - Free resouces
  ##########
  abstract function Connect($params, $devicename);
  abstract function Disconnect();

  ##########
  # Send the specified text to the device over the opened port.
  ##########
  function SendText($text)
  {
    fwrite($this->console, $text);
  }

  ##########
  # Reads all text possible from the device. If $timeout expires AND at least one character has
  # been read, return the string so far. If a key has been pressed, user terminates reading so
  # return false. If $prod_timeout expires (obviously no text read), then prod the router/switch
  ##########
  function ReadAllText($timeout, $prod_timeout, $interactive)
  {
    $result = "";
    $last_read = time();

    if ($interactive)
    {
      $old_stty = shell_exec('stty -g');
      shell_exec('stty -icanon -echo min 1 time 0');
      stream_set_blocking(STDIN, false);
    }

    while (true)
    {
      $c = fgetc($this->console);
      // Nothing was read
      if ($c === false)
      {
        // $timeout has expired AND something has been read
        if ((time() - $last_read > $timeout) && ($result != ""))
        {
          if ($interactive)
          {
            stream_set_blocking(STDIN, true);
            shell_exec('stty ' . $old_stty);
          }
          return $result;
        }

        // Key pressed, abort function and return false
        if (($interactive) && (fgetc(STDIN)))
        {
          stream_set_blocking(STDIN, true);
          shell_exec('stty ' . $old_stty);
          return false;
        }

        // $prod_timeout has expired, lets prod the router
        if (time() - $last_read > $prod_timeout)
        {
#          echo " \033[1;31;40m.\033[0;37;40m";
          $this->SendText("\r");
        }
        continue;
      }

      // Append character to string, update timeout check variable
      $result.= $c;
      $last_read = time();
    }
  }

  ##########
  # Read input from the device until either there is nothing to read or
  # the specified string is read from the console. Return the read string
  ##########
  function FlushInput($wait_string, $timeout)
  {
    if (strlen($wait_string) === 0) return "";

    $last_read = time();

    // Loop until timeout expires OR we found our string
    for ($result = ""; (((time() - $last_read) <= $timeout) && (strpos($result, $wait_string) === false)); )
    {
      $c = fgetc($this->console);

      if ($c !== false)
      {
        // A character was read, append to string, update timeout check variable
        $result.= $c;
        $last_read = time();
      }
    }
    return $result;
  }
}

?>
