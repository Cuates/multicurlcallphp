<?php
  /*
          File: multi_curl_call.php
        Created: 07/22/2020
        Updated: 07/22/2020
     Programmer: Cuates
     Updated By: Cuates
        Purpose: Retrieve data from the database and process them in bulk via CURL
  */

  // https://stackoverflow.com/questions/4368603/multiple-php-curl-posts-to-same-page
  // https://stackoverflow.com/questions/11480763/how-to-get-parameters-from-a-url-string
  // https://stackoverflow.com/questions/1355072/array-push-with-key-value-pair
  // https://stackoverflow.com/questions/1834703/php-foreach-loop-key-value
  // https://www.onlineaspect.com/2009/01/26/how-to-use-curl_multi-without-blocking
  // https://gist.github.com/anonymous/1a9eb381f6a5f260bd20
  // https://snelling.io/curl-multi-high-cpu-usage/

  // Include error check class
  include ("checkerrorclass.php");

  // Create an object of error check class
  $checkerrorcl = new checkerrorclass();

  // Set variables
  $developerNotify = 'cuates@email.com'; // Production email(s)
  // $developerNotify = 'cuates@email.com'; // Development email(s)
  $endUserEmailNotify = 'cuates@email.com'; // Production email(s)
  // $endUserEmailNotify = 'cuates@email.com'; // Development email(s)
  $externalEndUserEmailNotify = ''; // Production email(s)
  // $externalEndUserEmailNotify = 'cuates@email.com'; // Development email(s)
  $scriptName = 'Multi Curl Call Ingestion'; // Production
  // $scriptName = 'TEST Multi Curl Call Ingestion TEST'; // Development
  $fromEmailServer = 'Email Server';
  $fromEmailNotifier = 'email@email.com';

  // Retrieve any other issues not retrieved by the set_error_handler try/catch
  // Parameters are function name, $email_to, $email_subject, $from_mail, $from_name, $replyto, $email_cc and $email_bcc
  register_shutdown_function(array($checkerrorcl,'shutdown_notify'), $developerNotify, $scriptName . ' Error', $fromEmailNotifier, $fromEmailServer, $fromEmailNotifier);

  // Function to catch exception errors
  set_error_handler(function ($errno, $errstr, $errfile, $errline)
  {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
  });

  // Attempt to generate email
  try
  {
    // Set new memory limit Note: This will revert back to original limit upon end of script
    ini_set('memory_limit', '4095M');

    // // Set local
    // setlocale(LC_ALL, "en_US.utf8");

    // Declare download directory
    define ('TEMPDOC', '/var/www/html/Temp_Directory/');

    // Include database class file
    include ("multi_curl_class.php");

    // Create an object of database class as used by the API calls
    $multi_curl_cl = new multi_curl_class();

    // Initialize variables and arrays
    $errorFilename = "";
    $errorString = "";
    $errorPrefixFilename = "multi_curl_issue_"; // Production
    // $errorPrefixFilename = "multi_curl_dev_issue_"; // Development
    $errormessagearray = array();
    $foundArray = array();
    $finalArray = array();
    $finalFoundArray = array();
    $idNum = 0; // Change number here for what the id number is in the database table
    $idNumScript = 1; // Change number here for what the id number script is in the database table
    $accountStatus = array('deleted');
    $lineBreakString = array("\r\n", "\r", "\n");

    // Extract Sequence date time
    $extractTimeStamp = $multi_curl_cl->extractTimeStampDate($idNum);

    // Check if server error
    if (!isset($extractTimeStamp['SError']) && !array_key_exists('SError', $extractTimeStamp))
    {
      // Retrieve the date executed
      $seqdatetime = reset($extractTimeStamp);

      // Register (update) data into database table
      $regUpdateDataInformation = $multi_curl_cl->updateDataInformation($seqdatetime);

      // Explode database message
      $registerUpdateData = explode('~', $regUpdateDataInformation);

      // Set response message
      $registerUpdateServerResp = reset($registerUpdateData);
      $registerUpdateServerMesg = next($registerUpdateData);

      // Check if error with registering process
      if (trim($registerUpdateServerResp) !== "Success")
      {
        // Set array with error records for processing
        array_push($errormessagearray, array('Update Data In Bulk', '', '', $seqdatetime, $idNum, $registerUpdateServerResp, $registerUpdateServerMesg));
      }

      // Register (insert) data into database table
      $regInsertDataInformation = $multi_curl_cl->insertDataInformation($seqdatetime);

      // Explode database message
      $registerInsertData = explode('~', $regInsertDataInformation);

      // Set response message
      $registerInsertServerResp = reset($registerInsertData);
      $registerInsertServerMesg = next($registerInsertData);

      // Check if error with registering process
      if (trim($registerInsertServerResp) !== "Success")
      {
        // Set array with error records for processing
        array_push($errormessagearray, array('Insert Data In Bulk', '', '', $seqdatetime, $idNum, $registerInsertServerResp, $registerInsertServerMesg));
      }

      // Check if the registration has produced any issues
      if (count($errormessagearray) <= 0)
      {
        // Update sequence date
        $updateTimeStamp = $multi_curl_cl->updateDateTimeStamp($idNum);

        // Explode database message
        $updateTimeStampData = explode('~', $updateTimeStamp);

        // Set response message
        $updateTimeStampServerResp = reset($updateTimeStampData);
        $updateTimeStampServerMesg = next($updateTimeStampData);

        // Check if error with registering process
        if (trim($updateTimeStampServerResp) !== "Success")
        {
          // Set array with error records for processing
          array_push($errormessagearray, array('Update Date Time Stamp', '', '', $seqdatetime, $idNum, $updateTimeStampServerResp, $updateTimeStampServerMesg));
        }
      }
      else
      {
        // Set array with error records for processing
        array_push($errormessagearray, array('Update Date Time Stamp Not Updated', '', '', $seqdatetime, $idNum, 'Error', 'Was not Performed Due to Issue(s) in Register'));
      }
    }
    else
    {
      // Else error has occurred
      $extractTimeStampDataServerMesg = reset($extractTimeStamp);

      // Append error message
      array_push($errormessagearray, array('Extract Date Time Stamp Issue', '', '', '', $idNum, 'Error', $extractTimeStampDataServerMesg));
    }

    // Extract data
    $extractDataResponse = $multi_curl_cl->extractData();

    // Check if server error
    if (!isset($extractDataResponse['SError']) && !array_key_exists('SError', $extractDataResponse))
    {
      // Check if there is data to process
      if (count($extractDataResponse) > 0)
      {
        // Set parameter
        $authToken = "";

        // Authenticate call
        $authAPI = $multi_curl_cl->authenticateAPI();

        // Split string
        $authAPIRespArray = explode('~', $authAPI);

        // Set response and message
        $authAPIResp = reset($authAPIRespArray);
        $authAPIMesg = next($authAPIRespArray);

        // Check if the authentication was successful
        if ($authAPIResp === "Success")
        {
          // Decode JSON message
          $authResponse = json_decode($authAPIMesg, true);

          // Check if JSON was decoded properly
          if (json_last_error() == JSON_ERROR_NONE)
          {
            // Authenticate
            if (isset($authResponse["response"]["authToken"]))
            {
              // Set parameter
              $authToken = $authResponse["response"]["authToken"];
            }
            else
            {
              // Set array with error records for processing
              array_push($errormessagearray, array('API Authenticate Token Before Search', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
            }
          }
          else
          {
            // Append error message
            array_push($errormessagearray, array('JSON Issue Before Search', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
          }
        }
        else
        {
          // Append error message
          array_push($errormessagearray, array('Auth API Issue Before Search', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
        }

        // Check if auth token is not empty
        if ($authToken !== "")
        {
          // Bulk search call
          $searchAPIResponse = $multi_curl_cl->searchMultiAPI('?pg=1&pgsize=10', $authToken, $extractDataResponse);

          // Drop the data and shrink stack memory almost immediately
          $extractDataResponse = null;
          $extractDataResponse = array();

          // Check if server error was given when executing search call(s)
          if (!isset($searchAPIResponse['SError']) && !array_key_exists('SError', $searchAPIResponse))
          {
            // Process all search messages from the curl calls
            foreach($searchAPIResponse as $searchNumber => $searchAPIVals)
            {
              // Decode JSON message
              $searchResponse = json_decode($searchAPIVals, true);

              // Check if JSON was decoded properly
              if (json_last_error() == JSON_ERROR_NONE)
              {
                // Total Count
                if (isset($searchResponse["response"]["totalCount"]))
                {
                  // Initialize parameter
                  $totalValueCount = $searchResponse["response"]["totalCount"];

                  // Check if value exist
                  if ($totalValueCount > 0)
                  {
                    // Result ID
                    if (isset($searchResponse["response"]["results"][0]["root"]["id"]))
                    {
                      // Check if the status exist within the return message
                      if (isset($searchResponse["response"]["results"][0]["root"]["account"]["status"]))
                      {
                        // Check if the status is good to proceed
                        if (!in_array(strtolower($searchResponse["response"]["results"][0]["root"]["account"]["status"]), $accountStatus))
                        {
                          // Store all fields in the response
                          $postFieldsArray = $searchResponse["response"]["results"][0];

                          // Store found searched curl calls into array for later processing
                          $foundArray[$searchNumber] = $postFieldsArray;
                        }
                        else
                        {
                          // Update the table to status of 10 which will mean there are other issues that occurred
                          $validateDataValidationStatusResponse = $multi_curl_cl->validateData('0', '10', $searchNumber);

                          // Explode database message
                          $validateDataValidationStatusReturn = explode('~', $validateDataValidationStatusResponse);

                          // Set response message
                          $validateDataValidationStatusServerResp = reset($validateDataValidationStatusReturn);
                          $validateDataValidationStatusServerMesg = next($validateDataValidationStatusReturn);

                          // Check if the validate status was successful
                          if ($validateDataValidationStatusServerResp !== 'Success')
                          {
                            // Set array with error records for processing
                            array_push($errormessagearray, array('Update Data Validate Status Issue', $searchNumber, '', '', '', 'Error', $validateDataValidationStatusServerMesg));
                          }
                        }
                      }
                      else
                      {
                        // Set array with error records for processing
                        array_push($errormessagearray, array('API Search Result Status Issue', $searchNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $searchAPIVals)));
                      }
                    }
                    else
                    {
                      // Set array with error records for processing
                      array_push($errormessagearray, array('API Search Result ID Issue', $searchNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $searchAPIVals)));
                    }
                  }
                  else if ($totalValueCount === 0)
                  {
                    // Do nothing as there was no match for the number
                  }
                  else
                  {
                    // Initialize parameter
                    $errorMessageReturn = "";

                    // Check if error message exist
                    if (isset($authResponse["response"]["errors"][0]))
                    {
                      $errorMessageReturn = $authResponse["response"]["errors"][0];
                    }
                    else
                    {
                      // Else unknown error occurred
                      $errorMessageReturn = $searchAPIVals;
                    }

                    // Set array with error records for processing
                    array_push($errormessagearray, array('API Search Count Issue', $searchNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $errorMessageReturn)));
                  }
                }
                else
                {
                  // Set array with error records for processing
                  array_push($errormessagearray, array('API Search Count Error', $searchNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $searchAPIVals)));
                }
              }
              else
              {
                // Append error message
                array_push($errormessagearray, array('Search JSON Issue', $searchNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $searchAPIVals)));
              }
            }

            // Drop the data and shrink stack memory almost immediately
            $searchAPIResponse = null;
            $searchAPIResponse = array();
          }
          else
          {
            // Else error has occurred
            $searchAPIResponseServerMesg = reset($searchAPIResponse);

            // Append error message
            array_push($errormessagearray, array('Search API Response', '', '', '', '', 'Error', $searchAPIResponseServerMesg));
          }
        }

        // Call to database to build a JSON string per param 01 in array
        foreach($foundArray as $foundSearchNumber => $foundSearchValue)
        {
          // Extract Param 01 data
          $extractParam01DataResponse = $multi_curl_cl->extractParam01Data($foundSearchNumber);

          // Check if server error
          if (!isset($extractParam01DataResponse['SError']) && !array_key_exists('SError', $extractParam01DataResponse))
          {
            $jsonParam01 = isset($foundSearchValue['root']['jsonParam01']) ? trim($foundSearchValue['root']['jsonParam01']) : "";
            $jsonParam01 = ($jsonParam01 === '1') ? true : $jsonParam01;
            ($jsonParam01 === "") ? : $foundSearchValue['root']['jsonParam01'] = $jsonParam01;

            $numberString = reset($extractParam01DataResponse);

            $jsonParam02 = next($extractParam01DataResponse);
            $jsonParam02 = jsonParam02($jsonParam02);
            (strtolower($jsonParam02) === "" ? : $foundSearchValue['root']['extensions']['jsonParam02'] = $jsonParam02);

            $jsonParam03 = next($extractParam01DataResponse);
            $jsonParam03 = isset($foundSearchValue['root']['extensions']['jsonParam03']) ? trim($foundSearchValue['root']['extensions']['jsonParam03']) : trim($jsonParam03);
            $jsonParam03 = ($jsonParam03 === '1') ? true : $jsonParam03;

            // Check if the value from the database is true
            if ($jsonParam03 === 'true')
            {
              // Set to true
              $jsonParam03 = true;
            }
            else if ($jsonParam03 === 'false')
            {
              // Check if the value from the database is false
              // Set to false
              $jsonParam03 = false;
            }

            (strtolower($jsonParam04) === "" ? : $foundSearchValue['root']['extensions']['jsonParam04'] = $jsonParam04);

            // Set parameters
            $postFieldsJSON = '';
            $postFieldsJSON = '[' . json_encode($foundSearchValue) . ']';

            // Push JSON encoded string into an array for later processing
            array_push($finalArray, $postFieldsJSON);
          }
          else
          {
            // Else error has occurred
            $extractOneDataServerMesg = reset($extractParam01DataResponse);

            // Append error message
            array_push($errormessagearray, array('Extract Param 01 Data Issue', $foundSearchNumber, '', '', '', 'Error', $extractOneDataServerMesg));
          }
        }

        // Drop the data and shrink stack memory almost immediately
        $foundArray = null;
        $foundArray = array();

        // Check if there is data to process
        if (count($finalArray) > 0)
        {
          // Reset parameter
          $authToken = "";

          // Authenticate call
          $authAPI = $multi_curl_cl->authenticateAPI();

          // Split string
          $authAPIRespArray = explode('~', $authAPI);

          // Set response and message
          $authAPIResp = reset($authAPIRespArray);
          $authAPIMesg = next($authAPIRespArray);

          // Check if the authentication was successful
          if ($authAPIResp === "Success")
          {
            // Decode JSON message
            $authResponse = json_decode($authAPIMesg, true);

            // Check if JSON was decoded properly
            if (json_last_error() == JSON_ERROR_NONE)
            {
              // Authenticate
              if (isset($authResponse["response"]["authToken"]))
              {
                // Set parameter
                $authToken = $authResponse["response"]["authToken"];
              }
              else
              {
                // Set array with error records for processing
                array_push($errormessagearray, array('API Authenticate Token After Database Built', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
              }
            }
            else
            {
              // Append error message
              array_push($errormessagearray, array('Auth JSON Issue After Database Built', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
            }
          }
          else
          {
            // Append error message
            array_push($errormessagearray, array('Auth API Issue After Database Built', '', '', '', '', 'Error', str_replace($lineBreakString, '', $authAPIMesg)));
          }

          // Check if auth token is not empty
          if ($authToken !== "")
          {
            // POST update via API call in bulk
            $updateRootAPI = $multi_curl_cl->postUpdateDataMultiAPI($finalArray, $authToken);

            // Drop the data and shrink stack memory almost immediately
            $finalArray = null;
            $finalArray = array();

            // Check if server error was given when executing search call(s)
            if (!isset($updateRootAPI['SError']) && !array_key_exists('SError', $updateRootAPI))
            {
              // Process all posted update messages from the curl calls
              foreach($updateRootAPI as $updateRootNumber => $updateRootValue)
              {
                // Decode JSON message
                $updateRootResponse = json_decode($updateRootValue, true);

                // Check if JSON was decoded properly
                if (json_last_error() == JSON_ERROR_NONE)
                {
                  // Check if id was returned from the JSON string
                  if (isset($updateRootResponse["response"]["results"][0]["root"]["id"]))
                  {
                    // Store posted value into array for later processing
                    $finalFoundArray[$updateRootNumber] = $updateRootValue;

                    // Update the table with the new information
                    $validateDataValidationResponse = $multi_curl_cl->validateData('0', '3', $updateRootNumber);

                    // Explode database message
                    $validateDataValidationReturn = explode('~', $validateDataValidationResponse);

                    // Set response message
                    $validateDataValidationServerResp = reset($validateDataValidationReturn);
                    $validateDataValidationServerMesg = next($validateDataValidationReturn);

                    // Check if the validate status was successful
                    if ($validateDataValidationServerResp !== 'Success')
                    {
                      // Set array with error records for processing
                      array_push($errormessagearray, array('Update Data Validate Issue', $updateRootNumber, '', '', '', 'Error', $validateDataValidationServerMesg));
                    }
                  }
                  else
                  {
                    // Set array with error records for processing
                    array_push($errormessagearray, array('API Update Root ID Error', $updateRootNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $updateRootValue)));
                  }
                }
                else
                {
                  // Append error message
                  array_push($errormessagearray, array('Update Root JSON Issue', $updateRootNumber, '', '', '', 'Error', str_replace($lineBreakString, '', $updateRootValue)));
                }
              }

              // Drop the data and shrink stack memory almost immediately
              $updateRootAPI = null;
              $updateRootAPI = array();
            }
            else
            {
              // Else error has occurred
              $updateRootAPIResponseServerMesg = reset($updateRootAPI);

              // Append error message
              array_push($errormessagearray, array('POST Update Root API Response', '', '', '', '', 'Error', $updateRootAPIResponseServerMesg));
            }
          }
        }
      }
    }

    // Update sequence date
    $sequenceUpdate = $multi_curl_cl->updateDateTimeStamp($idNumScript);

    // Explode database message
    $sequenceUpdateData = explode('~', $sequenceUpdate);

    // Set response message
    $updateSeqServerResp = reset($sequenceUpdateData);
    $updateSeqServerMesg = next($sequenceUpdateData);

    // Check if error with registering process
    if (trim($updateSeqServerResp) !== "Success")
    {
      // Set array with error records for processing
      array_push($errormessagearray, array('Update Data Sequence', '', '', '', $idNumScript, $updateSeqServerResp, $updateSeqServerMesg));
    }

    // Check if error message array is not empty
    if (count($errormessagearray) > 0)
    {
      // Set prefix file name and headers
      $errorFilename = $errorPrefixFilename . date("Y-m-d_H-i-s") . '.csv';
      $colHeaderArray = array(array('Process', 'Number', 'Post Field String', 'Sequence Date Time', 'Sequence Number', 'Response', 'Message'));

      // Initialize variable
      $to = "";
      $to = $developerNotify;
      $to_cc = "";
      $to_bcc = "";
      $fromEmail = $fromEmailNotifier;
      $fromName = $fromEmailServer;
      $replyTo = $fromEmailNotifier;
      $subject = $scriptName . " Error";

      // Set the email headers
      $headers = "From: " . $fromEmailServer . " <" . $fromEmailNotifier . ">" . "\r\n";
      // $headers .= "CC: " . $to_cc . "\r\n";
      // $headers .= "BCC: " . $to_bcc . "\r\n";
      $headers .= "MIME-Version: 1.0\r\n";
      $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
      // $headers .= "X-Priority: 3\r\n";

      // Mail priority levels
      // "X-Priority" (values: 1, 3, or 5 from highest[1], normal[3], lowest[5])
      // Set priority and importance levels
      $xPriority = "";

      // Set the email body message
      $message = "<!DOCtype html>
      <html>
        <head>
          <title>
            Cron Job " . $scriptName . " Error
          </title>
          <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
          <!-- Include next line to use the latest version of IE -->
          <meta http-equiv=\"X-UA-Compatible\" content=\"IE=Edge\" />
        </head>
        <body>
          <div style=\"text-align: center;\">
            <h2>"
              . $scriptName .
              " Error
            </h2>
          </div>
          <div style=\"text-align: center;\">
            There was an issue with " . $scriptName . " Error process.
            <br />
            <br />
            Do not reply, your intended recipient will not receive the message.
          </div>
        </body>
      </html>";

      // Call notify developer function
      $multi_curl_cl->notifyDeveloper(TEMPDOC, $errorFilename, $colHeaderArray, $errormessagearray, $to, $to_cc, $to_bcc, $fromEmail, $fromName, $replyTo, $subject, $headers, $message, $xPriority);
    }
  }
  catch(Exception $e)
  {
    // Call to the function
    $checkerrorcl->caught_error_notify($e, $developerNotify, $scriptName . ' Error', $fromEmailNotifier, $fromEmailServer, $fromEmailNotifier);
  }
?>