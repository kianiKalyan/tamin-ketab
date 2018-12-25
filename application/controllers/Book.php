<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Book extends MY_Controller
{
    //TEST test1
    function __construct()
    {
        parent::__construct();
        //
        $c = $this->router->fetch_class();
        $a = $this->router->fetch_method();
        $isbn = $this->uri->segment(2);
        $this->developer->checkLogin();
        // check access
        if ($this->developer->supplierId > 0) {
            if (($a == "bookSave" and $this->developer->supplierAccess->getBookAdd() != 1)) {
                redirect($this->config->base_url(), "", 301);
            } elseif (
                (($a == "info" or $a == "infoSave" or $a == "images" or $a == "iconSave" or $a == "screenShotSave" or $a == "screenShotDelete" or $a == "videoSave" or $a == "packages" or $a == "packagesSave" or $a == "iab" or $a == "iabList" or $a == "iabSave" or $a == "iabEdit" or $a == "ads" or $a == "statistic" or $a == "statisticList" or $a == "media" or $a == "mediaSave" or $a == "mediaEdit" or $a == "mediaList" or $a == "mediaDelete" or $a == "publishUnpublished" or $a == "autoPublish") and $this->developer->supplierAccess->getBookData() != 1) or
                (($a == "comments" or $a == "commentsList" or $a == "commentsAnswer" or $a == "commentsReport") and $this->developer->supplierAccess->getComment() != 1)
            ) {
                if ($this->developer->supplierAccess->getBookData() == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($this->developer->supplierAccess->getComment() == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
        }
        //
        $this->load->model("TblBook", "", true);
        $this->load->model("TblBookCreator", "", true);
        $this->load->model("TblBookDownload", "", true);
        $this->load->model("TblBookImage", "", true);
        $this->load->model("TblBookPrice", "", true);
        $this->load->model("TblBookStatus", "", true);
        $this->load->model("TblBookVideo", "", true);
        $this->load->model("TblBookAudio", "", true);
        $this->load->model("TblBookView", "", true);
        $this->load->model("TblComment", "", true);
        $this->load->model("TblCommentAnswer", "", true);
        $this->load->model("TblCommentReport", "", true);
        $this->load->model("TblCommentLike", "", true);
        $this->load->model("TblCategory", "", true);
        $this->load->model("TblResolution", "", true);
        $this->load->model("TblDensityScreen", "", true);
        $this->load->model("TblCpu", "", true);
        $this->load->model("TblMedia", "", true);
        $this->load->model("TblMediaNews", "", true);
        $this->load->model("TblSale", "", true);
        //
        $this->load->library("upload");
    }

    // index
    public function index()
    {
        $books = null;
        $categories = null;
        $developers = null;
        //
        $tblUsers = $this->TblUser->find(array("where" => "id='" . $this->developer->developerId . "'"));
        if ($tblUsers != null and count($tblUsers) > 0) {
            foreach ($tblUsers as $user) {
                $developers[] = array
                (
                    "id" => $user->getId(),
                    "name" => $user->getNameFamilyFa()
                );
            }
        }
        //
        $tblCategories = $this->TblCategory->find(array("where" => "status='1'", "order" => "title_fa asc"));
        if ($tblCategories != null and count($tblCategories) > 0) {
            foreach ($tblCategories as $category) {
                //$category = new TblCategory();
                $categories[] = array
                (
                    "id" => $category->getId(),
                    "parent_id" => $category->getParentId(),
//                    "title" => $category->getTitleFa()." - ".$category->getTitleEn()
                    "title" => $category->getTitleFa()
                );
            }
        }
        //
        $whereBookIdAccess = "";
        if ($this->developer->supplierId > 0) {
            if ($this->developer->supplierAccessBook != null) {
                foreach ($this->developer->supplierAccessBook as $accessBookId) {
                    if ($accessBookId->getTypeAccess() == "book-data" or $accessBookId->getTypeAccess() == "comment" or $accessBookId->getTypeAccess() == "publish") {
                        $accessBookId = $accessBookId->getBookId();
                        $whereBookIdAccess .= "id='$accessBookId' or ";
                    }
                }
            }
        }
        $whereBookIdAccess = ($whereBookIdAccess != "") ? "and (" . trim($whereBookIdAccess, " or ") . ")" : "";
        $books = $this->TblBook->find(array("where" => "developer_id='" . $this->developer->developerId . "' $whereBookIdAccess", "order" => "datetime desc, id desc"));
        if ($books != null and count($books) > 0) {
            foreach ($books as $book) {
                $bookStatus = $book->getBookStatus();
                $rate = ($book->getCommentScore() > 0) ? round(($book->getCommentScore() / 5) * 100) : 0;
                $booksData[] = array
                (
                    "id" => $book->getId(),
                    "isbn" => $book->getIsbn(),
                    "title" => ($book->getTitleFa() != "") ? $book->getTitleFa() : $book->getIsbn(),
                    "date" => $this->datetime2->getJalaliDate($book->getDatetime(), "Y/m/d"),
                    "download" => $book->getTotalDownload(),
                    "price" => $book->getPriceNew(),
                    "price_physical" => $book->getPricePhysicalNew(),
                    "icon" => $this->config->item('main_url') . $book->getIconAddressThumb(),
                    "status" => ($bookStatus != "") ? $bookStatus->getTitle() : "",
                    "sale" => $book->getTotalSale(),
                    "comment_count" => $book->getCommentCount(),
                    "rate" => $rate,
                );
            }
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/index",
            "pageJsCss" => "book-index",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "books" => $booksData,
                "categories" => $categories,
                "developers" => $developers
            )
        );
        $this->twig->display("master", $data);
    }

    public function bookSave()
    {
        $result = 0;
        $errorIsbn = 0;
        $errorCategory = 0;
        $errorIsbnRepeat = 0;
        $errorBookFull = 0;
        $errorBookShort = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $isbn = $this->general->validateInputData($this->input->post("form-book-isbn"));
            $editionNumber = $this->general->validateInputData($this->input->post("form-book-edition-number"));
            $category = $this->general->validateInputData($this->input->post("form-book-category"));
            $category2 = $this->general->validateInputData($this->input->post("form-book-category-2"));
            $developer = $this->general->validateInputData($this->input->post("form-book-developer"));
            $bookFileShort = $_FILES["form-book-file-short"];
            $bookFileFull = $_FILES["form-book-file-full"];
            //
//            if ($this->general->stringLen($isbn) > 0 and $this->general->isValidNumber($isbn) and $category > 0 and $developer > 0 and $developer == $this->developer->developerId and (isset($bookFileShort) and $bookFileShort["size"] > 0) and (isset($bookFileFull) and $bookFileFull["size"] > 0)) {
            if ($this->general->stringLen($isbn) > 0 and $this->general->isValidNumber($isbn) and $category > 0 and $developer > 0 and $developer == $this->developer->developerId) {
                $user = $this->TblUser->findFirst(array("where" => "id='" . $this->developer->developerId . "'"));
                if ($user != null) {
                    $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "' and edition_number='" . $editionNumber . "' "));
                    if ($book == null) {
                        do {
                            $folderName = rand(11111111, 99999999999999);
                            // add by kiani
                            //////////////////////////////////end////////////////////
                            $checkFolderName = $this->TblBook->findFirst(array("where" => "uniqecode='$folderName'"));
                        } while ($checkFolderName != null);
                        // upload book
                        $savePathRoot = $this->general->getPathForSave() . date("Y");
                        $savePath = $this->general->getPathForSave() . date("Y") . "/$folderName/";
//                        $savePathTemp = $savePath . "temp/";
                        $savePathBook = $savePath . "book/";
                        $savePathScreenShot = $savePath . "screenshot/";
                        $savePathIcon = $savePath . "icon/";
                        $savePathVideo = $savePath . "video/";
                        $savePathAudio = $savePath . "audio/";
//                        $savePathVer = $savePath . "packages/";
                        if (!is_dir($savePathRoot)) // check exist year folder
                        {
                            mkdir($savePathRoot);
                        }
                        {
                            // create folders
                            mkdir($savePath);
                            mkdir($savePathBook);
                            mkdir($savePathScreenShot);
                            mkdir($savePathIcon);
                            mkdir($savePathVideo);
                            mkdir($savePathAudio);
                        }
                        //
                        $config = array
                        (
                            "upload_path" => $savePathBook,
                            "allowed_types" => "pdf|epub",
                            "max_size" => "153600",
                            "overwrite" => TRUE,
                            "file_ext_tolower" => TRUE,
                        );
                        if (isset($bookFileShort) and $bookFileShort["size"] > 0) {
                            // book short
                            $_FILES["userfile"]["name"] = $bookFileShort["name"];
                            $_FILES["userfile"]["type"] = $bookFileShort["type"];
                            $_FILES["userfile"]["tmp_name"] = $bookFileShort["tmp_name"];
                            $_FILES["userfile"]["error"] = $bookFileShort["error"];
                            $_FILES["userfile"]["size"] = $bookFileShort["size"];
                            $config["file_name"] = "book-short." . $this->general->fileType($bookFileShort["name"]);
                            $this->upload->initialize($config);
                            $uploadedBookShort = $this->upload->do_upload("form-book-file-short");
                            $sizeBookShort = $_FILES["userfile"]["size"];
                            $pathBookShort = $savePathBook . "book-short." . $this->general->fileType($bookFileShort["name"]);
                        } else {
                            $uploadedBookShort = 1;
                            $sizeBookShort = 0;
                            $pathBookShort = '';
                        }
                        if (isset($bookFileFull) and $bookFileFull["size"] > 0) {
                            // book full
                            $_FILES["userfile"]["name"] = $bookFileFull["name"];
                            $_FILES["userfile"]["type"] = $bookFileFull["type"];
                            $_FILES["userfile"]["tmp_name"] = $bookFileFull["tmp_name"];
                            $_FILES["userfile"]["error"] = $bookFileFull["error"];
                            $_FILES["userfile"]["size"] = $bookFileFull["size"];
                            $config["file_name"] = "book-full." . $this->general->fileType($bookFileFull["name"]);
                            $this->upload->initialize($config);
                            $uploadedBookFull = $this->upload->do_upload("form-book-file-full");
                            $sizeBookFull = $_FILES["userfile"]["size"];
                            $pathBookFull = $savePathBook . "book-full." . $this->general->fileType($bookFileFull["name"]);
                        } else {
                            $uploadedBookFull = 1;
                            $sizeBookFull = 0;
                            $pathBookFull = '';
                        }
                        //
                        if ($uploadedBookShort or $uploadedBookFull) {
                            $categoryParent = $this->TblCategory->findFirst(array("where" => "id='$category'"));
                            $categoryParent2 = ($category2 > 0) ? $this->TblCategory->findFirst(array("where" => "id='$category2'")) : null;
                            // save apk data in tbl_book
                            $book = new TblBook();
                            $book->setDeveloperId($this->developer->developerId);
                            $book->setBookTitle("");
                            $book->setTitleFa("");
                            $book->setTitleEn("");
                            $book->setIsbn($isbn);
                            $book->setPublishYear("");
                            $book->setAgesFa("");
                            $book->setAgesEn("");
                            $book->setLanguage("");
                            $book->setDesFa("");
                            $book->setDesEn("");
                            $book->setCategoryId1(($categoryParent != null) ? $categoryParent->getParentId() : 0);
                            $book->setCategoryId2($category);
                            $book->setCategoryIdSecondary1(($categoryParent2 != null) ? $categoryParent2->getParentId() : 0);
                            $book->setCategoryIdSecondary2($category2);
                            $book->setNumberPages(0);
                            $book->setEditionNumber($editionNumber);
                            $book->setTotalDownload(0);
                            $book->setTotalSale(0);
                            $book->setCommentCount(0);
                            $book->setCommentScore(0);
                            $book->setViewCount(0);
                            $book->setPriceLast(0);
                            $book->setPriceNew(0);
                            $book->setPriceReasonChangeFa(0);
                            $book->setPriceReasonChangeEn(0);
                            $book->setPricePhysicalLast(0);
                            $book->setPricePhysicalNew(0);
                            $book->setPricePhysicalReasonChangeFa(0);
                            $book->setPricePhysicalReasonChangeEn(0);
                            $book->setSupportTel("");
                            $book->setSupportEmail("");
                            $book->setSite("");
                            $book->setStatus(1);
                            $book->setAutoPublish(1);
                            $book->setFilesize($sizeBookShort);
                            $book->setFilesizeFull($sizeBookFull);
                            $book->setRate(0);
                            $book->setUniqecode($folderName);
                            $book->setFileAddress($pathBookShort);
                            $book->setFileAddressFull($pathBookFull);
                            $book->setIconAddress("");
                            $book->setIconAddressThumb("");
                            $book->setDatetime(time());
                            $resultSave = $book->save();
                            if ($resultSave) {
                                $result = 1;
                            }
                        } else {
                            if (!$uploadedBookShort) $errorBookShort = 1;
                            if (!$uploadedBookFull) $errorBookFull = 1;
                            $this->general->removeFullDir($savePath);
                        }
                    } else {
                        $errorIsbnRepeat = 1;
                    }
                }
            } else {
                if (!($this->general->stringLen($isbn) > 0 and $this->general->isValidNumber($isbn))) $errorIsbn = 1;
                if (!($category > 0)) $errorCategory = 1;
                if (!(isset($bookFileShort) and $bookFileShort["size"] > 0)) $errorBookShort = 1;
                if (!(isset($bookFileFull) and $bookFileFull["size"] > 0)) $errorBookFull = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorIsbn" => $errorIsbn,
            "errorCategory" => $errorCategory,
            "errorIsbnRepeat" => $errorIsbnRepeat,
            "errorBookFull" => $errorBookFull,
            "errorBookShort" => $errorBookShort,
        ));
    }

    // info
    public function info($isbn)
    {
        $categories = null;
        $book = null;
        //
        $tblCategories = $this->TblCategory->find(array("where" => "status='1'", "order" => "title_fa asc"));
        if ($tblCategories != null and count($tblCategories) > 0) {
            foreach ($tblCategories as $category) {
                $categories[] = array
                (
                    "id" => $category->getId(),
                    "parent_id" => $category->getParentId(),
//                    "title" => $category->getTitleFa()." - ".$category->getTitleEn()
                    "title" => $category->getTitleFa()
                );
            }
        }
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null/* and $book->getStatus() != 0*/) {
            $bookId = $book->getId();
            $bookStatusId = $book->getStatus();
            $bookStatus = $book->getBookStatus();
            $bookCreators = $book->getBookCreators();
            $bookCreatorsData = null;
            if ($bookCreators != null and count($bookCreators) > 0) {
                foreach ($bookCreators as $bookCreator) {
                    $bookCreatorsData[] = array
                    (
                        "post" => $bookCreator->getPost(),
                        "name_family" => $bookCreator->getNameFamily(),
                    );
                }
            }
            // get Accesses Book
            $accessesBook = $this->getAccessesBook($bookId);
            // check book id access to page
            if (!$this->getFlagAccessBook($bookId, "book-data")) {
                if ($accessesBook["book-data"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($accessesBook["comment"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
            // check book id access
            $flagPublishBook = $this->getFlagAccessBook($bookId, "publish");
            //
            $book = array
            (
                "id" => $book->getId(),
                "isbn" => $book->getIsbn(),
                "title_fa" => $book->getTitleFa(),
//                "title_en" => $book->getTitleEn(),
                "des_fa" => $book->getDesFa(),
//                "des_en" => $book->getDesEn(),
                "language" => $book->getLanguage(),
                "publish_year" => $book->getPublishYear(),
                "number_pages" => $book->getNumberPages(),
                "edition_number" => $book->getEditionNumber(),
                "weight" => $book->getWeight(),
                "doi" => $book->getDoi(),
//                "ages_fa" => $book->getAgesFa(),
//                "ages_en" => $book->getAgesEn(),
                "creators" => $bookCreatorsData,
                "category_parent_id_1" => $book->getCategoryId1(),
                "category_id_1" => $book->getCategoryId2(),
                "category_parent_id_2" => $book->getCategoryIdSecondary1(),
                "category_id_2" => $book->getCategoryIdSecondary2(),
                "price_new" => $book->getPriceNew(),
                "price_physical_new" => $book->getPricePhysicalNew(),
                "price_reason_change_fa" => $book->getPriceReasonChangeFa(),
                "price_reason_change_en" => $book->getPriceReasonChangeEn(),
                "support_tel" => $book->getSupportTel(),
                "support_email" => $book->getSupportEmail(),
                "site" => $book->getSite(),
                "icon" => $this->general->getPathForShowFile() . $book->getIconAddressThumb(),
                "status_id" => $book->getStatus(),
                "status" => ($bookStatus) ? $bookStatus->getTitle() : "",
                "veto_reason" => $book->getVetoReason(),
                "auto_publish" => $book->getAutoPublish(),
                "flagPublishBook" => $flagPublishBook,
            );
        } /*else if($book != null and $book->getStatus() == 0)
        {
            redirect($this->config->base_url()."book/$isbn/confirm", "", 301);
        }*/
        else {
            redirect($this->config->base_url() . "book", "", 301);
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/info",
            "pageJsCss" => "book-info",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "categories" => $categories,
                "book" => $book
            )
        );
        $this->twig->display("master", $data);
    }

    public function infoSave($isbn)
    {
        $result = 0;
        $errorTitleFa = 0;
//        $errorTitleEn = 0;
        $errorDesFa = 0;
//        $errorDesEn = 0;
        $errorCategory = 0;
        $errorPhone = 0;
        $errorEmail = 0;
        $errorYear = 0;
        $errorLanguage = 0;
        $errorNumberPages = 0;
        $errorWeight = 0;
        $errorDoi = 0;
//        $errorAgesFa = 0;
//        $errorAgesEn = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $titleFa = $this->general->validateInputData($this->input->post("form-book-info-title-fa"));
//            $titleEn = $this->general->validateInputData($this->input->post("form-book-info-title-en"));
            $desFa = $this->general->validateInputData($this->input->post("form-book-info-des-fa"));
            $desFa = htmlspecialchars($desFa, ENT_QUOTES, 'UTF-8');
            $desFa = htmlspecialchars_decode(htmlspecialchars_decode($desFa));
            //            $desEn = $this->general->validateInputData($this->input->post("form-book-info-des-en"));
            $year = $this->general->validateInputData($this->input->post("form-book-info-year"));
            $language = $this->general->validateInputData($this->input->post("form-book-info-language"));
            $numberPages = $this->general->validateInputData($this->input->post("form-book-info-number-pages"));
            $weight = $this->general->validateInputData($this->input->post("form-book-info-weight"));
            $doi = $this->general->validateInputData($this->input->post("form-book-info-doi"));
//            $agesFa = $this->general->validateInputData($this->input->post("form-book-info-ages-fa"));
//            $agesEn = $this->general->validateInputData($this->input->post("form-book-info-ages-en"));
            $category = $this->general->validateInputData($this->input->post("form-book-info-category"));
            $category2 = $this->general->validateInputData($this->input->post("form-book-info-category-2"));
            $abilityFa = $this->general->validateInputData($this->input->post("form-book-info-ability-fa"));
            $abilityEn = $this->general->validateInputData($this->input->post("form-book-info-ability-en"));
            $phone = $this->general->validateInputData($this->input->post("form-book-info-phone"));
            $email = $this->general->validateInputData($this->input->post("form-book-info-email"));
            $site = $this->general->validateInputData($this->input->post("form-book-info-site"));
            $price = $this->general->validateInputData($this->input->post("form-book-info-price"));
            $priceReasonFa = $this->general->validateInputData($this->input->post("form-book-info-price-reason-fa"));
//            $priceReasonEn = $this->general->validateInputData($this->input->post("form-book-info-price-reason-en"));
            $pricePhysical = $this->general->validateInputData($this->input->post("form-book-info-price-physical"));
            $pricePhysicalReasonFa = $this->general->validateInputData($this->input->post("form-book-info-price-physical-reason-fa"));
            $pricePhysicalReasonEn = $this->general->validateInputData($this->input->post("form-book-info-price-physical-reason-en"));
            $creatorPosts = $this->input->post("form-book-info-creator-post");
            $creatorNameFamilies = $this->input->post("form-book-info-creator-name-family");
            $bookFileShort = $_FILES["form-book-file-short"];
            $bookFileFull = $_FILES["form-book-file-full"];
//            echo '<pre>'; print_r($_FILES);
            //
//            if($this->general->stringLen($titleFa) >= 3 and $this->general->stringLen($titleEn) >= 3 and $this->general->stringLen($desFa) >= 10 and $this->general->stringLen($desEn) >= 10 and $category > 0 and $phone != "" and $this->general->isValidMail($email) and $this->general->stringLen($year) == 4 and $this->general->stringLen($language) >= 3 and $numberPages > 0 and $this->general->stringLen($agesFa) >= 3 and $this->general->stringLen($agesEn) >= 3)
            if ($this->general->stringLen($titleFa) >= 3 and $this->general->stringLen($desFa) >= 10 and $category > 0 and ($email == '' OR ($email != '' AND ($this->general->isValidMail($email)))) and $this->general->stringLen($year) == 4 and $this->general->stringLen($language) >= 3 and $numberPages > 0 and $weight > 0 and $this->general->stringLen($doi) > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    $folderName = $book->getUniqecode();
                    // check book id access to page
                    $savePath = $this->general->getPathForSave() . date("Y") . "/$folderName/";
//                        $savePathTemp = $savePath . "temp/";
                    $savePathBook = $savePath . "book/";
                    $config = array
                    (
                        "upload_path" => $savePathBook,
                        "allowed_types" => "pdf|epub",
                        "max_size" => "153600",
                        "overwrite" => TRUE,
                        "file_ext_tolower" => TRUE,
                    );
                    if (isset($bookFileShort) and $bookFileShort["size"] > 0) {
                        // book short
                        $_FILES["userfile"]["name"] = $bookFileShort["name"];
                        $_FILES["userfile"]["type"] = $bookFileShort["type"];
                        $_FILES["userfile"]["tmp_name"] = $bookFileShort["tmp_name"];
                        $_FILES["userfile"]["error"] = $bookFileShort["error"];
                        $_FILES["userfile"]["size"] = $bookFileShort["size"];
                        $config["file_name"] = "book-short." . $this->general->fileType($bookFileShort["name"]);
                        $this->upload->initialize($config);
                        $uploadedBookShort = $this->upload->do_upload("form-book-file-short");
                        $sizeBookShort = $_FILES["userfile"]["size"];
                        $pathBookShort = $savePathBook . "book-short." . $this->general->fileType($bookFileShort["name"]);
                    } else {
                        $sizeBookShort = $book->getFileSize();
                        $pathBookShort = $book->getFileAddress();
                    }
                    if (isset($bookFileFull) and $bookFileFull["size"] > 0) {
                        // book full
                        $_FILES["userfile"]["name"] = $bookFileFull["name"];
                        $_FILES["userfile"]["type"] = $bookFileFull["type"];
                        $_FILES["userfile"]["tmp_name"] = $bookFileFull["tmp_name"];
                        $_FILES["userfile"]["error"] = $bookFileFull["error"];
                        $_FILES["userfile"]["size"] = $bookFileFull["size"];
                        $config["file_name"] = "book-full." . $this->general->fileType($bookFileFull["name"]);
                        $this->upload->initialize($config);
                        $uploadedBookShort = $this->upload->do_upload("form-book-file-full");
                        $sizeBookFull = $_FILES["userfile"]["size"];
                        $pathBookFull = $savePathBook . "book-full." . $this->general->fileType($bookFileFull["name"]);
                    } else {
                        $sizeBookFull = $book->getFileSizeFull();
                        $pathBookFull = $book->getFileAddressFull();
                    }
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        $categoryParent = $this->TblCategory->findFirst(array("where" => "id='$category'"));
                        $categoryParent2 = ($category2 > 0) ? $this->TblCategory->findFirst(array("where" => "id='$category2'")) : null;
                        $priceLast = ($book->getPriceNew() > 0 and $price > 10000) ? $book->getPriceNew() : 0;
                        $priceReasonFa = ($price > 10000) ? $priceReasonFa : "";
//                        $priceReasonEn = ($price > 10000) ? $priceReasonEn : "";
                        $price = ($price > 10000) ? $price : $book->getPriceNew();
                        $pricePhysicalLast = ($book->getPricePhysicalNew() > 0 and $pricePhysical > 10000) ? $book->getPricePhysicalNew() : 0;
                        $pricePhysicalReasonFa = ($pricePhysical > 10000) ? $pricePhysicalReasonFa : "";
//                        $pricePhysicalReasonEn = ($pricePhysical > 10000) ? $pricePhysicalReasonEn : "";
                        $pricePhysical = ($pricePhysical > 10000) ? $pricePhysical : $book->getPricePhysicalNew();
                        //
                        $book->setTitleFa($titleFa);
//                        $book->setTitleEn($titleEn);
                        $book->setPublishYear($year);
//                        $book->setAgesFa($agesFa);
//                        $book->setAgesEn($agesEn);
                        $book->setLanguage($language);
                        $book->setDesFa($desFa);
//                        $book->setDesEn($desEn);
                        $book->setCategoryId1(($categoryParent != null) ? $categoryParent->getParentId() : 0);
                        $book->setCategoryId2($category);
                        $book->setCategoryIdSecondary1(($categoryParent2 != null) ? $categoryParent2->getParentId() : 0);
                        $book->setCategoryIdSecondary2($category2);
                        $book->setNumberPages($numberPages);
                        $book->setWeight($weight);
                        $book->setDoi($doi);
                        $book->setPriceLast($priceLast);
                        $book->setPriceNew($price);
                        $book->setPriceReasonChangeFa($priceReasonFa);
//                        $book->setPriceReasonChangeEn($priceReasonEn);
                        $book->setPricePhysicalLast($pricePhysicalLast);
                        $book->setPricePhysicalNew($pricePhysical);
                        $book->setPricePhysicalReasonChangeFa($pricePhysicalReasonFa);
//                        $book->setPricePhysicalReasonChangeEn($pricePhysicalReasonEn);
                        $book->setSupportTel($phone);
                        $book->setSupportEmail($email);
                        $book->setSite($site);
                        $book->setFilesize($sizeBookShort);
                        $book->setFilesizeFull($sizeBookFull);
                        $book->setFileAddress($pathBookShort);
                        $book->setFileAddressFull($pathBookFull);
                        $resultSave = $book->save();
                        if ($resultSave) {
                            $result = 1;
                            // delete creators
                            $bookCreators = $book->getBookCreators();
                            if ($bookCreators != null and count($bookCreators) > 0) {
                                foreach ($bookCreators as $bookCreator) {
                                    $bookCreator->delete();
                                }
                            }
                            // creators
                            if ($creatorNameFamilies != null and count($creatorNameFamilies) > 0) {
                                foreach ($creatorNameFamilies as $key => $creatorNameFamily) {
                                    $creatorPost = $this->general->validateInputData($creatorPosts[$key]);
                                    $creatorNameFamily = $this->general->validateInputData($creatorNameFamily);
                                    if ($creatorPost != "" and $creatorNameFamily != "") {
                                        $bookCreator = new TblBookCreator();
                                        $bookCreator->setBookId($bookId);
                                        $bookCreator->setNameFamily($creatorNameFamily);
                                        $bookCreator->setPost($creatorPost);
                                        $bookCreator->save();
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if (!($this->general->stringLen($titleFa) >= 3)) $errorTitleFa = 1;
//                if(!($this->general->stringLen($titleEn) >= 3)) $errorTitleEn = 1;
                if (!($this->general->stringLen($desFa) >= 10)) $errorDesFa = 1;
//                if(!($this->general->stringLen($desEn) >= 10)) $errorDesEn = 1;
                if (!($category > 0)) $errorCategory = 1;
                if (!($phone != "")) $errorPhone = 1;
                if (!($email == '' OR ($email != '' AND ($this->general->isValidMail($email))))) $errorEmail = 1;
                if (!($this->general->stringLen($year) == 4)) $errorYear = 1;
                if (!($this->general->stringLen($language) >= 3)) $errorLanguage = 1;
                if (!($numberPages > 0)) $errorNumberPages = 1;
                if (!($weight > 0)) $errorWeight = 1;
                if (!($this->general->stringLen($doi) == 0)) $errorDoi = 1;
//                if(!($this->general->stringLen($agesFa) >= 3)) $errorAgesFa = 1;
//                if(!($this->general->stringLen($agesEn) >= 3)) $errorAgesEn = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorTitleFa" => $errorTitleFa,
//            "errorTitleEn" => $errorTitleEn,
            "errorDesFa" => $errorDesFa,
//            "errorDesEn" => $errorDesEn,
            "errorCategory" => $errorCategory,
            "errorPhone" => $errorPhone,
            "errorEmail" => $errorEmail,
            "errorYear" => $errorYear,
            "errorLanguage" => $errorLanguage,
            "errorNumberPages" => $errorNumberPages,
            "errorWeight" => $errorWeight,
            "errorDoi" => $errorDoi,
//            "errorAgesFa" => $errorAgesFa,
//            "errorAgesEn" => $errorAgesEn,
        ));
    }

    // media
    public function media($isbn)
    {
        $medias = null;
        $book = null;
        //
        $tblMedias = $this->TblMedia->find(array("where" => "status='1'", "order" => "title_fa asc"));
        if ($tblMedias != null and count($tblMedias) > 0) {
            foreach ($tblMedias as $media) {
                $medias[] = array
                (
                    "id" => $media->getId(),
                    "title" => $media->getTitleFa()
                );
            }
        }
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
            $bookStatusId = $book->getStatus();
            $bookStatus = $book->getBookStatus();
            // get Accesses Book
            $accessesBook = $this->getAccessesBook($bookId);
            // check book id access to page
            if (!$this->getFlagAccessBook($bookId, "book-data")) {
                if ($accessesBook["book-data"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($accessesBook["comment"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
            // check book id access
            $flagPublishBook = $this->getFlagAccessBook($bookId, "publish");
            //
            $book = array
            (
                "id" => $book->getId(),
                "isbn" => $book->getIsbn(),
                "title_fa" => $book->getTitleFa(),
                "title_en" => $book->getTitleEn(),
                "des_fa" => $book->getDesFa(),
                "des_en" => $book->getDesEn(),
                "language" => $book->getLanguage(),
                "publish_year" => $book->getPublishYear(),
                "number_pages" => $book->getNumberPages(),
                "ages_fa" => $book->getAgesFa(),
                "ages_en" => $book->getAgesEn(),
                "category_parent_id_1" => $book->getCategoryId1(),
                "category_id_1" => $book->getCategoryId2(),
                "category_parent_id_2" => $book->getCategoryIdSecondary1(),
                "category_id_2" => $book->getCategoryIdSecondary2(),
                "price_new" => $book->getPriceNew(),
                "price_physical_new" => $book->getPricePhysicalNew(),
                "price_reason_change_fa" => $book->getPriceReasonChangeFa(),
                "price_reason_change_en" => $book->getPriceReasonChangeEn(),
                "support_tel" => $book->getSupportTel(),
                "support_email" => $book->getSupportEmail(),
                "site" => $book->getSite(),
                "icon" => $this->general->getPathForShowFile() . $book->getIconAddressThumb(),
                "status_id" => $book->getStatus(),
                "status" => ($bookStatus) ? $bookStatus->getTitle() : "",
                "veto_reason" => $book->getVetoReason(),
                "auto_publish" => $book->getAutoPublish(),
                "flagPublishBook" => $flagPublishBook,
            );
        } else {
            redirect($this->config->base_url() . "book", "", 301);
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/media",
            "pageJsCss" => "book-media",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "medias" => $medias,
                "book" => $book
            )
        );
        $this->twig->display("master", $data);
    }

    public function mediaList($isbn)
    {
        $mediaNewsData = null;
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
            // check book id access to page
            //if($this->getFlagAccessBook($bookId, "book-data"))
            {
                $tblMediaNews = $this->TblMediaNews->find(array("where" => "user_id='" . $this->developer->developerId . "'", "order" => "datetime asc"));
                if ($tblMediaNews != null and count($tblMediaNews) > 0) {
                    foreach ($tblMediaNews as $mediaNews) {
                        switch ($mediaNews->getStatus()) {
                            case "1":
                                $status = "تایید شده";
                                break;
                            case "2":
                                $status = "رد شده";
                                break;
                            default:
                                $status = "در صف بررسی";
                        }
                        $media = $mediaNews->getMedia();
                        //
                        $mediaNewsData[] = array
                        (
                            "id" => $mediaNews->getId(),
                            "title" => $this->general->htmlSpecialCharsDecode($mediaNews->getTitleFa()),
                            "des" => $this->general->htmlSpecialCharsDecode(nl2br($mediaNews->getDesFa())),
                            "link" => $mediaNews->getLink(),
                            "media_id" => $mediaNews->getMediaId(),
                            "media_title" => ($media != null) ? $this->general->htmlSpecialCharsDecode($media->getTitleFa()) : "",
                            "status" => $status,
                            "date" => $this->datetime2->getJalaliDate($mediaNews->getDatetime(), "Y/m/d")
                        );
                    }
                }
            }
        }
        //
        echo json_encode
        (
            array("data" => $mediaNewsData)
        );
    }

    public function mediaSave($isbn)
    {
        $result = 0;
        $errorTitle = 0;
        $errorDes = 0;
        $errorMedia = 0;
        $errorLink = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $title = $this->general->validateInputData($this->input->post("form-book-media-news-title"));
            $link = $this->general->validateInputData($this->input->post("form-book-media-news-link"));
            $des = $this->general->validateInputData($this->input->post("form-book-media-news-des"));
            $media = $this->general->validateInputData($this->input->post("form-book-media-news-media"));
            //
            if ($this->general->stringLen($title) >= 3 and $this->general->stringLen($des) >= 10 and $media > 0 and $this->general->isValidLink($link)) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    //if($this->getFlagAccessBook($bookId, "book-data"))
                    {
                        $mediaNews = new TblMediaNews();
                        $mediaNews->setUserId($this->developer->developerId);
                        $mediaNews->setBookId($bookId);
                        $mediaNews->setTitleFa($title);
                        $mediaNews->setTitleEn("");
                        $mediaNews->setDesFa($des);
                        $mediaNews->setDesEn("");
                        $mediaNews->setMediaId($media);
                        $mediaNews->setLink($link);
                        $mediaNews->setStatus(0);
                        $mediaNews->setDatetime(time());
                        $resultSave = $mediaNews->save();
                        if ($resultSave) {
                            $result = 1;
                        }
                    }
                }
            } else {
                if (!($this->general->stringLen($title) >= 3)) $errorTitle = 1;
                if (!($this->general->stringLen($des) >= 10)) $errorDes = 1;
                if (!($media > 0)) $errorMedia = 1;
                if (!($this->general->isValidLink($link))) $errorLink = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorTitle" => $errorTitle,
            "errorDes" => $errorDes,
            "errorMedia" => $errorMedia,
            "errorLink" => $errorLink
        ));
    }

    public function mediaEdit($isbn)
    {
        $result = 0;
        $errorTitle = 0;
        $errorDes = 0;
        $errorMedia = 0;
        $errorLink = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $id = $this->general->validateInputData($this->input->post("form-book-media-news-id"));
            $title = $this->general->validateInputData($this->input->post("form-book-media-news-title"));
            $link = $this->general->validateInputData($this->input->post("form-book-media-news-link"));
            $des = $this->general->validateInputData($this->input->post("form-book-media-news-des"));
            $media = $this->general->validateInputData($this->input->post("form-book-media-news-media"));
            //
            if ($id > 0 and $this->general->stringLen($title) >= 3 and $this->general->stringLen($des) >= 10 and $media > 0 and $this->general->isValidLink($link)) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    //if($this->getFlagAccessBook($bookId, "book-data"))
                    {
                        $mediaNews = $this->TblMediaNews->findFirst(array("where" => "id='$id' and user_id='" . $this->developer->developerId . "'"));
                        if ($mediaNews != null) {
                            $mediaNews->setTitleFa($title);
                            $mediaNews->setTitleEn("");
                            $mediaNews->setDesFa($des);
                            $mediaNews->setDesEn("");
                            $mediaNews->setMediaId($media);
                            $mediaNews->setLink($link);
                            $mediaNews->setStatus(0);
                            $mediaNews->setDatetime(time());
                            $resultSave = $mediaNews->save();
                            if ($resultSave) {
                                $result = 1;
                            }
                        }
                    }
                }
            } else {
                if (!($this->general->stringLen($title) >= 3)) $errorTitle = 1;
                if (!($this->general->stringLen($des) >= 10)) $errorDes = 1;
                if (!($media > 0)) $errorMedia = 1;
                if (!($this->general->isValidLink($link))) $errorLink = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorTitle" => $errorTitle,
            "errorDes" => $errorDes,
            "errorMedia" => $errorMedia,
            "errorLink" => $errorLink
        ));
    }

    public function mediaDelete($isbn)
    {
        $result = 0;
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $id = $this->general->validateInputData($this->input->post("id"));
            //
            if ($id > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    //if($this->getFlagAccessBook($bookId, "book-data"))
                    {
                        $mediaNews = $this->TblMediaNews->findFirst(array("where" => "id='$id' and user_id='" . $this->developer->developerId . "'"));
                        if ($mediaNews != null) {
                            // delete
                            $resultDelete = $mediaNews->delete();
                            if ($resultDelete) {
                                $result = 1;
                            }
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

    // images
    public function images($isbn)
    {
        $screenShots = null;
        $videos = null;
        $audios = null;
        $book = null;
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
            $bookStatusId = $book->getStatus();
            $bookStatus = $book->getBookStatus();
            // get Accesses Book
            $accessesBook = $this->getAccessesBook($bookId);
            // check book id access to page
            if (!$this->getFlagAccessBook($bookId, "book-data")) {
                if ($accessesBook["book-data"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($accessesBook["comment"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
            // check book id access
            $flagPublishBook = $this->getFlagAccessBook($bookId, "publish");
            //
            $book = array
            (
                "id" => $book->getId(),
                "isbn" => $book->getIsbn(),
                "title_fa" => $book->getTitleFa(),
                "title_en" => $book->getTitleEn(),
                "des_fa" => $book->getDesFa(),
                "des_en" => $book->getDesEn(),
                "language" => $book->getLanguage(),
                "publish_year" => $book->getPublishYear(),
                "number_pages" => $book->getNumberPages(),
                "ages_fa" => $book->getAgesFa(),
                "ages_en" => $book->getAgesEn(),
                "category_parent_id_1" => $book->getCategoryId1(),
                "category_id_1" => $book->getCategoryId2(),
                "category_parent_id_2" => $book->getCategoryIdSecondary1(),
                "category_id_2" => $book->getCategoryIdSecondary2(),
                "price_new" => $book->getPriceNew(),
                "price_physical_new" => $book->getPricePhysicalNew(),
                "price_reason_change_fa" => $book->getPriceReasonChangeFa(),
                "price_reason_change_en" => $book->getPriceReasonChangeEn(),
                "support_tel" => $book->getSupportTel(),
                "support_email" => $book->getSupportEmail(),
                "site" => $book->getSite(),
                "icon" => $this->general->getPathForShowFile() . $book->getIconAddressThumb(),
                "status_id" => $book->getStatus(),
                "status" => ($bookStatus) ? $bookStatus->getTitle() : "",
                "veto_reason" => $book->getVetoReason(),
                "auto_publish" => $book->getAutoPublish(),
                "flagPublishBook" => $flagPublishBook,
            );
            // screenShot
            $bookImages = $this->TblBookImage->find(array("where" => "book_id='$bookId'", "order" => "id desc"));
            if ($bookImages != null) {
                foreach ($bookImages as $sh) {
                    $imgUrl = $this->general->getPathForShowFile() . $sh->getThumbName();
                    $screenShots[] = array
                    (
                        "id" => $sh->getId(),
                        "url" => $imgUrl
                    );
                }
            }
            // video
            $bookVideos = $this->TblBookVideo->find(array("where" => "book_id='$bookId'", "order" => "id desc"));
            if ($bookVideos != null) {
                foreach ($bookVideos as $bookVideo) {
                    $videoUrl = $this->general->getPathForShowFile() . $bookVideo->getName();
                    $imgUrl = $this->general->getPathForShowFile() . $bookVideo->getImgThumb();
                    $videos[] = array
                    (
                        "id" => $bookVideo->getId(),
                        "video" => $videoUrl,
                        "url" => $imgUrl
                    );
                }
            }
            // audio
            $bookAudios = $this->TblBookAudio->find(array("where" => "book_id='$bookId'", "order" => "id desc"));
            if ($bookAudios != null) {
                foreach ($bookAudios as $bookAudio) {
                    $audioUrl = $this->general->getPathForShowFile() . $bookAudio->getName();
                    $audios[] = array
                    (
                        "id" => $bookAudio->getId(),
                        "url" => $audioUrl
                    );
                }
            }
        } else {
            redirect($this->config->base_url() . "book", "", 301);
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/images",
            "pageJsCss" => "book-images",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "screenShots" => $screenShots,
                "videos" => $videos,
                "audios" => $audios,
                "book" => $book
            )
        );
        $this->twig->display("master", $data);
    }

    public function iconSave($isbn)
    {
        $result = 0;
        $errorImage = 0;
        $iconUrl = "";
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $iconImage = $_FILES["form-book-icon-img"];
            //
            if (isset($iconImage) and $iconImage["size"] > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        $bookFolderName = $book->getUniqecode();
                        $bookFolderYear = date("Y");
                        //
                        $uploadImage = null;
                        $uploadedImage = false;
                        // upload image
                        $savePath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/icon/";
                        $config = array
                        (
                            "upload_path" => $savePath,
                            "allowed_types" => "png",
                            "max_size" => "2000",
                            "max_width" => "2000",
                            "max_height" => "2000",
                            "min_width" => "200",
                            "min_height" => "200",
                            "overwrite" => TRUE,
                            "file_ext_tolower" => TRUE,
                        );
                        $this->upload->initialize($config);
                        $uploadImage = $this->upload;
                        $uploadedImage = $uploadImage->do_upload("form-book-icon-img");
                        //
                        if ($uploadedImage) {
                            $iconImageName = $isbn . $uploadImage->file_ext;
                            $iconImageName128 = $isbn . "_128_128" . $uploadImage->file_ext;
                            $pathIconImage = $savePath . $iconImageName;
                            $pathIconImage_db = str_replace("../", "/", $pathIconImage);
                            $pathIconImage128 = $savePath . $iconImageName128;
                            $pathIconImage128_db = str_replace("../", "/", $pathIconImage128);
                            // rename & build thumb
                            rename($savePath . $uploadImage->file_name, $pathIconImage);
                            $this->general->buildThumbnail($pathIconImage, $pathIconImage128, 128, 128);
                            // save
                            $book->setIconAddress($pathIconImage_db);
                            $book->setIconAddressThumb($pathIconImage128_db);
                            $resultSave = $book->save();
                            if ($resultSave) {
                                $result = 1;
                                $iconUrl = $this->general->getPathForShowFile() . $pathIconImage128_db;
                            }
                        } else {
                            $errorImage = 1;
                        }
                    }
                }
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorImage" => $errorImage,
            "iconUrl" => $iconUrl,
        ));
    }

    public function screenShotSave($isbn)
    {
        $result = 0;
        $errorImage = 0;
        $imgId = "";
        $imgUrl = "";
        $screenShotClass = "";
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $screenShotImage = $_FILES["form-book-screenshot-img"];
            $screenShotClass = $_POST["form-book-screenshot-class"];
            //
            if (isset($screenShotImage) and $screenShotImage["size"] > 0 and $screenShotClass != "") {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    $bookFolderName = $book->getUniqecode();
                    $bookFolderYear = date("Y");
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        //
                        $uploadImage = null;
                        $uploadedImage = false;
                        // upload image
                        $savePath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/screenshot/";
                        $config = array
                        (
                            "upload_path" => $savePath,
                            "allowed_types" => "png|jpg|jpeg",
                            "max_size" => "500",
                            "overwrite" => TRUE,
                            "file_ext_tolower" => TRUE,
                        );
                        $this->upload->initialize($config);
                        $uploadImage = $this->upload;
                        $uploadedImage = $uploadImage->do_upload("form-book-screenshot-img");
                        //
                        if ($uploadedImage) {
                            $screenShotImageName = time() . "_" . $isbn . $uploadImage->file_ext;
                            $screenShotImageNameThumb = "thumb_$screenShotImageName";
                            $pathScreenShotImage = $savePath . $screenShotImageName;
                            $pathScreenShotImage_db = str_replace("../", "/", $pathScreenShotImage);
                            $pathScreenShotImageThumb = $savePath . $screenShotImageNameThumb;
                            $pathScreenShotImageThumb_db = str_replace("../", "/", $pathScreenShotImageThumb);
                            // rename & build thumb & watermark
                            rename($savePath . $uploadImage->file_name, $pathScreenShotImage);
                            // build thumb
                            $this->general->buildThumbnail($pathScreenShotImage, $pathScreenShotImageThumb, 256, 256);
                            // watermark
                            $configWM['source_image'] = $pathScreenShotImage;
                            $configWM['wm_type'] = 'overlay';
                            $configWM['wm_overlay_path'] = './public/images/logo-book.png';
                            //$configWM['width'] = '93';
                            //$configWM['height'] = '41';
                            $configWM['wm_opacity'] = '100';
                            $configWM['wm_vrt_alignment'] = 'bottom';
                            $configWM['wm_hor_alignment'] = 'right';
                            $configWM['wm_hor_offset'] = '50';
                            $configWM['wm_vrt_offset'] = '50';
                            $this->load->library('image_lib');
                            $this->image_lib->initialize($configWM);
                            $this->image_lib->watermark();
                            // watermark
                            $configWM['source_image'] = $pathScreenShotImageThumb;
                            $configWM['wm_type'] = 'overlay';
                            $configWM['wm_overlay_path'] = './public/images/logo-book.png';
                            $configWM['width'] = '40';
                            $configWM['height'] = '18';
                            $configWM['wm_opacity'] = '100';
                            $configWM['wm_vrt_alignment'] = 'bottom';
                            $configWM['wm_hor_alignment'] = 'right';
                            $configWM['wm_hor_offset'] = '10';
                            $configWM['wm_vrt_offset'] = '10';
                            $this->load->library('image_lib');
                            $this->image_lib->initialize($configWM);
                            $this->image_lib->watermark();
                            // save
                            $bookImage = new TblBookImage();
                            $bookImage->setBookid($bookId);
                            $bookImage->setName($pathScreenShotImage_db);
                            $bookImage->setThumbName($pathScreenShotImageThumb_db);
                            $bookImage->setType("portrait");
                            $resultSave = $bookImage->save();
                            if ($resultSave) {
                                $result = 1;
                                $imgId = $bookImage->getId();
                                $imgUrl = $this->general->getPathForShowFile() . $pathScreenShotImageThumb_db;
                            }
                        } else {
                            $errorImage = 1;
                        }
                    }
                }
            } else {
                $errorImage = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "imgId" => $imgId,
            "imgUrl" => $imgUrl,
            "errorImage" => $errorImage,
            "screenShotClass" => $screenShotClass,
        ));
    }

    public function screenShotDelete($isbn)
    {
        $result = 0;
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $id = $this->general->validateInputData($this->input->post("id"));
            //
            if ($id > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        $bookImage = $this->TblBookImage->findFirst(array("where" => "id='$id' and book_id='$bookId'"));
                        if ($bookImage != null) {
                            // delete
                            $resultDelete = $bookImage->delete();
                            if ($resultDelete) {
                                $result = 1;
                                //
                                if (is_file($this->general->getPathForDeleteFile() . $bookImage->getName())) unlink($this->general->getPathForDeleteFile() . $bookImage->getName());
                                if (is_file($this->general->getPathForDeleteFile() . $bookImage->getThumbName())) unlink($this->general->getPathForDeleteFile() . $bookImage->getThumbName());
                            }
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

    public function videoSave($isbn)
    {
        $result = 0;
        $errorVideo = 0;
        $videoId = "";
        $videoUrl = "";
        $imgUrl = "";
        $videoClass = "";
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $videoFile = $_FILES["form-book-video-file"];
            $videoClass = $_POST["form-book-video-class"];
            //
            if (isset($videoFile) and $videoFile["size"] > 0 and $videoClass != "") {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $datetime = time();
                    $bookId = $book->getId();
                    $bookFolderName = $book->getUniqecode();
                    $bookFolderYear = date("Y");
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        // upload video
                        $savePath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/video/";
                        $config = array
                        (
                            "upload_path" => $savePath,
                            "file_name" => "$datetime-$isbn." . $this->general->fileType($videoFile["name"]),
                            "allowed_types" => "mp4",
                            "max_size" => "7168",
                            "overwrite" => TRUE,
                            "file_ext_tolower" => TRUE,
                        );
                        $this->upload->initialize($config);
                        $uploadVideo = $this->upload;
                        $uploadedVideo = $uploadVideo->do_upload("form-book-video-file");
                        //
                        if ($uploadedVideo) {
                            // screenShot
                            $videoPath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/video/" . $uploadVideo->file_name;
                            $videoPath_db = str_replace("../", "/", $videoPath);
                            /* $screenShotContent = $this->general->screenShotOfVideo($videoPath);
                             $pathScreenShot = $savePath . "$datetime-$isbn.jpg";
                             $pathScreenShot_db = str_replace("../", "/", $pathScreenShot);
                             $pathScreenShotThumb = $savePath . "$datetime-$isbn-thumb.jpg";
                             $pathScreenShotThumb_db = str_replace("../", "/", $pathScreenShotThumb);
                             // save & build thumb & watermark
                             file_put_contents($pathScreenShot, $screenShotContent);
                             // build thumb
                             $this->general->buildThumbnail($pathScreenShot, $pathScreenShotThumb, 320, 180);
                             // watermark
                             $configWM['source_image'] = $pathScreenShot;
                             $configWM['wm_type'] = 'overlay';
                             $configWM['wm_overlay_path'] = './public/images/logo-book.png';
                             $configWM['wm_opacity'] = '100';
                             $configWM['wm_vrt_alignment'] = 'bottom';
                             $configWM['wm_hor_alignment'] = 'right';
                             $configWM['wm_hor_offset'] = '50';
                             $configWM['wm_vrt_offset'] = '50';
                             $this->load->library('image_lib');
                             $this->image_lib->initialize($configWM);
                             $this->image_lib->watermark();
                             // watermark
                             $configWM['source_image'] = $pathScreenShotThumb;
                             $configWM['wm_type'] = 'overlay';
                             $configWM['wm_overlay_path'] = './public/images/logo-book.png';
                             $configWM['width'] = '40';
                             $configWM['height'] = '18';
                             $configWM['wm_opacity'] = '100';
                             $configWM['wm_vrt_alignment'] = 'bottom';
                             $configWM['wm_hor_alignment'] = 'right';
                             $configWM['wm_hor_offset'] = '10';
                             $configWM['wm_vrt_offset'] = '10';
                             $this->load->library('image_lib');
                             $this->image_lib->initialize($configWM);
                             $this->image_lib->watermark();*/
                            // save
                            $bookVideo = new TblBookVideo();
                            $bookVideo->setBookid($bookId);
                            $bookVideo->setName($videoPath_db);
//                            $bookVideo->setImg($pathScreenShot_db);
//                            $bookVideo->setImgThumb($pathScreenShotThumb_db);
                            $resultSave = $bookVideo->save();
                            if ($resultSave) {
                                $result = 1;
                                $videoId = $bookVideo->getId();
                                $videoUrl = $this->general->getPathForShowFile() . $videoPath;
//                                $imgUrl = $this->general->getPathForShowFile() . $pathScreenShotThumb_db;
                            }
                        } else {
                            $errorVideo = 1;
                        }
                    }
                }
            } else {
                $errorVideo = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "videoId" => $videoId,
            "videoUrl" => $videoUrl,
            "imgUrl" => $imgUrl,
            "errorVideo" => $errorVideo,
            "videoClass" => $videoClass,
        ));
    }

    public function videoDelete($isbn)
    {
        $result = 0;
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $id = $this->general->validateInputData($this->input->post("id"));
            //
            if ($id > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        $bookVideo = $this->TblBookVideo->findFirst(array("where" => "id='$id' and book_id='$bookId'"));
                        if ($bookVideo != null) {
                            // delete
                            $resultDelete = $bookVideo->delete();
                            if ($resultDelete) {
                                $result = 1;
                                //
                                if (is_file($this->general->getPathForDeleteFile() . $bookVideo->getName())) unlink($this->general->getPathForDeleteFile() . $bookVideo->getName());
                                if (is_file($this->general->getPathForDeleteFile() . $bookVideo->getImg())) unlink($this->general->getPathForDeleteFile() . $bookVideo->getImg());
                                if (is_file($this->general->getPathForDeleteFile() . $bookVideo->getImgThumb())) unlink($this->general->getPathForDeleteFile() . $bookVideo->getImgThumb());
                            }
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

    public function audioSave($isbn)
    {
        $result = 0;
        $errorAudio = 0;
        $audioId = "";
        $audioUrl = "";
        $audioClass = "";
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $audioFile = $_FILES["form-book-audio-file"];
            $audioClass = $_POST["form-book-audio-class"];
            //
            if (isset($audioFile) and $audioFile["size"] > 0 and $audioClass != "") {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    // upload video
                    $datetime = time();
                    $bookId = $book->getId();
                    $bookFolderName = $book->getUniqecode();
                    $bookFolderYear = date("Y");
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        // upload audio
                        $savePath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/audio/";
                        $config = array
                        (
                            "upload_path" => $savePath,
                            "file_name" => "$datetime-$isbn." . $this->general->fileType($audioFile["name"]),
                            "allowed_types" => "mp3",
                            "max_size" => "30072",
                            "overwrite" => TRUE,
                            "file_ext_tolower" => TRUE,
                        );
                        $this->upload->initialize($config);
                        $uploadAudio = $this->upload;
                        $uploadedAudio = $uploadAudio->do_upload("form-book-audio-file");
                        //
                        if ($uploadedAudio) {
                            $audioPath = $this->general->getPathForSave() . "$bookFolderYear/$bookFolderName/audio/" . $uploadAudio->file_name;
                            $audioPath_db = str_replace("../", "/", $audioPath);
                            // save
                            $bookAudio = new TblBookAudio();
                            $bookAudio->setBookid($bookId);
                            $bookAudio->setName($audioPath_db);
                            $resultSave = $bookAudio->save();
                            if ($resultSave) {
                                $result = 1;
                                $audioId = $bookAudio->getId();
                                $audioUrl = $this->general->getPathForShowFile() . $audioPath;
                            }
                        } else {
                            $errorAudio = 1;
                        }
                    }
                }
            } else {
                $errorAudio = 1;
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "audioId" => $audioId,
            "audioUrl" => $audioUrl,
            "errorAudio" => $errorAudio,
            "audioClass" => $audioClass,
        ));
    }

    public function audioDelete($isbn)
    {
        $result = 0;
        //
        if ($this->general->checkRefOfMySite()) {
            // get data
            $id = $this->general->validateInputData($this->input->post("id"));
            //
            if ($id > 0) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    if ($this->getFlagAccessBook($bookId, "book-data")) {
                        $bookAudio = $this->TblBookAudio->findFirst(array("where" => "id='$id' and book_id='$bookId'"));
                        if ($bookAudio != null) {
                            // delete
                            $resultDelete = $bookAudio->delete();
                            if ($resultDelete) {
                                $result = 1;
                                //
                                if (is_file($this->general->getPathForDeleteFile() . $bookAudio->getName())) unlink($this->general->getPathForDeleteFile() . $bookAudio->getName());
                            }
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

    // statistic
    public function statistic($isbn)
    {
        $book = null;
        $totalView = null;
        $totalDownload = null;
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
            $bookStatusId = $book->getStatus();
            $bookStatus = $book->getBookStatus();
            // get Accesses Book
            $accessesBook = $this->getAccessesBook($bookId);
            // check book id access to page
            if (!$this->getFlagAccessBook($bookId, "book-data")) {
                if ($accessesBook["book-data"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($accessesBook["comment"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
            // check book id access
            $flagPublishBook = $this->getFlagAccessBook($bookId, "publish");
            //
            $book = array
            (
                "id" => $book->getId(),
                "isbn" => $book->getIsbn(),
                "title_fa" => $book->getTitleFa(),
                "title_en" => $book->getTitleEn(),
                "des_fa" => $book->getDesFa(),
                "des_en" => $book->getDesEn(),
                "language" => $book->getLanguage(),
                "publish_year" => $book->getPublishYear(),
                "number_pages" => $book->getNumberPages(),
                "ages_fa" => $book->getAgesFa(),
                "ages_en" => $book->getAgesEn(),
                "category_parent_id_1" => $book->getCategoryId1(),
                "category_id_1" => $book->getCategoryId2(),
                "category_parent_id_2" => $book->getCategoryIdSecondary1(),
                "category_id_2" => $book->getCategoryIdSecondary2(),
                "price_new" => $book->getPriceNew(),
                "price_physical_new" => $book->getPricePhysicalNew(),
                "price_reason_change_fa" => $book->getPriceReasonChangeFa(),
                "price_reason_change_en" => $book->getPriceReasonChangeEn(),
                "support_tel" => $book->getSupportTel(),
                "support_email" => $book->getSupportEmail(),
                "site" => $book->getSite(),
                "icon" => $this->general->getPathForShowFile() . $book->getIconAddressThumb(),
                "status_id" => $book->getStatus(),
                "status" => ($bookStatus) ? $bookStatus->getTitle() : "",
                "veto_reason" => $book->getVetoReason(),
                "auto_publish" => $book->getAutoPublish(),
                "flagPublishBook" => $flagPublishBook,
            );
            $totalViewCount = $this->TblBookView->count("book_id='$bookId'");
            $totalDownloadCount = $this->TblBookDownload->count("book_id='$bookId'");
            $totalViewDateTime = $this->TblBookView->findFirst(array("where" => "book_id='$bookId'", "order" => "datetime desc"));
            $totalViewDate = ($totalViewDateTime != null) ? $this->datetime2->getJalaliDate($totalViewDateTime->getDatetime(), "Y/m/d") : "";
            $totalViewTime = ($totalViewDateTime != null) ? $this->datetime2->getTime($totalViewDateTime->getDatetime()) : "";
            $totalDownloadDateTime = $this->TblBookDownload->findFirst(array("where" => "book_id='$bookId'", "order" => "datetime desc"));
            $totalDownloadDate = ($totalDownloadDateTime != null) ? $this->datetime2->getJalaliDate($totalDownloadDateTime->getDatetime(), "Y/m/d") : "";
            $totalDownloadTime = ($totalDownloadDateTime != null) ? $this->datetime2->getTime($totalDownloadDateTime->getDatetime()) : "";
            //
            $totalView = array
            (
                "count" => $totalViewCount,
                "date" => $totalViewDate,
                "time" => $totalViewTime,
            );
            $totalDownload = array
            (
                "count" => $totalDownloadCount,
                "date" => $totalDownloadDate,
                "time" => $totalDownloadTime,
            );
        } else {
            redirect($this->config->base_url() . "book", "", 301);
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/statistic",
            "pageJsCss" => "book-statistic",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "book" => $book,
                "totalView" => $totalView,
                "totalDownload" => $totalDownload,
            )
        );
        $this->twig->display("master", $data);
    }

    public function statisticList($isbn)
    {
        $statisticData = null;
        $errorDate = 0;
        $errorDateBig = 0;
        // get data
        $type = $this->general->validateInputData($this->input->post("type"));
        $dateStart = $this->general->validateInputData($this->input->post("date-start"));
        $dateEnd = $this->general->validateInputData($this->input->post("date-end"));
        $dateTimeStart = ($dateStart != "") ? $this->datetime2->shamsiToDatetimeSecond($dateStart, "00:00:00") : $this->datetime2->datetimeBeforeNDays(time(), 30);
        $dateTimeEnd = ($dateEnd != "") ? $this->datetime2->shamsiToDatetimeSecond($dateEnd, "23:59:59") : time();
        //
        if ($dateTimeStart > 0 and $dateTimeEnd > 0 and $dateTimeStart < $dateTimeEnd) {
            if ($type == "download" or $type == "view") {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    // check book id access to page
                    //if($this->getFlagAccessBook($bookId, "book-data"))
                    {
                        $countDays = $this->datetime2->countDaysBetween2Dates($dateTimeStart, $dateTimeEnd);
                        //
                        if ($countDays <= 90) {
                            if ($type == "download") {
                                for ($i = 0; $i <= $countDays; $i++) {
                                    $temp_dateTimeStart = $this->datetime2->datetimeAfterNDays($dateTimeStart, $i);
                                    $temp_dateTimeEnd = $this->datetime2->shamsiToDatetimeSecond($this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"), "23:59:59");
                                    $count = $this->TblBookDownload->count("book_id='$bookId' and datetime >= $temp_dateTimeStart and datetime <= $temp_dateTimeEnd");
                                    $statisticData[] = array
                                    (
                                        "day" => $this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"),
                                        "count" => $count,
                                    );
                                }
                            } else if ($type == "view") {
                                for ($i = 0; $i <= $countDays; $i++) {
                                    $temp_dateTimeStart = $this->datetime2->datetimeAfterNDays($dateTimeStart, $i);
                                    $temp_dateTimeEnd = $this->datetime2->shamsiToDatetimeSecond($this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"), "23:59:59");
                                    $count = $this->TblBookView->count("book_id='$bookId' and datetime >= $temp_dateTimeStart and datetime <= $temp_dateTimeEnd");
                                    $statisticData[] = array
                                    (
                                        "day" => $this->datetime2->getJalaliDate($temp_dateTimeStart, "Y/m/d"),
                                        "count" => $count,
                                    );
                                }
                            }
                        } else {
                            $errorDateBig = 1;
                        }
                    }
                }
            }
        } else {
            $errorDate = 1;
        }
        //
        echo json_encode
        (
            array("data" => $statisticData, "errorDateBig" => $errorDateBig, "errorDate" => $errorDate)
        );
    }

    // comments
    public function comments($isbn)
    {
        $bookData = null;
        $bookScore = 0;
        $bookCountScore = 0;
        $bookCountComment = 0;
        $scores = null;
        //
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
            $bookStatusId = $book->getStatus();
            $bookStatus = $book->getBookStatus();
            // get Accesses Book
            $accessesBook = $this->getAccessesBook($bookId);
            // check book id access to page
            if (!$this->getFlagAccessBook($bookId, "comment")) {
                if ($accessesBook["book-data"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/info", "", 301);
                } elseif ($accessesBook["comment"] == 1) {
                    redirect($this->config->base_url() . "book/$isbn/comments", "", 301);
                } else {
                    redirect($this->config->base_url(), "", 301);
                }
            }
            // check book id access
            $flagPublishBook = $this->getFlagAccessBook($bookId, "publish");
            //
            $bookData = array
            (
                "id" => $book->getId(),
                "isbn" => $book->getIsbn(),
                "title_fa" => $book->getTitleFa(),
                "title_en" => $book->getTitleEn(),
                "des_fa" => $book->getDesFa(),
                "des_en" => $book->getDesEn(),
                "language" => $book->getLanguage(),
                "publish_year" => $book->getPublishYear(),
                "number_pages" => $book->getNumberPages(),
                "ages_fa" => $book->getAgesFa(),
                "ages_en" => $book->getAgesEn(),
                "category_parent_id_1" => $book->getCategoryId1(),
                "category_id_1" => $book->getCategoryId2(),
                "category_parent_id_2" => $book->getCategoryIdSecondary1(),
                "category_id_2" => $book->getCategoryIdSecondary2(),
                "price_new" => $book->getPriceNew(),
                "price_physical_new" => $book->getPricePhysicalNew(),
                "price_reason_change_fa" => $book->getPriceReasonChangeFa(),
                "price_reason_change_en" => $book->getPriceReasonChangeEn(),
                "support_tel" => $book->getSupportTel(),
                "support_email" => $book->getSupportEmail(),
                "site" => $book->getSite(),
                "icon" => $this->general->getPathForShowFile() . $book->getIconAddressThumb(),
                "status_id" => $book->getStatus(),
                "status" => ($bookStatus) ? $bookStatus->getTitle() : "",
                "veto_reason" => $book->getVetoReason(),
                "auto_publish" => $book->getAutoPublish(),
                "flagPublishBook" => $flagPublishBook,
            );
            //
            $bookScore = $book->getCommentScore();
            $bookCountScore = $this->TblComment->count("book_id='$bookId' and score > 0 and status='1'");
            $bookCountComment = $this->TblComment->count("book_id='$bookId' and des!='' and status='1'");
            //
            for ($i = 1; $i <= 5; $i++) {
                $_count = $this->TblComment->count("book_id='$bookId' and score='$i' and status='1'");
                $_percent = ($_count > 0) ? (($_count / $bookCountScore) * 100) : 0;
                $scores[$i] = array("count" => $_count, "percent" => $_percent);
            }
        } else {
            redirect($this->config->base_url() . "book", "", 301);
        }
        //
        $data = array
        (
            "pageTitle" => "کتاب ها",
            "pageView" => "book/comments",
            "pageJsCss" => "book-comments",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "book" => $bookData,
                "bookScore" => $bookScore,
                "bookCountScore" => $bookCountScore,
                "bookCountComment" => $bookCountComment,
                "scores" => $scores,
            )
        );
        $this->twig->display("master", $data);
    }

    public function commentsList($isbn)
    {
        $commentsData = null;
        $totalPageNumber = 0;
        // get data
        $filterTime = $this->general->validateInputData($this->input->post("time"));
        $filterScore = $this->general->validateInputData($this->input->post("score"));
        $pageNumber = $this->general->validateInputData($this->input->post("page-number"));
        $pageNumber = (is_numeric($pageNumber) and $pageNumber > 0) ? $pageNumber : 1;
        $offset = ($pageNumber - 1) * $this->general->rowInFind;
        //
        if ($filterTime == "new") {
            $order = "datetime desc";
        } elseif ($filterTime == "last") {
            $order = "datetime asc";
        } else {
            $order = "datetime desc";
        }
        $where = "";
        if ($filterScore != "" and $filterScore > 0) {
            $where .= " and score='$filterScore'";
        }
        //
        $bookId = 0;
        $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
        if ($book != null and $book->getStatus() != 0) {
            $bookId = $book->getId();
        }
        // check book id access to page
        //if($this->getFlagAccessBook($bookId, "comment"))
        {
            //
            $tblComments = $this->TblComment->find(array(
                "where" => "book_id='$bookId' and status='1' $where",
                "order" => "$order",
                "limit" => array("value" => $this->general->rowInFind, "offset" => $offset),
            ));
            if ($tblComments != null and count($tblComments) > 0) {
                foreach ($tblComments as $comment) {
                    //$comment=new TblComment();
                    $commentId = $comment->getId();
                    $userId = $comment->getUserId();
                    //
                    $tblUser = $this->TblUser->findFirst(array("where" => "id='$userId'"));
                    $userImg = $this->general->foundImage($tblUser->getUserImg(), true);
                    $userName = $tblUser->getNameFamilyFa();
                    //
                    $tblCommentAnswer = $this->TblCommentAnswer->findFirst(array("where" => "comment_id='$commentId'"));
                    $answer = ($tblCommentAnswer != null) ? $tblCommentAnswer->getDes() : "";
                    //
                    $tblCommentReport = $this->TblCommentReport->findFirst(array("where" => "comment_id='$commentId'"));
                    $report = ($tblCommentReport != null) ? $tblCommentReport->getDes() : "";
                    //
                    $commentsData[] = array
                    (
                        "id" => $comment->getId(),
                        "user_pic" => $userImg,
                        "user_name" => $userName,
                        "score_percent" => $comment->getScore() * 20,
                        "like" => $comment->getClike(),
                        "dislike" => $comment->getDislike(),
                        "des" => $this->general->htmlSpecialCharsDecode($comment->getDes()),
                        "answer" => $answer,
                        "report" => $report,
                        "date" => $this->datetime2->getJalaliDate($comment->getDatetime(), "Y/m/d")
                    );
                }
            }
            // calculate total page number
            $signboardsCount = $this->TblComment->count("book_id='$bookId' and status='1' $where");
            if ($signboardsCount > 0) $totalPageNumber = ceil($signboardsCount / $this->general->rowInFind); else $totalPageNumber = 0;
        }
        //
        echo json_encode
        (
            array("data" => $commentsData, "total_page_number" => $totalPageNumber, "page_number" => $pageNumber)
        );
    }

    public function commentsAnswer($isbn)
    {
        $result = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $commentId = $this->general->validateInputData($this->input->post("form-comment-answer-id"));
            $des = $this->general->validateInputData($this->input->post("form-comment-answer-des"));
            //
            if ($commentId > 0 and $this->general->stringLen($des) >= 10) {
                $tblComment = $this->TblComment->findFirst(array("where" => "id='$commentId'"));
                $tblCommentAnswer = $this->TblCommentAnswer->findFirst(array("where" => "comment_id='$commentId'"));
                if ($tblComment != null and $tblComment->getId() > 0 and $tblCommentAnswer == null) {
                    $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                    if ($book != null and $book->getStatus() != 0) {
                        $bookId = $book->getId();
                        // check book id access to page
                        //if($this->getFlagAccessBook($bookId, "comment"))
                        {
                            $tblCommentAnswer = new TblCommentAnswer();
                            $tblCommentAnswer->setCommentId($commentId);
                            $tblCommentAnswer->setUserId($this->developer->developerId);
                            $tblCommentAnswer->setSupplierId(0);
                            $tblCommentAnswer->setDes($des);
                            $tblCommentAnswer->setStatus(0);
                            $tblCommentAnswer->setDatetime(time());
                            $resultSave = $tblCommentAnswer->save();
                            if ($resultSave) {
                                $result = 1;
                            }
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

    public function commentsReport($isbn)
    {
        $result = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $commentId = $this->general->validateInputData($this->input->post("form-comment-report-id"));
            $des = $this->general->validateInputData($this->input->post("form-comment-report-des"));
            //
            if ($commentId > 0 and $this->general->stringLen($des) >= 10) {
                $tblComment = $this->TblComment->findFirst(array("where" => "id='$commentId'"));
                $tblCommentReport = $this->TblCommentReport->findFirst(array("where" => "comment_id='$commentId'"));
                if ($tblComment != null and $tblComment->getId() > 0 and $tblCommentReport == null) {
                    $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                    if ($book != null and $book->getStatus() != 0) {
                        $bookId = $book->getId();
                        // check book id access to page
                        //if($this->getFlagAccessBook($bookId, "comment"))
                        {
                            $tblCommentReport = new TblCommentReport();
                            $tblCommentReport->setCommentId($commentId);
                            $tblCommentReport->setUserId($this->developer->developerId);
                            $tblCommentReport->setSupplierId(0);
                            $tblCommentReport->setDes($des);
                            $tblCommentReport->setStatus(0);
                            $tblCommentReport->setDatetime(time());
                            $resultSave = $tblCommentReport->save();
                            if ($resultSave) {
                                $result = 1;
                            }
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

    // publish - unpublished
    public function publishUnpublished($isbn)
    {
        $result = 0;
        $errorData = 0;
        if ($this->general->checkRefOfMySite()) {
            $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
            if ($book != null and $book->getStatus() != 0) {
                $bookId = $book->getId();
                $resultSave = 0;
                //
                //if($this->getFlagAccessBook($bookId, "publish"))
                {
                    // publish
                    if ($book->getStatus() == 1 or $book->getStatus() == 3 or $book->getStatus() == 4 or $book->getStatus() == 8) {
                        // check book data is valid
                        $bookImageCount = $this->TblBookImage->count("book_id='$bookId'");
                        $bookIconFlag = ($book->getIconAddress() != "") ? true : false;
                        $bookDataFlag = ($book->getTitleFa() != "" and $book->getTitleEn() != "" and $book->getDesFa() != "" and $book->getDesEn() != "" and $book->getSupportTel() != "" and $book->getSupportEmail() != "" and $book->getCategoryId2() > 0 and $book->getCategoryId1() > 0 and $book->getAgesFa() != "" and $book->getAgesEn() != "" and $book->getPublishYear() > 0 and $book->getLanguage() != "" and $book->getNumberPages() > 0) ? true : false;
                        //
                        if ($bookDataFlag and $bookIconFlag and $bookImageCount > 0) {
                            $book->setStatus(2);
                            $resultSave = $book->save();
                        } else {
                            $errorData = 1;
                        }
                    } // unpublished
                    elseif ($book->getStatus() == 2 or $book->getStatus() == 6 or $book->getStatus() == 7) {
                        $book->setStatus(8);
                        $resultSave = $book->save();
                    }
                    //
                    if ($resultSave) {
                        $result = 1;
                    }
                }
            }
        }
        //
        echo json_encode(array
        (
            "result" => $result,
            "errorData" => $errorData
        ));
    }

    // auto publish
    public function autoPublish($isbn)
    {
        $result = 0;
        if ($this->general->checkRefOfMySite()) {
            // get data
            $publish = $this->general->validateInputData($this->input->post("publish"));
            //
            if ($publish == 0 or $publish == 1) {
                $book = $this->TblBook->findFirst(array("where" => "isbn='$isbn' and developer_id='" . $this->developer->developerId . "'"));
                if ($book != null and $book->getStatus() != 0) {
                    $bookId = $book->getId();
                    //if($this->getFlagAccessBook($bookId, "publish"))
                    {
                        $book->setAutoPublish($publish);
                        $resultSave = $book->save();
                        //
                        if ($resultSave) {
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

    //
    public function getFlagAccessBook($bookId, $typeAccess)
    {
        $flagAccessBook = false;
        if ($this->developer->supplierId > 0) {
            if ($this->developer->supplierAccessBook != null) {
                foreach ($this->developer->supplierAccessBook as $accessBookId) {
                    if ($accessBookId->getTypeaccess() == $typeAccess) {
                        if ($accessBookId->getBookid() == $bookId) {
                            $flagAccessBook = true;
                            break;
                        }
                    }
                }
            }
        } else {
            $flagAccessBook = true;
        }
        return $flagAccessBook;
    }

    //
    public function getAccessesBook($bookId)
    {
        $accessesBook = array
        (
            "book-data" => 0,
            "publish" => 0,
            "support" => 0,
            "comment" => 0
        );
        if ($this->developer->supplierId > 0) {
            if ($this->developer->supplierAccessBook != null) {
                foreach ($this->developer->supplierAccessBook as $accessBookId) {
                    if ($accessBookId->getBookid() == $bookId) $accessesBook[$accessBookId->getTypeaccess()] = 1;
                }
            }
        }
        $this->twig->addGlobal("accessesBook", $accessesBook);
        return $accessesBook;
    }
}