<?php

class Load extends Controller
{
   public function spinner($tipe)
   {
      $this->view(__CLASS__ . "/spinner_" . $tipe);
   }
}
