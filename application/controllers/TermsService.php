<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class TermsService extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        //
        $this->load->model("TblTermsService", "", true);
    }

    public function index()
    {
        $tblTermsService = $this->TblTermsService->findFirst();

        //
        $data = array
        (
            "pageTitle" => "شرایط استفاده از خدمات",
            "pageView" => "terms-service/index",
            "pageJsCss" => "terms-service-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "title" => "شرایط استفاده از خدمات",
                "text" => $this->general->htmlSpecialCharsDecode($tblTermsService->getDesFa())
            )
        );
        $this->twig->display("master", $data);
    }
}
