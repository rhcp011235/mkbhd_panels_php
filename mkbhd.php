<?php

// Download MKBHD wallpapers
// @john011235
error_reporting(E_ERROR | E_PARSE);
$VERBOSE = 1;

class MyDB extends SQLite3 {
	function __construct() {
	   $this->open('panels.db');
	}
 }
 
 $db = new MyDB();
 if(!$db) {
	echo $db->lastErrorMsg();
 } else {
	echo "Opened database successfully\n";
 }

	$sql =<<<EOF
      CREATE TABLE MKBHD_PANELS
      (ID INT PRIMARY KEY     NOT NULL,
      URL           TEXT    NOT NULL);
EOF;
$ret = $db->exec($sql);
if(!$ret)
{
	if ($VERBOSE) echo $db->lastErrorMsg() . "\n";
} else {
	if ($VERBOSE) echo "Table created successfully\n";
}

if (!file_exists('./mkbhd_walls/')) {
    mkdir('./mkbhd_walls', 0777, true);
}


$walls = file_get_contents('https://storage.googleapis.com/panels-api/data/20240916/media-1a-i-p~s');
$decoded_json = json_decode($walls, false);

$wallpapers = $decoded_json->data;
$i = 0;

foreach ($wallpapers as $key => $value) 
{
	// Only download 4K/HD images
	if ($value->dhd == "") continue;
	
    $id = $key;
	$wallpaper_URL = $value->dhd;

	$sql =<<<EOF
      INSERT INTO MKBHD_PANELS (ID,URL)
      VALUES ($id, '$wallpaper_URL' );
EOF;
$ret = $db->exec($sql);
   if(!$ret) {
	if ($db->lastErrorMsg() == 'UNIQUE constraint failed: MKBHD_PANELS.ID')
	continue;
   } else {
	if ($VERBOSE) echo "Records created successfully\n";
	if ($VERBOSE) echo "Downloading " . $wallpaper_URL . "\n";
   }
   
   $image = file_get_contents($wallpaper_URL);
   file_put_contents("./mkbhd_walls/wall_$i.jpg", $image);
   $i++;
}
$db->close();

if ($VERBOSE) echo "Database Closed\n";
?>
