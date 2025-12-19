<?php

class DBC
{
    const db_host = 'localhost';

    const dbm = [
        // DEVELOPMENT DATABASES (MODE = 'dev')
        'dev' => [
            0 => [
                "db" => "mdl_main", 
                "user" => "root",   
                "pass" => ""        
            ],
            1 => [
                "db" => "mdl_laundry",
                "user" => "root",     
                "pass" => ""
            ],
            2 => [
                "db" => "mdl_sale",   
                "user" => "root",     
                "pass" => ""
            ],
            3 => [
                "db" => "mdl_resto",  
                "user" => "root",     
                "pass" => ""
            ],
            4 => [
                "db" => "mdl_depot",
                "user" => "root",
                "pass" => ""
            ],
            5 => [
                "db" => "mdl_salon",
                "user" => "root",
                "pass" => ""
            ]
        ],
        // PRODUCTION DATABASES (MODE = 'pro')
        'pro' => [
            0 => [
                "db" => "mdl_main", 
                "user" => "mdl_main",
                "pass" => "wB5KjfjRYfPXBtFF"
            ],
            1 => [
                "db" => "mdl_laundry",
                "user" => "mdl_laundry",
                "pass" => "3p66WMjmPa6AmidN"
            ],
            2 => [
                "db" => "mdl_sale",
                "user" => "mdl_sale",
                "pass" => ""
            ],
            3 => [
                "db" => "mdl_resto",
                "user" => "mdl_resto",
                "pass" => ""
            ],
            4 => [
                "db" => "mdl_depot",
                "user" => "mdl_depot",
                "pass" => ""
            ],
            5 => [
                "db" => "mdl_salon",
                "user" => "mdl_salon",
                "pass" => "W6FLRYyeKFZdTpHC"
            ]
        ]
    ];
}
