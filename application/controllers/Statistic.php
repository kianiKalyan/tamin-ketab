<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Statistic extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        // check access
        if($this->developer->supplierId > 0)
        {
            if($this->developer->supplierAccess->getStatistic() != 1) redirect($this->config->base_url(), "", 301);
        }

        //
        $this->load->model("TblBook", "", true);
        $this->load->model("TblBookView", "", true);
        $this->load->model("TblBookDownload", "", true);

        $this->load->model("TblSale", "", true);
    }

    // sale
    public function sale()
    {
        $books = null;

        $tblBooks = $this->TblBook->find(array(
            "where" => "developer_id='".$this->developer->developerId."' and status!='0' and id In (Select book_id From tbl_sale)",
            "order" => "title_fa asc",
        ));

        if($tblBooks != null and count($tblBooks) > 0)
        {
            foreach ($tblBooks as $book)
            {
                $books[] = array
                (
                    "id" => $book->getId(),
                    "title" => $book->getTitleFa()
                );
            }
        }

        //
        $data = array
        (
            "pageTitle" => "گزارشات و نمودارها",
            "pageView" => "statistic/sale",
            "pageJsCss" => "statistic-sale",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "books" => $books,
            )
        );
        $this->twig->display("master", $data);
    }

    public function saleList()
    {
        $statisticData = null;
        $errorDate = 0;
        $errorDateBig = 0;
        $where = "";
        $whereBookId = "";

        // get data
        $bookId = $this->general->validateInputData($this->input->post("book"));
        $dateStart = $this->general->validateInputData($this->input->post("date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("date-end"));

        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();

        //
        if($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd)
        {
            $countDays = $this->datetime2->countDaysBetween2Dates($dateTimeStart, $dateTimeEnd);

            //
            if($countDays <= 90)
            {
                // get developer book ids
                $tblBooks = $this->TblBook->find(array(
                    "where" => "developer_id='".$this->developer->developerId."' and status!='0' and id In (Select book_id From tbl_sale)",
                    "order" => "title_fa asc",
                ));
                if($tblBooks != null and count($tblBooks) > 0)
                {
                    foreach ($tblBooks as $book)
                    {
                        $whereBookId .= "book_id='".$book->getId()."' or ";
                    }
                    $whereBookId = rtrim($whereBookId, " or ");
                }

                //
                $where .= ($whereBookId != "") ? (($where != "") ? " and " : "")."($whereBookId)" : "";
                $where .= ($bookId > 0) ? (($where != "") ? " and " : "")."(book_id='$bookId')" : "";
                $where .= (($where != "") ? " and " : "")."datetime >= $dateTimeStart and datetime <= $dateTimeEnd";

                // list
                $tblSales = $this->TblSale->find(array("where" => "$where", "order" => "datetime desc", "limit" => array("value" => 100, "offset" => 0)));
                if($tblSales != null and count($tblSales) > 0)
                {
                    foreach ($tblSales as $sale)
                    {
                        $book = $sale->getBook();

                        //
                        $statisticData["list"][] = array
                        (
                            "title" => ($book != null) ? $book->getTitleFa() : "",
                            "token" => $sale->getToken(),
                            "price" => $sale->getPrice(),
                            "date" => $this->datetime2->getJalaliDate($sale->getDatetime(), "Y/m/d")
                        );
                    }
                }

                // chart
                for($i = 0; $i <= $countDays; $i++)
                {
                    $temp_dateTimeStart = $this->datetime2->datetimeAfterNDays($dateTimeStart, $i);
                    $temp_dateTimeEnd = $this->datetime2->shamsiToDatetimeSecond($this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"), "23:59:59");
                    $price = 0;

                    $tblSales = $this->TblSale->find(array("where" => "$where and (datetime >= $temp_dateTimeStart and datetime <= $temp_dateTimeEnd)"));
                    if($tblSales != null and count($tblSales) > 0)
                    {
                        foreach ($tblSales as $sale)
                        {
                            $price += $sale->getPrice();
                        }
                    }

                    $statisticData["chart"][] = array
                    (
                        "day" => $this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"),
                        "price" => $price,
                    );
                }
            }
            else
            {
                $errorDateBig = 1;
            }
        }
        else
        {
            $errorDate = 1;
        }

        //
        echo json_encode
        (
            array("data" => $statisticData, "errorDateBig" => $errorDateBig, "errorDate" => $errorDate)
        );
    }

    // download
    public function download()
    {
        //
        $data = array
        (
            "pageTitle" => "گزارشات و نمودارها",
            "pageView" => "statistic/download",
            "pageJsCss" => "statistic-download",
            "showPageTitle" => true,
            "pageContent" => array()
        );
        $this->twig->display("master", $data);
    }

    public function downloadList()
    {
        $statisticData = null;
        $statisticDownloadData = null;
        $statisticViewData = null;
        $errorDate = 0;
        $total_countDownload = 0;
        $total_countView = 0;

        // get data
        $dateStart = $this->general->validateInputData($this->input->post("date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("date-end"));

        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();

        //
        if($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd)
        {
            $tblBooks = $this->TblBook->find(array(
                "where" => "developer_id='".$this->developer->developerId."' and status!='0'",
                "order" => "title_fa asc",
            ));
            if($tblBooks != null and count($tblBooks) > 0)
            {
                foreach ($tblBooks as $book)
                {
                    $bookId = $book->getId();

                    $countDownload = $this->TblBookDownload->count("book_id='$bookId' and datetime >= $dateTimeStart and datetime <= $dateTimeEnd");
                    $countView = $this->TblBookView->count("book_id='$bookId' and datetime >= $dateTimeStart and datetime <= $dateTimeEnd");

                    $statisticData[] = array
                    (
                        "title" => $book->getTitleFa(),
                        "count_download" => $countDownload,
                        "count_view" => $countView
                    );

                    $total_countDownload += $countDownload;
                    $total_countView += $countView;
                }
            }

            //
            if($statisticData != null)
            {
                $temp_statisticDownloadData = $this->general->arraySort($statisticData, "count_download");
                for ($i = count($temp_statisticDownloadData) - 1; $i >= 0; $i--)
                {
                    $statisticDownloadData[] = array
                    (
                        "title" => $temp_statisticDownloadData[$i]["title"],
                        "count_download" => $temp_statisticDownloadData[$i]["count_download"],
                        "percent" => ($temp_statisticDownloadData[$i]["count_download"] > 0) ? round(($temp_statisticDownloadData[$i]["count_download"] / $total_countDownload) * 100, 2) : 0
                    );
                }
            }

            //
            if($statisticData != null)
            {
                $temp_statisticViewData = $this->general->arraySort($statisticData, "count_view");
                for ($i = count($temp_statisticViewData) - 1; $i >= 0; $i--)
                {
                    $statisticViewData[] = array
                    (
                        "title" => $temp_statisticViewData[$i]["title"],
                        "count_view" => $temp_statisticViewData[$i]["count_view"],
                        "percent" => ($temp_statisticViewData[$i]["count_view"] > 0) ? round(($temp_statisticViewData[$i]["count_view"] / $total_countView) * 100, 2) : 0
                    );
                }
            }

            //
            $statisticData = array
            (
                "list" => $statisticData,
                "download" => $statisticDownloadData,
                "view" => $statisticViewData
            );
        }
        else
        {
            $errorDate = 1;
        }

        //
        echo json_encode
        (
            array("data" => $statisticData, "errorDate" => $errorDate)
        );
    }

    // mobile
    public function mobile()
    {
        $books = null;

        //
        $tblBooks = $this->TblBook->find(array(
            "where" => "developer_id='".$this->developer->developerId."' and status!='0'",
            "order" => "title_fa asc",
        ));
        if($tblBooks != null and count($tblBooks) > 0)
        {
            foreach ($tblBooks as $book)
            {
                $books[] = array
                (
                    "id" => $book->getId(),
                    "title" => $book->getTitleFa()
                );
            }
        }

        //
        $data = array
        (
            "pageTitle" => "گزارشات و نمودارها",
            "pageView" => "statistic/mobile",
            "pageJsCss" => "statistic-mobile",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "books" => $books
            )
        );
        $this->twig->display("master", $data);
    }

    public function mobileList()
    {
        $statisticData = null;
        $errorDate = 0;
        $where = "";
        $whereBookId = "";

        // get data
        $bookId = $this->general->validateInputData($this->input->post("book"));
        $dateStart = $this->general->validateInputData($this->input->post("date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("date-end"));

        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();

        //
        if($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd)
        {
            // get developer book ids
            $tblBooks = $this->TblBook->find(array(
                "where" => "developer_id='".$this->developer->developerId."' and status!='0'",
            ));
            if($tblBooks != null and count($tblBooks) > 0)
            {
                foreach ($tblBooks as $book)
                {
                    $whereBookId .= "book_id='".$book->getId()."' or ";
                }
            }
            $whereBookId = ($whereBookId != "") ? rtrim($whereBookId, " or ") : "";

            //
            $where .= ($whereBookId != "") ? "($whereBookId)" : "";
            $where .= ($bookId > 0) ? (($where != "") ? " and " : "")."(book_id='$bookId')" : "";
            $where .= (($where != "") ? " and " : "")."datetime >= $dateTimeStart and datetime <= $dateTimeEnd";

            // api_level
            $tblBookView_apiLevels_totalCount = $this->TblBookView->count("$where");
            $tblBookView_apiLevels = $this->TblBookView->find(array("where" => "$where", "group" => "api_level"));
            if($tblBookView_apiLevels != null and count($tblBookView_apiLevels) > 0)
            {
                foreach ($tblBookView_apiLevels as $apiLevel)
                {
                    $apiLevelTitle = $apiLevel->getApilevel();
                    $total = $this->TblBookView->count("api_level='$apiLevelTitle'".(($where != "") ? "and ($where)" : ""));

                    //
                    $statisticData["api_level"][] = array
                    (
                        "title" => $apiLevel->getApilevel(),
                        "count" => $total,
                        "percent" => ($total > 0) ? round(($total / $tblBookView_apiLevels_totalCount) * 100, 2) : 0,
                    );
                }
            }

            // mobile_brand
            $tblBookView_mobileBrands_totalCount = $this->TblBookView->count("$where");
            $tblBookView_mobileBrands = $this->TblBookView->find(array("where" => "$where", "group" => "mobile_brand"));
            if($tblBookView_mobileBrands != null and count($tblBookView_mobileBrands) > 0)
            {
                foreach ($tblBookView_mobileBrands as $mobileBrand)
                {
                    $mobileBrandTitle = $mobileBrand->getMobileBrand();
                    $total = $this->TblBookView->count("mobile_brand='$mobileBrandTitle'".(($where != "") ? "and ($where)" : ""));

                    //
                    $statisticData["mobile_brand"][] = array
                    (
                        "title" => $mobileBrand->getMobileBrand(),
                        "count" => $total,
                        "percent" => ($total > 0) ? round(($total / $tblBookView_mobileBrands_totalCount) * 100, 2) : 0,
                    );
                }
            }

            // mobile_model
            $tblBookView_mobileModels_totalCount = $this->TblBookView->count("$where");
            $tblBookView_mobileModels = $this->TblBookView->find(array("where" => "$where", "group" => "mobile_model"));
            if($tblBookView_mobileModels != null and count($tblBookView_mobileModels) > 0)
            {
                foreach ($tblBookView_mobileModels as $mobileModel)
                {
                    $mobileModelTitle = $mobileModel->getMobileModel();
                    $total = $this->TblBookView->count("mobile_model='$mobileModelTitle'".(($where != "") ? "and ($where)" : ""));

                    //
                    $statisticData["mobile_model"][] = array
                    (
                        "title" => $mobileModel->getMobileModel(),
                        "count" => $total,
                        "percent" => ($total > 0) ? round(($total / $tblBookView_mobileModels_totalCount) * 100, 2) : 0,
                    );
                }
            }
        }
        else
        {
            $errorDate = 1;
        }

        //
        echo json_encode
        (
            array("data" => $statisticData, "errorDate" => $errorDate)
        );
    }
}
