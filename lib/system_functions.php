<?php

################################################################################
# CollectBaseDir($cisco_configs, $exam_info)                                   #
#                                                                              #
# Returns the top-level directory where collected configs are/should be stored.#
# This information is constructed from the collect_base variable in the Cisco  #
# system configuration file and from the unitcode/semester/shortname variables #
# in the exam configuration file. Here this information is passed in as arrays #
# already parsed from the INI files                                            #
################################################################################
function CollectBaseDir($cisco_configs, $exam_info)
{
  return "{$cisco_configs["collect_base"]}/{$exam_info["Exam Details"]["unitcode"]}/{$exam_info["Exam Details"]["semester"]}/{$exam_info["Exam Details"]["shortname"]}";
}

################################################################################
# RunTerminal()                                                                #
#                                                                              #
# Run the picocom terminal application. Displays instructions on how to exit.  #
# Function returns when terminal program is complete                           #
################################################################################
function RunTerminal()
{
  passthru("clear");
  echo "\n\n\033[7;32;40mType 'Ctrl-A Ctrl-X' to exit the terminal application...\033[7;37;40m\n";
  passthru("/usr/bin/picocom /dev/ttyUSB0");
}

################################################################################
# SerialState()                                                                #
#                                                                              #
# Returns a colour-coded string signifying whether the USB Serial Convertor is #
# attached or not. Information is determined by presence of /dev/ttyUSB0       #
################################################################################
function SerialState()
{
  if (file_exists("/dev/ttyUSB0")) return "\Zb\Z2USBSerial(OK)\Zn";
  return "\Zb\Z1USBSerial(Missing)\Zn";
}

################################################################################
# BatteryState()                                                               #
#                                                                              #
# Returns a colour-coded string signifying the current battery state.          #
# Information is extracted from the files in /proc/acpi/battery/BAT0           #
################################################################################
function BatteryState()
{
  $state = preg_replace('/\s\s+/', ' ', file_get_contents("/proc/acpi/battery/BAT0/state"));
  $info  = preg_replace('/\s\s+/', ' ', file_get_contents("/proc/acpi/battery/BAT0/info"));

  $state_lines = explode("\n", $state);
  $info_lines  = explode("\n", $info);

  foreach ($state_lines as $line)
  {
    $fields = explode(" ", $line);
    if (strpos($line, "remaining capacity:") === 0) $capacity = $fields[2];
    if (strpos($line, "charging state:") === 0) $batterystate = $fields[2];
  }
  foreach ($info_lines as $line)
  {
    $fields = explode(" ", $line);
    if (strpos($line, "last full capacity:") === 0) $fullcapacity = $fields[3];
  }

  $charge = min(100, round(100 * $capacity / $fullcapacity));
  if ($charge < 30)
    if ($charge > 15) $colour = "\Zb\Z3";
    else $colour = "\Zb\Z1";
  else
    $colour = "\Zb\Z2";

  return $colour . "BATTERY($batterystate - $charge%)\Zn";
}

################################################################################
# StatusString()                                                               #
#                                                                              #
# Returns a colour-coded string signifying system status that can be used as   #
# the title bar for all dialog windows                                         #
################################################################################
function StatusString()
{
  return "Status - " . SerialState() . " " . BatteryState();
}

################################################################################
# DisplayMenu($title, $text, $items, $other_options)                           #
#                                                                              #
# Displays a pretty menu with items selectable from the $items() array. Once   #
# a selection is made, returns a structure containing the return code and the  #
# output from the dialog application (usually the chosen menu item)            #
################################################################################
function DisplayInput($title, $text, $initial_value = NULL, $backtitle = NULL, $other_options = NULL)
{
  $menu_options = '--inputbox "' . $text . '" 30 70';
  if (isset($initial_value)) $menu_options.= ' "' . $initial_value . '"';

  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors \
               --title " ' . $title . ' " \
               ' . $other_options . ' ' . $menu_options,
       $output, $result);

  $return->code = $result;
  $return->output = $output[0];
  return $return;
}

################################################################################
# DisplayMenu($title, $text, $items, $other_options)                           #
#                                                                              #
# Displays a pretty menu with items selectable from the $items() array. Once   #
# a selection is made, returns a structure containing the return code and the  #
# output from the dialog application (usually the chosen menu item)            #
################################################################################
function DisplayMenu($title, $text, $items, $backtitle = NULL, $other_options = NULL)
{
  $menu_options = '--menu "' . $text . '" 30 70 25';
  foreach ($items as $key => $value)
  {
    $menu_options.= ' ' . $key . ' "' . $value . '"';
  }

  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors \
               --title " ' . $title . ' " \
               ' . $other_options . ' ' . $menu_options,
       $output, $result);

  $return->code = $result;
  $return->output = $output[0];
  return $return;
}

