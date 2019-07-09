<?php
session_start();
/* */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**/

// Get the contents of the JSON file 
$configAppJson = file_get_contents("configApp.json");
//var_dump($configAppJson); // show contents
// Convert to array 
$configApp = json_decode($configAppJson, true);

/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
include_once __DIR__ . '/google-api-php-client-2.2.3/vendor/autoload.php';
include_once "templates/base.php";
// echo pageHeader("File Upload - Uploading a simple file");
/*************************************************
 * Ensure you've downloaded your oauth credentials
 ************************************************/
if (!$oauth_credentials = getOAuthCredentialsFile()) {
  echo missingOAuth2CredentialsWarning();
  return;
}
/************************************************
 * The redirect URI is to the current page, e.g:
 * http://localhost:8080/simple-file-upload.php
 ************************************************/
$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
$client = new Google_Client();
$client->setAuthConfig($oauth_credentials);
$client->setRedirectUri($redirect_uri);
$client->addScope(["https://www.googleapis.com/auth/userinfo.email",
                  "https://www.googleapis.com/auth/drive",
                  "https://www.googleapis.com/auth/spreadsheets.readonly"]);
$objOAuthService = new Google_Service_Oauth2($client);
$service = new Google_Service_Sheets($client);
$drive = new Google_Service_Drive($client);

$flagSpreadsheet = false;
$flagHeader = false;
$mainSpreadsheetId = $configApp["mainSpreadsheetId"];
// add "?logout" to the URL to remove a token from the session
if (isset($_REQUEST['logout'])) {
  unset($_SESSION['upload_token']);
}
/************************************************
 * If we have a code back from the OAuth 2.0 flow,
 * we need to exchange that with the
 * Google_Client::fetchAccessTokenWithAuthCode()
 * function. We store the resultant access token
 * bundle in the session, and redirect to ourself.
 ************************************************/
if (isset($_GET['code']) && $_GET['code']) {
  $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
  //echo "<script>console.log( 'Debug Objects: ', \"" . var_export($token, true) . "\" );</script>";
  $client->setAccessToken($token);
  // store in the session also
  $_SESSION['upload_token'] = $token;
  // redirect back to the example
  //echo "<script>console.log( 'Debug Objects: change header = ". filter_var($redirect_uri, FILTER_SANITIZE_URL) . "');</script>";
  header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}
