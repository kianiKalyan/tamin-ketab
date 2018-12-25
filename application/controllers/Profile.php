<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $c = $this->router->fetch_class();
        $a = $this->router->fetch_method();
        $this->developer->checkLogin("$c-$a");

        // check access
        if($this->developer->supplierId > 0)
        {
            if(($a == "supplier" or $a == "supplierSave" or $a == "supplierList" or $a == "supplierAccess" or $a == "supplierAccessSave" or $a == "supplierDelete" or $a == "supplierDevSave" or $a == "supplierDevUserNameSave"))
            {
                redirect($this->config->base_url(), "", 301);
            }
            elseif(
                (($a == "index" or $a == "profileSave") and $this->developer->supplierAccess->getProfileMain() != 1) or
                (($a == "financialAccount" or $a == "financialAccountSave") and $this->developer->supplierAccess->getProfileFinancial() != 1) or
                (($a == "view" or $a == "viewSave" or $a == "viewDeleteImg") and $this->developer->supplierAccess->getProfileView() != 1)
            )
            {
                if($this->developer->supplierAccess->getProfileMain() == 1)
                {
                    redirect($this->config->base_url()."profile", "", 301);
                }
                elseif($this->developer->supplierAccess->getProfileFinancial() == 1)
                {
                    redirect($this->config->base_url()."profile/financial-account", "", 301);
                }
                elseif($this->developer->supplierAccess->getProfileView() == 1)
                {
                    redirect($this->config->base_url()."profile/view", "", 301);
                }
                else
                {
                    redirect($this->config->base_url(), "", 301);
                }
            }
        }

        //
        $this->load->model("TblBook", "", true);

        $this->load->model("TblUser", "", true);

        $this->load->model("TblCity", "", true);
        $this->load->model("TblProvince", "", true);

        $this->load->model("TblBank", "", true);
        $this->load->model("TblBankAccount", "", true);

        $this->load->model("TblSupplierAccess", "", true);
        $this->load->model("TblSupplierAccessBook", "", true);

        $this->load->model("TblTermsService", "", true);
    }

    // profile
    public function index()
    {
        $cities = $this->TblCity->find(array("order" => "title_fa asc"));
        $provinces = $this->TblProvince->find(array("order" => "title_fa asc"));
        $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));

        //
        $nationalCard = ($user->getNationalCardPath() != null and $user->getNationalCardPath() != "") ? $this->general->foundImage($user->getNationalCardPath(), false) : "";

        //
        $tblTermsService = $this->TblTermsService->findFirst();

        //
        $data = array
        (
            "pageTitle" => "مشخصات فردی",
            "pageView" => "profile/index",
            "pageJsCss" => "profile-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "cities" => $cities,
                "provinces" => $provinces,
                "user" => $user,
                "nationalCard" => $nationalCard,
                "termsService" => $this->general->htmlSpecialCharsDecode($tblTermsService->getDesFa())
            )
        );
        $this->twig->display("master", $data);
    }

    public function profileSave()
    {
        $result = 0;
        $errorNameFa = 0;
        $errorNameEn = 0;
        $errorMail = 0;
        $errorMailRepeat = 0;
        $errorNationalCode = 0;
        $errorMobile = 0;
        $errorMobileRepeat = 0;
        $errorTel = 0;
        //$errorPassword = 0;
        $errorNationalCardImage = 0;
        $errorNationalCardImageType = 0;
        $errorProvinceCity = 0;
        $errorPostalCode = 0;
        $errorAddress = 0;
        $errorTermsService = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $nameFa = $this->general->validateInputData($this->input->post("form-profile-name-fa"));
            $nameEn = $this->general->validateInputData($this->input->post("form-profile-name-en"));
            $email = $this->general->validateInputData($this->input->post("form-profile-email"));
            //$password = $this->general->validateInputData($this->input->post("form-profile-password"));
            $nationalCode = $this->general->validateInputData($this->input->post("form-profile-national-code"));
            $nationalCardImage = $_FILES["form-profile-national-card-image"];
            $nationalCardImageUrl = $this->general->validateInputData($this->input->post("form-profile-national-card-image-url"));
            $phone = $this->general->validateInputData($this->input->post("form-profile-phone"));
            $mobile = $this->general->validateInputData($this->input->post("form-profile-mobile"));
            $province = $this->general->validateInputData($this->input->post("form-profile-province"));
            $city = $this->general->validateInputData($this->input->post("form-profile-city"));
            $postalCode = $this->general->validateInputData($this->input->post("form-profile-postal-code"));
            $address = $this->general->validateInputData($this->input->post("form-profile-address"));
            $termsService = $this->general->validateInputData($this->input->post("form-profile-terms-service"));

            //
            if($this->general->stringLen($nameFa) >= 5 and $this->general->stringLen($nameEn) >= 5 and $this->general->isValidMail($email) and $this->general->stringLen($nationalCode) == 10 and $this->general->stringLen($mobile) == 11 and $this->general->stringLen($phone) == 11 and $province > 0 and $city > 0 and $this->general->stringLen($postalCode) == 10 and $this->general->stringLen($address) >= 10 and ((isset($nationalCardImage) and $nationalCardImage["size"] > 0) or $nationalCardImageUrl != "") and $termsService == 1/*and ($password == "" or ($password != "" and $this->general->stringLen($password) >= 5))*/)
            {
                $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                if($user != null)
                {
                    $email = ($user->getEmail() == "") ? $email : $user->getEmail();

                    //
                    $check_mobile = $this->TblUser->findFirst(array("where" => "mobile='$mobile' and id!='".$this->developer->developerId."'"));
                    $check_email = $this->TblUser->findFirst(array("where" => "email='$email' and id!='".$this->developer->developerId."'"));

                    //
                    if($check_mobile == null and $check_email == null)
                    {
                        $uploadNationalCardImage = null;
                        $uploadedNationalCardImage = false;

                        // upload national-card-image
                        $savePath = $this->general->getPathForSave()."images/user/";
                        $saveTmpPath = $savePath.$this->developer->developerId."-".time()."/";
                        if(isset($nationalCardImage) and $nationalCardImage["size"] > 0)
                        {
                            mkdir($saveTmpPath);

                            $config = array
                            (
                                "upload_path" => $saveTmpPath,
                                "allowed_types" => "jpg|jpeg|png",
                                "max_size" => "500",
                                "overwrite" => TRUE,
                                "file_ext_tolower" => TRUE,
                            );
                            $this->load->library("upload", $config);

                            $uploadNationalCardImage = $this->upload;
                            $uploadedNationalCardImage = $uploadNationalCardImage->do_upload("form-profile-national-card-image");
                        }

                        //
                        if(!(isset($nationalCardImage) and $nationalCardImage["size"] > 0) or $uploadedNationalCardImage)
                        {
                            // save
                            $user->setNameFamilyFa($nameFa);
                            $user->setNameFamilyEn($nameEn);
                            $user->setProvinceId($province);
                            $user->setCityId($city);
                            $user->setEmail($email);
                            $user->setNationalCode($nationalCode);
                            $user->setTel($phone);
                            $user->setMobile($mobile);
                            $user->setPostalCode($postalCode);
                            $user->setAddress($address);
                            $user->setDeveloper(1);
                            $user->setDatetime(time());
                            $resultSave = $user->save();

                            if($resultSave)
                            {
                                $result = 1;

                                if($uploadedNationalCardImage)
                                {
                                    $nationalCardImageName = "national-card-".$this->developer->developerId.$uploadNationalCardImage->file_ext;

                                    if(copy($saveTmpPath.$uploadNationalCardImage->file_name, $savePath.$nationalCardImageName))
                                    {
                                        $user->setNationalCard($nationalCardImageName);
                                        $user->setNationalCardPath(str_replace("../", "", $savePath).$nationalCardImageName);
                                        $user->save();
                                    }
                                }
                            }
                        }
                        else
                        {
                            $errorNationalCardImage = 1;
                        }

                        // remove tmp folder
                        if($uploadedNationalCardImage) unlink($saveTmpPath.$uploadNationalCardImage->file_name);
                        if(is_dir($saveTmpPath)) rmdir($saveTmpPath);
                    }
                    else
                    {
                        if(!($check_mobile == null)) $errorMobileRepeat = 1;
                        if(!($check_email == null)) $errorMailRepeat = 1;
                    }
                }
            }
            else
            {
                if(!($this->general->stringLen($nameFa) >= 5)) $errorNameFa = 1;
                if(!($this->general->stringLen($nameEn) >= 5)) $errorNameEn = 1;
                if(!($this->general->isValidMail($email))) $errorMail = 1;
                if(!($this->general->stringLen($nationalCode) == 10)) $errorNationalCode = 1;
                if(!((isset($nationalCardImage) and $nationalCardImage["size"] > 0) or $nationalCardImageUrl != "")) $errorNationalCardImage = 1;
                if(!($this->general->stringLen($mobile) == 11)) $errorMobile = 1;
                if(!($this->general->stringLen($phone) == 11)) $errorTel = 1;
                if(!($province > 0 and $city > 0)) $errorProvinceCity = 1;
                if(!($this->general->stringLen($postalCode) == 10)) $errorPostalCode = 1;
                if(!($this->general->stringLen($address) >= 10)) $errorAddress = 1;
                if(!($termsService == 1)) $errorTermsService = 1;
                //if(!($password == "" or ($password != "" and $this->general->stringLen($password) >= 5))) $errorPassword = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorNameFa" => $errorNameFa,
            "errorNameEn" => $errorNameEn,
            "errorMail" => $errorMail,
            "errorMailRepeat" => $errorMailRepeat,
            "errorNationalCode" => $errorNationalCode,
            "errorMobile" => $errorMobile,
            "errorMobileRepeat" => $errorMobileRepeat,
            "errorTel" => $errorTel,
            "errorNationalCardImage" => $errorNationalCardImage,
            "errorNationalCardImageType" => $errorNationalCardImageType,
            "errorProvinceCity" => $errorProvinceCity,
            "errorPostalCode" => $errorPostalCode,
            "errorAddress" => $errorAddress,
            "errorTermsService" => $errorTermsService
        ));
    }

    // financial-account
    public function financialAccount()
    {
        $banks = $this->TblBank->find(array("order" => "title_fa asc"));
        $userBankAccount = $this->TblBankAccount->findFirst(array("where" => "user_id='".$this->developer->developerId."'"));

        //
        $data = array
        (
            "pageTitle" => "اطلاعات حساب مالی",
            "pageView" => "profile/financial-account",
            "pageJsCss" => "profile-financial-account",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "banks" => $banks,
                "userBankAccount" => $userBankAccount
            )
        );
        $this->twig->display("master", $data);
    }

    public function financialAccountSave()
    {
        $result = 0;
        $errorUserType = 0;
        $errorBank = 0;
        $errorOwner = 0;
        $errorCardNumber = 0;
        $errorShaba = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $userType = $this->general->validateInputData($this->input->post("form-profile-financial-account-user-type"));
            $bank = $this->general->validateInputData($this->input->post("form-profile-financial-account-bank"));
            $owner = $this->general->validateInputData($this->input->post("form-profile-financial-account-owner"));
            $cardNumber = $this->general->validateInputData($this->input->post("form-profile-financial-account-card-number"));
            $shaba = $this->general->validateInputData($this->input->post("form-profile-financial-account-shaba"));

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                //
                if(($userType == 1 or $userType == 2) and $bank > 0 and $this->general->stringLen($owner) > 1 and $this->general->stringLen($cardNumber) == 16 and $this->general->stringLen($shaba) == 26)
                {
                    $userBankAccount = $this->TblBankAccount->findFirst(array("where" => "user_id='".$this->developer->developerId."'"));
                    if($userBankAccount == null) // new
                    {
                        $userBankAccount = new TblBankAccount();
                        $userBankAccount->setUserId($this->developer->developerId);
                        $userBankAccount->setAccountOwner($owner);
                        $userBankAccount->setBankId($bank);
                        $userBankAccount->setCardNumber($cardNumber);
                        $userBankAccount->setShabaNumber($shaba);
                        $userBankAccount->setUserType($userType);
                        $userBankAccount->setDatetime(time());
                    }
                    else // update
                    {
                        $userBankAccount->setAccountOwner($owner);
                        $userBankAccount->setBankId($bank);
                        $userBankAccount->setCardNumber($cardNumber);
                        $userBankAccount->setShabaNumber($shaba);
                        $userBankAccount->setUserType($userType);
                        $userBankAccount->setDatetime(time());
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
                    if(!($userType == 1 or $userType == 2)) $errorUserType = 1;
                    if(!($bank > 0)) $errorBank = 1;
                    if(!($this->general->stringLen($owner) > 1)) $errorOwner = 1;
                    if(!($this->general->stringLen($cardNumber) == 16)) $errorCardNumber = 1;
                    if(!($this->general->stringLen($shaba) == 26)) $errorShaba = 1;
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorUserType" => $errorUserType,
            "errorBank" => $errorBank,
            "errorOwner" => $errorOwner,
            "errorCardNumber" => $errorCardNumber,
            "errorShaba" => $errorShaba
        ));
    }

    // view
    public function view()
    {
        $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));

        //
        $userImg = ($user->getUserImgPath() != null and $user->getUserImgPath() != "") ? $this->general->foundImage($user->getUserImgPath(), true) : "";

        //
        $data = array
        (
            "pageTitle" => "پروفایل نمایشی کاربران",
            "pageView" => "profile/view",
            "pageJsCss" => "profile-view",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "user" => $user,
                "userImg" => $userImg
            )
        );
        $this->twig->display("master", $data);
    }

    public function viewSave()
    {
        $result = 0;
        $errorUserImage = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $title = $this->general->validateInputData($this->input->post("form-profile-view-title"));
            $site = $this->general->validateInputData($this->input->post("form-profile-view-site"));
            $email = $this->general->validateInputData($this->input->post("form-profile-view-email"));
            $des = $this->general->validateInputData($this->input->post("form-profile-view-des"));
            $image = $_FILES["form-profile-view-image"];
            $imageCropped = $_POST["form-profile-view-image-cropped"];

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                $uploadImage = null;
                $uploadedImage = false;

                // upload image
                $savePath = $this->general->getPathForSave()."images/user/";
                $saveTmpPath = $savePath.$this->developer->developerId."-".time()."/";
                if((isset($image) and $image["size"] > 0))
                {
                    mkdir($saveTmpPath);

                    $config = array
                    (
                        "upload_path" => $saveTmpPath,
                        "allowed_types" => "png",
                        "max_size" => "500",
                        "overwrite" => TRUE,
                        "file_ext_tolower" => TRUE,
                    );
                    $this->load->library("upload", $config);

                    $uploadImage = $this->upload;
                    $uploadedImage = $uploadImage->do_upload("form-profile-view-image");
                }

                //
                if(!(isset($image) and $image["size"] > 0) or ($uploadedImage and $imageCropped != ""))
                {
                    $saveImageCropped = false;
                    if($imageCropped != "")
                    {
                        // save ImageCropped
                        list($type, $imageCropped) = explode(";", $imageCropped);
                        list(, $imageCropped) = explode(",", $imageCropped);
                        $imageCropped = base64_decode($imageCropped);
                        $saveImageCropped = file_put_contents($saveTmpPath . $uploadImage->file_name, $imageCropped);
                    }

                    if($imageCropped == "" or ($imageCropped != "" and $saveImageCropped))
                    {
                        // save
                        $user->setTitle($title);
                        $user->setSite($site);
                        $user->setEmailView($email);
                        $user->setAbout($des);
                        $resultSave = $user->save();

                        if($resultSave)
                        {
                            $result = 1;

                            if($uploadedImage)
                            {
                                $userImgName = "user-".$this->developer->developerId.$uploadImage->file_ext;

                                if(copy($saveTmpPath.$uploadImage->file_name, $savePath.$userImgName))
                                {
                                    $user->setUserImg($userImgName);
                                    $user->setUserImgPath(str_replace("../", "", $savePath).$userImgName);
                                    $user->save();
                                }
                            }
                        }
                    }
                }
                else
                {
                    $errorUserImage = 1;
                }

                // remove tmp folder
                if($uploadedImage) unlink($saveTmpPath.$uploadImage->file_name);
                if(is_dir($saveTmpPath)) rmdir($saveTmpPath);
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorUserImage" => $errorUserImage
        ));
    }

    public function viewDeleteImg()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                $savePath = "./public/files/images/user/";
                $imgPath = "$savePath/user-".$this->developer->developerId.".png";

                if(is_file($imgPath))
                {
                    if(unlink($imgPath)) $result = 1;
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result
        ));
    }

    // supplier
    public function supplier()
    {
        $user = null;
        $books = null;

        //
        $tblBooks = $this->TblBook->find(array(
            "where" => "developer_id='".$this->developer->developerId."'",
            "order" => "title_fa asc",
        ));
        if($tblBooks != null and count($tblBooks) > 0)
        {
            foreach ($tblBooks as $book)
            {
                if($book->getTitleFa() != "")
                {
                    $books[] = array
                    (
                        "id" => $book->getId(),
                        "title" => $book->getTitleFa()
                    );
                }
            }
        }

        //
        $tblUser = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
        if($tblUser != null)
        {
            $user = array
            (
                "title" => $tblUser->getTitle(),
                "user_name" => $tblUser->getUserName(),
                "site" => $tblUser->getSite()
            );
        }

        //
        $data = array
        (
            "pageTitle" => "مدیریت کارپردازان",
            "pageView" => "profile/supplier",
            "pageJsCss" => "profile-supplier",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "user" => $user,
                "books" => $books
            )
        );
        $this->twig->display("master", $data);
    }

    public function supplierSave()
    {
        $result = 0;
        $errorNameFamily = 0;
        $errorPassword = 0;
        $errorMail = 0;
        $errorMailRepeat = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $nameFamily = $this->general->validateInputData($this->input->post("form-supplier-name-family"));
            $email = $this->general->validateInputData($this->input->post("form-supplier-email"));
            $password = $this->general->validateInputData($this->input->post("form-supplier-password"));
            $bookData = $this->general->validateInputData($this->input->post("form-supplier-book-data"));
            $bookDataBooks = $this->input->post("form-supplier-book-data-books");
            $comment = $this->general->validateInputData($this->input->post("form-supplier-comment"));
            $commentBooks = $this->input->post("form-supplier-comment-books");
            $publish = $this->general->validateInputData($this->input->post("form-supplier-publish"));
            $publishBooks = $this->input->post("form-supplier-publish-books");
            $support = $this->general->validateInputData($this->input->post("form-supplier-support"));
            $supportBooks = $this->input->post("form-supplier-support-books");
            $bookAdd = $this->general->validateInputData($this->input->post("form-supplier-book-add"));
            $statistic = $this->general->validateInputData($this->input->post("form-supplier-statistic"));
            $financial = $this->general->validateInputData($this->input->post("form-supplier-financial"));
            $profileMain = $this->general->validateInputData($this->input->post("form-supplier-profile-main"));
            $profileFinancial = $this->general->validateInputData($this->input->post("form-supplier-profile-financial"));
            $profileView = $this->general->validateInputData($this->input->post("form-supplier-profile-view"));

            $bookData = (isset($bookData) and $bookData == 1) ? 1 : 0;
            $comment = (isset($publish) and $comment == 1) ? 1 : 0;
            $publish = (isset($publish) and $publish == 1) ? 1 : 0;
            $support = (isset($support) and $support == 1) ? 1 : 0;
            $bookAdd = (isset($bookAdd) and $bookAdd == 1) ? 1 : 0;
            $statistic = (isset($statistic) and $statistic == 1) ? 1 : 0;
            $financial = (isset($financial) and $financial == 1) ? 1 : 0;
            $profileMain = (isset($profileMain) and $profileMain == 1) ? 1 : 0;
            $profileFinancial = (isset($profileFinancial) and $profileFinancial == 1) ? 1 : 0;
            $profileView = (isset($profileView) and $profileView == 1) ? 1 : 0;

            //
            if($this->general->stringLen($nameFamily) >= 5 and $this->general->stringLen($password) >= 5 and $this->general->isValidMail($email))
            {
                $checkMail = $this->TblUser->findFirst(array("where" => "email='$email'"));
                if($checkMail == null)
                {
                    $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                    if($user != null)
                    {
                        // save supplier
                        $supplier = new TblUser();
                        $supplier->setNameFamilyFa($nameFamily);
                        $supplier->setPassword($this->general->encodePassword($password));
                        $supplier->setEmail($email);
                        $supplier->setSupplier(1);
                        $supplier->setDeveloperId($this->developer->developerId);
                        $supplier->setDatetime(time());
                        $resultSave = $supplier->save();

                        $supplierId = $supplier->getId();

                        // save supplier access
                        $supplierAccess = new TblSupplierAccess();
                        $supplierAccess->setSupplierId($supplierId);
                        $supplierAccess->setBookAdd($bookAdd);
                        $supplierAccess->setBookData($bookData);
                        $supplierAccess->setFinancial($financial);
                        $supplierAccess->setPublish($publish);
                        $supplierAccess->setComment($comment);
                        $supplierAccess->setStatistic($statistic);
                        $supplierAccess->setSupport($support);
                        $supplierAccess->setProfileMain($profileMain);
                        $supplierAccess->setProfileFinancial($profileFinancial);
                        $supplierAccess->setProfileView($profileView);
                        $supplierAccess->save();

                        // save supplier access book
                        if($bookData == 1 and $bookDataBooks != null)
                        {
                            foreach ($bookDataBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("book-data");
                                $supplierAccessBook->save();
                            }
                        }
                        if($comment == 1 and $commentBooks != null)
                        {
                            foreach ($commentBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("comment");
                                $supplierAccessBook->save();
                            }
                        }
                        if($publish == 1 and $publishBooks != null)
                        {
                            foreach ($publishBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("publish");
                                $supplierAccessBook->save();
                            }
                        }
                        if($support == 1 and $supportBooks != null)
                        {
                            foreach ($supportBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("support");
                                $supplierAccessBook->save();
                            }
                        }

                        if($resultSave)
                        {
                            $result = 1;
                        }
                    }
                }
                else
                {
                    $errorMailRepeat = 1;
                }
            }
            else
            {
                if(!($this->general->stringLen($nameFamily) >= 5)) $errorNameFamily = 1;
                if(!($this->general->stringLen($password) >= 5)) $errorPassword = 1;
                if(!($this->general->isValidMail($email))) $errorMail = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorNameFamily" => $errorNameFamily,
            "errorPassword" => $errorPassword,
            "errorMail" => $errorMail,
            "errorMailRepeat" => $errorMailRepeat
        ));
    }

    public function supplierAccessSave()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $supplierId = $this->general->validateInputData($this->input->post("form-supplier-access-id"));
            $bookData = $this->general->validateInputData($this->input->post("form-supplier-access-book-data"));
            $bookDataBooks = $this->input->post("form-supplier-access-book-data-books");
            $comment = $this->general->validateInputData($this->input->post("form-supplier-access-comment"));
            $commentBooks = $this->input->post("form-supplier-access-comment-books");
            $publish = $this->general->validateInputData($this->input->post("form-supplier-access-publish"));
            $publishBooks = $this->input->post("form-supplier-access-publish-books");
            $support = $this->general->validateInputData($this->input->post("form-supplier-access-support"));
            $supportBooks = $this->input->post("form-supplier-access-support-books");
            $bookAdd = $this->general->validateInputData($this->input->post("form-supplier-access-book-add"));
            $statistic = $this->general->validateInputData($this->input->post("form-supplier-access-statistic"));
            $financial = $this->general->validateInputData($this->input->post("form-supplier-access-financial"));
            $profileMain = $this->general->validateInputData($this->input->post("form-supplier-access-profile-main"));
            $profileFinancial = $this->general->validateInputData($this->input->post("form-supplier-access-profile-financial"));
            $profileView = $this->general->validateInputData($this->input->post("form-supplier-access-profile-view"));

            $bookData = (isset($bookData) and $bookData == 1) ? 1 : 0;
            $publish = (isset($publish) and $publish == 1) ? 1 : 0;
            $comment = (isset($comment) and $comment == 1) ? 1 : 0;
            $support = (isset($support) and $support == 1) ? 1 : 0;
            $bookAdd = (isset($bookAdd) and $bookAdd == 1) ? 1 : 0;
            $statistic = (isset($statistic) and $statistic == 1) ? 1 : 0;
            $financial = (isset($financial) and $financial == 1) ? 1 : 0;
            $profileMain = (isset($profileMain) and $profileMain == 1) ? 1 : 0;
            $profileFinancial = (isset($profileFinancial) and $profileFinancial == 1) ? 1 : 0;
            $profileView = (isset($profileView) and $profileView == 1) ? 1 : 0;

            //
            if($supplierId > 0)
            {
                $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                if($user != null)
                {
                    $tblSupplier = $this->TblUser->findFirst(array("where" => "id='$supplierId' and developer_id='".$this->developer->developerId."'"));
                    if($tblSupplier != null)
                    {
                        // save supplier access
                        $supplierAccess = $this->TblSupplierAccess->findFirst(array("where" => "supplier_id='$supplierId'"));
                        if($supplierAccess != null and $supplierAccess->getId() > 0)
                        {
                            $supplierAccess->setSupplierId($supplierId);
                            $supplierAccess->setBookAdd($bookAdd);
                            $supplierAccess->setBookData($bookData);
                            $supplierAccess->setFinancial($financial);
                            $supplierAccess->setComment($comment);
                            $supplierAccess->setPublish($publish);
                            $supplierAccess->setStatistic($statistic);
                            $supplierAccess->setSupport($support);
                            $supplierAccess->setProfileMain($profileMain);
                            $supplierAccess->setProfileFinancial($profileFinancial);
                            $supplierAccess->setProfileView($profileView);
                            $supplierAccess->save();
                        }

                        // delete supplier access book
                        $supplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$supplierId'"));
                        if($supplierAccessBooks != null and count($supplierAccessBooks) > 0)
                        {
                            foreach ($supplierAccessBooks as $supplierAccessBook)
                            {
                                $supplierAccessBook->delete();
                            }
                        }

                        // save supplier access book
                        if($bookData == 1 and $bookDataBooks != null)
                        {
                            foreach ($bookDataBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("book-data");
                                $supplierAccessBook->save();
                            }
                        }
                        if($comment == 1 and $commentBooks != null)
                        {
                            foreach ($commentBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("comment");
                                $supplierAccessBook->save();
                            }
                        }
                        if($publish == 1 and $publishBooks != null)
                        {
                            foreach ($publishBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("publish");
                                $supplierAccessBook->save();
                            }
                        }
                        if($support == 1 and $supportBooks != null)
                        {
                            foreach ($supportBooks as $bookId)
                            {
                                $supplierAccessBook = new TblSupplierAccessBook();
                                $supplierAccessBook->setSupplierId($supplierId);
                                $supplierAccessBook->setBookId($bookId);
                                $supplierAccessBook->setTypeAccess("support");
                                $supplierAccessBook->save();
                            }
                        }

                        //
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

    public function supplierList()
    {
        $suppliersData = null;

        //
        $tblSuppliers = $this->TblUser->find(array("where" => "supplier='1' and developer_id='".$this->developer->developerId."'", "order" => "datetime asc"));
        if($tblSuppliers != null and count($tblSuppliers) > 0)
        {
            foreach($tblSuppliers as $supplier)
            {
                $suppliersData[] = array
                (
                    "id" => $supplier->getId(),
                    "name_family" => $this->general->htmlSpecialCharsDecode($supplier->getNameFamilyFa()),
                    "mail" => $this->general->htmlSpecialCharsDecode($supplier->getEmail())
                );
            }
        }

        //
        echo json_encode
        (
            array("data" => $suppliersData)
        );
    }

    public function supplierAccess()
    {
        $supplierData = null;

        // get data
        $id = $this->general->validateInputData($this->input->post("id"));

        //
        if($id > 0)
        {
            $tblSupplier = $this->TblUser->findFirst(array("where" => "id='$id' and developer_id='".$this->developer->developerId."'"));
            if($tblSupplier != null and $tblSupplier->getId() > 0)
            {
                $tblSupplierAccess = $this->TblSupplierAccess->findFirst(array("where" => "supplier_id='$id'"));
                if($tblSupplierAccess != null and $tblSupplierAccess->getId() > 0)
                {
                    $bookDataBooks = null;
                    $tblSupplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$id' and type_access='book-data'"));
                    if($tblSupplierAccessBooks != null and count($tblSupplierAccessBooks) > 0)
                    {
                        foreach($tblSupplierAccessBooks as $saa)
                        {
                            $bookDataBooks[] = $saa->getBookId();
                        }
                    }
                    $commentBooks = null;
                    $tblSupplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$id' and type_access='comment'"));
                    if($tblSupplierAccessBooks != null and count($tblSupplierAccessBooks) > 0)
                    {
                        foreach($tblSupplierAccessBooks as $saa)
                        {
                            $commentBooks[] = $saa->getBookId();
                        }
                    }
                    $publishBooks = null;
                    $tblSupplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$id' and type_access='publish'"));
                    if($tblSupplierAccessBooks != null and count($tblSupplierAccessBooks) > 0)
                    {
                        foreach($tblSupplierAccessBooks as $saa)
                        {
                            $publishBooks[] = $saa->getBookId();
                        }
                    }
                    $supportBooks = null;
                    $tblSupplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$id' and type_access='support'"));
                    if($tblSupplierAccessBooks != null and count($tblSupplierAccessBooks) > 0)
                    {
                        foreach($tblSupplierAccessBooks as $saa)
                        {
                            $supportBooks[] = $saa->getBookId();
                        }
                    }

                    //
                    $supplierData = array
                    (
                        "book_data" => $tblSupplierAccess->getBookData(),
                        "book_data_books" => $bookDataBooks,
                        "comment" => $tblSupplierAccess->getComment(),
                        "comment_books" => $commentBooks,
                        "publish" => $tblSupplierAccess->getPublish(),
                        "publish_books" => $publishBooks,
                        "support" => $tblSupplierAccess->getSupport(),
                        "support_books" => $supportBooks,
                        "book_add" => $tblSupplierAccess->getBookAdd(),
                        "financial" => $tblSupplierAccess->getFinancial(),
                        "statistic" => $tblSupplierAccess->getStatistic(),
                        "profile_main" => $tblSupplierAccess->getProfileMain(),
                        "profile_financial" => $tblSupplierAccess->getProfileFinancial(),
                        "profile_view" => $tblSupplierAccess->getProfileView()
                    );
                }
            }
        }

        //
        echo json_encode
        (
            array("data" => $supplierData)
        );
    }

    public function supplierDelete()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $id = $this->general->validateInputData($this->input->post("id"));

            //
            if($id > 0)
            {
                $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                if($user != null)
                {
                    $tblSupplier = $this->TblUser->findFirst(array("where" => "id='$id' and developer_id='".$this->developer->developerId."'"));
                    if($tblSupplier != null)
                    {
                        // delete
                        $resultDelete = $tblSupplier->delete();

                        if($resultDelete)
                        {
                            // delete supplier access
                            $supplierAccess = $this->TblSupplierAccess->findFirst(array("where" => "supplier_id='$id'"));
                            if($supplierAccess != null and $supplierAccess->getId() > 0)
                            {
                                $supplierAccess->delete();
                            }

                            // delete supplier access book
                            $supplierAccessBooks = $this->TblSupplierAccessBook->find(array("where" => "supplier_id='$id'"));
                            if($supplierAccessBooks != null and count($supplierAccessBooks) > 0)
                            {
                                foreach ($supplierAccessBooks as $supplierAccessBook)
                                {
                                    $supplierAccessBook->delete();
                                }
                            }

                            //
                            $result = 1;
                        }
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

    public function supplierDevSave()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $title = $this->general->validateInputData($this->input->post("form-dev-title"));
            $site = $this->general->validateInputData($this->input->post("form-dev-site"));

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
            if($user != null)
            {
                // save
                $user->setTitle($title);
                $user->setSite($site);
                $resultSave = $user->save();

                if($resultSave)
                {
                    $result = 1;
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result
        ));
    }

    public function supplierDevUserNameSave()
    {
        $result = 0;
        $errorUserName = 0;
        $errorUserNameRepeat = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $userName = $this->general->validateInputData($this->input->post("form-dev-user-name-id"));

            //
            $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."' and user_name=''"));
            if($user != null)
            {
                if($this->general->validateUserName($userName))
                {
                    $userCheckExist = $this->TblUser->findFirst(array("where" => "user_name='$userName'"));
                    if($userCheckExist == null)
                    {
                        // save
                        $user->setUserName($userName);
                        $resultSave = $user->save();

                        if($resultSave)
                        {
                            $result = 1;
                        }
                    }
                    else
                    {
                        $errorUserNameRepeat = 1;
                    }
                }
                else
                {
                    $errorUserName = 1;
                }
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorUserName" => $errorUserName,
            "errorUserNameRepeat" => $errorUserNameRepeat
        ));
    }

    // changePassword
    public function changePassword()
    {
        //
        $data = array
        (
            "pageTitle" => "تغییر کلمه عبور",
            "pageView" => "profile/change-password",
            "pageJsCss" => "profile-change-password",
            "showPageTitle" => true,
            "pageContent" => array()
        );
        $this->twig->display("master", $data);
    }

    public function changePasswordSave()
    {
        $result = 0;
        $errorPassword = 0;
        $errorNewPassword = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $password = $this->general->validateInputData($this->input->post("form-profile-change-password-password"));
            $newPassword = $this->general->validateInputData($this->input->post("form-profile-change-password-new-password-rep"));
            $newPasswordRep = $this->general->validateInputData($this->input->post("form-profile-change-password-new-password-rep"));

            //
            if(($password != "" and $this->general->stringLen($password) >= 5) and ($newPassword != "" and $this->general->stringLen($newPassword) >= 5) and ($newPasswordRep != "" and $this->general->stringLen($newPasswordRep) >= 5) and ($newPassword == $newPasswordRep))
            {
                $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                if($user != null)
                {
                    // save
                    $user->setPassword($this->general->encodePassword($newPassword));
                    $resultSave = $user->save();

                    if($resultSave)
                    {
                        $result = 1;
                    }
                }
            }
            else
            {
                if(!($password != "" and $this->general->stringLen($password) >= 5)) $errorPassword = 1;
                if(!(($newPassword != "" and $this->general->stringLen($newPassword) >= 5) and ($newPasswordRep != "" and $this->general->stringLen($newPasswordRep) >= 5) and ($newPassword == $newPasswordRep))) $errorNewPassword = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorPassword" => $errorPassword,
            "errorNewPassword" => $errorNewPassword
        ));
    }
}