<?php

class Get extends Controller
{
   public function wa_nota($ref)
   {
      echo $this->helper('WAGenerator')->get_nota($ref);
   }
}
