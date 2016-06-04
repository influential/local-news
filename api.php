<?php
session_start();
require 'vendor/autoload.php';
use Aws\Lambda\LambdaClient;

/**
 * Simply connects to the local database
 * @return Mysqli connection instance
 */

function connectDB()
{
    //Note actual database credentials should be entered here
    return new mysqli('localhost', 'root', 'password', 'data');
}

/**
 * Stops program and launches when error state is reached
 * @param $message - Error message to output
 */

function error($message)
{
    $status = 'error';
    $error = $message;
    $response = array('status' => $status, 'error' => $error);
    echo json_encode($response);
    exit();
}

/**
 * Makes a HTTP GET request, uses Googlebot as User-Agent
 * @param $url - URL to query
 * @return HTTP response from request
 */

function curl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    $error = curl_error($ch);
    if ($error) {
        error($error);
    }
    curl_close($ch);
    return $data;
}

/**
 * Checks if the sitemap has certain substrings that indicates it is an unuseful sitemap
 * Also checks if a date is given that is not in the same year as 2016
 * @param $sitemap - URL of the sitemap to validate
 * @return Boolean indicating validity
 */

function validSitemap($sitemap)
{
    return (strpos($sitemap->loc, 'video') === false) && (strpos($sitemap->loc, 'categor') === false) && (strpos($sitemap->loc, 'stories') === false) && (strpos($sitemap->loc, 'event_part') === false) && !preg_match('/201[0-57-9]/', $row['sitemap']);
}

/**
 * Main function that runs to seed the database with valid sitemaps
 * THIS WILL OVERWRITE DATABASE VALUES, only needs to be run on occasion
 * This function may take a long time, it is loading 1000+ pages
 */

function seedSitemaps()
{
    $db = connectDB();
    //Get all news websites in database
    $query = 'SELECT url from tables';
    $result = $db->query($query);
    while ($row = $result->fetch_assoc()) {
        $add = [];
        $site = $row['url'];
        $body = curlXML($site.'/robots.txt');
        $maps = [];
        //Extract all .xml URLs from the file, could be a better regex though
        preg_match_all('/http(.+).xml/', $body, $matches);
        $maps = $matches[0];
        foreach ($maps as $map) {
            $errors = libxml_use_internal_errors(true);
            //Load each sitemap found to check it
            $xml = simplexml_load_string(curl($map));
            //Make sure XML is valid, just in case
            if ($xml !== false) {
                //See if it is a sitemap of sitemaps
                if (count($xml->sitemap) != 0) {
                    foreach ($xml->sitemap as $sitemap) {
                        if (validSitemap($sitemap->loc)) {
                            $add[] = $sitemap->loc;
                        }
                    }
                //If it doesn't contain sitemaps and likely just has articles or other links
                } elseif (count($xml->url) > 10) {
                    $add[] = $map;
                }
                //In case duplicates are added, since we are blindly accepting 2nd level sitemaps
                $add = array_unique($add);
                //Insert into database
                foreach ($add as $sitemap) {
                    $query = 'INSERT INTO sitemaps (site, sitemap) VALUES ("'.$site.'", "'.$sitemap.'")';
                    $results = $db->query($query);
                }
            }
            libxml_clear_errors();
        }
    }
}

/**
 * Makes a HTTP GET request, similar to other curl function, but doesn't throw any errors
 * @param $url - URL to query
 * @return HTTP response from request or false
 */

function curlXML($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //Only return data if page exists, a lot of these URLs will return with 404s
    if ($code == 200) {
        return data;
    } else {
        return false;
    }
}

/**
 * Finds Latitude/Longitude of address using Google Geocoding API
 * Don't use CURL helper function from before, https must be used
 * @param $address - Address to get location of
 * @return Array of location values, or error message
 */

function coordinates($address)
{
    //Enter actual Google Maps API Key here
    $key = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&key='.$key;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), true);
    if ($response['status'] == 'OK') {
        $latitude = $response['results'][0]['geometry']['location']['lat'];
        $longitude = $response['results'][0]['geometry']['location']['lng'];
        return array('latitude' => $latitude, 'longitude' => $longitude);
    } elseif ($response['status'] == 'ZERO_RESULTS') {
        return error('Cannot Detect Location');
    } elseif ($response['status'] == 'OVER_QUERY_LIMIT') {
        return error('Query Limit Reached');
    } else {
        return error('Geocoding API Error');
    }
}

/**
 * Given mile radius and address, finds nearby news stations
 * @param $miles - Searching radius from address
 * @param $address - Address to get location of
 * @return Array of nearby news stations, or error message
 */

