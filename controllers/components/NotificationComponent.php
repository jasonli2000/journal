<?php
/*=========================================================================
 *
 *  Copyright OSHERA Consortium
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *         http://www.apache.org/licenses/LICENSE-2.0.txt
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 *=========================================================================*/

class Journal_NotificationComponent extends AppComponent
{
  /**
   * This function is being called when a non-administrator
   * submit a journal.
   * @TODO send out an email to all administrators who can
   * approve this submission.
   * @TODO send out an email to nofity author(submitter) that
   * the new submission is currently under review.
   */
  protected $defaultAdminEmail = "-admin@osehra.org";
  public function sendForApproval($resourceDao)
    {
    //TODO & make sure multiple notification
    // Need to send email notification to
    // *. Administrator of the community
    // *. Editors in this specific issue
    // *. Submitter
    $this->getLogger()->warn("Send for approval is called" . $resourceDao->getName());
    $fc = Zend_Controller_Front::getInstance();
    $baseUrl = UtilityComponent::getServerURL().$fc->getBaseUrl();

    $layout = new Zend_View();
    $view = new Zend_View();
    $layout->setScriptPath(BASE_PATH . '/privateModules/journal/views/email');
    $view->setScriptPath(BASE_PATH . '/privateModules/journal/views/email');

    $contactEmail = $resourceDao->getSubmitter()->getEmail();
    $this->getLogger()->warn("Contact Email is " . $contactEmail);
    //$contactEmail = ""; //@TODO, find a way to get the email address
    // extract the information from resourceDao
    $adminGroup = $resourceDao->getAdminGroup();
    $adminUsers = $adminGroup->getUsers();
    $adminList = "";
    foreach ($adminUsers as $adminUser)
      {
      $adminList .= $adminUser->getEmail() . ",";
      }
    $this->getLogger()->warn("AdminList is " . $adminList);
    // extract the editor group based resourceDao
    $folder = end($resourceDao->getFolders());
    $editGroup = '';
    $editList = '';
    foreach ($folder->getFolderpolicygroup() as $policy)
      {
      if ($policy->getPolicy() == MIDAS_POLICY_ADMIN && $adminGroup->getKey() != $policy->getGroupId())
        {
        $editGroup = $policy->getGroup();
        break;
        }
      }
    if (!empty($editGroup))
      {
        $editUsers = $editGroup->getUsers();
        foreach ($editUsers as $editUser)
          {
          $editList .= $editUser->getEmail() . ",";
          }
      }
    $this->getLogger()->warn("editList is " . $editList);
    $name = $resourceDao->getName();
    $view->assign("webroot", $baseUrl);
    $view->assign("name", $name);
    $view->assign("contactEmail", $contactEmail);
    $layout->assign("webroot", $baseUrl);
    $layout->assign("content", $view->render('newuser.phtml'));
    $bodyText = $layout->render('layout.phtml');
    $subject = 'A New Submission is waiting for approval';
    $to = '';
    // form the email headers part
    $headers = $this->formMailHeader($contactEmail, editGroup, $adminList);
    $this->getLogger()->warn("Email Header is " . $headers);
    // send mail to the submitter
    mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    // send mail to admins
    mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    }

  /**
   * This function is being called when a new journal is submitted.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newArticle($resourceDao)
    {

    }
  /**
   * This function is being called whenever a new comments is added to a
   * journal.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newComment($resourceDao)
    {

    }
  /**
   * This function is being called whenever a new review is added to a
   * journal.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newReview($resourceDao)
    {

    }

  private function formMailHeader($contactEmail, $ccList, $bccList)
    {
    $fromEmail = "OTJ Support<otj@osehra.org>"; // @TODO, change to valid email
    $replyEmail = "no-reply@osehra.org"; # do not reply to osehra
    $linesep = "\r\n";
    $headers = 'To: ' . $contactEmail . $linesep;
    $headers .= 'From: ' . $fromEmail . $linesep;
    $headers .= "Reply-To: " . $replyEmail . $linesep;
    if (!empty($ccList))
      {
      $headers .= "Cc: " . $ccList . $linesep;
      }
    if (!empty($bccList))
      {
      $headers .= "Bcc: " . $bccList . $linesep;
      }
    $headers .='X-Mailer: PHP/' . phpversion();
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    return $headers;
    }
} // end class
