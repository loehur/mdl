<?php

class Biteship
{
    private $host = "https://api.biteship.com";
    private $key = "biteship_test.eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1lIjoicGVyYWh1MTIzNjU0IiwidXNlcklkIjoiNjU5ZTBiYmYzMDg3NjBkNTg3YzhhZDNjIiwiaWF0IjoxNzA5OTc2MjUwfQ.TlpFxcyW0ftiMyWL2b4KPRrFBUEA-zeq5F0h6QT2dxU";
    //private $key = "biteship_live.eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1lIjoidml0YXBpY3R1cmFfYWJmX2tleWFwaSIsInVzZXJJZCI6IjY1OWUwYmJmMzA4NzYwZDU4N2M4YWQzYyIsImlhdCI6MTcxMDIyNDQ2NH0.KiqfLU-GtU0RTCv-FZ-UglkXfvY3KpsLCqENrvUmoHY";

    function get_area($input)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/v1/maps/areas?countries=ID&input=' . str_replace(" ", "+", $input)  . '&type=single',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => array(),
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->key,
                'content-type: application/json'
            )
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);

        if (isset($res['areas'])) {
            foreach ($res['areas'] as $k => $v) {
                if (strtoupper($v['administrative_division_level_3_name']) <> strtoupper($input)) {
                    unset($res[$k]);
                }
            }
        } else {
            $res['areas'] = [];
        }

        return $res['areas'];
    }

    function get_area_id($id)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->host . '/v1/maps/areas/' . $id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => array(),
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $this->key,
                'content-type: application/json'
            )
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
        if (!isset($res['areas'])) {
            $res['areas'] = [];
        }
        return $res['areas'];
    }

    function cek_ongkir($origin, $dest_id, $dest_lat, $dest_long, $mode = 0)
    {
        $items = [];
        $count = 0;

        $items[0] = [
            "name" => "Kain Laundry",
            "description" => 'Kain Laundry',
            "value" => "500000",
            "length" => 1,
            "width" => 1,
            "height" => 1,
            "weight" => 5000,
            "quantity" => 1
        ];
        $count += 1;


        $curl = curl_init();
        $params = [
            "origin_area_id" => $origin['area_id'],
            "destination_area_id" => $dest_id,
            "origin_latitude" => $origin['latt'],
            "origin_longitude" => $origin['longt'],
            "destination_latitude" => $dest_lat,
            "destination_longitude" => $dest_long,
            "couriers" => "gojek,grab",
            "items" => $items
        ];

        $reques_body = json_encode($params);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => $this->host . '/v1/rates/couriers',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $reques_body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $this->key,
                    'content-type: application/json'
                ]
            ]
        );

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);

        if (isset($res['pricing'])) {
            return $res['pricing'];
        } else {
            return [];
        }
    }

    // function cek_ongkir_cs($dest_id, $dest_lat, $dest_long, $courier)
    // {
    //     $items = [];
    //     $count = 0;
    //     foreach ($_SESSION['cart_cs'] as $c) {
    //         $items[$count] = [
    //             "name" => $c['product'],
    //             "description" => $c['detail'],
    //             "value" => $c['total'],
    //             "length" => $c['length'],
    //             "width" => $c['width'],
    //             "height" => $c['height'],
    //             "weight" => $c['weight'],
    //             "quantity" => $c['qty']
    //         ];
    //         $count += 1;
    //     }

    //     $curl = curl_init();
    //     $params = [
    //         "origin_area_id" => PC::SETTING['origin_id'],
    //         "destination_area_id" => $dest_id,
    //         "origin_latitude" => PC::SETTING['lat'],
    //         "origin_longitude" => PC::SETTING['long'],
    //         "destination_latitude" => $dest_lat,
    //         "destination_longitude" => $dest_long,
    //         "couriers" => $courier,
    //         "items" => $items
    //     ];

    //     $reques_body = json_encode($params);
    //     curl_setopt_array(
    //         $curl,
    //         [
    //             CURLOPT_URL => $this->host . '/v1/rates/couriers',
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'POST',
    //             CURLOPT_POSTFIELDS => $reques_body,
    //             CURLOPT_HTTPHEADER => [
    //                 'Authorization: ' . $this->key,
    //                 'content-type: application/json'
    //             ]
    //         ]
    //     );

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     $res = json_decode($response, true);

    //     if (isset($res['pricing'])) {
    //         return $res['pricing'];
    //     } else {
    //         return $res;
    //     }
    // }

    // function order($p, $method)
    // {
    //     $curl = curl_init();

    //     $count = 0;
    //     foreach ($_SESSION['cart_cs'] as $c) {
    //         $items[$count] = [
    //             "name" => $c['product'],
    //             "description" => $c['detail'],
    //             "value" => $c['total'],
    //             "length" => $c['length'],
    //             "width" => $c['width'],
    //             "height" => $c['height'],
    //             "weight" => $c['weight'],
    //             "quantity" => $c['qty']
    //         ];
    //         $count += 1;
    //     }

    //     $params = [
    //         "origin_contact_name" => PC::SETTING['origin_name'],
    //         "origin_contact_phone" => PC::SETTING['origin_contact_phone'],
    //         "origin_address" => PC::SETTING['origin_address'],
    //         "origin_note" => PC::SETTING['origin_contact_phone'],
    //         "origin_postal_code" => 28111,
    //         "origin_area_id" => PC::SETTING['origin_id'],
    //         "origin_collection_method" => $method,
    //         "origin_coordinate" => [
    //             "latitude" =>  PC::SETTING['lat'],
    //             "longitude" => PC::SETTING['long']
    //         ],
    //         "destination_contact_name" => $p['name'],
    //         "destination_contact_phone" => $p['hp'],
    //         "destination_address" => $p['address'],
    //         "destination_note" => $p['hp'],
    //         "destination_postal_code" => $p['postal_code'],
    //         "destination_area_id " => $p['area_id'],
    //         "destination_coordinate" => [
    //             "latitude" => $p['latt'],
    //             "longitude" => $p['longt']
    //         ],
    //         "courier_company" =>  $p['courier_company'],
    //         "courier_type" =>  $p['courier_type'],
    //         "delivery_type" => "now",
    //         "order_note" => "",
    //         "metadata" => [],
    //         "items" => $items
    //     ];

    //     $reques_body = json_encode($params);
    //     curl_setopt_array(
    //         $curl,
    //         [
    //             CURLOPT_URL => 'https://api.biteship.com/v1/orders',
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'POST',
    //             CURLOPT_POSTFIELDS => $reques_body,
    //             CURLOPT_HTTPHEADER => [
    //                 'Authorization: ' . $this->key,
    //                 'content-type: application/json'
    //             ]
    //         ]
    //     );

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     $res = json_decode($response, true);
    //     return $res;
    // }

    // function tracking($id)
    // {
    //     $curl = curl_init();
    //     $params = [];

    //     $reques_body = json_encode($params);
    //     curl_setopt_array(
    //         $curl,
    //         [
    //             CURLOPT_URL => 'https://api.biteship.com/v1/trackings/' . $id,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'GET',
    //             CURLOPT_POSTFIELDS => $reques_body,
    //             CURLOPT_HTTPHEADER => [
    //                 'Authorization: ' . $this->key,
    //                 'content-type: application/json'
    //             ]
    //         ]
    //     );

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     $res = json_decode($response, true);
    //     return $res;
    // }

    // function tracking_public($waybill, $courier)
    // {
    //     $curl = curl_init();
    //     $params = [];

    //     $reques_body = json_encode($params);
    //     curl_setopt_array(
    //         $curl,
    //         [
    //             CURLOPT_URL => 'https://api.biteship.com/v1/trackings/' . $waybill . '/couriers/' . $courier,
    //             CURLOPT_RETURNTRANSFER => true,
    //             CURLOPT_ENCODING => '',
    //             CURLOPT_MAXREDIRS => 10,
    //             CURLOPT_TIMEOUT => 0,
    //             CURLOPT_FOLLOWLOCATION => true,
    //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //             CURLOPT_CUSTOMREQUEST => 'GET',
    //             CURLOPT_POSTFIELDS => $reques_body,
    //             CURLOPT_HTTPHEADER => [
    //                 'Authorization: ' . $this->key,
    //                 'content-type: application/json'
    //             ]
    //         ]
    //     );

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     $res = json_decode($response, true);
    //     return $res;
    // }
}
