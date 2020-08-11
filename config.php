<?php
    setlocale(LC_TIME, "de_DE");

    // config
    $_CONFIG = array(
        "database" => array(
            "icalserver" => include __DIR__."/credentials/database.icalserver.php"
        )
    );

    // TODO: Validate configs

    // initialize database
    require_once __DIR__.'/lib/DB/DB.php';
    $DB = new \Interdose\DB('icalserver');
?>