#!/usr/bin/php
<?php
date_default_timezone_set("Europe/Prague");
$api_key = "";
$api_sec = "";

$conf="oauth.conf";

if (file_exists($conf)) {
  require_once($conf);
  }
else {
  $oauth_token = "";
  $oauth_token_secret = "";
}

$pref_sizes = array("Large 1600", "Large 1024", "Large", "Original");

$out_base = "./photos/";
$req_url = "https://www.flickr.com/services/oauth/request_token";
$auth_url = "https://www.flickr.com/services/oauth/authorize";
$acc_url = "https://www.flickr.com/services/oauth/access_token";
$api_url = "https://api.flickr.com/services/rest/";

if (!file_exists($out_base)) {
    mkdir($out_base);
}

$oauth = new OAuth($api_key, $api_sec, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
$oauth->enableDebug();

// Checking whether we already went through user authentication or not
// (If yes, then there is token/secret saved in the config file) 
if ($oauth_token=="" || $oauth_token_secret=="") {
  echo "No OAuth access token/secret found.\n";

  // Standard OAuth procedure for getting an access token follows
  // We are not using callback, but verifier, hence "oob" instead of the callback URL
  $request_token_info = $oauth->getRequestToken($req_url, "oob");
  echo "Visit ".$auth_url."?oauth_token=".$request_token_info['oauth_token']."\n";
  $oauth_verifier = readline("Authenticate the app and copy the verifier code:");
  $oauth->setToken($request_token_info['oauth_token'],$request_token_info['oauth_token_secret']);
  $access_token_info = $oauth->getAccessToken($acc_url, null, $oauth_verifier);
  $oauth_token = $access_token_info['oauth_token'];
  $oauth_token_secret = $access_token_info['oauth_token_secret'];

  // Saving token/secret for the next time
  $f = fopen("oauth.conf", "w");
  fputs($f, "<?php\n\$oauth_token=\"$oauth_token\";\n\$oauth_token_secret=\"$oauth_token_secret\";\n?>\n");
  fclose($f);
}
else {
  echo "OAuth access token/secret found, will use them.\n"; 
}

$oauth->setToken($oauth_token, $oauth_token_secret);
try {

  // Getting User ID
  $oauth->fetch($api_url, array("format"=>"php_serial", "method"=>"flickr.test.login"),OAUTH_HTTP_METHOD_GET);
  $uid = unserialize($oauth->getLastResponse())["user"]["id"];
  echo "User ID: $uid\n";

  // Getting list of user's photosets
  $oauth->fetch($api_url, array("format"=>"php_serial", "method"=>"flickr.photosets.getlist"),OAUTH_HTTP_METHOD_GET);
  $sets = unserialize($oauth->getLastResponse())["photosets"]["photoset"];

  // Iterating through sets
  foreach ($sets as $set) {
    echo "Set ID: ".$set['id'].", title: ".$set['title']['_content'].", photos: ".$set['photos']."\n";

    // Removing funny characters from photoset names, keeping national characters
    $out_dir = preg_replace("/[^[:alnum:]]+/ui", "_", $set['title']['_content']);
    echo "Directory $out_base$out_dir ";

    // If the target directory doesn't exist, create it
    if (!file_exists($out_base.$out_dir)) {
      echo "doesn't exist, creating.\n";
      mkdir($out_base.$out_dir);
    }
    else {
      echo "already exists, will use it.\n";
    }

    $oauth->fetch($api_url, array("format"=>"php_serial", "method"=>"flickr.photosets.getphotos", "photoset_id"=>$set['id'], "user_id"=>$uid), OAUTH_HTTP_METHOD_GET);
    $photos = unserialize($oauth->getLastResponse())['photoset']['photo'];

    // Iterating through all photos
    foreach ($photos as $photo) {
      echo "\tPhoto ID: ".$photo['id']."\n";

      // Getting photo title to use it as a filename
      // If title is empty, date and time of taking the photo  will be used as a filename
      // Funny characters removed
      $oauth->fetch($api_url, array("format"=>"php_serial", "method"=>"flickr.photos.getinfo", "photo_id"=>$photo['id']), OAUTH_HTTP_METHOD_GET);
      $photo_info = unserialize($oauth->getLastResponse())['photo'];
      $photo_file_name = $photo_info['title']['_content'] == "" ? $photo_info['dates']['taken'] : $photo_info['title']['_content'];
      $photo_file_name = preg_replace("/[^[:alnum:]]+/ui", "_", $photo_file_name);

      // Getting list of available photo sizes
      $oauth->fetch($api_url, array("format"=>"php_serial", "method"=>"flickr.photos.getsizes", "photo_id"=>$photo['id']), OAUTH_HTTP_METHOD_GET);
      $sizes = unserialize($oauth->getLastResponse())['sizes']['size'];

      $urls = array();

      // Iterating through all photo sizes and searching for preferred
      foreach ($sizes as $size) {
        if (in_array($size['label'], $pref_sizes)) {
          echo "\t\tSize: ".$size['label']." found, URL: ".$size['source']."\n";
          $urls[$size['label']] = $size['source'];
        }
      }

      // Downloading the most preferred size
      foreach ($pref_sizes as $pref) {
        if (array_key_exists($pref, $urls)) {

          // Getting file extension from the URL used to download
          $photo_file_ext = substr($urls[$pref], strrpos($urls[$pref], "."));

          // Full target path for saving
          $photo_full_path = $out_base.$out_dir."/".$photo_file_name.$photo_file_ext;

          // Fetching the photo and saving it
          echo "\t\tDownloading $pref to $photo_full_path\n\n";
          $photo_file_contents = file_get_contents($urls[$pref]);
          $f = fopen($photo_full_path,"w");
          fputs($f, $photo_file_contents);
          fclose($f);
          break;
        }
      }
      unset($urls);
    }
  }
}

catch (OAuthException $e) {
  // Logging the exception
  ob_flush();
  ob_start();
  var_dump($e);
  file_put_contents("error.log", ob_get_flush());
}

?>
