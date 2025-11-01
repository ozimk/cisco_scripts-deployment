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
##                                                                                                ##
## Callable functions/methods:                                                                    ##
##  constructor($program_title, $multiple_rooms, $terminate_function)                             ##
##    Construct an instance of the class by querying the user which rooms they would like to      ##
##    download device details from. $program_title is a string to display at the top of the       ##
##    screen, $multiple_rooms allows the user to select more than one room (or one only if False) ##
##    while $terminate_function is a callback function to ask the user if they want to quit the   ##
##    application. Construction results in the internal $device_db array being populated with a   ##
##    complete set of access information to all booked devices                                    ##
##  db_recursive_walk($function)
##    Walk through the device database and call the $function callback function on each leaf node ##
##    in the database. The callback function takes one parameter which is an editable copy of the ##
##    leaf device information  should return False IF this leaf node is to be purged from the database (removed from communicating with) or 
####################################################################################################
class Smartrack_Information
{
  public $program_title, $multiple_rooms, $terminate_function, $selected_rooms, $auth_details, $device_db;

  ##########
  ## Constructor
  ##
  ## Query user and download all device access information
  ## - Store provided intitialisation variables
  ##   o program_title      - Title to display at top of screen
  ##   o multiple_rooms     - True if we wish to download information for multiple ATC rooms
  ##   o terminate_function - Callback function to ask the user if they wish to quit/terminate program
  ## - Initialise internal variables
  ## - Query the user for which rooms they want to download information for (request_room_selection())
  ## - Retrieve the SmartRack communication details (fetch_atc_device_details())
  ##########
  function __construct($program_title, $multiple_rooms, $terminate_function)
  {
    $this->program_title = $program_title;
    $this->multiple_rooms = $multiple_rooms;
    $this->terminate_function = $terminate_function;

    $this->selected_rooms = array();
    $this->auth_details = null;
    $this->device_db = array();

    // Query the user for which rooms they want to access
    $this->request_room_selection();

    // Retrieve information from SmartRack about these rooms
    $this->fetch_atc_device_details();
  }
  function Smartrack_Information($program_title, $multiple_rooms, $terminate_function)
  {
    $this->program_title = $program_title;
    $this->multiple_rooms = $multiple_rooms;
    $this->terminate_function = $terminate_function;

    $this->selected_rooms = array();
    $this->auth_details = null;
    $this->device_db = array();

    // Query the user for which rooms they want to access
    $this->request_room_selection();

    // Retrieve information from SmartRack about these rooms
    $this->fetch_atc_device_details();
  }

  ##########
  ## request_room_selection()
  ##
  ## Asks the user which rooms you wish to extract device booking information from. The list of
  ## possible rooms is in the $server_information member variable. A dialog box with a checklist of
  ## rooms is displayed, allowing users the option of terminating the program. We loop repeatedly
  ## until the user quits or selects at least one room. The selected rooms are used to initialise
  ## the $selected_rooms member variable
  ##########
  function request_room_selection()
  {
    $server_information = array('ATC328' => array("shortcut"    => '8',
                                                  "description" => "Cisco Devices in ATC328",
                                                  "url"         => "https://ictencsvr1.ict.swin.edu.au/agent/get_all.php",
                                                 ),

                                'ATC329' => array("shortcut"    => '9',
                                                  "description" => "Cisco Devices in ATC329",
                                                  "url"         => "https://ictencsvr6.ict.swin.edu.au/agent/get_all.php",
                                                 ),

                                'ATC330' => array("shortcut"    => '0',
                                                  "description" => "Cisco Devices in ATC330",
                                                  "url"         => "https://ictencsvr11.ict.swin.edu.au/agent/get_all.php",
                                                 )
                               );


    foreach ($server_information as $details) $items[$details['shortcut']] = $details['description'];

    while (true)
    {
      if ($this->multiple_rooms)
        $choice = DisplayCheckList(" ATC Room Selection ",
                                   "Please select in which rooms you would like to manage devices",
                                   $items,
                                   $this->program_title,
                                   '--cancel-label "Quit"'
                                  );
      else
        $choice = DisplayRadioList(" ATC Room Selection ",
                                   "Please select in which rooms you would like to manage devices",
                                   $items,
                                   $this->program_title,
                                   '--cancel-label "Quit"', "ATC328"
                                  );

      switch ($choice->code)
      {
        case 0:
          // $selected will be an array of shortcut values from $server_information
          $selected = array_filter(explode(" ", $choice->output));
          array_walk($selected, create_function('&$val', '$val = trim($val, \'"\');'));

          // Copy $server_information but remove entries that have not been selected
          $this->selected_rooms = array_filter($server_information, function ($element) use ($selected) { return in_array($element['shortcut'], $selected); } );

          if (count($this->selected_rooms) > 0) return;

          DisplayMessage(" ATC Room Selection ",
                         "ERROR: You must select at least one room with which to manage devices",
                         $this->program_title
                        );
          break;
        default:
          call_user_func($this->terminate_function());
      }
    }
  }

