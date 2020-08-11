<?php
    /**
     * config.php
     * 
     * general config needed for all the files
     * 
     * @version     2020-08-11
     * @copyright   (C) 2020 Sebastian Fuhrmann
     * @author      Sebastian Fuhrmann <sebastian.fuhrmann@rz-fuhrmann.de>
     * 
     */
    setlocale(LC_TIME, "de_DE");

    // config
    $_CONFIG = array(
        "database" => array(
            "icalserver" => include __DIR__."/credentials/database.icalserver.php"
        ),
        "importer" => array(
            "importdir" => __DIR__ . '/_import/'
        )
    );

    // initialize database
    require_once __DIR__.'/lib/DB/DB.php';
    $DB = new \Interdose\DB('icalserver');
?>