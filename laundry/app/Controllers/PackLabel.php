<?php

class PackLabel extends Controller
{
   function __construct()
   {
      $this->session_cek();
   }

   /** Pack Label digabung ke Operan — redirect bookmark lama */
   function index()
   {
      header('Location: ' . URL::BASE_URL . 'Operan');
      exit;
   }

   function __call($name, $arguments)
   {
      header('Location: ' . URL::BASE_URL . 'Operan');
      exit;
   }
}
