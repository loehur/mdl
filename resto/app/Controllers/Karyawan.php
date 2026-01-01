<?php

class Karyawan extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index($en)
   {
      $aktif = [
         1 => "Aktif",
         0 => "Non Aktif"
      ];

      $layout = ['title' => "Karyawan " . $aktif[$en]];
      $where = $this->wCabang . " AND en = " . $en;
      $data = $this->db(0)->get_where("user", $where);
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   function insert() {}

   function updateCell() {}
}
