<?php

####################################################################################################
# Load the provided system_functions library                                                       #
####################################################################################################
require_once("{$cisco_configs["lib_path"]}/system_functions.php");

####################################################################################################
## This class communicates with the user to get SmartRack room selection and SIMS authentication  ##
## information, and then communicates with the SmartRack system(s) to download device access      ##
## information to populate a database which can later be used to actually communicate with the    ##
## nominated devices                                                                              ##
##                                                                                                ##
## Constructing an instance of this class will go through the entire query/download process which ##
## then makes the data available via the public $device_db member variable                        ##
####################################################################################################
class ATC_Communications
{
  public $program_title, $multiple_rooms, $terminate_function, $selected_rooms, $auth_details, $device_db;

  ##########
  ## Constructor
  ##
  ## Passed the directory containing the stored capture files
  ## - Loop through all files in the directory reading the contents of each one that
  ##   is an interesting file to parse to understand the configuration
  ## - Parse the "show run" output as that sets up our base data structure
  ## - Parse other interesting captures in turn
  ##########
  function ATC_Information($program_title, $device_db)
  {
    $this->program_title = $program_title;
    $this->device_db     = $device_db;

    // Retrieve information from SmartRack about these rooms
    $this->fetch_atc_device_details();
  }



####################################################################################################
#                          >>>>>>>>>> CHILD PROCESS FUNCTIONS <<<<<<<<<<                           #
  ####################################################################################################
  ## sig_child($signal)
  ##
  ## Called whenever a child process dies, this can happen in three ways:
  ##  1) success
  ##  2) failure
  ##  3) termination
  ##
  ## Wait for a particular process to cleanup (pcntl_wait()) in a loop (so we handle all dead
  ## processes in the handler (we return when no more terminated child processes exist. For each
  ## terminated child process ($pid):
  ## - Get the $device_name allocated to that child process
  ## - If the process was killed, set the error_message data field in $device_db for this device to
  ##   reflect what happened
  ## - If the process terminated but returned failure, set the error_message data field in $device_db
  ##   for this device to reflect what happened and increment the $borked_pids count so we know how
  ##   many have failed pre-maturely
  ## - Otherwise the collection worked OK, we remove the $pid from the list of child PIDs so we know
  ##   which processes are still executing
  ####################################################################################################
  function sig_child($signal)
  {
    global $device_db, $child_pids, $borked_pids;

    while(($pid = pcntl_wait($status, WNOHANG)) > 0)
    {
      $room = $this->child_pids[$pid]["room";
      $enclosure = $this->child_pids[$pid]["enclosure"];
      $kit = $this->child_pids[$pid]["kit"];
      $device = $this->child_pids[$pid]["device"];

      // Child process timed out, forced kill
      if (!pcntl_wifexited($status))
        $this->device_db[$device][$kit][$enclosure][$room]["error_message"] = "Communication process timed-out and was forcibly terminated";
      // Child process exited with error
      else if (pcntl_wexitstatus($status) > 0) 
      {
        $this->device_db[$device][$kit][$enclosure][$room][$device_name]["error_message"] = "Communication process returned failure";
        $this->borked_pids++;
      // Successful termination of child process, delete INI file
      } else
      {
        unset($this->child_pids[$pid]);
      }
    }
  }

  ####################################################################################################
  ## countdown_delay($delay)
  ##
  ## Display a progress indicator including successful uploads, failed uploads, and a countdown timer
  ## If all uploads succeed, terminate the delay/loop, if all processes are finished (either
  ## successfully or not) terminate the delay/loop
  ##
  ## - Loop for the number of seconds to deploy the countdown
  ##   o Update progress display
  ##   o If there are no more child process, we have finished so terminate the loop early
  ##   o If the number of unfinished child processes equal the number of borked (failed) processes,
  ##     we need to stop and troubleshoot (no actual running processes) so we terminate the loop early
  ##   o Sleep for 1 second to make the countdown timer run at a proper pace
  ##   o Call pcntl_signal_dispatch() to make sure that all pending signals are handled by the signal
  ##     handler
  ####################################################################################################
  function countdown_delay($delay)
  {
    $total_jobs = count($this->child_pids);

    $progress_items = array();

    $progress_items["Running uploads:"] = "($total_jobs)";
    $progress_items["Failed uploads:"]  = "(None)";
    $progress_items["Countdown timer"]  = "-100";

    for ($count = $delay; $count > 0; $count--)
    {
      $progress_items["Running uploads:"] = "(" . count($this->child_pids) . ")";
      $progress_items["Countdown timer"]  = "-" . round(100 - (100 * $count / $delay));
      if ($this->borked_pids > 0) $progress_items["Failed uploads:"] = "(" . $this->borked_pids . ")";
      $percent_complete = 100 - (100 * (count($this->child_pids) + $this->borked_pids) / $total_jobs);

      DisplayProgress("Upload progress", $percent_complete, "Overall percentage of completed uploads", $progress_items, $this->program_title);

      // Terminate loop early if there are no more child processes running
      if (count($this->child_pids) === 0) return;

      // Terminate loop early if the remaining child processes are all borked
      if ($this->borked_pids === count($this->child_pids)) return;

      // Pause and continue
      sleep(1);

      pcntl_signal_dispatch();
    }
  }

  ####################################################################################################
  ## terminate_children()
  ##
  ## Terminate all child processes managed by the class that are still running
  ##
  ## - Loop through each process information stored in $child_pids member variable
  ##   o Attempt to kill the process, displaying a progress bar as we go
  ##   o Wait for the process to actually die before moving to the next
  ####################################################################################################
  function terminate_children()
  {
    $total_children = count($this->child_pids);
    $count = 0;

    foreach ($this->child_pids as $pid => $device_details)
    {
      if (posix_kill($pid, 0))
      {
        DisplayProgress("Terminating Stuck Collection Processes", 100 * $count / $total_children, 
                        "Killing process $pid...\n", array(), $device_details['fullname'] . " (" . $device_details['room'] . ")");

        posix_kill($pid, SIGINT);
      }
      pcntl_waitpid($pid, $status);

      $count++;
    }
  }

  ####################################################################################################
  ## db_recursive_walk($function, &$array = Null)
  ##
  ## Perform a recursive walk through the $device_db member variable to update/modify leaf entries
  ## using a user provided function. Default call is with the function as a parameter, this will
  ## recursively call itself with $device_db initially, and then with sub-array of $device_db
  ##
  ## - Initial call ($array is NULL)
  ##   o Check that $function is actually a function and fail if it isn't
  ##   o Return the result of calling itself with $device_db
  ## - Subsequent calls ($array is not NULL)
  ##   o Loop through each element in $array
  ##     - If the element has an index key matching 'fullname', we've reached a node where device
  ##       information is stored
  ##       o Run $function() on the leaf node
  ##       o If $function() returns false, then we need to delete/purge this leaf node from the
  ##         array, otherwise we update the leaf node
  ##     - Otherwise we are not a leaf node:
  ##       o Recursively call ourselves on the array element to walk the tree
  ##       o Once the tree is walked, check that it didn't result in this node being purged of all
  ##         children, if so we need to purge this node from the array as well
  ####################################################################################################
  function launch_child_processes($function, &$array = Null)
  {
    // Initial call, recursive call with device database
    if (is_null($array))
    {
      if (!is_callable($function))
      {
        DisplayMessage(" ERROR filtering device database ", "The provided filtering function in the source code is not a function, this is a terminal problem", $this->program_title);
        exit(1);
      }
 
      $this->pid = 0;
      return $this->db_recursive_walk($function, $this->device_db);
    }

    // Loop through elements
    foreach ($array as $key => $value)
    {
      // Reached leaf node, this is where we communicate with the device
      if (array_key_exists('fullname', $value))
      {
          // Parallel processing, launch a child process to capture output from
          // the device. This process will terminate when completed
          if (($pid = pcntl_fork()) == 0) $function($value);

          // Store the process ID so we know which devices were collected by which processes
          $this->child_pids[$pid] = $value;
      } else
      {
        // Not leaf node, do recursive call. If this results in no more children, purge this node as well
        $this->db_recursive_walk($function, $array[$key]);
      }
    }
  }

  function UploadDevices($inter_ssh_delay, $total_delay)
  {
    global $device_db, $child_pids, $borked_pids;

    $pid = 0;
    $borked_pids = 0;
    $child_pids = array();

    // Loop through each device in turn
    foreach ($device_db as $device_name => $device_details)
    {
      // Parallel processing, launch a child process to capture output from
      // the device. This process will terminate when completed
      if (($pid = pcntl_fork()) == 0) ChildProcess_UploadDevice($device_name);

      // Store the process ID so we know which devices were collected by which processes
      $child_pids[$pid] = $device_name;

      // Wait for next connection attempt
      usleep($inter_ssh_delay);
    }

    // Give upload processes a chance to succeed
    Countdown_Delay($total_delay);

    // $child_pids contains list of processes we need to terminate
    if (count($child_pids) > 0) TerminateChildren($child_pids);

    // Use list of $child_pids to build a new, cut-down version of $device_db
    $fail_list = array();
    foreach ($child_pids as $pid => $device_name)
    {
      $fail_list[$device_name] = $device_db[$device_name];
      $fail_list[$device_name]["error_message"] = "Upload process was forcibly terminated";
      unset($child_pids[$pid]);
    }
    $device_db = $fail_list;

    // If there is still something in $device_db, we failed and need to try again
    if (count($device_db) > 0) return false;
  }



}


?>
