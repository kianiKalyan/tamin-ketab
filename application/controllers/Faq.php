<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Faq extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        //
        $this->load->model("TblFaq", "", true);
        $this->load->model("TblFaqSubject", "", true);
    }

    public function index()
    {
        $faqs = $this->TblFaq->find(array("order" => "title_fa asc"));
        $faqSubjects = $this->TblFaqSubject->find(array("where" => "status='1'", "order" => "title_fa asc"));

        //
        $data = array
        (
            "pageTitle" => "سوالات متداول",
            "pageView" => "faq/index",
            "pageJsCss" => "faq-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "title" => "سوالات متداول",
                "faqs" => $faqs,
                "faqSubjects" => $faqSubjects
            )
        );
        $this->twig->display("master", $data);
    }
}