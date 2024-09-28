<?php

// Download MKBHD wallpapers
// @john011235

error_reporting(E_ERROR | E_PARSE);

// Set up SQLite database connection
try {
    $db = new PDO('sqlite:panels.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS MKBHD_PANELS (
        ID INTEGER PRIMARY KEY AUTOINCREMENT,
        URL TEXT
    )
");

// Fetch JSON data from the URL
$json_url = 'https://storage.googleapis.com/panels-api/data/20240916/media-1a-i-p~s';
$walls = file_get_contents($json_url);

if ($walls === false) {
    die("Failed to fetch data from the URL.");
}

// Decode the JSON into a PHP array
$decoded_json = json_decode($walls, false);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Failed to decode JSON: " . json_last_error_msg());
}

// Directory to save downloaded items
$download_dir = 'mkbhd_walls/';
if (!is_dir($download_dir)) {
    mkdir($download_dir, 0777, true);
}

$wallpapers = $decoded_json->data;

// Loop through each item and process
foreach ($wallpapers as $key => $value) {   
    // Only grab HD/4K 
    if (empty($value->dhd)) continue;
    
    // URL of the actual wallpaper we are getting
    $wallpaper_URL = $value->dhd;
    
    if ($wallpaper_URL) {
        // Check if the item has already been downloaded
        $stmt = $db->prepare("SELECT COUNT(*) FROM MKBHD_PANELS WHERE ID = :id");
        $stmt->execute([':id' => $key]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            echo "Skipping already downloaded item: $wallpaper_URL\n";
            continue;
        }

        // Download the file
        $filename = basename(parse_url($wallpaper_URL, PHP_URL_PATH));
        $file_path = $download_dir . $filename;
        if (file_put_contents($file_path, file_get_contents($wallpaper_URL)) === false) {
            echo "Failed to download: $wallpaper_URL\n";
            continue;
        }

        // Insert the item into the SQLite database
        $stmt = $db->prepare("
            INSERT INTO MKBHD_PANELS (ID, URL)
            VALUES (:id, :url)
        ");
        $stmt->execute([
            ':id' => $key,
            ':url' => $wallpaper_URL
        ]);

        echo "Downloaded and processed: $wallpaper_URL\n";
    } else {
        echo "Skipping item without a URL.\n";
    }
}

echo "All items processed.\n";
?>
