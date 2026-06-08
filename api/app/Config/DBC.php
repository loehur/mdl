<?php

class DBC
{
    const db_host = \Env::DB_HOST ?? 'localhost';
    const dbm = \Env::DB_CREDENTIALS ?? [
        'dev' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_water", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_salon", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_investasi", "user" => "root", "pass" => ""]
        ],
        'pro' => [
            0 => ["db" => "mdl_main", "user" => "mdl_main", "pass" => "wB5KjfjRYfPXBtFF"],
            1 => ["db" => "mdl_laundry", "user" => "mdl_laundry", "pass" => "3p66WMjmPa6AmidN"],
            2 => ["db" => "mdl_resto", "user" => "mdl_resto", "pass" => "BY4PRtSDysp8Akfz"],
            3 => ["db" => "mdl_water", "user" => "mdl_water", "pass" => "csFW7fjxxTXB7ryR"],
            4 => ["db" => "mdl_salon", "user" => "mdl_salon", "pass" => "W6FLRYyeKFZdTpHC"],
            5 => ["db" => "mdl_investasi", "user" => "mdl_investasi", "pass" => "CHANGE_ME_INVESTASI"]
        ]
    ];
}
