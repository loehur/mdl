<?php

class Time extends Controller
{
   function get($mode)
   {
      echo date($mode);
   }
}
