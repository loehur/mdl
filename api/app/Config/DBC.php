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
                "db" => "u123456_main", 
                "user" => "u123456_user",
                "pass" => "password_prod"
            ],
            1 => [
                "db" => "u123456_laundry",
                "user" => "u123456_user",
                "pass" => "password_prod"
            ],
            2 => [
                "db" => "u123456_sale",
                "user" => "u123456_user",
                "pass" => "password_prod"
            ],
            3 => [
                "db" => "u123456_resto",
                "user" => "u123456_user",
                "pass" => "password_prod"
            ],
            4 => [
                "db" => "u123456_depot",
                "user" => "u123456_user",
                "pass" => "password_prod"
            ],
            5 => [
                "db" => "u123456_salon",
                "user" => "u123456_user",
                "pass" => "password_prod"
            ]
        ]
    ];
}
