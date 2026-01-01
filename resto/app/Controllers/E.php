<?php

class E extends Controller
{
   public function e($e)
   {
      $this->view(__CLASS__ . "/" . $e);
   }

   function e_page($e)
   {
      $this->view('layout', ["title" => "Page Soon"]);
      $this->view(__CLASS__ . "/" . $e);
   }
}
