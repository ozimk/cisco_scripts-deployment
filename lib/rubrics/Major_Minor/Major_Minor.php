<?php

##################################################################################################################################################################
class Rubric_Major_Minor extends Rubric_Base
{
  private $error_classes, $error_logs, $major_error_log, $minor_error_log, $maximum_mark, $major_penalties, $minor_penalties;

  ################################################################################################################################################################
  ## Constuctor ($config, $output_directory)                                                                                                                    ##
  ##                                                                                                                                                            ##
  ##    $config           - String containing variables to configure the rubric                                                                                 ##
  ##    $output_directory - Path location to store generated output files from the (inherited) rubric class                                                     ##
  ##                                                                                                                                                            ##
  ## - Call the parent constructor to initialise the class                                                                                                      ##
  ## - Print the rubric name to screen                                                                                                                          ##
  ## - Initialise internal member variables to default values                                                                                                   ##
  ## - Loop through all configured variables in $config                                                                                                         ##
  ##   o If "maximum", set the maximum mark available for the exam                                                                                              ##
  ##   o If "major_pen", convert the value to a list of integers containing the per-error count penalties and store the result                                  ##
  ##   o If "minor_pen", convert the value to a list of integers containing the per-error count penalties and store the result                                  ##
  ##   o If unknown, print an error message and abort                                                                                                           ##
  ## - To the arrays containing penalties for major and minor errors:                                                                                           ##
  ##   o Prepend the penalty for no errors of this type (always 0)                                                                                              ##
  ##   o Prepend the penalty for more than nominated errors of this type (always the maximum available mark                                                     ##
  ################################################################################################################################################################
  function __construct($config, $output_directory)
  {
    parent::__construct($config, $output_directory);

    $this->results_extension = "major_minor";

    $this->print_colour("cyan", "Major_Minor\n");

    $this->error_classes = $this->load_classifier("Major_Minor", "ccna");

    echo "      Setting configuration...\t\t";

    $this->maximum_mark = 0;
    $this->major_penalties = array();
    $this->minor_penalties = array();

    $param_count = preg_match_all('/(\w+):([^,]*)/', $config, $config_details);
    for($i = 0; $i < $param_count; $i++)
    {
      switch ($config_details[1][$i])
      {
        case 'maximum':   $this->maximum_mark = intval($config_details[2][$i]);
                          echo "maximum({$this->maximum_mark})  ";
                          break;
        case 'major_pen': $this->major_penalties = array_map('intval', explode(':', $config_details[2][$i]));
                          echo "major_pen({$config_details[2][$i]})  ";
                          break;
        case 'minor_pen': $this->minor_penalties = array_map('intval', explode(':', $config_details[2][$i]));
                          echo "minor_pen({$config_details[2][$i]})  ";
                          break;
        default:          echo "ERROR: Unknown parameter ({$config_details[1][$i]})\n";
                          exit(1);
      }
    }
    echo "\n";

    // Add minimum and maximum penalties to the start and ends of corresponding arrays
    array_unshift($this->major_penalties, 0);
    array_unshift($this->minor_penalties, 0);
    array_push($this->major_penalties, $this->maximum_mark);
    array_push($this->minor_penalties, $this->maximum_mark);
  }

  ################################################################################################################################################################
  ## log_error($error_class, $error_type, $error_message)                                                                                                       ##
  ##                                                                                                                                                            ##
  ## Overloaded abstract function to log an error against the rubric                                                                                            ##
  ##                                                                                                                                                            ##
  ## Based on the result of calling the is_major() member function, append the error to the internal $major_error_log or $minor_error_log member variables      ##
  ################################################################################################################################################################
  function log_error($error_class, $error_type, $error_message)
  {
    $this->append_feedback_error(self::$error_descriptions[$error_class][$error_type], $error_message, $this->error_logs[$this->error_classes[$error_class][$error_type]]);
  }

  ################################################################################################################################################################
  ## save_result($prepend_all, $prepend_pass, $prepend_fail)                                                                                                    ##
  ##                                                                                                                                                            ##
  ##    $prepend_all  - String to prepend to the generated student feedback                                                                                     ##
  ##    $prepend_pass - String to prepend to feedback if the student has passed                                                                                 ##
  ##    $prepend_fail - String to prepend to feedback if the student has failed                                                                                 ##
  ##    $esp          - Boolean to indicate whether we should create the ESP upload file for this rubric instance                                               ##
  ##                                                                                                                                                            ##
  ## Overloaded abstract function to save all collated errors/feedback to output file                                                                           ##
  ##                                                                                                                                                            ##
  ## - The score for the Major_Minor rubric is calculated by subtracting penalties from the maximum mark. As the score can't be less than 0, set that as a      ##
  ##   minimum                                                                                                                                                  ##
  ## - Construct the student feedback                                                                                                                           ##
  ##   o Start with $prepend_all                                                                                                                                ##
  ##   o Append either $prepend_pass or $prepent_fail depending on the student score                                                                            ##
  ##   o If the student made major errors, append the feedback string generated by the internal $major_error_log member variable                                ##
  ##   o If the student made minor errors, append the feedback string generated by the internal $minor_error_log member variable                                ##
  ## - Call write_output_file() to generate the output file with extension "major_minor", pass on the ESP file creation flag                                    ##
  ## - Return the generated $result array                                                                                                                       ##
  ################################################################################################################################################################
  function save_result($prepend_all, $prepend_pass, $prepend_fail, $esp = false)
  {
    $result['score'] = max($this->maximum_mark - $this->major_penalties[min(count($this->error_logs['major']), count($this->major_penalties) - 1)] - $this->minor_penalties[min(count($this->error_logs['minor']), count($this->minor_penalties) - 1)], 0);

    $result['feedback'] = $prepend_all;
    $result['feedback'].= (($result['score'] > 0)?$prepend_pass:$prepend_fail) . "\n\n";

    if (count($this->error_logs['major']) > 0)
      $result['feedback'].= $this->get_feedback_string($this->error_logs['major'], "You have committed one or more major errors that will cause your network (or parts of your network) not to work.\nDetails of your error(s) are listed below:\n\n");

    if (count($this->error_logs['minor']) > 0)
      $result['feedback'].= $this->get_feedback_string($this->error_logs['minor'], "You have not properly followed instructions on the Lab Exam and have committed one or more minor errors. This type of error will not on its own cause your network to fail, but may impact on your final result. Please refer to the Skills Exam marking guide on Blackboard for more information.\nDetails of your error(s) are listed below:\n\n");

    $this->write_output_file($result, $esp);

    return $result;
  }
}

?>
