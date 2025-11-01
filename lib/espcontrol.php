<?php

if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
  require_once("{$cisco_configs["lib_path"]}/http_request.php");
}

################################################################################
# class EspControl                                                             #
#                                                                              #
# Class instance to upload results to ESP                                      #
#                                                                              #
# Requirements:                                                                #
#  - PHP HttpRequest class                                                     #
#                                                                              #
# Usage:                                                                       #
#  - Create an instance of EspControl using the constructor                    #
#  - Use SubmitMark() to upload results for a single student                   #
#  - Use Logout() when no more results to upload                               #
#                                                                              #
# Created: 17 Dec 2008 (Felix Hariyadi)                                        #
#                                                                              #
# primarily for:                                                               #
#  submitMark() - Submit student mark & comment to ESP                         #
################################################################################
# Version 1: December 17 2008 (Felix Hariyadi)                                 #
#                                                                              #
# Version 2: October 8 2009 (Jason But)                                        #
# - Modified to support new ESP and to streamline code                         #
#                                                                              #
# Version 3: October XX 2011 (Jason But)                                       #
# - Change config options for new plugin architecture                          #
################################################################################

// Definition of ESP configuration, change this to reflect current configuration
define("ESP_URL",'https://esp.swin.edu.au');

define("DEBUG_MODE",false);
//define("DEBUG_MODE",true);

class EspControl
{
  public $httpquery, $username, $password, $search_subjects, $subjects;

  ##########
  # Constructor
  #
  # - Instantiate HTTPRequest object
  # - Set username, password and subject lists
  # - Login to ESP
  ##########
  function __construct($username, $password, $search_subjects)
  {
    $this->debug("ESP: Login details: " . $username . "(" . $password . ")");
    $this->debug("ESP: Units to assess: " . $search_subjects);
    $this->httpquery = new HttpRequest();
    $this->httpquery->enableCookies();
    $this->username = $username;
    $this->password = $password;
    $this->search_subjectlist = $search_subjects;

    // login
    $this->debug("ESP: Login to " . ESP_URL . " ...");
    if (!$this->Login())
    {
      echo "Error: Fail to login !\n";
      return false;
    }
  }
  function EspControl($username, $password, $search_subjects)
  {
    $this->debug("ESP: Login details: " . $username . "(" . $password . ")");
    $this->debug("ESP: Units to assess: " . $search_subjects);
    $this->httpquery = new HttpRequest();
    $this->httpquery->enableCookies();
    $this->username = $username;
    $this->password = $password;
    $this->search_subjectlist = $search_subjects;

    // login
    $this->debug("ESP: Login to " . ESP_URL . " ...");
    if (!$this->Login())
    {
      echo "Error: Fail to login !\n";
      return false;
    }
  }

  ##########
  # send() : Helper function, send POST to web server
  #   $url       : String URL of the webpage ("http://esp.it.swin.edu.au")
  #   $arguments : Array of Query String data or Post data to be sent along with HTTP Request (array('username2' => 'mike'))
  #
  #   returns    : Array of response data or NULL if no response or error
  ##########
  function send($url, $arguments)
  {
    $this->httpquery->setMethod(HttpRequest::METH_POST);
    $this->httpquery->setPostFields($arguments);
    $this->httpquery->setURL($url);
    try
    {
      $this->debug("ESP: HTTP Query to:" . $url);
      $this->httpquery->send();
      if ($this->httpquery->getResponseCode() == 200)
      {
        return $this->httpquery->getResponseData();
      }
      return null;
    }
    catch (HttpException $ex)
    {
      echo $ex;
      return null;
    }
  }

