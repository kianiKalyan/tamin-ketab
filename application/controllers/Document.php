<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Document extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        //
        $this->load->model("TblDocument", "", true);
        $this->load->model("TblDocumentGroup", "", true);
    }

    public function index($id = 0)
    {
        // document menu
        $documentMenu = null;
        $tblDocuments = $this->TblDocument->find(array("where" => "document_group_id='0' and status='1'"));
        $tblDocumentGroups = $this->TblDocumentGroup->find(array("where" => "status='1'", "order" => "title_fa asc"));

        //
        if($tblDocuments != null and count($tblDocuments) > 0)
        {
            foreach ($tblDocuments as $doc)
            {
                //$doc = new TblDocument();
                $documentMenu[] = array
                (
                    "id" => $doc->getId(),
                    "title" => $this->general->htmlSpecialCharsDecode($doc->getTitleFa()),
                    "sub_menu" => null
                );
            }
        }
        //
        if($tblDocumentGroups != null and count($tblDocumentGroups) > 0)
        {
            foreach ($tblDocumentGroups as $docGroup)
            {
                //$docGroup = new TblDocumentGroup();
                $tmpDocumentMenu = null;
                $docGroupId = $docGroup->getId();
                $tblDocuments = $this->TblDocument->find(array("where" => "document_group_id='$docGroupId' and status='1'"));

                //
                if($tblDocuments != null and count($tblDocuments) > 0)
                {
                    foreach ($tblDocuments as $doc)
                    {
                        //$doc = new TblDocument();
                        $tmpDocumentMenu[] = array
                        (
                            "id" => $doc->getId(),
                            "title" => $this->general->htmlSpecialCharsDecode($doc->getTitleFa()),
                            "sub_menu" => null
                        );
                    }

                    //
                    $documentMenu[] = array
                    (
                        "id" => $docGroup->getId(),
                        "title" => $this->general->htmlSpecialCharsDecode($docGroup->getTitleFa()),
                        "sub_menu" => $tmpDocumentMenu
                    );
                }
            }
        }
        $documentMenu = $this->general->arraySort($documentMenu, array("title"));

        // document text
        $tblDocument = $this->TblDocument->findFirst(array("where" => "id='$id'"));
        $tblDocument = ($tblDocument == null) ? $this->TblDocument->findFirst(array("where" => "status='1'", "order" => "id asc")) : $tblDocument;

        //
        $data = array
        (
            "pageTitle" => "مستندات",
            "pageView" => "document/index",
            "pageJsCss" => "document-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "currentDocumentId" => $id,
                "documentMenu" => $documentMenu,
                "title" => $this->general->htmlSpecialCharsDecode($tblDocument->getTitleFa()),
                "text" => $this->general->htmlSpecialCharsDecode($tblDocument->getDesFa())
            )
        );
        $this->twig->display("master", $data);
    }
}