// set the access token as part of the client
if (!empty($_SESSION['upload_token'])) {
  //echo "<script>console.log( 'Debug Objects: upload_token = ".$_SESSION['upload_token']."');</script>";
  $client->setAccessToken($_SESSION['upload_token']);
  if ($client->isAccessTokenExpired()) {
    //echo "<script>console.log( 'Debug Objects: unset upload_token');</script>";
    unset($_SESSION['upload_token']);
  }
} else {
  $authUrl = $client->createAuthUrl();
}
/************************************************
 * If we're signed in then lets try to upload our
 * file. For larger files, see fileupload.php.
 ************************************************/
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $client->getAccessToken()) {
  // The ID of the spreadsheet to update.
  $spreadsheetId = getSpreadsheetId();  // TODO: Update placeholder value.

  switch ($_POST['btn']) {
    case 'read':
      // Prints the names and majors of students in a sample spreadsheet:
      // https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
      
      //$spreadsheetId = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';
      $range = 'Folha1!A2:G';
      $today = date("Y/m/d");
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
      $values = $response->getValues();
      if (empty($values)) {
          print "No data found.\n";
      } else {
          print "<ul class=\"list-group\">";
          print "<li class=\"list-group-item disabled\" aria-disabled=\"true\">Data, Hora:</li>";
          foreach ($values as $row) {
              // Print columns A and E, which correspond to indices 0 and 4.
              if($row[1] == $today){
                $badge = $row[6] == "in" ? "badge-primary" : "badge-warning";
                printf("<li class=\"list-group-item d-flex justify-content-between align-items-center\">
                        %s, %s",$row[1], $row[2]);
                if($row[6] == "out")
                  printf("<span class=\"badge badge-light badge-pill\">%s</span>", $row[4]);  
                printf("<span class=\"badge %s badge-pill\">%s</span>", $badge, $row[6]);
                printf("</li>");
              }
          }
          print "</ul>";
      }

      $range = 'Folha2!A2:L';
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
      $values = $response->getValues();
      if (!empty($values)) {
        print("<div class=\"table-responsive table-striped\"><table class=\"table\">");
        print("
          <thead>
            <tr>
              <th scope=\"col\">Date</th>
              <th scope=\"col\">NOW_HOUR</th>
              <th scope=\"col\">LAST_HOUR</th>
              <th scope=\"col\">NOW_HOUR-LAST_HOUR</th>
              <th scope=\"col\">TOTAL_HOUR</th>
              <th scope=\"col\">DaTODAY_HOURte</th>
              <th scope=\"col\">LAST_STATUS</th>
              <th scope=\"col\">LEFT</th>
              <th scope=\"col\">N_PICA</th>
              <th scope=\"col\">EXIT</th>
              <th scope=\"col\">WEEK</th>
              <th scope=\"col\">BALANCE</th>
            </tr>
          </thead>
          <tbody>
        ");
        foreach ($values as $row) {
          // Print columns A and E, which correspond to indices 0 and 4.
          if($row[0] == $today){
            printf("
            <tr>
              <th scope=\"row\">%s</th>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
            </tr>
            ",$row[0],$row[1],$row[2],$row[3],$row[4],$row[5],$row[6],$row[7],$row[8],$row[9],$row[10],$row[11]);
          }
        }
        print("</tbody></table></div>");
      }

      break;
    case 'create_sheet':
      $title = 'picaPonto.viseu.biz';
      $spreadsheet = new Google_Service_Sheets_Spreadsheet([
        'properties' => [
            'title' => $title,
            'timeZone' => 'Europe/Lisbon'
        ],
        'sheets' => [
          [
            'properties' => [
              'title' => 'Folha1',
              'sheetId' => 01,
              'index' => 01,
              'hidden' => false
            ]/*,
            'data' => [
              'startRow' => 0,
              'startColumn' => 0,
              'rowData' => [
                'values' => [
                  "ID"
                ]
              ]
            ]*/
          ],
          [
            'properties' => [
              'title' => 'Folha2',
              'sheetId' => 02,
              'index' => 02,
              'hidden' => false
            ]
          ]
        ]
      ]);
      $spreadsheet = $service->spreadsheets->create($spreadsheet, [
          'fields' => 'spreadsheetId'
      ]);
      printf("Spreadsheet ID: %s\n", $spreadsheet->spreadsheetId);
      
      // ADD ID to MAIN SHEET
      $values = [
        [$objOAuthService->userinfo->get()["email"], $spreadsheet->spreadsheetId],
      ];
      //'majorDimension' => 'ROWS',
      $body = new Google_Service_Sheets_ValueRange([
          'values' => $values,
          'majorDimension' => 'ROWS'
      ]);

      $valueInputOption = "USER_ENTERED";
      $params = [
          'valueInputOption' => $valueInputOption
      ];
      $range = "Folha11!A:B";
      $result = $service->spreadsheets_values->append($mainSpreadsheetId, $range, $body, $params);
      printf("%d cells appended.", $result->getUpdates()->getUpdatedCells());

      break;
    case 'add_header':
      // The ID of the spreadsheet to update.
      //$spreadsheetId = '';  // TODO: Update placeholder value.

      $values = [
        ["ID", "Data", "Hora", "Torniquete", "Tempo", "Falta", "Saida"]        
      ];
      // ["Totals", "=SUM(B2:B4)", "=SUM(C2:C4)", "=MAX(D2:D4)"]
      $body = new Google_Service_Sheets_ValueRange([
          'values' => $values
      ]);

      $valueInputOption = "USER_ENTERED";
      $params = [
          'valueInputOption' => $valueInputOption
      ];
      $range = "Folha1!A1:G5";
      $result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
      printf("%d cells updated.", $result->getUpdatedCells());
      break;
    case 'new_row':
      // The ID of the spreadsheet to update.
      //$spreadsheetId = '';  // TODO: Update placeholder value.

      $range = 'Folha1!A2:G';
      $today = date("Y-m-d");
      $response = $service->spreadsheets_values->get($spreadsheetId, $range);
      $values = $response->getValues();
      $rowCount = sizeof($values);
      $saida = $values[$rowCount-1][6] == "in" ? "out" : "in";

      $values = [
        [$rowCount+1, date("Y/m/d"), date("H:i:s"), "Torniquete", "=SE(Folha1!\$G".($rowCount+2)." = \"out\";\$C".($rowCount+2)."-\$C".($rowCount+1).";0)", "Falta", $saida],
      ];
      //'majorDimension' => 'ROWS',
      $body = new Google_Service_Sheets_ValueRange([
          'values' => $values,
          'majorDimension' => 'ROWS'
      ]);

      $valueInputOption = "USER_ENTERED";
      $params = [
          'valueInputOption' => $valueInputOption
      ];
      $range = "Folha1!A:G";
      $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
      printf("%d cells appended.", $result->getUpdates()->getUpdatedCells());
      break;
    default:
      //# code...
      break;
  }
  

} //else
if ($client->getAccessToken()) {
  $userData = $objOAuthService->userinfo->get();
  
  // Check if spreadsheet
  if(empty($_SESSION['spreadsheetId'])) {
    $range = 'Folha11!A2:B';
    $email = $userData["email"];
    $response = $service->spreadsheets_values->get($mainSpreadsheetId, $range);
    $values = $response->getValues();
    if (empty($values)) {
        print "No MAIN data found.\n";
    } else {
      foreach ($values as $row) 
        if($row[0] == $email){ // user already with spreadsheetId
          // store in the session also spreadsheetId
          $_SESSION['spreadsheetId'] = $row[1];
          $spreadsheetId = $row[1];
          $flagSpreadsheet = true;
        }
    }
  } else {
    $spreadsheetId = $_SESSION['spreadsheetId'];
    $flagSpreadsheet = true;
  }
  

  // Check if spreadsheet has header
  if($flagSpreadsheet) {
    $mainSpreadsheetId = getSpreadsheetId();
    $range = 'Folha1!A1:G1';
    $header = array("ID", "Data", "Hora", "Torniquete", "Tempo", "Falta", "Saida");  
    $response = $service->spreadsheets_values->get($mainSpreadsheetId, $range);
    $values = $response->getValues();
    if (empty($values)) {
        print "No HEADER data found.\n";
    } else {
      $values = $values[0];
      $flagHeader = true;
      for ($i=0; $i < count($values) ; $i++) { 
        if($values[$i] != $header[$i]){
          $flagHeader = false;
          break;
        }
      }
      if(!$flagHeader) {
        print "Need to create header.\n";
      }
    }

  }
  
}

//print_r($flagSpreadsheet ? "1" : "0");
//print_r($flagHeader ? "1" : "0");
//print_r($spreadsheetId);

function getSpreadsheetId() {
  if (!empty($_SESSION['spreadsheetId']))
    return $_SESSION['spreadsheetId'];
  /*
  else if(!empty($configApp["spreadsheetId"]))
    return $configApp["spreadsheetId"];
  */
  else
    return null;
}
?>


<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">

        <title>PicaPonto</title>
    </head>

    <body>

        <div class="box">
            <?php if (isset($authUrl)): ?>
              <div class="request">
                <a class='login' href='<?= $authUrl ?>'>Connect Me!</a>
              </div>
            <?php else: ?>
                <?php if($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                    <div class="shortened">
                        <p>Your call was successful! Check...</p>
                    </div>
                <?php endif ?>
              <form method="POST">
                <?php if(empty($flagSpreadsheet) || !$flagSpreadsheet): ?>
                    <input type="submit" name="btn" value="create_sheet" class="btn btn-secondary">
                <?php elseif(empty($flagHeader) || !$flagHeader): ?>
                    <input type="submit" name="btn" value="add_header" class="btn btn-success">
                <?php elseif(!empty($spreadsheetId) && $spreadsheetId != ""): ?>
                    <input type="submit" name="btn" value="read" class="btn btn-primary">
                    <input type="submit" name="btn" value="new_row" class="btn btn-danger">
                <?php endif ?>
                
                <!--
                <button type="button" class="btn btn-warning">Warning</button>
                <button type="button" class="btn btn-info">Info</button>
                <button type="button" class="btn btn-light">Light</button>
                <button type="button" class="btn btn-dark">Dark</button>
      
                <button type="button" class="btn btn-link">Link</button>
                -->
              </form>
            <?php endif ?>
        </div>

        <!-- Optional JavaScript -->
        <!-- jQuery first, then Popper.js, then Bootstrap JS -->
        <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
    </body>
</html>


<?= pageFooter(__FILE__) ?>