  ##########
  # Login()
  #
  # Login to ESP by using stored username and password. Once the login is
  # complete, parse the list of available subjects and extract those that match
  # those in $search_subjects. Store in $subjects[] the subject ID so we can
  # poll these later
  #
  # Return : Boolean value representing success of the operation
  ##########
  function Login()
  {
    $url = ESP_URL . '/index.php';
    $arguments = array('action' => 'LOGIN', 'username' => $this->username, 'password' => $this->password);

    $response = $this->send($url, $arguments);
    if ($response == null) return false;

    // get list of subjects available
    $dom = new DomDocument();
    if (@$dom->loadHTML($response["body"]) === false) return false;
    $subject_list = $dom->getElementById("main_subject_selector");
    $subject_options = $subject_list->getElementsByTagName("option");
    foreach($subject_options as $option)
    {
      if (strpos($this->search_subjectlist, trim($option->nodeValue))!==FALSE)
        $this->subjects[] = $option->attributes->getNamedItem("value")->nodeValue;
    }
    return true;
  }

  ##########
  # Logout()
  #
  # Logout from ESP 
  ##########
  function Logout()
  {
    $url = ESP_URL . '/';
    $arguments = array('action' => 'LOGOUT');

    $response = $this->send($url, $arguments);
  }

  ##########
  # SelectSubject()
  #
  # Select the nominated subject ID within ESP, all future ESP requests are for
  # this subject only. Subject ID must be of the form extracted in the Login()
  # function
  #
  #   $subject_id : The Subject ID to select
  #
  #   return      : Boolean value indicating success
  ##########
  function SelectSubject($subject_id)
  {
    $url = ESP_URL . '/index.php';
    $arguments = array('action'=>'SELECT_SUBJECT');
    $arguments["subjectid"] = $subject_id;

    $response = $this->send($url, $arguments);
    return ($response != null)?(true):(false);
  }

  ##########
  # StudentExists()
  #
  # For the nominated student ID and assignment number, search the list of
  # available assessments in the currently selected subject to see if the
  # student exists or not. We call the marking.php page and search the 
  # returned HTML contents for the nominated student ID
  #
  # $student_id        : The student id (i.e. s5402506)
  # $assignment_number : The assignment number within ESP to check for a submission
  #
  # returns            : Boolean value indicating success (student exists in
  #                      this subject or not)
  ##########
  function StudentExists($student_id, $assessment_number)
  {
    $url = ESP_URL . '/subject/marking.php';
    $arguments = array('action' => 'SELECT_ASSIGNMENT', 'assignment' => $assessment_number);

    $response = $this->send($url, $arguments);

    if ($response == null) return false;

    if (strpos($response["body"],$student_id) !== false) return true;
    $this->debug("ESP: " . $student_id . " not in subject");
    return false;
  }

  ##########
  # SelectSubjectForStudent()
  #
  # For the nominated student ID and assignment number, search the list of
  # available subjects to see in which subject the student has an assessment
  # that can be marked. We loop through all subject ID values in $subjects[],
  # select the subject <SelectSubject()>, then check if the student exists in
  # the currently selected subject <StudentExists()>. Return when the student
  # is found or there are no more subjects to search. If student is found, the
  # correct subject will be selected within ESP
  #
  # $student_id        : The student id (i.e. s5402506)
  # $assignment_number : The assignment number within ESP to check for a submission
  #
  # returns            : Boolean value indicating success
  ##########
  function selectSubjectForStudent($student_id, $assignment_number)
  {
    $i = 0;

    for($i = 0; $i < count($this->subjects); $i++)
    {
      $this->debug("ESP: Searching subject: " . $this->subjects[$i]);
      $this->SelectSubject($this->subjects[$i]);
      if ($this->StudentExists($student_id, $assignment_number)) return true;
    }

    $this->debug("ESP: ERROR - Student not found in ESP");
    return false;
  }

