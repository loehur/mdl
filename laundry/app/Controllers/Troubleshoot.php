<?php

class Troubleshoot extends Controller
{
   function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   function index($cetak = [])
   {
      $data_operasi = ['title' => __CLASS__];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view(__CLASS__ . '/content');
   }
}
