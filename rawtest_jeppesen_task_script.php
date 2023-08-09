<?php

      $redir = 'https://localhost/.....Jeppesen/task.php';
      $clientCredentials = base64_encode('CLIENT ID HERE' .':'. 'SECRET ID HERE');
      $resfile = './jeppesen_accounts__......_users_memberships.csv';
      $tempFileInfo = './tempFileInfo.txt';

  //#### First, get the auth code

  if(! isset($_GET['code']) && ! isset($_GET['state']) )
  {
   header('Location: https://.....memberclicks.net/oauth/v1/authorize?response_type=code&client_id=a5Y........kkQK&state=8d.....-3df5-11e6-ac61-9e.....e77&redirect_uri=https%3A%2F%2Flocalhost%2F....Jeppesen%2Ftask.php');
   exit;
  }
   else
   {
   	//#### Then get the token


      $data = array( 'Authorization' => 'Basic '.$clientCredentials.'', 'grant_type' => 'authorization_code', 'code' => $_GET["code"], 'scope' => 'read', 'redirect_uri' => $redir );
      $data = http_build_query($data);
      $headers = [
       'Host: .....memberclicks.net',
       'Authorization: Basic '.$clientCredentials.'',
       'Content-Type: application/x-www-form-urlencoded',
       'Cache-Control: no-cache',
      ];

       $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL,'https://.....memberclicks.net/oauth/v1/token');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $mc_res = curl_exec($ch);

        if($mc_res === false){
         echo'<h2>cURL error</h2>'; exit;
        } else {
          $mc_res = json_decode($mc_res, true);
        }

         curl_close($ch);
   }

  //#### If we have got the token

    extract($mc_res, EXTR_PREFIX_SAME, "wddx");

   //#### Get the first members page list
   ######

    if( !empty($access_token) )
    {

    	$headers = [
       'Host: ......memberclicks.net',
       'Accept: application/json',
       'Authorization: Bearer '.$access_token.'',
       'Cache-Control: no-cache',
      ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL,'https://mmopa.memberclicks.net/api/v1/profile');
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
        curl_setopt($ch, CURLOPT_XOAUTH2_BEARER, $access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

         $response_json = curl_exec($ch);

   ////////////////
   // BUILD CSV (first round)
   ////////////////

   // header (first line)
   // ADD OR REMOVE fields here and more below!!
   // ALSO into the other while loop!

   $list = array (
    array('created_at','expires_at','user_email','first_name','last_name','user_id','product_id','product_name','status','jeppesen account number')
   );

         $response = json_decode($response_json, true);
          curl_close($ch);
          echo'<br>OK BUILDING FIRST PAGE<br><br><br><pre>';
         // print_r($response);

        if( !empty($response['profiles']) && $response['count'] == 10 )
        {
        	// ADD OR REMOVE fields to match the above header array()
          foreach( $response['profiles'] as $p_response )
          {
          	// Should we add only members that own a Jeppesen account number?
          	// And active members?
           if ( $p_response['[Member Status]'] == 'Active' && ! empty($p_response['Jeppesen Account Number'])
                && is_numeric($p_response['Jeppesen Account Number']) )
           {
             // 'Created Date' conversion like memberpress
            $dateString = trim($p_response['[Created Date]']);
            $d = DateTime::createFromFormat('m/d/Y h:i:s A', $dateString);
            if ($d === false) {
            $newDateString = 'IncorrectDateString';
             } else {
                $p_response['[Created Date]'] = $d->format('Y-m-d h:i:s');
               }

           // 'Expiration Date' conversion to be like memberpress
           $dateString = trim($p_response['[Expiration Date]']);
          // on Memberpress lifetime was 0000-00-00 00:00:00 in memberclick is an emtpy val
           if( !empty($dateString) )
            {
              $d = DateTime::createFromFormat('m/d/Y', $dateString);
              if ($d === false) {
               $newDateString = 'IncorrectDateString';
              } else {
                $newDateString = $d->format('Y-m-d');
                $p_response['[Expiration Date]'] = $newDateString . ' 00:00:00';
               }
            } else {$p_response['[Expiration Date]'] = '0000-00-00 00:00:00'; } // '0000-00-00 00:00:00' lifetime memberpress



/*
   created_at                expires_at
 2021-11-23 09:02:26      2024-01-28 23:59:59 OR 0000-00-00 00:00:00
   YY MM GG                 YY MM GG
  [[Created Date]]          [[Expiration Date]]
 02/27/2023 11:26:03 AM     12/04/2023 OR empty
 MM GG YY                   MM GG YY

*/

       $list[] = array($p_response['[Created Date]'],$p_response['[Expiration Date]'],$p_response['[Email | Primary]'], $p_response['[Name | First]'], $p_response['[Name | Last]'], $p_response['[Member Type]'], $p_response['[Member Number]'], $p_response['[Profile ID]'], $p_response['[Member Status]'], $p_response['Jeppesen Account Number']);

             // Put data from the first page/profile API response

             $fp = fopen($resfile, 'w');

              foreach ($list as $fields) {
               fputcsv($fp, $fields,'~');
              }

               fclose($fp);



            }

           } // END foreach that add the 'first page' items

              // clean the resulting file content from unwanted chars
              $handle = fopen($resfile, "r");
              $content = fread($handle, filesize($resfile));
              fclose($handle);
                // Remove unwanted chars from the file
                $check = array('"', "&#8211;");
                $replace   = array("", "-");
                $cleaned = str_replace($check, $replace, $content);

                $fp = fopen($resfile, 'w');
                fwrite($fp, $cleaned);
                fclose($fp);

              while(is_resource($fp))
              {
              //Handle still open
               fclose($fp);
               $fp = null;
              }
              // END clean

         }

    } // END if( !empty($access_token) )

   //
   // WHILE FOR PAGES
   //


        $arrayPut = array_slice($response, 0, 8);
        $arrayPut['token'] = $access_token;

        file_put_contents($tempFileInfo, serialize($arrayPut),LOCK_EX);

        $currentPageData = unserialize(file_get_contents($tempFileInfo));
        echo'<br>OK currentPageData file_get_contents<br><br><br><pre>';
        print_r($currentPageData);
      // exit;

        $pageNumber = $currentPageData['pageNumber']; // ... since we already have get the 1st page,
        $count = $partial = $currentPageData['count'];
        $totalCountAll = $currentPageData['totalCount'];
        $access_token = $currentPageData['token'];

    	$headers = [
       'Host: ......memberclicks.net',
       'Accept: application/json',
       'Authorization: Bearer '.$access_token.'',
       'Cache-Control: no-cache',
      ];

    $totalPageCount = intval(($totalCountAll-10)/100);
    $test_pc = ($totalPageCount*100)+10;
    if( $test_pc < $totalCountAll ) { $totalPageCount++; }

     	 while ($pageNumber < $totalPageCount) {
     //echo $partial.'<br>';

      $pageNumber++;

      echo '<br>Pagenumber is'. $pageNumber.'<br>';
      echo '<br>totalPageCount is'. $totalPageCount.'<br>';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL,'https://mmopa.memberclicks.net/api/v1/profile?pageNumber='.$pageNumber.'&pageSize=100');
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
        curl_setopt($ch, CURLOPT_XOAUTH2_BEARER, $access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

         $response_json = curl_exec($ch);

          $response = json_decode($response_json, true);
          curl_close($ch);

   ////////////////
   // Continue BUILD CSV adding pages data
   ////////////////
       if( !empty($response['profiles']) )
        {
        	// ADD OR REMOVE fields to match the above header array()
          foreach( $response['profiles'] as $p_response )
          {
          	// Should we add only members that own a Jeppesen account number?
          	// And active members?
           if ( $p_response['[Member Status]'] == 'Active' && ! empty($p_response['Jeppesen Account Number'])
                && is_numeric($p_response['Jeppesen Account Number']) )
           {

              // 'Created Date' conversion like memberpress
            $dateString = trim($p_response['[Created Date]']);
            $d = DateTime::createFromFormat('m/d/Y h:i:s A', $dateString);
            if ($d === false) {
            $newDateString = 'IncorrectDateString';
             } else {
                $p_response['[Created Date]'] = $d->format('Y-m-d h:i:s');
               }

           // 'Expiration Date' conversion to be like memberpress
           $dateString = trim($p_response['[Expiration Date]']);
          // on Memberpress lifetime was 0000-00-00 00:00:00 in memberclick is an emtpy val
           if( !empty($dateString) )
            {
              $d = DateTime::createFromFormat('m/d/Y', $dateString);
              if ($d === false) {
               $newDateString = 'IncorrectDateString';
              } else {
                $newDateString = $d->format('Y-m-d');
                $p_response['[Expiration Date]'] = $newDateString . ' 00:00:00';
               }
            } else {$p_response['[Expiration Date]'] = '0000-00-00 00:00:00'; } // '0000-00-00 00:00:00' lifetime memberpress



     $list[] = array($p_response['[Created Date]'],$p_response['[Expiration Date]'],$p_response['[Email | Primary]'], $p_response['[Name | First]'], $p_response['[Name | Last]'], $p_response['[Member Type]'], $p_response['[Member Number]'], $p_response['[Profile ID]'], $p_response['[Member Status]'], $p_response['Jeppesen Account Number']);

     // Put pages data from each subsequent API response

     $fp = fopen($resfile, 'w');

       foreach ($list as $fields) {
        fputcsv($fp, $fields,'~');
       }

      }

      } // END foreach

     /*      fclose($fp);
      while(is_resource($fp))
       {
       //Handle still open
        fclose($fp);
        $fp = null;
       }*/


      } // END if( !empty($response['profiles']) )

        $arrayPut = array_slice($response, 0, 8);
        $currentPageData = unserialize(file_get_contents($tempFileInfo));
        $arrayPut['token'] = $currentPageData['token'];

      if( file_put_contents($tempFileInfo, serialize($arrayPut),LOCK_EX) )
       {
        echo $partial += $response['count'];

        echo '<br />pagenumber is '.$pageNumber.'<br />';
         //exit;
        }

     }// END WHILE


           // END if( !empty($response['profiles']) )



          exit;


  } // END if( !empty($mc_res) )

