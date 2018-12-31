<?php

require 'vendor/autoload.php';

# Update these variables:
$ipn_url = "https://example.org/civicrm/payment/ipn/9";
$sk = 'sk_live_XXX';
$from_date = '2018-12-18 22:49:00';
$to_date = '2018-12-28 18:05:50';

# Optional: you can set a event ID to get events after this one.
# This is used for paging, but also because the loop exits on errors,
# so you might want to set this once you have started replaying events.
#
# $after = 'evt_1DjWQpDb5Xlrd8JfL8tescJF';
$after = NULL;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

\Stripe\Stripe::setApiKey($sk);

$client = new Client();
$count = 0;

do {
  // List all recent events
  $response = \Stripe\Event::all([
    'created' => [
      'gt' => strtotime($from_date),
      'lt' => strtotime($to_date),
    ],
    'delivery_success' => false,
    'limit' => 100,
    'starting_after' => $after,
  ]);

  echo "Received " . count($response->data) . " events...\n\n";

  foreach ($response->data as $key => $val) {
    if ($val->type == 'charge.failed') {
      // CiviCRM often fatals with these, if the subscription was deleted from CiviCRM?
      echo 'Skipping ' . $val->id . ' (created: ' . date('Y-m-d H:i:s', $val->created) . ", type = " . $val->type . "\n";
      continue;
    }

    echo 'Processing: ' . $val->id . ' (created: ' . date('Y-m-d H:i:s', $val->created) . ' ...';

    try {
      $post = $client->post($ipn_url, [
        GuzzleHttp\RequestOptions::JSON => $val,
      ]);

      $code = $post->getStatusCode();

      if ($code == 200) {
        echo " OK\n";
      }
      else {
        echo " FAILED/stopping (received: $code) - \n";
        print_r($val);
        exit(1);
      }
    }
    catch (Exception $e) {
      echo " FAILED/stopping (" . $e->getMessage() . ")\n";
      print_r($val);
      exit(1);
    }

    $after = $val->id;
  }
} while (count($response->data));

echo "All done.\n";
