<?php

################################################################################
# Dictionary containing prompts to look for and which mode in the router that  #
# they correspond to.                                                          #
# NOTE: This is currently incomplete                                           #
################################################################################
$prompts_SC['>']                                                                    = "ena";
$prompts_SC['Would you like to enter the initial configuration dialog? [yes/no]:']  = "no";
$prompts_SC['Would you like to terminate autoinstall? [yes]:']                      = "yes";
$prompts_SC['Press RETURN to get started!']                                         = "\r";
$prompts_SC['tcl)#']                                                                = "exit";
$prompts_SC[')#']                                                                   = "end";
$prompts_SC['Password:']                                                            = "cisco12345";

$prompts_SC['username']     = "Username:";
$prompts_KEY['--More--'] = 'q';
$prompts_KEY['<--- More --->'] = 'q';

################################################################################
# class DeviceControl                                                          #
#                                                                              #
# Class instance to implement control of a router/switch via the console port. #
# Allows primarily for:                                                        #
#
#  SendCommand()    - Send any command directly to the router                  #
#  GoEnable()       - Putting/moving the router into "enable" mode             #
#  CaptureCommand() - Execute the specified command, capture/return the output #
#  ResetRouter()    - Erase configs and reload the router                      #
################################################################################
class DeviceControl
{
  public $console, $enable_prompt;

  ##########
  # Constructor, connect to the router via telnet
  # parameter
  ##########
  function __construct($console)
  {
    $this->console = $console;
  }
  function DeviceControl($console)
  {
    $this->console = $console;
  }

  ##########
  # Send the specified command to the device over the console
  # Print the command if asked
  ##########
  function SendCommand($cmd, $display = false)
  {
    if ($display === true) echo "\033[1;36;40m$cmd\033[0;37;40m\n";

    $this->console->SendText("$cmd\r\n");
  }

  ##########
  # Obtains the current prompt from the device. We read all input from the console
  # until there is nothing left to read. We then split this into lines and return
  # the most recent line with some text on it. If there is nothing to return, we 
  # try to trigger some more output by sending a carriage return to the device
  # before calling ReadAllText() again
  ##########
  function ObtainCurrentPrompt($interactive)
  {
    $temp = "";
    $last_read = time();

    while (true)
    {
      if (($temp = $this->console->ReadAllText(2, 3, $interactive)) === false)
      {
        echo "  \033[1;31;40mERROR: Aborting going to enable mode\033[0;37;40m\n";
        return false;
      }

      $temp_lines = explode("\r\n", $temp);
      for ($i = count($temp_lines) - 1; $i >= 0; $i--)
      {
        if ($temp_lines[$i] != "") return trim($temp_lines[$i]);
      }
      $this->SendCommand("\r");
    }
  }

