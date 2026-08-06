<?php

class Delivery extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Delivery Order'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('delivery/index', ['data_operasi' => $data_operasi]);
   }
}
