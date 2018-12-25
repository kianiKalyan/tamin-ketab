<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

        //
        $c = $this->router->fetch_class();
        $a = $this->router->fetch_method();
        $this->developer->checkLogin("$c-$a");

        //
        $this->load->model("TblUser", "", true);
    }

    public function index()
    {
        //
        $data = array
        (
            "pageTitle" => "صفحه ورود",
            "pageView" => "login/index",
            "pageJsCss" => "login-index",
            "showPageTitle" => true,
            "pageContent" => array()
        );
        $this->twig->display("master-login", $data);
    }

    public function enter()
    {
        $result = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $mailMobile = $this->general->validateInputData($this->input->post("form-login-mail-mobile"));
            $password = $this->general->validateInputData($this->input->post("form-login-password"));

            //
            if($this->general->stringLen($mailMobile) >= 3 and $this->general->stringLen($password) >= 5)
            {
                $password = $this->general->encodePassword($password);

                $user = $this->TblUser->findFirst(array("where" => "(email='$mailMobile' or mobile='$mailMobile') and password='$password'"));
                if($user != null and $user->getId() > 0)
                {
                    $this->session->set_userdata("UserID", $user->getId());

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

    public function forgetPassword()
    {
        $result = 0;
        $errorMailMobile = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $mailMobile = $this->general->validateInputData($this->input->post("form-forget-password-mail-mobile"));

            //
            if($this->general->isValidMail($mailMobile) or is_numeric($mailMobile))
            {
                $user = $this->TblUser->findFirst(array("where" => "email='$mailMobile' or mobile='$mailMobile'"));
                if($user != null and $user->getId() > 0)
                {
                    // build new password
                    $passwordNew = md5(sha1(rand(0, 9631587452)));
                    $pointStart = rand(0, strlen($passwordNew) - 12);
                    $length = rand(6, 12);
                    $passwordNew = substr($passwordNew, $pointStart, $length);

                    // save password in tbl
                    $user->setPassword($this->general->encodePassword($passwordNew));
                    $user->save();

                    $mail = $user->getEmail();
                    $mobile = $user->getMobile();
                    $siteTitle = $this->general->siteTitle;

                    if($mail != "")
                    {
                        // send password to user mail
                        $this->load->library("email");
                        $this->email
                            ->from("info@".$this->general->mySiteUrl(), $siteTitle)
                            ->to("$mail")
                            ->subject("کلمه عبور جدید")
                            ->message("<div style='direction: rtl'>با سلام<br /><br />شما درخواست ارسال کلمه عبور جدید در سایت $siteTitle را داده اید.<br /><br /> کلمه عبور شما $passwordNew  می باشد.</div>")
                            ->set_mailtype("html");

                        if($this->email->send()) $result = 1;
                    }
                    else if($mobile != "")
                    {
                        $message = "با سلام\nشما درخواست ارسال کلمه عبور جدید در سایت $siteTitle را داده اید.\nکلمه عبور شما$passwordNew می باشد.";

                        $result = $this->general->sendSMS($mobile, $message);

                        if($result) $result = 1;
                    }
                }
                else
                {
                    $errorMailMobile = 1;
                }
            }
            else
            {
                if(!($this->general->isValidMail($mailMobile) or is_numeric($mailMobile))) $errorMailMobile = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorMailMobile" => $errorMailMobile
        ));
    }

    public function first()
    {
        $flagShowForm = false;
        $activeLink = "";

        $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));

        if($user->getPassword() != "" and $user->getLoginConfirm() == 0)
        {
            $email = $user->getEmail();
            $confirmCodeTime = md5(time());
            $confirmCode = $user->getPassword().$confirmCodeTime.sha1($email);
            $confirmCode2 = $this->general->encodeActivation($confirmCode);

            //
            $user->setLoginConfirmCode($confirmCode);
            $result = $user->save();

            //
            if($result)
            {
                $siteTitle = $this->general->siteTitle;

                // send link to mail
                $activationLink = $this->config->base_url()."login/confirm/".$confirmCode2;

                $messageMail = "<div style='direction: rtl'>دوست گرامی! ورود شما را به جمع ناشران $siteTitle تبریک عرض می نماییم.<br /><br />بعد از کلیک بر روی لینک زیر و فعالسازی حساب کاربری خود می توانید وارد پنل ناشران شوید.<br /><br /><a href='$activationLink'>$activationLink</a><br /><br />با تشکر</div>";

                // mail
                if($email != "")
                {
                    $this->load->library("email");
                    $this->email
                        ->from("info@".$this->general->mySiteUrl(), $siteTitle)
                        ->to("$email")
                        ->subject("خوش آمدگویی")
                        ->message($messageMail)
                        ->set_mailtype("html");
                    $this->email->send();
                }
            }
        }
        else
        {
            // show form
            $flagShowForm = true;
        }

        //
        $data = array
        (
            "pageTitle" => "ثبت اطلاعات حساب کاربری",
            "pageView" => "login/first",
            "pageJsCss" => "login-first",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "user" => $user,
                "flagShowForm" => $flagShowForm,
                "activeLink" => $activeLink
            )
        );
        $this->twig->display("master-login", $data);
    }

    public function firstSave()
    {
        $result = 0;
        $errorMail = 0;
        $errorPassword = 0;
        $errorMobile = 0;
        $errorMobileRepeat = 0;
        $errorMailRepeat = 0;

        //
        if($this->general->checkRefOfMySite())
        {
            // get data
            $email = $this->general->validateInputData($this->input->post("form-login-first-email"));
            $mobile = $this->general->validateInputData($this->input->post("form-login-first-mobile"));
            $password = $this->general->validateInputData($this->input->post("form-login-first-password"));
            $passwordRep = $this->general->validateInputData($this->input->post("form-login-first-password-rep"));

            //
            if($this->general->isValidMail($email) and $this->general->stringLen($mobile) == 11 and ($password != "" and $this->general->stringLen($password) >= 5) and ($passwordRep != "" and $this->general->stringLen($passwordRep) >= 5) and ($password == $passwordRep))
            {
                $user = $this->TblUser->findFirst(array("where" => "id='".$this->developer->developerId."'"));
                if($user != null and $user->getId() > 0)
                {
                    $passwordMain = $password;
                    $password = $this->general->encodePassword($password);
                    $email = ($user->getEmail() == "") ? $email : $user->getEmail();

                    //
                    $check_mobile = $this->TblUser->findFirst(array("where" => "mobile='$mobile' and id!='".$this->developer->developerId."'"));
                    $check_email = $this->TblUser->findFirst(array("where" => "email='$email' and id!='".$this->developer->developerId."'"));

                    //
                    if($check_mobile == null and $check_email == null)
                    {
                        $confirmCodeTime = md5(time());
                        $confirmCode = $password.$confirmCodeTime.sha1($email);
                        $confirmCode2 = $this->general->encodeActivation($confirmCode);

                        // save
                        $user->setEmail($email);
                        $user->setMobile($mobile);
                        $user->setPassword($password);
                        $user->setLoginConfirm(0);
                        $user->setLoginConfirmCode($confirmCode);
                        $user->setDatetime(time());
                        $resultSave = $user->save();

                        if($resultSave)
                        {
                            $activationLink = $this->config->base_url()."login/confirm/".$confirmCode2;

                            $siteTitle = $this->general->siteTitle;
                            
                            $messageMail = "<div style='direction: rtl'>دوست گرامی! ورود شما را به جمع ناشران $siteTitle تبریک عرض می نماییم.<br /><br />بعد از کلیک بر روی لینک زیر و فعالسازی حساب کاربری خود می توانید وارد پنل ناشران شوید.<br /><br /><a href='$activationLink'>$activationLink</a><br /><br />مشخصات شما جهت ورود به شرح ذیل است:<br />ایمیل: $email<br />تلفن همراه: $mobile<br /><br />با تشکر</div>";
                            $messageSms = "دوست گرامی! ورود شما را به جمع ناشران $siteTitle تبریک عرض می نماییم.\n\nبعد از کلیک بر روی لینک زیر و فعالسازی حساب کاربری خود می توانید وارد پنل ناشران شوید.\n\n$activationLink\n\nمشخصات شما جهت ورود به شرح ذیل است:\n\nایمیل: $email\nتلفن همراه: $mobile\n\nبا تشکر";
                            
                            // mail
                            if($email != "")
                            {
                                $this->load->library("email");
                                $this->email
                                    ->from("info@".$this->general->mySiteUrl(), $siteTitle)
                                    ->to("$email")
                                    ->subject("خوش آمدگویی")
                                    ->message($messageMail)
                                    ->set_mailtype("html");
                                $this->email->send();
                            }

                            // sms
                            if($mobile != "")
                            {
                                $this->general->sendSMS($mobile, $messageSms);
                            }

                            //
                            $result = 1;
                        }
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
                if(!($this->general->isValidMail($email))) $errorMail = 1;
                if(!($this->general->stringLen($mobile) == 11)) $errorMobile = 1;
                if(!(($password != "" and $this->general->stringLen($password) >= 5) and ($passwordRep != "" and $this->general->stringLen($passwordRep) >= 5) and ($password == $passwordRep))) $errorPassword = 1;
            }
        }

        //
        echo json_encode(array
        (
            "result" => $result,
            "errorMail" => $errorMail,
            "errorPassword" => $errorPassword,
            "errorMobile" => $errorMobile,
            "errorMobileRepeat" => $errorMobileRepeat,
            "errorMailRepeat" => $errorMailRepeat
        ));
    }

    public function confirm($activationCode = "")
    {
        $confirmResult = false;

        //
        if($activationCode != "")
        {
            $user = $this->TblUser->findFirst(array("where" => "sha1(md5(sha1(login_confirm_code)))='$activationCode'"));
            if($user != null and $user->getId() > 0)
            {
                $user->setLoginConfirm(1);
                $result = $user->save();

                if($result)
                {
                    $confirmResult = true;
                }
            }
        }

        //
        $data = array
        (
            "pageTitle" => "فعالسازی حساب کاربری",
            "pageView" => "login/confirm",
            "pageJsCss" => "login-confirm",
            "showPageTitle" => true,
            "pageContent" => array
            (
                "confirmResult" => $confirmResult
            )
        );
        $this->twig->display("master-login", $data);
    }
}