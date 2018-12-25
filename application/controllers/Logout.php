<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Logout extends MY_Controller
{
    public function exitUser()
    {
        //$this->session->userdata("UserID");
        $this->session->unset_userdata("UserID");
        $this->session->sess_destroy();

        redirect($this->config->item("main_url"), "", 301);
    }
}
