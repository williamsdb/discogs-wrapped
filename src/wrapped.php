<?php

    class nowPlayingException extends Exception {}

    // set error handling
    error_reporting(E_NOTICE);
    ini_set('display_errors', 0);

    // have we got a config file?
    try {
        require __DIR__.'/config.php';
    } catch (\Throwable $th) {
        throw new nowPlayingException("config.php file not found. Have you renamed from config_dummy.php?.");
    }

    session_start();

    // set the year to process
    if (isset($_REQUEST['year'])){
        $year = $_REQUEST['year'];
    }else{
        $year = date("Y");
    }

    // get the details from Discogs
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

        // set variables
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
        $randomArtistKey = array_rand($artists,5);

        // prepare the results
        $results = [
            'year' => $year,
            'total' => $total,
            'coverUrlTotal' => $artists[$randomArtistKey[3]]['cover'],
            'yearTotal' => $yearTotal,
            'coverUrlYear' => $artists[$randomArtistKey[2]]['cover'],
            'yearPreviousTotal' => $yearPreviousTotal,
            'coverUrlYearPrevious' => $artists[$randomArtistKey[4]]['cover'],
            'mostFrequentArtist' => array_key_first($artists),
            'highestCountArtist' => $artists[array_key_first($artists)]['count'],
            'coverUrlArtist' => $artists[array_key_first($artists)]['cover'],
            'mostFrequentFormat' => array_key_first($formats),
            'highestCountFormat' => $formats[array_key_first($formats)]['count'],
            'coverUrlFormat' => $artists[$randomArtistKey[0]]['cover'],
            'mostFrequentGenre' => array_key_first($genres),
            'highestCountGenre' => $genres[array_key_first($genres)]['count'],
            'coverUrlGenre' => $artists[$randomArtistKey[1]]['cover'],
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
        $title = 'Year: <select name="years" id="years" onchange="updateYear()">';
        for ($i = $year-10; $i <= date("Y"); $i++){
            if ($i == $year){
                $title .= '<option value="'.$i.'" selected>'.$i.'</option>';
            }else{
                $title .= '<option value="'.$i.'">'.$i.'</option>';
            }
        }
        $title .= '</select>';
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
        $title = "Added in ".$results['year'];
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
        session_destroy();
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
    <link rel="apple-touch-icon" sizes="57x57" href="favicon-57x57.png">
    <link rel="apple-touch-icon" sizes="72x72" href="favicon-72x72.png">
    <link rel="apple-touch-icon" sizes="114x114" href="favicon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="favicon-120x120.png">
    <link rel="apple-touch-icon" sizes="152x152" href="favicon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="favicon-180x180.png">

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
        a:visited, a:link {
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
        <?php if ($next != 7){ ?>
            <p><a class="refresh" href="wrapped.php?next=<?php echo $next ?>&year=<?php echo $year ?>" id="reloadLink" aria-haspopup="true" aria-expanded="false">Next</a></p>
        <?php }else{ ?>
            <p><a class="refresh" href="wrapped.php" id="reloadLink" aria-haspopup="true" aria-expanded="false">Done</a></p>
        <?php } ?>
        <small>Built by <a href="https://neilthompson.me">Neil Thompson</a>.</small></div>

        <script>
        function countToX(target, duration) {
            if (document.getElementById('counter')) {
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

        function updateYear() {
            // Get the selected year from the dropdown
            const selectedYear = document.getElementById('years').value;

            // Get the anchor link element
            const reloadLink = document.getElementById('reloadLink');

            // Update the href attribute with the selected year
            const url = new URL(reloadLink.href); // Parse the current href
            url.searchParams.set('year', selectedYear); // Update the 'year' parameter
            reloadLink.href = url.toString(); // Set the updated URL back to the href
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
