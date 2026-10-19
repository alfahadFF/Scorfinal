<?php
/* ALFAMAN on one.com — MariaDB (the database included with one.com).
   1) one.com Control Panel → Advanced settings → PHP and database settings:
      create/note the database. Details shown there: hostname, DB name, user.
   2) Run install.sql in phpMyAdmin (one.com provides it) to create tables.
   3) Copy this file to config.php and fill the four marked values.      */
return [
    'driver' => 'mysql',
    'host'   => 'YOUR_DB_HOST.one.com',   // <-- from PHP and database settings
    'port'   => 3306,
    'name'   => 'YOUR_DB_NAME',           // <--
    'user'   => 'YOUR_DB_USER',           // <--
    'pass'   => 'YOUR_DB_PASSWORD',       // <--
    'site'   => __DIR__ . '/../scor1',    // scor1 folder next to the alfaman folder
];
