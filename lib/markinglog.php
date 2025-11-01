<?php

################################################################################
# class MarkingLog                                                             #
#                                                                              #
# Keeps track of log messages under different section descriptions. Also counts#
# the number of logs and tallies up any penalties. Can be asked to print and   #
# return all error messages in one hit (after testing is complete) and return  #
# any other calculated values                                                  #
################################################################################
class MarkingLog
{
  protected $header, $description, $messages, $log_count, $penalty;

  ##########
  # Constructor. Sets the header for the log (only printed if something is
  # logged and clears all other internal variables
  ##########
  function MarkingLog($header)
  {
    $this->header = $header;
    $this->description = "";
    $this->messages = array();
    $this->log_count = 0;
    $this->penalty = 0;
  }

  ##########
  # Sets the new description for the next section of logged entries
  ##########
  function SetDescription($new_description)
  {
    $this->description = $new_description;
  }

  ##########
  # Logs the provided error message in the previously set description block with
  # the provided penalty. The message is appended to the end of other messages
  # for this description, the count of error messages is increased and the penalty
  # tracking variable is incremented by the provided ammount
  ##########
  function Error($message, $pen = 0)
  {
    if (!isset($this->messages[$this->description]))
      $this->messages[$this->description] = "";
    $this->messages[$this->description] .= "  ERROR: $message\n";

    $this->log_count++;
    $this->penalty += $pen;
  } 

  ##########
  # Return the total penalty count for this logger instance
  ##########
  function ErrorPenalty()
  {
    return $this->penalty;
  }

  ##########
  # Return the number of error messages logged for this logger instance
  ##########
  function ErrorCount()
  {
    return $this->log_count;
  }

  ##########
  # Return a string containing all logged error messages grouped into the
  # description blocks. Optionally print to screen in coloured format
  ##########
  function Message($print = false)
  {
    if ($this->log_count == 0) return "";

    $result = $this->header . "\n";
    if ($print) echo $result;

    foreach ($this->messages as $description => $log)
    {
      if ($print)
        echo "\033[7;31;40m" . $description . "\n\033[0;37;40m\033[1;31;40m" . $log . "\033[0;37;40m";
      $result .= $description . "\n" . $log;
    }

    if ($print) echo "\n";
    $result .= "\n";

    return $result;
  }
}

?>
