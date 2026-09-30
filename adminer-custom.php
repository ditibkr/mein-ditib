<?php
function adminer_object() {
    // Adminer Klasse erst nach dem include verfügbar
    // Daher: direkt adminer.php laden ohne Objekt
    return null;
}

// Login-Check überschreiben bevor adminer.php lädt
$_POST['auth'] = [
    'driver'   => 'sqlite',
    'server'   => '',
    'username' => '',
    'password' => '',
    'db'       => '/data/spenden.sqlite',
];

include './adminer.php';