  ##########
  # After obtaining the prompt (last_line) from the device, how do we handle it
  # in our quest to move to "enable" mode.
  # - Some prompts are handled by sending a new command to the router, these prompts
  #   are stored in $prompts_SC (SC = Send Command). If the last portion of the
  #   prompt to be handled matches, we send the nominated command and return from
  #   the function (false means we are not in "enable" mode yet
  # - Some prompts are handled by sending a single key press to the router, these
  #   prompts are stored in $prompts_KEY. Same matching rule as the previous case
  #   and same return codes
  # - If we don't match any known prompts and the last character of $device_prompt
  #   is a '#', then this is most likely the "enable" prompt, we are in the correct
  #   mode. We store $device_prompt as our device $enable_prompt. We alse run some
  #   commands to facilitate capture of output. Finally we are done so we return true
  # - If we get here, we are in an unknown mode, send a carriage return to the device
  #   and see if that helps when re-reading the next prompt
  ##########
  function HandlePrompt($device_prompt)
  {
    global $prompts_SC, $prompts_KEY;

    echo "  Prompt: $device_prompt";

    // Prompts handled by sending a single key-press to the router
    foreach($prompts_KEY as $prompt => $key)
    {
      if (strrpos($device_prompt, $prompt) === (strlen($device_prompt) - strlen($prompt)))
      {
        echo "\033[1;36;40m$key\033[0;37;40m\n";
        $this->console->SendText($key);
        reset($prompts_KEY);
        return false;
      }
    }

    // Prompts handled by sending a command to the router
    foreach($prompts_SC as $prompt => $command)
    {
      if (strrpos($device_prompt, $prompt) === (strlen($device_prompt) - strlen($prompt))) 
      {
        $this->SendCommand($command, true);
        reset($prompts_SC);
        return false;
      }
    }

    // If no matching prompt and the last character is a '#', then we are in enable mode
    // Store the prompt for capturing output and run some special commands
    if (strrpos($device_prompt, '#') === (strlen($device_prompt) - 1)) 
    {
      echo "\n  Storing device enable prompt: \033[1;36;40m$device_prompt\033[0;37;40m\n";
      $this->enable_prompt = $device_prompt;
#      $this->SendCommand("conf t");
#      $this->SendCommand("line console 0");
#      $this->SendCommand("logging synchronous");
#      $this->SendCommand("end");
      $this->SendCommand("terminal length 0");
      $this->SendCommand("terminal pager 0");
      $this->SendCommand("undebug all");
      return true;
    }

    // Otherwise we don't know, probably a router status message, push enter and read again
    // echo "  \033[1;31;40mUnknown prompt - trying again\033[0;37;40m\n";
    $this->SendCommand("\r", true);
    return false;
  }

  ##########
  # Try to put the device into enable mode, we may need to wake it up, then
  # we read input and process it until it eventually gets into enable mode.
  # We have hit enable mode when ProcessBuffer() returns true
  ##########
  function GoEnable($interactive = true)
  {
    sleep(5);

    echo "  Waking up device\n";
    $this->SendCommand("");

    if (($device_prompt = $this->ObtainCurrentPrompt($interactive)) === false) return false;
    while ($this->HandlePrompt($device_prompt) === false)
    {
      if (($device_prompt = $this->ObtainCurrentPrompt($interactive)) === false) return false;
    }
  }

  ##########
  # Sends a command to the device, captures and returns the output.
  #   $cmd
  #   $end_response_prompt - When we see this string in the captured text, the
  #                          command has concluded and we are ready to stop
  #                          capturing output and return
  #   $command_type        - Information for screen display
  #   $check_newline       - The expected $end_response_prompt should be
  #                          preceeded by a newline (\r\n)
  #
  # An empty line is sent first so that the FlushInput() function works properly.
  # We then discard all input up to and including the command we just sent. We
  # read all characters until we see the expected prompt, appending to $result.
  # When we get all required output, we return the entire captured buffer.
  ##########
  function CaptureResponse($cmd, $end_response_prompt, $command_type, $check_newline = true)
  {
    echo "  $command_type: $cmd\n";

    $strpos_check = ($check_newline)?"\r\n":"";
    $strpos_check.= $end_response_prompt;

    $this->SendCommand("");
    $this->SendCommand($cmd);
    $this->console->FlushInput($cmd, 2);

    $result = "";

    $result.= $this->console->FlushInput($end_response_prompt, 5);

    while (strpos($result, $strpos_check) === false)
    {
      $this->SendCommand("");
      $this->SendCommand("");
      $result.= $this->console->FlushInput($end_response_prompt, 5);
    }

    return $result;
  }

  ##########
  # Sends a command to the device (expected to be in enable Wmode), captures and
  # returns the output. Call CaptureResponse() to actually do the work. Strip
  # any excess (\r\n!) from the output if requested before returning
  ##########
  function CaptureCommand($cmd, $strip_excess_bangs = false)
  {
    // To possibly replace up-to but not including - if ($strip_excess_bangs...
    // $result = $this->CaptureResponse($cmd, $this->enable_prompt, 'Capturing');

    echo "  Capturing: $cmd\n";

    $this->SendCommand("");
    $this->SendCommand($cmd);
    $this->console->FlushInput($cmd, 2);
    $result = "";

    $result.= $this->console->FlushInput($this->enable_prompt, 5);

    while (strpos($result, "\r\n" . $this->enable_prompt) === false)
    {
      $this->SendCommand("");
      $this->SendCommand("");
      $result.= $this->console->FlushInput($this->enable_prompt, 5);
    }


    if ($strip_excess_bangs) $result = preg_replace("/(\r\n!)+/", "\r\n!", $result);
    return $result;
  }