  ##########
  ## request_auth_details()
  ##
  ## Asks user for their SIMS username and password, also allows user to terminate the program. We
  ## loop repeatedly until the user quits or enters some details. Entered password is hashed out
  ## for privacy, entered details are stored in $this->auth_details
  ##########
  function request_auth_details()
  {
    while (true)
    {
      exec('dialog --backtitle "' . $this->program_title . '" \
                   --stdout --colors --insecure --cancel-label "Quit" \
                   --title " ATC Website Authentication Information " \
                   --mixedform "Enter Swinburne SIMS Details below:\n" 25 70 15 \
                               "Username:" 2 2 "" 2 15 50 50 0 \
                               "Password:" 4 2 "" 4 15 50 50 1',
           $output, $result);

      switch ($result)
      {
        case 0:  $this->auth_details = array("username" => $output[0], "password" => $output[1]);
                 return;
        default: $this->terminate_function();
      }
    }
  }

  ##########
  ## fetch_room_details($room)
  ##
  ## Connects to the URL for the specified $room using the stored authentication details and
  ## attempts to download device details for that room. Possible outcomes are:
  ##  - Error connecting to server, terminate program with error message
  ##  - Authentication error, display error message and return false to force program to re-query
  ##    user for authentication details
  ##  - Return content of response, list of pre-formatted device details
  ##########
  function fetch_room_details($room)
  {
    $httpquery = new HttpRequest();

    $httpquery->setURL($this->selected_rooms[$room]['url']);
    $httpquery->setMethod(HttpRequest::METH_POST);
    $httpquery->setPostFields($this->auth_details);
    $httpquery->send();

    if ($httpquery->getResponseCode() != 200)
    {
      DisplayMessage(" ERROR Accessing Site ", "Unable to connect to $url, this is a terminal problem", $this->program_title);
      exit(1);
    }

    $result = $httpquery->getResponseData();

    if ($result["body"] == "Logon error\n")
    {
      DisplayMessage(" ERROR Authenticating ", "Bad username/password combination supplied", $this->program_title);
      return false;
    }

    return $result["body"];
  }

