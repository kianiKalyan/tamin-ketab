<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Support extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        //
        $this->load->model("TblSignboard", "", true);
        $this->load->model("TblSignboardGroup", "", true);

        $this->load->model("TblFaq", "", true);
        $this->load->model("TblFaqSubject", "", true);

        $this->load->model("TblBook", "", true);

        $this->load->model("TblSupport", "", true);
    }

    public function index()
    {
        $whereAppIdAccess = "";

        if($this->developer->supplierId > 0)
        {
            if($this->developer->supplierAccessBook != null)
            {
                foreach ($this->developer->supplierAccessBook as $accessAppId)
                {
                    if($accessAppId->getTypeAccess() == "support")
                    {
                        $accessAppId = $accessAppId->getBookId();
                        $whereAppIdAccess .= "id='$accessAppId' or ";
                    }
                }
            }
        }
        $whereAppIdAccess = ($whereAppIdAccess != "") ? "and (".trim($whereAppIdAccess, " or ").")" : "";

        //
        $faqs = $this->TblFaq->find(array("order" => "title_fa asc"));
        $faqSubjects = $this->TblFaqSubject->find(array("where" => "status='1'", "order" => "title_fa asc"));
        $books = $this->TblBook->find(array(
            "where" => "developer_id='".$this->developer->developerId."' $whereAppIdAccess",
            "order" => "title_fa asc",
        ));

        //
        $data = array
        (
            "pageTitle" => "تیکت و پشتیبانی",
            "pageView" => "support/index",
            "pageJsCss" => "support-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "title" => "تیکت و پشتیبانی",
                "faqs" => $faqs,
                "faqSubjects" => $faqSubjects,
                "books" => $books
            )
        );
        $this->twig->display("master", $data);
    }

    public function ticketSave()
    {
        $result = 0;
        $errorSubject = 0;
        $errorText = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $appId = $this->general->validateInputData($this->input->post("form-support-ticket-app"));
            $subject = $this->general->validateInputData($this->input->post("form-support-ticket-subject"));
            $text = $this->general->validateInputData($this->input->post("form-support-ticket-text"));

            // check app id access
            $flagAccessAppId = false;
            if($this->developer->supplierId > 0)
            {
                if($this->developer->supplierAccessBook != null)
                {
                    foreach ($this->developer->supplierAccessBook as $accessAppId)
                    {
                        if($accessAppId->getTypeAccess() == "support")
                        {
                            if($accessAppId->getBookId() == $appId) { $flagAccessAppId = true; break; }
                        }
                    }
                }
            }
            else
            {
                $flagAccessAppId = true;
            }

            //
            if($this->general->stringLen($subject) >= 5 and $this->general->stringLen($text) >= 10 and $flagAccessAppId)
            {
                // save
                $support = new TblSupport();
                $support->setUserId($this->developer->developerId);
                $support->setBookId($appId);
                $support->setSubject($subject);
                $support->setDes($text);
                $support->setStatus(0);
                $support->setDatetime(time());
                $resultSave = $support->save();

                if($resultSave)
                {
                    $result = 1;
                }
            }
            else
            {
                if(!($this->general->stringLen($subject) >= 5)) $errorSubject = 1;
                if(!($this->general->stringLen($text) >= 10)) $errorText = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorSubject" => $errorSubject,
            "errorText" => $errorText
        ));
    }

    public function signboard()
    {
        //
        $data = array
        (
            "pageTitle" => "اطلاعیه ها و پیام ها",
            "pageView" => "support/signboard",
            "pageJsCss" => "support-signboard",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "title" => "اطلاعیه ها و پیام ها"
            )
        );
        $this->twig->display("master", $data);
    }

    public function signboardList()
    {
        $signboardsData = null;

        // get data
        $pageNumber = $this->general->validateInputData($this->input->post("page-number"));
        $pageNumber = (is_numeric($pageNumber) and $pageNumber > 0) ? $pageNumber : 1;

        $offset = ($pageNumber - 1) * $this->general->rowInFind;

        //
        $tblSignboards = $this->TblSignboard->find(array("where" => "status='1'", "order" => "datetime asc", "limit" => array("value" => $this->general->rowInFind, "offset" => $offset)));
        if($tblSignboards != null and count($tblSignboards) > 0)
        {
            foreach($tblSignboards as $signboard)
            {
                $signboardGroupId = $signboard->getSignboardGroupId();
                $signboardGroup = $this->TblSignboardGroup->findFirst(array("where" => "id='$signboardGroupId'"));
                $signboardGroupTitle = ($signboardGroup != null) ? $signboardGroup->getTitleFa() : "";

                //
                $signboardsData[] = array
                (
                    "id" => $signboard->getId(),
                    "title" => $this->general->htmlSpecialCharsDecode($signboard->getTitleFa()),
                    "des" => $this->general->htmlSpecialCharsDecode($signboard->getDesFa()),
                    "group" => $signboardGroupTitle,
                    "date" => $this->datetime2->getJalaliDate($signboard->getDatetime(), "Y/m/d")
                );
            }
        }

        // calculate total page number
        $signboardsCount = $this->TblSignboard->count();
        if($signboardsCount > 0) $totalPageNumber = ceil($signboardsCount / $this->general->rowInFind); else $totalPageNumber = 0;

        //
        echo json_encode
        (
            array("data" => $signboardsData, "total_page_number" => $totalPageNumber, "page_number" => $pageNumber)
        );
    }
}