################################################################################
# DisplayMenu($title, $text, $items, $other_options)                           #
#                                                                              #
# Displays a pretty menu with items selectable from the $items() array. Once   #
# a selection is made, returns a structure containing the return code and the  #
# output from the dialog application (usually the chosen menu item             #
################################################################################
function DisplayCheckList($title, $text, $items, $backtitle = NULL, $other_options = NULL, $selected = NULL)
{
  $clist_options = '--checklist "' . $text . '" 30 70 25';
  foreach ($items as $key => $value) 
  {
    $clist_options.= ' ' . $key . ' "' . $value;
    $clist_options.= (($selected !== NULL) && (strpos($selected, $key) !== FALSE))?'" on':'" off';
  }

  if ($backtitle === NULL) $backtitle = BackTitle();

  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors \
               --title " ' . $title . ' " \
               ' . $other_options . ' ' . $clist_options,
       $output, $result);

  $return->code = $result;
  $return->output = $output[0];
  return $return;
}

################################################################################
# DisplayMenu($title, $text, $items, $other_options)                           #
#                                                                              #
# Displays a pretty menu with items selectable from the $items() array. Once   #
# a selection is made, returns a structure containing the return code and the  #
# output from the dialog application (usually the chosen menu item             #
################################################################################
function DisplayRadioList($title, $text, $items, $backtitle = NULL, $other_options = NULL, $selected = NULL)
{
  $rlist_options = '--radiolist "' . $text . '" 30 70 25';
  foreach ($items as $key => $value) 
  {
    $rlist_options.= ' ' . $key . ' "' . $value;
    $rlist_options.= (($selected !== NULL) && (strpos($value, $selected) !== FALSE))?'" on':'" off';
  }

  if ($backtitle === NULL) $backtitle = BackTitle();

  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors \
               --title " ' . $title . ' " \
               ' . $other_options . ' ' . $rlist_options,
       $output, $result);

  $return->code = $result;
  $return->output = $output[0];
  return $return;
}

################################################################################
# DisplayYesNo($title, $text, $other_options)                                  #
#                                                                              #
# Displays a message box with a question, returns true if the user chooses     #
# "Yes" and false for any other selection                                      #
################################################################################
function DisplayYesNo($title, $text, $backtitle = NULL, $other_options = NULL)
{
  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors --no-collapse \
               --title " ' . $title . ' " ' . $other_options . ' \
               --yesno "' . $text . '" 0 0' ,
       $output, $result);

  return ($result === 0);
}

################################################################################
# DisplayMessage($title, $text, $other_options)                                #
#                                                                              #
# Displays a message box and waits for the user to hit enter to continue       #
################################################################################
function DisplayMessage($title, $text, $backtitle = NULL, $other_options = NULL)
{
  pclose(popen('dialog --backtitle "' . $backtitle . '" --colors \
                   --title " ' . $title . ' " ' . $other_options . ' \
                   --msgbox "' . $text . '" 30 70', 'w'));
}

################################################################################
# DisplayMessage($title, $text, $other_options)                                #
#                                                                              #
# Displays a message box and waits for the user to hit enter to continue       #
################################################################################
function DisplayStatus($title, $text, $backtitle = NULL, $other_options = NULL)
{

  pclose(popen('dialog --backtitle "' . $backtitle . '" --colors \
                       --title " ' . $title . ' " ' . $other_options . ' \
                       --infobox "' . $text . '" 30 70', 'w'));
}

################################################################################
# DisplayMenu($title, $text, $items, $other_options)                           #
#                                                                              #
# Displays a pretty menu with items selectable from the $items() array. Once   #
# a selection is made, returns a structure containing the return code and the  #
# output from the dialog application (usually the chosen menu item)            #
################################################################################
function DisplayProgress($title, $percent_complete, $progress_text, $items, $backtitle = NULL, $other_options = NULL)
{
  $gauge_options = '--mixedgauge "' . $progress_text . '" 20 70 ' . round($percent_complete);
  foreach ($items as $key => $value)
    $gauge_options.= ' "' . $key . '" "' . $value . '"';

  exec('dialog --backtitle "' . $backtitle . '" --stdout --colors \
               --title " ' . $title . ' " \
               ' . $other_options . ' ' . $gauge_options,
       $output, $result);

  $return->code = $result;
  $return->output = $output[0];
  return $return;
}


?>
