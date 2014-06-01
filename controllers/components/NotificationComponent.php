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
   * submit a journal article.
   * @TODO send out an email to all administrators who can
   * approve this submission.
   * @TODO send out an email to nofity author(submitter) that
   * the new submission is currently under review.
   */
  protected $defaultAdminEmail = "-fadmin@osehra.org";
  private $_layout;
  private $_view;

  public function sendForApproval($resourceDao)
    {
    //TODO & make sure multiple notification
    // Need to send email notification to
    // *. Administrator of the community
    // *. Editors in this specific issue
    // *. Submitter
    $this->getLogger()->debug("Send for approval is called" . $resourceDao->getName());
    $fc = Zend_Controller_Front::getInstance();
    $baseUrl = UtilityComponent::getServerURL().$fc->getBaseUrl();
    $scriptpath = BASE_PATH . '/privateModules/journal/views/email';
    $this->_createEmailView($scriptpath, $baseUrl);
    $contactEmail = $resourceDao->getSubmitter()->getEmail();
    $this->getLogger()->info("Contact Email is " . $contactEmail);
    $adminList = $this->_getSubmissionAdmins($resourceDao);
    $this->getLogger()->info("AdminList is " . $adminList);
    // extract the editor group based resourceDao
    $editList = $this->_getSubmissionEditorEmails($resourceDao);
    $this->getLogger()->info("editList is " . $editList);
    $name = $resourceDao->getName();
    $description = $resourceDao->getDescription();
    $handle = $resourceDao->getHandle();
    $authors = $resourceDao->getAuthors();
    $itemId = $resourceDao->getItemId();
    $revisionId = $resourceDao->getRevision()->itemrevision_id;
    $authList = '';
    foreach ($authors as $author)
      {
      $authList .= join(" ", $author) . ",";
      }
    if (!empty($authList)) $authList = substr($authList, 0, -1);
    $this->getLogger()->info("Name is " . $name);
    $this->getLogger()->info("Description is " . $description);
    $this->getLogger()->info("handle is " . $handle);
    $this->getLogger()->info("Authors are " . $authList);
    $approveLink = "/journal/submit?revisionId=" . $revisionId;
    $this->getLogger()->info("link is " . $approveLink);
    $this->getLogger()->info("ItemId is " . $itemId);
    $this->_view->assign("name", $name);
    $this->_view->assign("author", $authList);
    $this->_view->assign("description", $description);
    $this->_view->assign("link", $approveLink);
    $this->_layout->assign("content", $this->_view->render('sendforapproval.phtml'));
    $bodyText = $this->_layout->render('layout.phtml');

    $this->getLogger()->info("Body Text is " . $bodyText);
    $subject = 'New Submission - Pending Approval: ' . $name;
    $to = '';
    // form the email headers part
    $headers = $this->_formMailHeader($contactEmail, $editList, $adminList);
    $this->getLogger()->info("Email Header is " . $headers);
    // send mail to the editors
    $result = mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    $this->getLogger()->info("mail result is " . $result);
    // send mail to the submitter
    $this->_createEmailView($scriptpath, $baseUrl);
    $readlink = "/journal/view/" . $revisionId;
    $submitter = $resourceDao->getSubmitter();
    $name = $submitter.getFullName();
    $this->getLogger()->info("name is " . $name);
    $this->_view->assign("name", $name);
    $this->_view->assign("link", $readlink);
    $this->_layout->assign("content", $this->_view->render('waitforapproval.phtml'));
    $headers = $this->_formMailHeader($contactEmail, null, null);
    $bodyText = $this->_layout->render('layout.phtml');
    $this->getLogger()->info("Body Text is " . $bodyText);
    $this->getLogger()->info("Email Header is " . $headers);
    $result = mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    $this->getLogger()->info("mail result is " . $result);
    }

  /**
   * This function is being called when a new journal is submitted.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newArticle($resourceDao)
    {
    $this->getLogger()->info("New Article is Added");
    // @TODO Check user settings, but I do not think it has been implemented yet
    $fc = Zend_Controller_Front::getInstance();
    $baseUrl = UtilityComponent::getServerURL().$fc->getBaseUrl();
    $scriptpath = BASE_PATH . '/privateModules/journal/views/email';
    $this->_createEmailView($scriptpath, $baseUrl);
    $contactEmail = $resourceDao->getSubmitter()->getEmail();
    $this->getLogger()->info("Contact Email is " . $contactEmail);
    $name = $resourceDao->getName();
    $description = $resourceDao->getDescription();
    $handle = $resourceDao->getHandle();
    $authors = $resourceDao->getAuthors();
    $itemId = $resourceDao->getItemId();
    $revisionId = $resourceDao->getRevision()->itemrevision_id;
    $authList = '';
    foreach ($authors as $author)
      {
      $authList .= join(" ", $author) . ",";
      }
    if (!empty($authList)) $authList = substr($authList, 0, -1);
    $this->getLogger()->info("Name is " . $name);
    $this->getLogger()->info("Description is " . $description);
    $this->getLogger()->info("handle is " . $handle);
    $this->getLogger()->info("Authors are " . $authList);
    $handleLink = "http://hdl.handle.net/" . $handle;
    $this->getLogger()->info("link is " . $approveLink);
    $this->getLogger()->info("ItemId is " . $itemId);
    $this->_view->assign("name", $name);
    $this->_view->assign("author", $authList);
    $this->_view->assign("description", $description);
    $this->_view->assign("link", $handleLink);
    $this->_layout->assign("content", $this->_view->render('newsubmission.phtml'));
    $bodyText = $this->_layout->render('layout.phtml');
    $this->getLogger()->info("Body Text is " . $bodyText);
    $subject = 'New Submission: ' . $name;
    $to = '';
    // form the email headers part
    $editList = $this->_getSubmissionAdminEmails($resourceDao);
    $adminList = $this->_getSubmissionEditorEmails($resourceDao);
    $headers = $this->_formMailHeader($contactEmail, $editList, $adminList);
    $this->getLogger()->info("Email Header is " . $headers);
    // send mail to the editors
    mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    }
  /**
   * This function is being called whenever a new comments is added to a
   * journal.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newComment($commentDao)
    {
    $this->getLogger()->info("New Comment is Added");
    $itemId = $commentDao->getItemId();
    $userId = $commentDao->getUserId();
    $comment = $commentDao->getComment();
    $item = MidasLoader::loadModel("Item")->load($itemId);
    $userDao = MidasLoader::loadModel("User")->load($userId);
    $fc = Zend_Controller_Front::getInstance();
    $baseUrl = UtilityComponent::getServerURL().$fc->getBaseUrl();
    $scriptpath = BASE_PATH . '/privateModules/journal/views/email';
    $this->_createEmailView($scriptpath, $baseUrl);
    // No need to check the permission here as it should have some access
    // to this journal item to make the comment
    $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $item->toArray(), "journal");
    $submitter = $resourceDao->getSubmitter();
    $contactEmail = $submitter->getEmail();
    $title = $resourceDao->getName();
    $handle = $resourceDao->getHandle();
    $this->getLogger()->info("contact email is " . $contactEmail);
    $this->getLogger()->info("comment email is " . $comment);
    $this->getLogger()->info("comment user is " . $userDao->getFullName());
    $this->_view->assign("name", $userDao->getFullName());
    $this->_view->assign("title", $title);
    $this->_view->assign("comments", $comment);
    $handleLink = "http://hdl.handle.net/" . $handle;
    $this->_view->assign("link", $handleLink);
    $this->_layout->assign("content", $this->_view->render('newcomment.phtml'));
    $bodyText = $this->_layout->render('layout.phtml');
    $this->getLogger()->info("Body Text is " . $bodyText);
    $subject = 'Comment Added - Submission: ' . $name;
    $to = '';
    // form the email headers part
    $editList = $this->_getSubmissionEditorEmails($resourceDao);
    $adminList = $this->_getSubmissionAdminEmails($resourceDao);
    $headers = $this->_formMailHeader($contactEmail, $editList, $adminList);
    $this->getLogger()->info("Email Header is " . $headers);
    // send mail to the editors
    mail($to, $subject, $bodyText, $headers, $this->defaultAdminEmail);
    }
  /**
   * This function is being called whenever a new review is added to a
   * journal.
   * @TODO send out email to notify author as well as all users that are
   * subscribe to this notification.
   */
  public function newReview($resourceDao)
    {
    $this->getLogger()->info("New Review is Added");
    }

  /**
   * private functions
   */
  private function _createEmailView($scriptpath, $baseUrl)
    {
    $this->_layout = new Zend_View();
    $this->_view = new Zend_View();
    $this->_layout->setScriptPath($scriptpath);
    $this->_view->setScriptPath($scriptpath);
    $this->_view->assign("webroot", $baseUrl);
    $this->_layout->assign("webroot", $baseUrl);
    }
  private function _formMailHeader($contactEmail, $ccList, $bccList)
    {
    $fromEmail = "OSEHRA Technical Journal <no-reply@osehra.org>";
    $replyEmail = "OSEHRA Technical Journal <no-reply@osehra.org>";
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
    $headers .='X-Mailer: PHP/' . phpversion() . $linesep;
    $headers .= "MIME-Version: 1.0" . $linesep;
    $headers .= "Content-type: text/html; charset=iso-8859-1"; 
    return $headers;
    }
  private function _getSubmissionAdminEmails($resourceDao)
    {
    // extract the information from resourceDao
    $adminGroup = $resourceDao->getAdminGroup();
    $adminUsers = $adminGroup->getUsers();
    $adminList = '';
    foreach ($adminUsers as $adminUser)
      {
      $adminList .= $adminUser->getEmail() . ',';
      }
    if (!empty($adminList)) $adminList = substr($adminList, 0, -1);
    return $adminList;
    }
  private function _getSubmissionEditorEmails($resourceDao)
    {
    $folder = end($resourceDao->getFolders());
    $editGroup = '';
    $editList = '';
    $adminGroup = $resourceDao->getAdminGroup();
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
          $editList .= $editUser->getEmail() . ',';
          }
        if (!empty($editList)) $editList = substr($editList, 0, -1);
      }
    return $editList;
    }
  private function _testMail()
    {
    $to      = 'lij@osehra.org';
    $subject = 'the subject';
    $message = 'hello';
    $headers = 'From: no-reply@osehra.com' . "\r\n" .
        'Reply-To: no-reply@example.com' . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

    $result = mail($to, $subject, $message, $headers);
    $this->getLogger()->info("mail result is " . $result);
    }
} // end class
