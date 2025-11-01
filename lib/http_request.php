<?php
###############################################################################
# HTTPRequest
# This class is used for backwards comptiability to php v5.3 and below. post 5.3 httpRequest was removed
# THis class should be include in scripts using pecl_http http Request with a version check for 5.4 and greater
# This class encapauslates s=some functionality of cURL to mimix the functionality of pecl_http HTTPRequest class
##############################################################################
class HttpRequest {
    private $ch;
    private $response_code;

    private $data;

    const METH_POST = CURLOPT_POST;
    const METH_GET = CURLOPT_HTTPGET;

    function __construct(){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }
    function HTTPRequest(){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    function setURL($url){
        curl_setopt($this->ch, CURLOPT_URL,$url);
    }

    function setMethod($method){
        curl_setopt($this->ch, $method, true);
    }

    function setPostFields($key_values){
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,$key_values);
    }

    function send(){
        $this->data = curl_exec($this->ch);
        $this->response_code = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        curl_close($this->ch);
    }

    function getResponseCode(){
        return $this->response_code;
    }

    function getResponseData(){
        $result = []; //this is more for backwards cmpatibliity but could later use to seperate header and body
        $result["body"] = $this->data;
        return $result;
    }

    function enableCookies(){
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, "");
    }

}

?>