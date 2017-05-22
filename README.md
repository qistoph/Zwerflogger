# CyberZwerftocht

## Requirements
* phpqrcode
* php5-sqlite

## Installation
* `git clone <repo location>`
* Edit `config.sample.php` and save as `config.php`
* Make sure to set a `session_name` and `hide_secret`
* Create the database, e.g. `sqlite3 zwerfdata.db < zwerfdata.schema`
* Insert the required teams using `sqlite3 zwerfdata.db`:
  * `INSERT INTO teams VALUES(lower(hex(randomblob(16))), 'Team Unicorn');`
  * Field 1, the id, must be a 32 characters hex string
* Insert the beacons using `sqlite3 zwerfdata.db`:
  * `INSERT INTO beacons VALUES(lower(hex(randomblob(16))), 'First', 1, 1);`
  * Field 1, the id, must be a 32 characters hex string
  * Field 2, the name, a unique name for the beacon. Will be visible to the teams (website, QR-label)
  * Field 3, the score, the score for this beacon
  * Field 4, mandatory, should be 0 or 1 to indicate if this beacon MUST be visited (1)
* Make the database writeable for your HTTP-server, e.g.:
  * `chown :www-data . zwerfdata.db`
  * `chmod g+w . zwerfdata.db`
* Make the `web` directory accessible in your HTTP-server
* Visit the qr.php page (e.g. https://domain.com/qr.php?hide=SECRET) and print the labels
