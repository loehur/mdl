<?php

class DBC
{
    const db_host = \Env::DB_HOST ?? 'localhost';
    const dbm = \Env::DB_CREDENTIALS ?? [
        'dev' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ],
        'pro' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ]
    ];
}