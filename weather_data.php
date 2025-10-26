<?php
// Start output buffering to catch any unwanted output
ob_start();

include('config/config.php');
global $map, $fork;

// Clear any output that might have been generated during includes
ob_clean();

// Suppress all PHP errors/warnings for JSON API endpoints
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
// init map
if (strtolower($map) === "rdm") {
    $scanner = new \Scanner\RDM();
} elseif (strtolower($map) === "golbat") {
    $scanner = new \Scanner\Golbat();
} elseif (strtolower($map) === "rocketmap") {
    if (strtolower($fork) === "mad") {
        $scanner = new \Scanner\RocketMap_MAD();
    }
}
if (isset($_POST['cell_id'])) {
    $return_weather = $scanner->get_weather_by_cell_id($_POST['cell_id']);
} else {
    // $timestamp = (isset($_POST['ts']) ? $_POST['ts'] : null);
    $return_weather  = $scanner->get_weather();
}
$d['weather'] = $return_weather;
$d['timestamp'] = time();
$jaysson = json_encode($d);

// End output buffering and send clean JSON response
ob_end_clean();
echo $jaysson;
