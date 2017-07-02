<html>
<body>

<form method="POST">
    <label for="stripe_json">Content of file provided by Stripe</label><br>
    <textarea name="stripe_json" rows="5" cols="40"></textarea><br>
    <input type="submit">
</form>

<pre>Content should follow <a href="https://gist.github.com/anonymous/aa3fe02f9b11cdb1dda4287a5fced500">this format</a>.</pre>

<?php

// Form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['stripe_json'])) {
    
    $stripe_accounts = json_decode($_POST['stripe_json']); // Should be in this format: https://gist.github.com/anonymous/aa3fe02f9b11cdb1dda4287a5fced500
    
    if (json_last_error() != JSON_ERROR_NONE) exit;
    
    include_once('wp-config.php'); // Load some WP vars
    
    $mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if (!$mysqli) {
        echo "Error: Unable to connect to MySQL.".PHP_EOL;
        echo "Debugging errno: ".mysqli_connect_errno() . PHP_EOL;
        echo "Debugging error: ".mysqli_connect_error() . PHP_EOL;
        exit;
    }
    
    foreach ($stripe_accounts as $wc_braintree_customer_id => $stripe_account) {
        // Get WP user_id
        $user_id = $mysqli->query("SELECT `user_id` FROM `wp_usermeta` WHERE `meta_key` = 'wc_braintree_customer_id' AND `meta_value` = ".$wc_braintree_customer_id)->fetch_object()->user_id;
        
        if (empty($user_id)) continue;
        
        // Create new payment_tokens
        foreach ($stripe_account->cards as $card) {

            $query = mysqli_query($mysqli, "SELECT * FROM `wp_woocommerce_payment_tokens` WHERE `user_id` = ".$user_id." AND `token` = '".$card->id."'");
            
            if(mysqli_num_rows($query) > 0) {
                echo "<pre>Card already exists: ".$card->id."</pre>";
            } else {
                // Create token
                $query = "INSERT INTO `wp_woocommerce_payment_tokens` (`gateway_id`,`token`,`user_id`,`type`,`is_default`) VALUES ('stripe', '".$card->id."', ".$user_id.", 'CC', 1)";
                $mysqli->query($query);
                
                // Add token meta
                $query = "INSERT INTO `wp_woocommerce_payment_tokenmeta` (`payment_token_id`,`meta_key`,`meta_value`) VALUES (".$mysqli->insert_id.",'last4','".$card->last4."'),(".$mysqli->insert_id.",'expiry_year','".$card->exp_year."'),(".$mysqli->insert_id.",'expiry_month','".$card->exp_month."'),(".$mysqli->insert_id.",'card_type','".strtolower($card->brand)."')";
                $mysqli->query($query);
                
                echo '<pre>Created new card for '.$user_id.': '.$card->brand.' '.$card->last4.' '.$card->exp_month.'/'.$card->exp_year.'</pre>';
            }
        }
    }
    
    mysqli_close($mysqli);
}

?>

</body>
</html>
