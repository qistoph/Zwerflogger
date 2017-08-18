# Cyberzwerftocht

## Requirements
* phpqrcode
* php5-sqlite

## Installation
* `git clone <repo location>`
* Edit `config.sample.php` and save as `config.php`
* Make sure to set a `session_name` and `hide_secret`
* Create the database, e.g. `sqlite3 data/zwerfdata.db < zwerfdata.schema`
* Insert the required teams using `sqlite3 data/zwerfdata.db`:
  * `INSERT INTO teams VALUES(lower(hex(randomblob(16))), 'Team Unicorn');`
  * Field 1, the id, must be a 32 characters hex string
* Insert the beacons using `sqlite3 data/zwerfdata.db`:
  * `INSERT INTO beacons VALUES(lower(hex(randomblob(16))), 'First', 1, 1);`
  * Field 1, the id, must be a 32 characters hex string
  * Field 2, the name, a unique name for the beacon. Will be visible to the teams (website, QR-label)
  * Field 3, the score, the score for this beacon
* Make the database writeable for your HTTP-server, e.g.:
  * `chown -R :www-data data`
  * `chmod -R g+w data`
* Make the `web` directory accessible in your HTTP-server
* Visit the qr.php page (e.g. https://domain.com/qr.php?hide=SECRET) and print the labels
