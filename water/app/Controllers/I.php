<?php

class I extends Controller
{
   function q() //gambar qris
   {
      echo "<img style='display: block; margin-left: auto; margin-right: auto; margin-top:30px; max-width:100vw; max-height:100vh' src='" . URL::ASSET_URL . "img/pmw_qris.jpeg'>";
   }
}
