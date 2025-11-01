<?php

##################################################################################################################################################################
class Rubric_Feedback extends Rubric_Base
{
  private $feedback_error_log;

  ################################################################################################################################################################
  ## Constuctor ($config, $output_directory)                                                                                                                    ##
  ##                                                                                                                                                            ##
  ##    $config           - String containing variables to configure the rubric                                                                                 ##
  ##    $output_directory - Path location to store generated output files from the (inherited) rubric class                                                     ##
  ##                                                                                                                                                            ##
  ## The Feedback rubric has no configuration. We call the parent constructor to initialise the class and then print the rubric name to screen                  ##
  ################################################################################################################################################################
  function __construct($config, $output_directory)
  {
    parent::__construct($config, $output_directory);

    $this->results_extension = "feedback";

    $this->print_colour("cyan", "Feedback\n");
  }

  ################################################################################################################################################################
  ## log_error($error_class, $error_type, $error_message)                                                                                                       ##
  ##                                                                                                                                                            ##
  ## Overloaded abstract function to log an error against the rubric                                                                                            ##
  ##                                                                                                                                                            ##
  ## All errors are appended to the internal $feedback_error_log member variable                                                                                ##
  ################################################################################################################################################################
  function log_error($error_class, $error_type, $error_message)
  {
    $this->append_feedback_error(self::$error_descriptions[$error_class][$error_type], $error_message, $this->feedback_error_log);
  }

  ################################################################################################################################################################
  ## save_result($prepend_all, $prepend_pass, $prepend_fail)                                                                                                    ##
  ##                                                                                                                                                            ##
  ##    $prepend_all  - String to prepend to the generated student feedback                                                                                     ##
  ##    $prepend_pass - String to prepend to feedback if the student has passed (ignored for the Feedback rubric as there is no concept of pass/fail)           ##
  ##    $prepend_fail - String to prepend to feedback if the student has failed (ignored for the Feedback rubric as there is no concept of pass/fail)           ##
  ##    $esp          - Boolean to indicate whether we should create the ESP upload file for this rubric instance                                               ##
  ##                                                                                                                                                            ##
  ## Overloaded abstract function to save all collated errors/feedback to output file                                                                           ##
  ##                                                                                                                                                            ##
  ## - The score for the Feedback rubric is always 0                                                                                                            ##
  ## - Construct the student feedback - there are no passes or fails so the feedback is always $prepend_all followed by the feedback string generated by the    ##
  ##   internal $feedback_error_log member variable                                                                                                             ##
  ## - Call write_output_file() to generate the output file with extension "feedback", pass on the ESP file creation flag                                       ##
  ## - Return the generated $result array                                                                                                                       ##
  ################################################################################################################################################################
  function save_result($prepend_all, $prepend_pass, $prepend_fail, $esp = false)
  {
    $result['score'] = 0;

    $result['feedback'] = $prepend_all;

    if (count($this->feedback_error_log) > 0)
      $result['feedback'].= $this->get_feedback_string($this->feedback_error_log, "This rubric does not provide a final score on your exam, below is some feedback on the errors that have been detected with your configuration\nNOTE: It is possible that further errors that are beyond the ability of the marking program to determine have also been made.\n\n");

    $this->write_output_file($result, $esp);

    return $result;
  }
}

?>
