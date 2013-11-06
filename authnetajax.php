<?php
  require('database_utilities.inc.php');
  require('site_wide_utilities.inc.php');
  
  $db = new database;
  $db->get_connection_info($_SERVER['DOCUMENT_ROOT'].'/mimik/mimik_configuration/database_connection_info.csv');
  $db->connect();
  
  if(!empty($_POST)){
    if($_POST['payment_method']=='cc'){
      $last_space_pos = strrpos($_POST['name'],' ');
      $first_name = substr($_POST['name'],0,$last_space_pos);
      $last_name = substr($_POST['name'],$last_space_pos+1);
      $exp_date = str_pad($_POST['exp_date_month'],2,'0',STR_PAD_LEFT).date('y',mktime(0,0,0,$_POST['exp_date_month'],1,$_POST['exp_date_year']));
      
      if($_POST['state']){
        $sql = "select abbreviation from mimik_State where id = ".$_POST['state'];
        $state = $db->fetch($sql);
        $state = $state[0]['abbreviation'];
      }
      
      $post_url = "https://secure.authorize.net/gateway/transact.dll";
      #$post_url = "https://test.authorize.net/gateway/transact.dll";
      $post_values = array(
        "x_login"			=> "[REMOVED]",
        "x_tran_key"		=> "[REMOVED]",
        
        /*"x_login"			=> "[REMOVED]",
        "x_tran_key"		=> "[REMOVED]",*/
      
        "x_version"			=> "3.1",
        "x_delim_data"		=> "TRUE",
        "x_delim_char"		=> "|",
        "x_relay_response"	=> "FALSE",
      
        "x_type"			=> "AUTH_CAPTURE",
        "x_method"			=> "CC",
        "x_card_num"		=> $_POST['card_number'],
        "x_exp_date"		=> $exp_date,
        "x_card_code"		=> $_POST['cvv_code'],
      
        "x_amount"			=> $_POST['total'],
        "x_description"		=> "Conference Registration for ".$_POST['institution'],
      
        "x_first_name"		=> $first_name,
        "x_last_name"		=> $last_name,
        "x_address"			=> $_POST['address']."\n".$_POST['address_2'],
        "x_state"			=> $_POST['state'],
        "x_zip"				=> $_POST['zip']
      );
      
      $post_string = "";
      foreach( $post_values as $key => $value )
        { $post_string .= "$key=" . urlencode( $value ) . "&"; }
      $post_string = rtrim( $post_string, "& " );
      
      $request = curl_init($post_url);
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
        $post_response = curl_exec($request);
      curl_close ($request);
      
      $response_array = explode($post_values["x_delim_char"],$post_response);
      if($response_array[3]!='This transaction has been approved.') echo $response_array[3];
      $payment_details = 'Credit card ending in '.substr($_POST['card_number'],12,4);
      $delete_token = true;
    }elseif($_POST['payment_method']=='po'){
      echo'<p><strong>Your registration has been accepted.</strong></p>',
        '<p>Confirmations of payment will be sent via email as soon as payment is processed.</p>',
        '<p>Confirmations of registration will be sent to attendees via email as soon as payment is processed.</p>';
      $payment_details = 'Purchase order #'.$_POST['purchase_order_number'];
      $delete_token = true;
    }else $delete_token = false;
  }
  if($delete_token && $_POST['id']){
    $sql = "update mimik_Conference_Registrations set token = '' where id = ".$_POST['id']." limit 1;";
    $db->query($sql);
    
    $sql = "select * from mimik_Conference_Attendees where registration = ".$_POST['id'];
    $attendees = $db->fetch($sql);
    
    if(!empty($attendees)){
      $attendees_str = 'Attendees:'."\r\n";
      foreach($attendees as $attendee){
        $attendees_str .= $attendee['first_name'].' '.$attendee['last_name']."\r\n";
      }
      $attendees_str .= "\r\n";
    }
    
    $to = $_POST['email'];
    $subject = 'Core Knowledge 2011 Annual Conference - Registration Accepted';
    $message = 'Thank you for registering to attend our conference!'."\r\n".
      'Confirmations of payment will be sent via email as soon as payment is processed.'."\r\n".
      'Confirmations of registration will be sent to attendees via email as soon as payment is processed.'."\r\n".
      'Below are the details of your payment:'."\r\n\r\n".
      'Total: $'.$_POST['total']."\r\n".
      $payment_details."\r\n\r\n".
      $attendees_str.
      'We look forward to seeing you in Orlando!'."\r\n\r\n".
      '- The Core Knowledge Foundation';
    $headers = 'From: Core Knowledge Foundation <conference@coreknowledge.org>'."\r\n".
      'Reply-To: Core Knowledge Foundation <conference@coreknowledge.org>'."\r\n".
      'X-Mailer: PHP/'.phpversion();
    
    $mail_sent = mail($to,$subject,$message,$headers);
    if(!$mail_sent) error_log('Failed to send email confirmation for Core Knowledge conference registration.');
  }
  $db->disconnect();
?>
