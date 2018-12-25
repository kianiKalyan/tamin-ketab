<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Financial extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $this->developer->checkLogin();

        // check access
        if($this->developer->supplierId > 0)
        {
            if($this->developer->supplierAccess->getFinancial() != 1) redirect($this->config->base_url(), "", 301);
        }

        //
        $this->load->model("TblClient", "", true);

        $this->load->model("TblBook", "", true);

        $this->load->model("TblSale", "", true);

        $this->load->model("TblBankAccount", "", true);

        $this->load->model("TblSettlement", "", true);
    }

    //
    public function settlement()
    {
        $settlementsData = null;

        //
        $userBankAccount = $this->TblBankAccount->findFirst(array("where" => "user_id='".$this->developer->developerId."'"));
        $shaba = ($userBankAccount != null) ? $userBankAccount->getShabaNumber() : "";

        //
        $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
        $settlementInMonth = ($user != null) ? $user->getSettlementEndMonth() : 0;
        $priceForSettlement = ($user != null) ? $user->getPriceSettlement() : 0;

        //
        $totalDeposited = 0;
        $tblSettlements = $this->TblSettlement->find(array("where" => "user_id='".$this->developer->developerId."' and status='1'"));
        if($tblSettlements != null and count($tblSettlements) > 0)
        {
            foreach ($tblSettlements as $settlement)
            {
                $totalDeposited += $settlement->getPrice();

                $settlementsData[] = array
                (
                    "price" => $this->general->priceFormat($settlement->getPrice()),
                    "datetime_request" => $this->datetime2->getJalaliDate($settlement->getDatetimeRequest(), "Y/m/d"),
                    "datetime_settlement" => $this->datetime2->getJalaliDate($settlement->getDatetimeSettlement(), "Y/m/d"),
                    "account" => $settlement->getUserAccount(),
                );
            }
        }

        //
        $tblSettlement = $this->TblSettlement->findFirst(array("where" => "user_id='".$this->developer->developerId."' and status='0'"));
        $settlementInProgress = ($tblSettlement != null and $tblSettlement->getId() > 0) ? 1 : 0;

        //
        $data = array
        (
            "pageTitle" => "تسویه حساب",
            "pageView" => "financial/settlement",
            "pageJsCss" => "financial-settlement",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "settlementsData" => $settlementsData,
                "shaba" => $shaba,
                "settlementInMonth" => $settlementInMonth,
                "priceForSettlement" => $this->general->priceFormat($priceForSettlement),
                "totalDeposited" => $this->general->priceFormat($totalDeposited),
                "settlementInProgress" => $settlementInProgress
            )
        );
        $this->twig->display("master", $data);
    }

    public function settlementShabaSave()
    {
        $result = 0;
        $errorShaba = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $shaba = $this->general->validateInputData($this->input->post("shaba"));

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                //
                if($this->general->stringLen($shaba) == 26)
                {
                    $userBankAccount = $this->TblBankAccount->findFirst(array("where" => "user_id='".$this->developer->developerId."'"));
                    if($userBankAccount == null) // new
                    {
                        $userBankAccount = new TblBankAccount();
                        $userBankAccount->setUserId($this->developer->developerId);
                        $userBankAccount->setAccountOwner("");
                        $userBankAccount->setBankId("");
                        $userBankAccount->setCardNumber("");
                        $userBankAccount->setShabaNumber($shaba);
                        $userBankAccount->setUserType(1);
                        $userBankAccount->setDatetime(time());
                    }
                    else // update
                    {
                        $userBankAccount->setShabaNumber($shaba);
                    }

                    // save
                    $resultSave = $userBankAccount->save();

                    if($resultSave)
                    {
                        $result = 1;
                    }
                }
                else
                {
                    if(!($this->general->stringLen($shaba) == 26)) $errorShaba = 1;
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorShaba" => $errorShaba
        ));
    }

    public function settlementInMonth()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $settlementInMonth = $this->general->validateInputData($this->input->post("settlement-in-month"));

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                //
                if($settlementInMonth == 0 or $settlementInMonth == 1)
                {
                    // save
                    $user->setSettlementEndMonth($settlementInMonth);
                    $resultSave = $user->save();

                    if($resultSave)
                    {
                        $result = 1;
                    }
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result
        ));
    }

    public function settlementRequest()
    {
        $result = 0;
        $inProgress = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                $userBankAccount = $this->TblBankAccount->findFirst(array("where" => "user_id='".$this->developer->developerId."'"));
                $shaba = ($userBankAccount != null) ? $userBankAccount->getShabaNumber() : "";

                $priceSettlement = $user->getPriceSettlement();

                //
                if($priceSettlement > 0 and $shaba != "")
                {
                    $tblSettlement = $this->TblSettlement->findFirst(array("where" => "user_id='".$this->developer->developerId."' and status='0'"));
                    if($tblSettlement == null)
                    {
                        // save
                        $tblSettlement = new TblSettlement();
                        $tblSettlement->setUserId($this->developer->developerId);
                        $tblSettlement->setAdminId(0);
                        $tblSettlement->setPrice($priceSettlement);
                        $tblSettlement->setUserAccount($shaba);
                        $tblSettlement->setDatetimeSettlement(0);
                        $tblSettlement->setDatetimeRequest(time());
                        $tblSettlement->setStatus(0);
                        $resultSave = $tblSettlement->save();

                        if($resultSave)
                        {
                            $result = 1;
                        }
                    }
                    else
                    {
                        $inProgress = 1;
                    }
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "inProgress" => $inProgress
        ));
    }

    // transaction
    public function transaction()
    {
        //
        $data = array
        (
            "pageTitle" => "تراکنش ها",
            "pageView" => "financial/transaction",
            "pageJsCss" => "financial-transaction",
            "showPageTitle" => true,
            "pageContent" => array()
        );
        $this->twig->display("master", $data);
    }

    public function transactionList()
    {
        $statisticData = null;
        $errorDate = 0;
        $errorDateBig = 0;
        $where = "";
        $whereBookId = "";

        // get data
        $token = $this->general->validateInputData($this->input->post("token"));
        $dateStart = $this->general->validateInputData($this->input->post("date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("date-end"));

        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();

        //
        if(($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd) or ($token != ""))
        {
            $countDays = $this->datetime2->countDaysBetween2Dates($dateTimeStart, $dateTimeEnd);

            //
            if($countDays <= 90)
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
                    $whereBookId = rtrim($whereBookId, " or ");
                }

                //
                $where .= ($whereBookId != "") ? (($where != "") ? " and " : "")."($whereBookId)" : "";
                if($token != "")
                {
                    $where .= (($where != "") ? " and " : "")."token='$token'";
                }
                else
                {
                    $where .= (($where != "") ? " and " : "")."datetime >= $dateTimeStart and datetime <= $dateTimeEnd";
                }

                // list
                $tblSales = $this->TblSale->find(array("where" => "$where", "order" => "datetime desc"));
                if($tblSales != null and count($tblSales) > 0)
                {
                    foreach ($tblSales as $sale)
                    {
                        $book = $sale->getBook();

                        //
                        $statisticData[] = array
                        (
                            "title" => ($book != null) ? $book->getTitleFa() : "",
                            "token" => $sale->getToken(),
                            "price" => $sale->getPrice(),
                            "date" => $this->datetime2->getJalaliDate($sale->getDatetime(), "Y/m/d")
                        );
                    }
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

    public function transactionDownload()
    {
        $statisticData = null;
        $where = "";
        $whereBookId = "";

        // get data
        $token = "";
        $dateStart = $this->general->validateInputData($this->input->post("form-financial-transaction-download-date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("form-financial-transaction-download-date-end"));

        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();

        //
        if(($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd) or ($token != ""))
        {
            $countDays = $this->datetime2->countDaysBetween2Dates($dateTimeStart, $dateTimeEnd);

            //
            if($countDays <= 90)
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
                    $whereBookId = rtrim($whereBookId, " or ");
                }

                //
                $where .= ($whereBookId != "") ? (($where != "") ? " and " : "")."($whereBookId)" : "";
                if($token != "")
                {
                    $where .= (($where != "") ? " and " : "")."token='$token'";
                }
                else
                {
                    $where .= (($where != "") ? " and " : "")."datetime >= $dateTimeStart and datetime <= $dateTimeEnd";
                }

                // list
                $tblSales = $this->TblSale->find(array("where" => "$where", "order" => "datetime desc"));
                if($tblSales != null and count($tblSales) > 0)
                {
                    foreach ($tblSales as $sale)
                    {
                        $book = $sale->getBook();

                        //
                        $statisticData[] = array
                        (
                            "title" => ($book != null) ? $book->getTitleFa() : "",
                            "token" => $sale->getToken(),
                            "price" => $sale->getPrice(),
                            "date" => $this->datetime2->getJalaliDate($sale->getDatetime(), "Y/m/d")
                        );
                    }
                }

                // build file
                require_once("application/libraries/PHPExcel.php");
                $alphas = range('A', 'Z');
                define('EOL', (PHP_SAPI == 'cli') ? PHP_EOL : '');

                $objPHPExcel = new PHPExcel();
                $objPHPExcel->getProperties()
                    ->setCreator("Ajil")
                    ->setLastModifiedBy("Ajil")
                    ->setTitle("Office 2007 XLSX Ajil Document")
                    ->setSubject("Office 2007 XLSX Ajil Document")
                    ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
                    ->setKeywords("office 2007 ")
                    ->setCategory("Ajil");

                // column name
                $objPHPExcel->setActiveSheetIndex(0);
                $arrColTitle = array("عنوان", "توکن", "مبلغ", "تاریخ");
                foreach ($arrColTitle as $key2 => $val2)
                {
                    $objPHPExcel->getActiveSheet()->setCellValue($alphas[$key2] . '1', $val2);
                }

                //
                $i = 2;
                foreach ($statisticData as $fields)
                {
                    $objPHPExcel->getActiveSheet()->setCellValue($alphas["0"].$i, $fields["title"]);
                    $objPHPExcel->getActiveSheet()->setCellValue($alphas["1"].$i, $fields["token"]);
                    $objPHPExcel->getActiveSheet()->setCellValue($alphas["2"].$i, $fields["price"]);
                    $objPHPExcel->getActiveSheet()->setCellValue($alphas["3"].$i, $fields["date"]);

                    $i++;
                }
                $objPHPExcel->setActiveSheetIndex(0);

                //
                header('Content-Type: book/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="financial-transaction-' . time() . '.xlsx"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                ob_end_clean();
                $objWriter->save('php://output');
            }
        }
    }
}