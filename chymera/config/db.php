<?php

$tier = "DEV"; // Default tier

if(strcmp($tier, "DEV") == 0) {
    $DB_HOST = "fsitgl-mysql04d.ncifcrf.gov";
    $DB_NAME = "Chymera";
    $DB_USER = "chymera";
    $DB_PASS = '7ItEDO4af4RI71pOT5jOyanODIRi1i';
}
else if(strcmp($tier, "TEST") == 0) {
    
    $DB_HOST = "fsitgl-mysql04d.ncifcrf.gov";
    $DB_NAME = "Chymera";
    $DB_USER = "chymera";
    $DB_PASS = '';
}
else if(strcmp($tier, "PROD") == 0) {
    
    $DB_HOST = "fsitgl-mysql04p.ncifcrf.gov";
    $DB_NAME = "Chymera";
    $DB_USER = "chymera";
    $DB_PASS = '';
}