  ##########
  # Sends a command to the device (expected to be in enable Wmode), captures and
  # returns the output. Call CaptureResponse() to actually do the work. Strip
  # any excess (\r\n!) from the output if requested before returning
  ##########
  function CaptureCommandASA($cmd, $strip_excess_bangs = false)
  {
    // To possibly replace up-to but not including - if ($strip_excess_bangs...
    // $result = $this->CaptureResponse($cmd, $this->enable_prompt, 'Capturing');

    echo "  Capturing: $cmd\n";

    $this->SendCommand("");
    $this->SendCommand($cmd);
    $this->console->FlushInput($cmd, 2);
    $result = "";

    $result.= $this->console->FlushInput($this->enable_prompt, 5);

    while (strpos($result, "\r" . $this->enable_prompt) === false)
    {
      $this->SendCommand("");
      $this->SendCommand("");
      $result.= $this->console->FlushInput($this->enable_prompt, 5);
    }


    if ($strip_excess_bangs) $result = preg_replace("/(\r!)+/", "\r!", $result);
    return $result;
  }

  ##########
  # Uploads a (single) configuration line to a device. Device is expected to be
  # in "conf t" mode already. Call CaptureResponse() with the command, waiting
  # to see the configuration mode prompt ")#" to decide that the command was
  # successfully uploaded.
  ##########
  function UploadConfigLine($cmd)
  {
    if (strlen($cmd) > 0) $this->CaptureResponse(trim($cmd), ")#", "Uploading", false);
  }

  ##########
  # Erase the start-up configuration and prepare for a reset
  ##########
  function EraseRouter()
  {
    $this->SendCommand("erase startup-config");
    $this->SendCommand("");
    $this->SendCommand("");
    //reset config reg
    //get ios check
  }

  ##########
  # Erase the start-up configuration and vlan.dat file, then prepare for a reset
  ##########
  function EraseSwitch()
  {
    $this->SendCommand("configure terminal");
    $this->SendCommand("config-register 0x2102");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("end");
    $this->SendCommand("erase startup-config");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("delete vlan.dat");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("");
  }

  ##########
  # Erase the start-up configuration and prepare for a reset
  ##########
  function EraseASA()
  {
    $this->SendCommand("write erase");
    $this->SendCommand("");
    $this->SendCommand("");
  }

  ##########
  # Reload a router - enter "no" if necessary when asked if you want to
  # save the configuration
  ##########
  function ReloadRouter()
  {
    $this->SendCommand("configure terminal");
    $this->SendCommand("config-register 0x2102");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("end");
    $this->SendCommand("reload");
    $this->SendCommand("");
    $this->SendCommand("");
    $this->SendCommand("no");
    $this->SendCommand("");
    $this->SendCommand("");	
  }

  ##########
  # Reload a switch - same commands as reloading a router
  ##########
  function ReloadSwitch()
  {
    $this->ReloadRouter();
  }

  ##########
  # Reload a switch - same commands as reloading a router
  ##########
  function ReloadASA()
  {
    $this->ReloadRouter();
  }

  ##########
  # Reset a router by erasing startup-config and reloading it
  ##########
  function ResetRouter()
  {
    $this->EraseRouter();
    $this->ReloadRouter();
  }

  ##########
  # Reset a switch by erasing startup-config/vlan.dat and reloading it
  ##########
  function ResetSwitch()
  {
    $this->EraseSwitch();
    $this->ReloadSwitch();
  }

  function Reset3650Switch(){
    $this->SendCommand("no system ignore startup-config switch all");
    $this->EraseSwitch();
    $this->ReloadSwitch();
  }

  ##########
  # Reset a switch by erasing startup-config/vlan.dat and reloading it
  ##########
  function ResetASA()
  {
    $this->EraseASA();
    $this->ReloadASA();
  }
}

?>
