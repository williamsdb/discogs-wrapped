<?php

    class nowPlayingException extends Exception {}

    // set error handling
    error_reporting(E_NOTICE);
    ini_set('display_errors', 0);

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // have we got a config file?
    try {
        require __DIR__.'/config.php';
    } catch (\Throwable $th) {
        throw new nowPlayingException("config.php file not found. Have you renamed from config_dummy.php?.");
    }

    session_start();

    if (!isset($_SESSION['results']) && isset($_REQUEST['next']) && $_REQUEST['next'] == 1) {

        // get the user details
        $ch = curl_init($endpoint."/users/{$username}");
        curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Discogs token={$token}"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) {
            die('Error: ' . $httpCode . ' ' . $response);
        }

        // get the response and convert it to an array
        $dets = json_decode($response);

        // find the total number of items in the collection
        $total = $dets->num_collection;

        // set the year to process
        if (isset($_REQUEST['year'])){
            $year = $_REQUEST['year'];
        }else{
            $year = date("Y");
        }

        $page = 0;
        $yearTotal = 0;
        $yearPreviousTotal = 0;
        $artists = [];
        $formats = [];
        $genres = [];

        // loop through the collection
        while ($page <= ($total / 100)) {

            // get the a page of release details
            $ch = curl_init($endpoint."/users/{$username}/collection/folders/0/releases?page=".$page."&per_page=100&sort=added&sort_order=desc");
            curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscogsClient/1.0 +https://nei.lt/now-playing');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET' );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Discogs token={$token}"
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode != 200) {
                die('Error: ' . $httpCode . ' ' . $response);
            }
        
            // get the response and convert it to an array
            $dets = json_decode($response);

            // Cycle through the seasons and check if they are in the database already
            foreach ($dets->releases as $release) {

                // check if the release is in the year we are interested in
                if (substr($release->date_added,0,4) == $year){
                    $yearTotal++;

                    // update the artist counts
                    $artistName = $release->basic_information->artists[0]->name;
                    if (isset($artists[$artistName]['count'])) {
                        $artists[$artistName]['count']++;
                        $artists[$artistName]['cover'] = $release->basic_information->cover_image;
                    } else {
                        $artists[$artistName]['count'] = 1;
                        $artists[$artistName]['cover'] = $release->basic_information->cover_image;
                    }

                    // update the format counts
                    $format = $release->basic_information->formats[0]->name;
                    if (isset($formats[$format]['count'])) {
                        $formats[$format]['count']++;
                    } else {
                        $formats[$format]['count'] = 1;
                    }

                    // update the genre counts
                    foreach ($release->basic_information->genres as $genre) {
                        if (isset($genres[$genre]['count'])) {
                            $genres[$genre]['count']++;
                        } else {
                            $genres[$genre]['count'] = 1;
                        }
                    }

                }elseif (substr($release->date_added,0,4) == $year-1){
                    $yearPreviousTotal++;
                }else{
                    break;  // we are done
                }
            
            }

            // Increment the page number
            $page++;

        }

        // Sort the artists array by the 'count' key in descending order
        uasort($artists, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        // Sort the formats array by the 'count' key in descending order
        uasort($formats, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
    
        // Sort the genres array by the 'count' key in descending order
        uasort($genres, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Select a random artist
        $randomArtistKey = array_rand($artists);
        $randomCover1 = $artists[$randomArtistKey]['cover'];
        $randomArtistKey = array_rand($artists);
        $randomCover2 = $artists[$randomArtistKey]['cover'];
        $randomArtistKey = array_rand($artists);
        $randomCover3 = $artists[$randomArtistKey]['cover'];
        $randomArtistKey = array_rand($artists);
        $randomCover4 = $artists[$randomArtistKey]['cover'];
        $randomArtistKey = array_rand($artists);
        $randomCover5 = $artists[$randomArtistKey]['cover'];
        
        $results = [
            'year' => $year,
            'total' => $total,
            'coverUrlTotal' => $randomCover4,
            'yearTotal' => $yearTotal,
            'coverUrlYear' => $randomCover3,
            'yearPreviousTotal' => $yearPreviousTotal,
            'coverUrlYearPrevious' => $randomCover5,
            'mostFrequentArtist' => array_key_first($artists),
            'highestCountArtist' => $artists[array_key_first($artists)]['count'],
            'coverUrlArtist' => $artists[array_key_first($artists)]['cover'],
            'mostFrequentFormat' => array_key_first($formats),
            'highestCountFormat' => $formats[array_key_first($formats)]['count'],
            'coverUrlFormat' => $randomCover1,
            'mostFrequentGenre' => array_key_first($genres),
            'highestCountGenre' => $genres[array_key_first($genres)]['count'],
            'coverUrlGenre' => $randomCover2,
        ];

        $_SESSION['results'] = $results;

    }

    // prepare variables for the page
    if (isset($_REQUEST['next'])){
        $next = $_REQUEST['next'];
        $results = $_SESSION['results'];
    }else{
        $next = 0;
    }
    if ($next == 0){
        $img = "nocoverart.jpeg";
        $title = "You Discogs Stats";
        $content = 'Click Next to get started';
        $num = 0;
        $next++;
    }elseif ($next == 1){
        $img = $results['coverUrlTotal'];
        $title = "Number in Collection";
        $content = '<div class="counter" id="counter">0</div>';
        $num = $results['total'];
        $next++;
    }elseif ($next == 2){
        $img = $results['coverUrlYear'];
        $title = "Added in ".date("Y");
        $content = '<div class="counter" id="counter">0</div>';
        $num = $results['yearTotal'];
        $next++;
    }elseif ($next == 3){
        $img = $results['coverUrlYearPrevious'];
        $title = "Difference from ".($results['year']-1);
        $diff = $results['yearTotal'] - $results['yearPreviousTotal'];
        if ($diff > 0){
            $content = 'You added '.$diff.' more records in '.($results['year']).' than '.($results['year']-1);
            $num = $diff;
        }elseif ($diff < 0){
            $content = 'You added '.abs($diff).' fewer records in '.($results['year']).' than '.($results['year']-1);
            $num = $diff;
        }  else{
            $content = 'You added the same number of records in '.($results['year']).' as '.($results['year']-1);
            $num = 0;
        }
        $num = 0;
        $next++;
    }elseif ($next == 4){
        $img = $results['coverUrlArtist'];
        $title = "Most Added Artist";
        $content = $results['mostFrequentArtist'];
        $num = 0;
        $next++;
    }elseif ($next == 5){
        $img = $results['coverUrlFormat'];
        $title = "Most Added Format";
        $content = $results['mostFrequentFormat'];
        $num = 0;
        $next++;
    }elseif ($next == 6){
        $img = $results['coverUrlGenre'];
        $title = "Most Added Genre";
        $content = $results['mostFrequentGenre'];
        $num = 0;
        $next++;        
    }elseif ($next == 7){
        session_destroy();
        header("Location: wrapped.php");
    }


    function array_to_html($val, $var=FALSE) {
        $do_nothing = true;
        $indent_size = 20;
        $out = '';
        $colors = array(
            "Teal",
            "YellowGreen",
            "Tomato",
            "Navy",
            "MidnightBlue",
            "FireBrick",
            "DarkGreen"
            );
      
          // Get string structure
          ob_start();
          print_r($val);
          $val = ob_get_contents();
          ob_end_clean();
      
          // Color counter
          $current = 0;
      
          // Split the string into character array
          $array = preg_split('//', $val, -1, PREG_SPLIT_NO_EMPTY);
          foreach($array as $char) {
              if($char == "[")
                  if(!$do_nothing)
                      if ($var) { $out .= "</div>"; }else{ echo "</div>"; }
                  else $do_nothing = false;
              if($char == "[")
                  if ($var) { $out .= "<div>"; }else{ echo "<div>"; }
              if($char == ")") {
                  if ($var) { $out .= "</div></div>"; }else{ echo "</div></div>"; }
                  $current--;
              }
      
              if ($var) { $out .= $char; }else{ echo $char; }
      
              if($char == "(") {
                  if ($var){
                    $out .= "<div class='indent' style='padding-left: {$indent_size}px; color: ".($colors[$current % count($colors)]).";'>";
                  }else{
                    echo "<div class='indent' style='padding-left: {$indent_size}px; color: ".($colors[$current % count($colors)]).";'>";
                  }
                  $do_nothing = true;
                  $current++;
              }
          }
    
          return $out;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimal-ui, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Favicon -->
	<link rel="shortcut icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" sizes="57x57" href="/favicon-57x57.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/favicon-72x72.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/favicon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicon-120x120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon-180x180.png">

    <title>Discogs Wrapped</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #FFFFFF;
            display: flex;
            flex-direction: column; /* Arrange elements in a column */
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center; /* Center-align text by default */
        }
        a:visited {
            color: #FFFFFF;
        }
        .now-playing {
            text-align: center;
            background-color: #1DB954;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 320px; 
            cursor: pointer;
            position: relative;
        }
        .cover-art {
            width: 300px;
            height: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30px;
            height: 30px;
            border: 4px solid rgba(255, 0, 0, 0.5);
            border-top: 4px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            visibility: hidden; /* Initially hidden */
            z-index: 2; /* Ensure it appears above content */
        }
        .song-title {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
            word-wrap: break-word; 
            overflow-wrap: break-word; 
        }
        .artist {
            font-size: 20px;
            margin: 5px 0;
        }
        .release-year {
            font-size: 16px;
            color: #B3B3B3;
        }
        .above-text,
        .below-text {
            font-size: 18px;
            margin: 10px 0;
        }
        h1 {
            font-size: 36px;
        }
        .refresh {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .counter {
            font-size: 3rem;
            font-weight: bold;
            color: #FFF;
        }
        @keyframes spin {
            from {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="above-text"><h1>Discogs Wrapped</h1></div>
    <div class="now-playing">
        <div class="loading-spinner"></div>
        <img src="<?php echo $img ?>" alt="Cover Art" class="cover-art">
        <div class="song-title"><?php echo $title ?></div>
        <div class="artist"><?php echo $content ?></div>
    </div>
    <div class="below-text">
        <p><a class="refresh" href="wrapped.php?next=<?php echo $next ?>" id="reloadLink" aria-haspopup="true" aria-expanded="false">Next</a></p>
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>.</small></div>

        <script>
        function countToX(target, duration) {
            const counterElement = document.getElementById('counter');
            const stepTime = Math.abs(Math.floor(duration / target));
            let current = 0;

            const timer = setInterval(() => {
                current += 1;
                counterElement.textContent = current;

                if (current >= target) {
                    clearInterval(timer);
                }
            }, stepTime);
        }

        // Start counting to 100 over 2 seconds
        countToX(<?php echo $num ?>, 2000);

        // Show the spinner
        function showSpinner() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.visibility = 'visible'; // Use visibility instead of display for compatibility
            }
        }

        // Hide the spinner
        function hideSpinner() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.style.visibility = 'hidden';
            }
        }

        // Open in a new tab
        function openInNewTab(url) {
            window.open(url, '_blank'); // Open URL in a new tab
        }

        // Reload the page
        document.getElementById('reloadLink').addEventListener('click', function (event) {
            showSpinner(); // Show spinner
        });

        // Polyfill for `Element.closest` for older browsers
        if (!Element.prototype.closest) {
            Element.prototype.closest = function (selector) {
                var el = this;
                while (el) {
                    if (el.matches(selector)) return el;
                    el = el.parentElement || el.parentNode;
                }
                return null;
            };
        }
    </script></body>
</html>