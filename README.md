# CyberZwerftocht

## Requirements
* phpqrcode
* php5-sqlite

## Installation
* `git clone <repo location>`
* Edit `config.sample.php` and save as `config.php`
* Make sure to set a `session_name` and `hide_secret`
* Create the database, e.g. `sqlite3 zwerfdata.db < zwerfdata.schema`
* Make the database writeable for your HTTP-server, e.g.:
  * `chown :www-data . zwerfdata.db`
  * `chmod g+w . zwerfdata.db`
* Make the `web` directory accessible in your HTTP-server
