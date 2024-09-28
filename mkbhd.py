import os
import sqlite3
import requests
import json
from urllib.parse import urlparse, parse_qs

# Set up SQLite database connection
try:
    conn = sqlite3.connect('panels.db')
    cursor = conn.cursor()
except sqlite3.Error as e:
    print(f"Database connection failed: {e}")
    exit(1)

# Create table if it doesn't exist
cursor.execute("""
    CREATE TABLE IF NOT EXISTS MKBHD_PANELS (
        ID INTEGER PRIMARY KEY AUTOINCREMENT,
        URL TEXT
    )
""")
conn.commit()

# Fetch JSON data from the URL
json_url = 'https://storage.googleapis.com/panels-api/data/20240916/media-1a-i-p~s'
response = requests.get(json_url)

if response.status_code != 200:
    print("Failed to fetch data from the URL.")
    exit(1)

# Decode the JSON into a Python dictionary
try:
    decoded_json = response.json()
except json.JSONDecodeError as e:
    print(f"Failed to decode JSON: {e}")
    exit(1)

# Directory to save downloaded items
download_dir = 'mkbhd_walls/'
if not os.path.isdir(download_dir):
    os.makedirs(download_dir, exist_ok=True)

wallpapers = decoded_json['data']

# Loop through each item and process
for key, value in wallpapers.items():
    # Only grab HD/4K
    if not isinstance(value, dict) or 'dhd' not in value:
        continue

    # URL of the actual wallpaper we are getting
    wallpaper_URL = value['dhd']

    if wallpaper_URL:
        # Check if the item has already been downloaded
        cursor.execute("SELECT COUNT(*) FROM MKBHD_PANELS WHERE ID = ?", (key,))
        exists = cursor.fetchone()[0]

        if exists:
            print(f"Skipping already downloaded item: {wallpaper_URL}")
            continue

        # Download the file
        parsed_url = urlparse(wallpaper_URL)
        filename = os.path.basename(parsed_url.path)
        file_path = os.path.join(download_dir, filename)
        try:
            with open(file_path, 'wb') as f:
                f.write(requests.get(wallpaper_URL).content)
        except Exception as e:
            print(f"Failed to download: {wallpaper_URL} - {e}")
            continue

        # Insert the item into the SQLite database
        cursor.execute("INSERT INTO MKBHD_PANELS (ID, URL) VALUES (?, ?)", (key, wallpaper_URL))
        conn.commit()

        print(f"Downloaded and processed: {wallpaper_URL}")
    else:
        print("Skipping item without a URL.")

print("All items processed.")
conn.close()

