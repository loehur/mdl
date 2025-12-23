<?php

class Get extends Controller
{
   public function wa_nota($ref)
   {
      echo $this->helper('WAGenerator')->get_nota($ref);
   }

   public function wa_selesai($penjualan)
   {
      echo $this->helper('WAGenerator')->get_selesai($penjualan);
   }
}
