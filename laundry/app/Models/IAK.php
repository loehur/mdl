<?php

class IAK extends URL
{
    public $ik_username, $ik_apiKey, $ik_prepaid_url, $ik_postpaid_url;

    public function __construct()
    {
        $this->ik_username = $this->dec_2("QTBc9AMLsNbRuyZH");
        $this->ik_apiKey = $this->dec_2("RjBY81EE5NfZvy5HYgvhSluatw==");
        $this->ik_prepaid_url = "https://" . $this->dec_2("AXoItlRa5MGA6X1ZO18=") . "/";
        $this->ik_postpaid_url = "https://" . $this->dec_2("HGcPr1lW8JqF+3dZPF6i") . "/";
    }


    function post_cek($ref_id)
    {
        $sign = md5($this->ik_username . $this->ik_apiKey . "cs");
        $url = $this->ik_postpaid_url . 'api/v1/bill/check';
        $data = [
            "commands" => "checkstatus",
            "username" => $this->ik_username,
            "ref_id"     => $ref_id,
            "sign" => $sign,
        ];

        $postdata = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, JSON_PRESERVE_ZERO_FRACTION);
        return $response;
    }

    public function post_inquiry($code, $customer_id, $id_cabang)
    {

        $ref_id = "mdlpost-" . date('YmdHis') . "-" . $id_cabang;
        $sign = md5($this->ik_username . $this->ik_apiKey . $ref_id);
        $url = $this->ik_postpaid_url . 'api/v1/bill/check';

        $data = [
            "commands" => "inq-pasca",
            "username" => $this->ik_username,
            "code" => $code,
            "hp" => $customer_id,
            "ref_id" => $ref_id,
            "sign" => $sign,
        ];


        $postdata = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, JSON_PRESERVE_ZERO_FRACTION);
        return $response;
    }

    function post_pay($a)
    {
        $tr_id = $a['tr_id'];

        $sign = md5($this->ik_username . $this->ik_apiKey . $tr_id);
        $url = $this->ik_postpaid_url . 'api/v1/bill/check';
        $data = [
            "commands" => "pay-pasca",
            "username" => $this->ik_username,
            "tr_id"    => $tr_id,
            "sign" => $sign,
        ];

        $postdata = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, JSON_PRESERVE_ZERO_FRACTION);
        return $response;
    }

    function pre_pay($ref_id, $customer_id, $product_code)
    {
        //EKSEKUSI TOPUP
        $sign = md5($this->ik_username . $this->ik_apiKey . $ref_id);
        $url = $this->ik_prepaid_url . 'api/top-up';
        $data = [
            "username" => $this->ik_username,
            "ref_id"     => $ref_id,
            "customer_id" => $customer_id,
            "product_code"  => $product_code,
            "sign" => $sign,
        ];

        $postdata = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, JSON_PRESERVE_ZERO_FRACTION);
        return $response;
    }

    public function pre_cek($ref_id)
    {
        $sign = md5($this->ik_username . $this->ik_apiKey . $ref_id);
        $url = $this->ik_prepaid_url . 'api/check-status';
        $data = [
            "username" => $this->ik_username,
            "ref_id"     => $ref_id,
            "sign" => $sign,
        ];

        $postdata = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, JSON_PRESERVE_ZERO_FRACTION);
        return $response;
    }

    function dec_2($encryption)
    {
        $ciphering = "AES-128-CTR";
        $options = 0;

        $decryption_iv = '1234567891011121';
        $decryption_key = "j499uL0v3ly&N3lyL0vEly_F0r3ver";

        $decryption = openssl_decrypt(
            $encryption,
            $ciphering,
            $decryption_key,
            $options,
            $decryption_iv
        );

        return $decryption;
    }
}
