<?php

class Place
{
    public $key = "gwfctr54EwUPf8";
    public $host = "https://api.mdl.my.id/";

    public function provinsi()
    {
        $url = $this->host . 'Wilayah/provinsi/' . $this->key;
        return $this->curl_get($url);
    }

    public function kota($id)
    {
        $url = $this->host . 'Wilayah/kota/' . $this->key;

        $post = [
            'id' => $id,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);

        curl_close($ch);
        $res = json_decode($response, true);
        return $res;
    }

    public function kecamatan($id)
    {
        $url = $this->host . 'Wilayah/kecamatan/' . $this->key;

        $post = [
            'id' => $id,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);

        curl_close($ch);
        $res = json_decode($response, true);
        return $res;
    }

    function curl_get($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
        return $res;
    }
}
