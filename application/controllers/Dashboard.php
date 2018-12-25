<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        //
        $this->load->model("TblBook", "", true);
        $this->load->model("TblBookDownload", "", true);

        $this->load->model("TblSettlement", "", true);

        $this->load->model("TblSignboard", "", true);

        $this->load->model("TblComment", "", true);

        $this->load->model("TblSale", "", true);
    }

    public function index()
	{
        $whereBookId = "";

        // get developer app ids
        $tblBooks = $this->TblBook->find(array(
            "where" => "developer_id='".$this->developer->developerId."' and status!='0'",
        ));

        if($tblBooks != null and count($tblBooks) > 0)
        {
            foreach ($tblBooks as $Book)
            {
                $whereBookId .= "Book_id='".$Book->getId()."' or ";
            }
        }
        $whereBookId = rtrim($whereBookId, " or ");

        //
        $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
        $priceForSettlement = ($user != null) ? $user->getPriceSettlement() : 0;

        //
        $totalDeposited = 0;
        $tblSettlements = $this->TblSettlement->find(array("where" => "user_id='".$this->developer->developerId."' and status='1'"));
        if($tblSettlements != null and count($tblSettlements) > 0)
        {
            foreach ($tblSettlements as $settlement)
            {
                $totalDeposited += $settlement->getPrice();
            }
        }

        //
        $signboardsData = null;
        $tblSignboards = $this->TblSignboard->find(array("where" => "status='1'", "order" => "datetime desc", "limit" => array("value" => 3, "offset" => 0)));
        if($tblSignboards != null and count($tblSignboards) > 0)
        {
            foreach($tblSignboards as $signboard)
            {
                $signboardsData[] = array
                (
                    "title" => $this->general->htmlSpecialCharsDecode($signboard->getTitleFa()),
                    "date" => $this->datetime2->getJalaliDate($signboard->getDatetime(), "Y/m/d")
                );
            }
        }

        //
        $commentsData = null;
        if($whereBookId != "")
        {
            $tblComments = $this->TblComment->find(array(
                "where" => "($whereBookId) and status='1'",
                "order" => "datetime desc",
                "limit" => array("value" => 3, "offset" => 0),
            ));
            if($tblComments != null and count($tblComments) > 0)
            {
                foreach($tblComments as $comment)
                {
                    $commentsData[] = array
                    (
                        "des" => $this->general->htmlSpecialCharsDecode($comment->getDes()),
                        "date" => $this->datetime2->getJalaliDate($comment->getDatetime(), "Y/m/d")
                    );
                }
            }
        }

        //
        $saleData = null;
        if($whereBookId != "")
        {
            $tblSales = $this->TblSale->find(array("where" => "$whereBookId", "order" => "datetime desc", "limit" => array("value" => 3, "offset" => 0)));
            if($tblSales != null and count($tblSales) > 0)
            {
                foreach ($tblSales as $sale)
                {
                    $book = $sale->getBook();

                    //
                    $saleData[] = array
                    (
                        "title" => ($book != null) ? $book->getTitleFa() : "",
                        "date" => $this->datetime2->getJalaliDate($sale->getDatetime(), "Y/m/d")
                    );
                }
            }
        }

        //
        $saleChart = null;
        {
            $dateTimeStart = $this->datetime2->datetimeBeforeNDays(time(), 6);

            for($i = 0; $i < 7; $i++)
            {
                $temp_dateTimeStart = $this->datetime2->datetimeAfterNDays($dateTimeStart, $i);
                $temp_dateTimeEnd = $this->datetime2->shamsiToDatetimeSecond($this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"), "23:59:59");
                $price = 0;

                if($whereBookId != "")
                {
                    $tblSales = $this->TblSale->find(array("where" => "($whereBookId) and (datetime >= $temp_dateTimeStart and datetime <= $temp_dateTimeEnd)"));
                    if($tblSales != null and count($tblSales) > 0)
                    {
                        foreach ($tblSales as $sale)
                        {
                            $price += $sale->getPrice();
                        }
                    }
                }

                $saleChart[] = array
                (
                    "day" => $this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"),
                    "price" => $price,
                );
            }
        }

        //
        $downloadChart = null;
        {
            $dateTimeStart = $this->datetime2->datetimeBeforeNDays(time(), 6);

            for($i = 0; $i < 7; $i++)
            {
                $temp_dateTimeStart = $this->datetime2->datetimeAfterNDays($dateTimeStart, $i);
                $temp_dateTimeEnd = $this->datetime2->shamsiToDatetimeSecond($this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"), "23:59:59");

                if($whereBookId != "")
                    $count = $this->TblBookDownload->count("($whereBookId) and (datetime >= $temp_dateTimeStart and datetime <= $temp_dateTimeEnd)");
                else
                    $count = 0;

                $downloadChart[] = array
                (
                    "day" => $this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"),
                    "count" => $count,
                );
            }
        }

        //
        $data = array
        (
            "pageTitle" => "داشبورد",
            "pageView" => "dashboard/index",
            "pageJsCss" => "dashboard",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "priceForSettlement" => $this->general->priceFormat($priceForSettlement),
                "totalDeposited" => $this->general->priceFormat($totalDeposited),
                "signboardsData" => $signboardsData,
                "commentsData" => $commentsData,
                "saleData" => $saleData,
                "saleChart" => $saleChart,
                "downloadChart" => $downloadChart,
            )
        );
        $this->twig->display("master", $data);
	}
}
