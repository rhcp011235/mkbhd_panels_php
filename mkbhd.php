<?php

// Download MKBHD wallpapers
// @john011235

error_reporting(E_ERROR | E_PARSE);

// Configuration
define('DB_PATH', 'sqlite:panels.db');
define('JSON_URL', 'https://storage.googleapis.com/panels-api/data/20240916/media-1a-i-p~s');
define('DOWNLOAD_DIR', 'mkbhd_walls/');
define('MAX_CONCURRENT_DOWNLOADS', 5);

// Set up SQLite database connection
function getDatabaseConnection() {
    try {
        $db = new PDO(DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        logError("Database connection failed: " . $e->getMessage());
        exit;
    }
}

// Create table if it doesn't exist
function createTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS MKBHD_PANELS (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            URL TEXT
        )
    ");
}

// Fetch JSON data from the URL
function fetchJsonData($url) {
    $data = file_get_contents($url);
    if ($data === false) {
        logError("Failed to fetch data from the URL.");
        exit;
    }
    return $data;
}

// Decode JSON data
function decodeJson($json) {
    $decoded = json_decode($json, false);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("Failed to decode JSON: " . json_last_error_msg());
        exit;
    }
    return $decoded;
}

// Log errors
function logError($message) {
    error_log($message);
    echo $message . "\n";
}

// Check if file exists
function fileExists($filePath) {
    return file_exists($filePath);
}

// Download files concurrently
function downloadFilesConcurrently($urls) {
    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$url] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    $results = [];
    foreach ($curlHandles as $url => $ch) {
        $results[$url] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
    return $results;
}

// Main script
$db = getDatabaseConnection();
createTable($db);

$jsonData = fetchJsonData(JSON_URL);
$decodedJson = decodeJson($jsonData);

if (!is_dir(DOWNLOAD_DIR)) {
    mkdir(DOWNLOAD_DIR, 0777, true);
}

$wallpapers = $decodedJson->data;
$urlsToDownload = [];

foreach ($wallpapers as $key => $value) {
    if (empty($value->dhd)) continue;

    $wallpaperURL = $value->dhd;

    if ($wallpaperURL) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM MKBHD_PANELS WHERE ID = :id");
        $stmt->execute([':id' => $key]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            echo "Skipping already downloaded item: $wallpaperURL\n";
            continue;
        }

        $urlsToDownload[$key] = $wallpaperURL;
    } else {
        echo "Skipping item without a URL.\n";
    }
}

$chunks = array_chunk($urlsToDownload, MAX_CONCURRENT_DOWNLOADS, true);
foreach ($chunks as $chunk) {
    $results = downloadFilesConcurrently($chunk);

    foreach ($results as $url => $content) {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filePath = DOWNLOAD_DIR . $filename;

        if (file_put_contents($filePath, $content) === false) {
            logError("Failed to save: $url");
            continue;
        }

        $stmt = $db->prepare("
            INSERT INTO MKBHD_PANELS (ID, URL)
            VALUES (:id, :url)
        ");
        $stmt->execute([
            ':id' => array_search($url, $urlsToDownload),
            ':url' => $url
        ]);

        echo "Downloaded and processed: $url\n";
    }
}

echo "All items processed.\n";
?>