function findNearbyStations($miles, $address)
{
    //Gets latitude and longitude
    $location = coordinates($address);
    $latitude = $location['latitude'];
    $longitude = $location['longitude'];
    $db = connectDB();
    //This is an approximate, conservative translation from a mile to a degree of latitude/longitude
    $range = $miles / 70;
    //Find stations within range
    $query = 'SELECT * FROM tables WHERE latitude between '.($latitude - $range).' and '.($latitude + $range).' and longitude between '.($longitude - $range).' and '.($longitude + $range);
    $result = $db->query($query);
    $db->close();
    $close = [];
    //This loop refines the stations in range by using Haversine's formula for a much more accurate calculation
    while ($row = $result->fetch_assoc()) {
        $df = deg2rad($row['latitude'] - $latitude);
        $dl = deg2rad($row['longitude'] - $longitude);
        $a = sin($df/2) * sin($df/2) + cos(deg2rad($latitude)) * cos(deg2rad($row['latitude'])) * sin($dl/2) * sin($dl/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        if ((3959 * $c) <= $miles) {
            $close[] = $row['url'];
        }
    }
    if (count($close) < 1) {
        return error('No Nearby News Stations');
    }
    return $close;
}

/**
 * Finds news articles given a list of sitemaps and homepages
 * @param $sites - List of sitemaps and homepages to extract links from
 * @return AWS Lambda result as an array of links, or error message
 */

function lambda($sites)
{
    //Encode all of the sites to meet AWS Lambda parameter requirements
    for ($i = 0; $i < count($sites); $i++) {
        $sites[$i] = base64_encode($sites[$i]);
    }
    $payload = json_encode($sites);
    //Use LambdaClient, make sure to insert actual credentials
    $client = LambdaClient::factory(array(
        'credentials' => array(
            'key'    => 'xxxxxxxxxxxxxxxxx',
          'secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ),
        'region'  => 'us-west-2',
        'version' => 'latest'
    ));
    //Use client to set parameter and function data
    $result = $client->invoke(array(
        'FunctionName' => 'arn:aws:lambda:us-west-2:xxxxxxxxxxxx:function:lambdaname',
        'Payload' => $payload
    ));
    //decode returned payload
    $links = json_decode($result->get('Payload'))->links;

    //error here?

    return $links;
}

/**
 * Produces list of URLs to get links from
 * @param $sites - List of news stations to get articles from
 * @return Get article links from lambda function, remove duplicates, filter
 */

function getLinks($sites)
{
    $db = connectDB();
    $urls = [];
    foreach ($sites as $site) {
        $query = 'SELECT sitemap FROM sitemaps WHERE site = "'.$site.'"';
        $result = $db->query($query);
        //If the site has sitemaps, add them to the list with homepages
        while ($row = $result->fetch_assoc()) {
            $urls[] = $row['sitemap'];
        }
        $urls[] = $site;
    }
    return filterLinks(array_unique(lambda($urls)));
}

/**
 * Simple scoring algorithm for ranking article links
 * @param $links - List of news articles to rank
 * @return Array of scored, sorted links
 */

function filterLinks($links)
{
    $keep = [];
    //Don't keep links that represent a file, most common when scraping a homepage
    foreach ($links as $link) {
        if (!preg_match('/\.(ico|png|jpg|jpeg|css|js|gif|xml)/', $link)) {
            //If extra parameters are on URL, trim those off
            $index = strpos($link, '?');
            if ($index !== false) {
                $link = substr($link, 0, $index);
            }
            $keep[] = $link;
        }
    }
    //New array for storing link->score as key->value
    $scored = array();
    foreach ($keep as $link) {
        $score = 0;
        //If URL matches a date pattern like 2016/04/25, works with year and day swapped around too
        if (preg_match('/(\d\d\d\d|\d\d)(\/|-)\d\d(\/|-)(\d\d\d\d|\d\d)/', $link)) {
            $score++;
        }
        //If URL matches a unique identifier pattern ex: 123456
        if (preg_match('/\d\d\d\d\d\d/', $link)) {
            $score++;
        }
        //If URL matches a unique word title pattern ex: local-man-saves-two-boys-in-iowa
        if (preg_match('/\/\w*?-\w*?-\w*?-\w*?-/', $link)) {
            $score++;
        }
        //Hard length requirement of 30 to remove shortened URLs, http://www.domain.com part is a lot of chars
        if ($score > 0 || strlen($link) > 30) {
            $scored[$link] = $score;
        }
    }
    //Calculate the shares of each URL
    $shares = shares($keep);
    //Add to score based on number of each shares
    foreach ($scored as $key => $value) {
        $shares = $facebook[$key];
        if ($shares > 0 && $shares < 20) {
            $scored[$key] = $scored[$key] + 1;
        } elseif ($shares < 100) {
            $scored[$key] = $scored[$key] + 2;
        } elseif ($shares >= 100) {
            $scored[$key] = $scored[$key] + 3;
        }
    }
    //Sort based on score
    arsort($scored);
    return $scored;
}

/**
 * Gets number of shares, likes, comments, etc from a large collection of links
 * @param $links - List of news articles to get shares of
 * @return Array of total social shares for each link
 */

function shares($links)
{
    $query = 'https://api.facebook.com/method/links.getStats?urls=';
    //Can take a lot of URLs, so make it all one big query
    foreach ($links as $link) {
        $query = $query."'".$link."',";
    }
    $query[strlen($query) - 1] = '&';
    $query = $query.'format=json';
    $result = curl($query);
    $json = json_decode($result);
    $shares = array();
    foreach ($json as $value) {
        $url = $value->url;
        $shares[$url] = $value->total_count;
    }
    return $shares;
}

//Only run if POST request is made
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = 'success';
    $error = '';
    $links = getLinks(findNearbyStations($_POST['miles'], $_POST['location']));
    //Only return top 100
    $links = array_slice($links, 0, 100);
    $response = array('status' => $status, 'links' => $links, 'error' => $error);
    echo json_encode($response);
}