  ####################################################################################################
  ## fetch_atc_device_details()
  ##
  ## Fetches all booked device information from the SmartRack system and properly populates the
  ## $device_db member variable
  ## - Request SIMS username and password from the user
  ## - Loop through each selected room to contact
  ##   o Fetch the details for that room, if it fails due to authentication error, repeat the loop
  ##     requesting authentication information and fetching the device details
  ##   o If there is no device information returned (no devices in this room booked) then continue
  ##     directly to the next room at the top of the loop
  ##   o Explode the string of device details into an array of strings, one per device
  ##   o Loop through each device in turn
  ##     - Explode the device detail string into its components
  ##     - Extract the full device name, the enclosure colour, kit colour, and device within the kit
  ##     - Create a sub-entry for this device in the $device_db member variable containing all useful
  ##       information including name, server/username/password for access, nickname and device details
  ## - If there is at least one booked device, return as we are successful
  ## - If we get here, there are no booked devices ($device_db member variable is empty), display an
  ##   error message and terminate the program
  ####################################################################################################
  function fetch_atc_device_details()
  {
    $this->request_auth_details();

    foreach (array_keys($this->selected_rooms) as $room)
    {
      DisplayStatus(" Fetching Device Connection Details ", $this->selected_rooms[$room]['description'], $this->program_title);
      while (($details = $this->fetch_room_details($room)) === false)
      {
        $this->request_auth_details();
        DisplayStatus(" Fetching Device Connection Details ", $this->selected_rooms[$room]['description'], $this->program_title);
      }

      // No booked devices in this room
      if (strlen($details) === 0) continue;

      $devices = explode("\n", trim($details));

      foreach ($devices as $device_details)
      {
        $detail_array = explode(":", $device_details);

        $device_name = $detail_array[5];

        $first_bracket = strpos($device_name, "(");
        $first_bracket_end = strpos($device_name, ")");
        $last_bracket = strrpos($device_name, "(");
        $last_bracket_end = strrpos($device_name, ")");

        $enclosure = substr($device_name, $first_bracket+1, $first_bracket_end - $first_bracket-1);
        $kit = substr($device_name, $last_bracket+1, $last_bracket_end - $last_bracket-1);
        $device = trim(substr($device_name, $last_bracket_end+2));

        $this->device_db[$device][$kit][$enclosure][$room] = array("fullname"  => $device_name,
                                                                   "server"    => $detail_array[1],
                                                                   "username"  => $detail_array[2],
                                                                   "password"  => $detail_array[3],
                                                                   "nickname"  => (strlen($data[7]) == 0)?($device_name):($detail_array[7]),
                                                                   "room"      => $room,
                                                                   "enclosure" => $enclosure,
                                                                   "kit"       => $kit,
                                                                   "device"    => $device,
                                                                   "ini_file"  => "${room}_${enclosure}_${kit}_${device}.ini"
                                                                  );
      }
    }

    if (count($this->device_db) > 0) return;

    DisplayMessage(" ERROR retrieving device information ", "You have no booked devices to communicate with, this is a terminal problem", $this->program_title);
    exit(1);
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
  function db_recursive_walk($function, &$array = Null)
  {
    // Initial call, recursive call with device database
    if (is_null($array))
    {
      if (!is_callable($function))
      {
        DisplayMessage(" ERROR filtering device database ", "The provided filtering function in the source code is not a function, this is a terminal problem", $this->program_title);
        exit(1);
      }
      return $this->db_recursive_walk($function, $this->device_db);
    }

    // Loop through elements
    foreach ($array as $key => $value)
    {
      // Reached leaf node, call function, if unmodified then purge the entry from the array
      if (array_key_exists('fullname', $value))
      {
        $saved_value = $value;

        if ($function($value) === false) unset($array[$key]);
               
        if ($value != $saved_value) $array[$key] = $value;
      } else
      {
        // Not leaf node, do recursive call. If this results in no more children, purge this node as well
        $this->db_recursive_walk($function, $array[$key]);
        if (count($array[$key]) === 0) unset($array[$key]);
      }
    }
  }

  ####################################################################################################
  ## db_recursive_walk($function, &$array = Null)
  ##
  ## Create the INI files to pass to the child processes to perform the collection step
  ##
  ## - Recursively loop through the device database ($this->device_db) down to the leaf nodes
  ##   o Create the INI file to store the leaf node information
  ##   o Loop through all key/values in the leaf node
  ##     - Write to INI file format, excluding "ini_file" key
  ####################################################################################################
  function create_subprocess_ini_files()
  {
    $temp_dirname = "/tmp/smartrack_" . getmypid();

    //mkdir($dirname);

    foreach ($this->device_db as $device_details)
      foreach ($device_details as $kit_details)
        foreach ($kit_details as $enclosure_details)
          foreach ($enclosure_details as $config_details)
          {
            echo "$temp_dirname/" . $config_details["ini_file"] . "\n";
            foreach ($config_details as $key => $value)
            {
              if ($key === "ini_file") continue;

              if (is_array($value))
                foreach ($value as $elem) echo "${key}[] = $elem\n";
              else
                echo "$key = $value\n";
            }
          }
  }

}


?>
