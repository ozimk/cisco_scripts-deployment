<?php

##################################################################################################################################################################
abstract class Rubric_Base
{
  static protected $error_descriptions, $results_extension;

  protected $output_directory;

  ################################################################################################################################################################
  ## Constuctore ($config, $output_directory)                                                                                                                   ##
  ##                                                                                                                                                            ##
  ##    $config           - String containing variables to configure the rubric                                                                                 ##
  ##    $output_directory - Path location to store generated output files from the (inherited) rubric class                                                     ##
  ##                                                                                                                                                            ##
  ## - Stores the output directory variable so files can be saved to the correct location                                                                       ##
  ## - Check the singleton variable that stores all detailed descriptions of error classes/types. If it has not been instantiated, load the descriptions.ini    ##
  ##   file. For multiple instances of inherited classes, this file will only be loaded once                                                                    ##
  ## NOTE: It is expected that the parent constructor will be called from any inherited constrcutor                                                             ##
  ################################################################################################################################################################
  function __construct($config, $output_directory)
  {
    global $cisco_configs;

    $this->output_directory = $output_directory;

    if (is_null(self::$error_descriptions))
    {
      $descriptions_file = "{$cisco_configs["lib_path"]}/rubrics/descriptions.ini";

      if (!file_exists($descriptions_file))
      {
        echo "\n\033[1;31;40mERROR: \033[0;37;40m Error descriptions file ($descriptions_file) does not exist\n";
        exit(1);
      }

      echo "    Loading error descriptions...";
      $this->print_colour('cyan', "\t$descriptions_file\n");
      self::$error_descriptions = parse_ini_file($descriptions_file, true);
    }

    echo "    Loading marking rubric...\t\t";
  }

  ################################################################################################################################################################
  ## print_colour($colour, $text)                                                                                                                               ##
  ##                                                                                                                                                            ##
  ## Prints the provided text in the nominated colour                                                                                                           ##
  ################################################################################################################################################################
  protected function print_colour($colour, $text)
  {
    if($colour == "")           echo "\033[0;37;40m";
    else if($colour == "red")   echo "\033[7;31;40m";
    else if($colour == "pink")  echo "\033[1;31;40m";
    else if($colour == "green") echo "\033[7;32;40m";
    else if($colour == "brown") echo "\033[7;33;40m";
    else if($colour == "cyan")  echo "\033[1;36;40m";
    echo "$text\033[0;37;40m";
  }

  ################################################################################################################################################################
  ## abstract log_error($error_class, $error_type, $error_message)                                                                                              ##
  ##                                                                                                                                                            ##
  ## Abstract function to be implemented in inherited classes. The main assessment program will call this function with every error to log against the rubric.  ##
  ## The rubric will need to further log the information for feedback purposes and maintain internal state to enable it to calculate a final result based on    ##
  ## all logged errors                                                                                                                                         ##
  ################################################################################################################################################################
  abstract function log_error($error_class, $error_type, $error_message);

  ################################################################################################################################################################
  ## abstract save_result($prepend_all, $prepend_pass, $prepend_fail)                                                                                           ##
  ##                                                                                                                                                            ##
  ## Abstract function to be implemented in inherited classes. The main assessment program will call this function to generate the rubric output file. The      ##
  ## function is expected to return an array containing a final score and feedback for the student. The three parameters contain strings to respectively        ##
  ## prepend to all feedback, passed exam results, and failed exam results                                                                                      ##
  ################################################################################################################################################################
  abstract function save_result($prepend_all, $prepend_pass, $prepend_fail, $esp = false);

  ################################################################################################################################################################
  ## load_classifier($rubric_name, $filename)                                                                                                                   ##
  ##                                                                                                                                                            ##
  ## Load the error classifier of the nominated name for the nominated rubric. The classifier is stored in an INI file in the directory named for the rubric    ##
  ## We fail with an error if the INI file does not exist, otherwise we load and parse the file and return the associative array of classification information  ##
  ################################################################################################################################################################
  protected function load_classifier($rubric_name, $filename)
  {
    global $cisco_configs;

    $classifier_file = "{$cisco_configs["lib_path"]}/rubrics/$rubric_name/$filename.ini";

    if (!file_exists($classifier_file))
    {
      echo "\n\033[1;31;40mERROR: \033[0;37;40m Error classification file ($classifier_file) does not exist\n";
      exit(1);
    }

    echo "      Error classifications...";
    $this->print_colour('cyan', "\t\t$classifier_file\n");

    return parse_ini_file($classifier_file, true);
  }

  ################################################################################################################################################################
  ## append_feedback_error($category, $error_message, &$log_array)                                                                                              ##
  ##                                                                                                                                                            ##
  ## Append the error_message to the appropriate category in the nominated array of feedback messages. $log_array is passed by reference, so the original array ##
  ## is modified by this function. The array is indexed by the detailed description of a common error type, the error message provides feedback on the specific ##
  ## instance of the error.                                                                                                                                     ##
  ################################################################################################################################################################
  protected function append_feedback_error($category, $error_message, &$log_array)
  {
    $log_array[$category][] = "  $error_message";
  }

  ################################################################################################################################################################
  ## get_feedback_string($log_array)                                                                                                                            ##
  ##                                                                                                                                                            ##
  ## Create feedback based on the errors logged in the particular array. Construct a string by joining the array indices and values in a long, formatted string ##
  ## It is expected this string will eventually be output to file for eventual feedback to students and/or assessors.                                           ##
  ################################################################################################################################################################
  protected function get_feedback_string($log_array, $prepend)
  {
    $result = $prepend;

    foreach ($log_array as $type => $details) $result.= "$type\n" . implode("\n", $details) . "\n\n";

    return $result;
  }

  ################################################################################################################################################################
  ## write_output_file($extension, $result)                                                                                                                     ##
  ##                                                                                                                                                            ##
  ##    $result - Array containing final score ($result['score']) and student feedback ($result['feedback'])                                                    ##
  ##    $esp    - Boolean to indicate whether the results file should be soft-linked to the esp_upload file in the same directory                               ##
  ##                                                                                                                                                            ##
  ## Create the output file based on the results provided in $result. The file is created and the contents of $result are stored in the file. If $esp is true,  ##
  ## then create the softlink on the disk                                                                                                                       ##
  ################################################################################################################################################################
  protected function write_output_file($result, $esp)
  {
    $results_filename = "results." . $this->results_extension;
    $esp_filename = $this->output_directory . "/esp_upload";

    echo "    Writing file...\t\t\t";
    $this->print_colour("cyan", "$results_filename\n");

    $fout = fopen("{$this->output_directory}/$results_filename", "w+");
    fwrite($fout, $result['score']);
    fwrite($fout, "\n");
    fwrite($fout, $result['feedback']);
    fclose($fout);

    if ($esp)
    {
      echo "    Linking ESP file...\t\t\t";
      $this->print_colour("cyan", basename($esp_filename) . "\n");

      if (is_file($esp_filename)) unlink($esp_filename);
      symlink($results_filename, $esp_filename);
    }

  }
}

?>