  ##########
  # GetSubmitMarkingParameters()
  #
  # Set the HTTP arguments needed to submit a mark for the nominated Student ID
  # and assignment number, store the details of the final mark and comments to
  # upload to ESP. At this stage we have already selected the correct subject
  # for the nominated student ID.
  # - We load the marking.php page for the student and assignment number
  # - We parse the returned HTML to extract the value for "sid", this is a unique
  #   submission ID to ensure what we upload is tied to the correct assessment
  # - Create the full array of HTTP arguments to later use when submitting the
  #   mark. This includes the action, student ID, assignment number, submission
  #   ID, mark and comment
  # - Return the created HTTP arguments array
  #
  # $student_id        : The student id (i.e. s5402506)
  # $assignment_number : The assignment number within ESP to check for a submission
  # $mark              : Score for student
  # $comment           : Text to upload as comments for the assessment
  #
  # returns            : Array containing HTTP arguments or NULL on failure
  ##########
  function GetSubmitMarkingParameters($student_id, $assignment_number, $mark, $comment)
  {
    $url = ESP_URL . '/subject/marking.php';
    $arguments = array('action' => 'MARK', 'extend' => '3', 'student' => $student_id, 'assignment' => $assignment_number );

    $response = $this->send($url, $arguments);
    if ($response==null) return null;

    $dom = new DomDocument();
    if (@$dom->loadHTML($response["body"]) === false) return null;

    $xpath_query = "//input[@name='sid']";
    $xpath = new DOMXPath($dom);
    $sid = $xpath->query($xpath_query)->item(0)->attributes->getNamedItem("value")->nodeValue;
    $this->debug("ESP: Allocated Sheet ID - " . $sid);

    $combo_name = substr($response["body"], strpos($response["body"], 'combo['), 8);
    $comment_name = substr($response["body"], strpos($response["body"], 'comment['), 10);

    ## NEED TO EXTACT "combo[X]" and "comment[Y]" from $response["body"]
    $submitMarkingArgs = array('action' => 'SUBMIT_MARKING');
    $submitMarkingArgs['assignment'] = $assignment_number;
    $submitMarkingArgs['student'] = $student_id;
    $submitMarkingArgs['sid'] = $sid;
    $submitMarkingArgs[$combo_name] = $mark;
    $submitMarkingArgs[$comment_name] = $comment;

    return $submitMarkingArgs;
  }

  ##########
  # SubmitMark()
  #
  # Submit (to ESP) a nominated final mark and comments for the nominated Student
  # ID and assignment number.
  # - Correct bad formatting of the student ID
  # - Select the subject (within ESP) where this student has an assessment <via
  #   call to SelectSubjectForStudent()>
  # - Retrieve HTTP arguments to upload mark <via call to GetSubmitMarkingParameters()>
  # - Upload results to ESP
  # - Parse output for success
  #
  # $student_id        : The student id (i.e. s5402506)
  # $assignment_number : The assignment number within ESP to check for a submission
  # $mark              : Score for student
  # $comment           : Text to upload as comments for the assessment
  #
  # returns            : Boolean value indicating success
  ##########
  function SubmitMark($student_id, $assignment_number, $mark, $comment)
  {
    if (strpos($student_id,"s") !== 0) $student_id = "s" . $student_id;

    // select appropriate subject
    $this->debug("ESP: Select subject for $student_id");
    if (!$this->SelectSubjectForStudent($student_id, $assignment_number))
    {
      echo "Error: Can't find subject for $student_id\n";
      return false;
    }

    // Get submit HTTP arguments
    $arguments = $this->GetSubmitMarkingParameters($student_id, $assignment_number, $mark, $comment);

    // submit mark
    $this->debug("ESP: Uploading Result - " . $mark);
    $url = ESP_URL . '/subject/marking.php';
    $response = $this->send($url, $arguments);
    if ($response == null)
    {
      echo "Error: Uploading result failed\n";
      return false;
    }

    $dom = new DomDocument();
    if (@$dom->loadHTML($response["body"]) === false) $this->debug("Error: Can't parse ESP return Message!");

    $content = $dom->getElementById("content");
    $output = "\n----------------- ESP Result -------------------\n" . trim($content->nodeValue) .
              "\n------------------------------------------------\n";
    $this->debug($output);

    return true;
  }

  ##########
  # Echo or print message to the console, if debug mode is enabled
  ##########
  function debug($output)
  {
    if (DEBUG_MODE) echo $output . "\n";
  }
}
?>

