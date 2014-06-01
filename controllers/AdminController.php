<?php

class Reviewosehra_AdminController extends Reviewosehra_AppController
{
  // Initialization method. Called before every Action
  function init()
    {
    parent::init();    
    }
    
    
  /** Hidden migrate page*/
  function migrateAction()
    {
    $this->requireAdminPrivileges();
    $this->disableLayout();
    $this->disableView();
    MidasLoader::loadComponent("Migrate", "reviewosehra")->migrateQuestionlist();      
    }
    
  /** Manage an article reviews */
  function manageAction()
    {
    $itemId = $this->_getParam("itemId");
    $itemDao = MidasLoader::loadModel("Item")->load($itemId);   
    $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $itemDao->toArray(), "journal");
     if(!$this->logged || !$resourceDao->isAdmin($this->userSession->Dao)
            || !MidasLoader::loadModel("Item")->policyCheck($itemDao, $this->userSession->Dao, MIDAS_POLICY_READ))
      {
      throw new Zend_Exception('Read permission required', 403);
      }
      
    if($this->_request->isPost())      
      {
      $value = $_POST['certificationLevel'];
      if(!isset($_POST['certificationLevel']) || $_POST['certificationLevel'] == -1 || empty($_POST['certificationLevel']))
        {
        $value = "";
        }
      $resourceDao->setCertificationLevel($value);
      MidasLoader::loadModel("Item")->save($resourceDao);
      }
      
    $reviewsByRevisions = array();
    $revisions = $itemDao->getRevisions();
    foreach($revisions as $revision)
      {
      $reviewsByRevisions[$revision->getRevision()] = MidasLoader::loadModel("Review", "reviewosehra")->getByRevision($revision);
      }
      
    if(isset($_GET['deleteReview']) && is_numeric($_GET['deleteReview']))
      {
      $review = MidasLoader::loadModel("Review", "reviewosehra")->load($_GET['deleteReview']);
      if($review) MidasLoader::loadModel("Review", "reviewosehra")->delete($review);
      $this->_redirect("/reviewosehra/admin/manage?itemId=".$itemId);
      }

    if(isset($_GET['moveReview']) && is_numeric($_GET['moveReview']) &&
       isset($_GET['moveTo']) && is_numeric($_GET['moveTo'])      )
      {
      $review = MidasLoader::loadModel("Review", "reviewosehra")->load($_GET['moveReview']);
      foreach($revisions as $revision)
        {
        if($revision->getRevision() == $_GET['moveTo'])
          {
          $review-> setRevisionId($revision->getKey());
          MidasLoader::loadModel("Review", "reviewosehra")->save($review);
          Zend_Registry::get('notifier')->callback('CALLBACK_REVIEW_ADDED', array('review' => $review));
          }
        }
      $this->_redirect("/reviewosehra/admin/manage?itemId=".$itemId);
      }
      
    if(isset($_GET['phaseReview']) && is_numeric($_GET['phaseReview']) &&
       isset($_GET['setPhase']) && is_numeric($_GET['setPhase'])      )
      {
      foreach($revisions as $revision)
        {
        if($revision->getRevision() == $_GET['phaseReview'])
          {
          $resourceDao->_metadata = false;
          $resourceDao->setRevision($revision);
          $resourceDao->setMetaDataByQualifier("reviewPhase", $_GET['setPhase']);
          }
        }
      $this->_redirect("/reviewosehra/admin/manage?itemId=".$itemId);
      }
      
    $this->view->reviewsByRev = $reviewsByRevisions;
    $this->view->resource = $resourceDao;
    }
    
  /** Create/Edit question lists */
  function questionsAction()
    {   
    $this->requireAdminPrivileges();
    $this->view->questionlist = false;
    $this->view->topic = false;
    $listId = $this->_getParam("listId");
    $deleteListId = $this->_getParam("deleteListId");
    $deleteTopicId = $this->_getParam("deleteTopicId");
    $deleteQuestionId = $this->_getParam("deleteQuestionId");
    $topicId = $this->_getParam("topicId");
    $questionId = $this->_getParam("questionId");
    $moveAction = $this->_getParam("moveAction");
    if(isset($listId) && is_numeric($listId))
      {
      $this->view->questionlist = MidasLoader::loadModel("Questionlist", 'reviewosehra')->load($listId);
      }
    if(isset($topicId) && is_numeric($topicId))
      {
      $this->view->topic = MidasLoader::loadModel("Topic", 'reviewosehra')->load($topicId);
      if(isset($moveAction) && $moveAction == "up")
        {
        MidasLoader::loadModel("Topic", 'reviewosehra')->moveUp($this->view->topic);
        }
      elseif(isset($moveAction) && $moveAction == "down")
        {
        MidasLoader::loadModel("Topic", 'reviewosehra')->moveDown($this->view->topic);
        }
      }
    if(isset($questionId) && is_numeric($questionId))
      {
      $this->view->question = MidasLoader::loadModel("Question", 'reviewosehra')->load($questionId);
      $this->view->topic = $this->view->question->getTopic();
      if(isset($moveAction) && $moveAction == "up")
        {
        MidasLoader::loadModel("Question", 'reviewosehra')->moveUp($this->view->question);
        $this->view->question = false;
        }
      elseif(isset($moveAction) && $moveAction == "down")
        {
        MidasLoader::loadModel("Question", 'reviewosehra')->moveDown($this->view->question);
        $this->view->question = false;
        }
      }
      
    // Delete 
    if(isset($deleteListId) && is_numeric($deleteListId))
      {
      $list = MidasLoader::loadModel("Questionlist", 'reviewosehra')->load($deleteListId);
      MidasLoader::loadModel("Questionlist", 'reviewosehra')->delete($list);
      }
    if(isset($deleteTopicId) && is_numeric($deleteTopicId))
      {
      $topic = MidasLoader::loadModel("Topic", 'reviewosehra')->load($deleteTopicId);
      $this->view->questionlist = $topic->getQuestionlist();
      MidasLoader::loadModel("Topic", 'reviewosehra')->delete($topic);
      }
    if(isset($deleteQuestionId) && is_numeric($deleteQuestionId))
      {
      $question = MidasLoader::loadModel("Question", 'reviewosehra')->load($deleteQuestionId);
      $this->view->topic = $question->getTopic();
      MidasLoader::loadModel("Question", 'reviewosehra')->delete($question);
      }
      
    // Edit/Create topic
    if($this->getRequest()->isPost() && isset($_POST['topicname']))
      {
      if(is_numeric($_POST['oldtopic']))
        {
        $dao = MidasLoader::loadModel("Topic", 'reviewosehra')->load($_POST['oldtopic']);
        }
      else 
        {
        $dao = MidasLoader::newDao('TopicDao', 'reviewosehra');
        }
      $dao->setName($_POST['topicname']);
      $dao->setDescription($_POST['topicdescription']);
      $dao->setQuestionlistId($_POST['selectedlist']);
      MidasLoader::loadModel("Topic", 'reviewosehra')->save($dao);
      $this->view->topic = $dao;
      }
    
    // Edit/Create list
    if($this->getRequest()->isPost() && isset($_POST['newname']) && is_numeric($_POST['newcategory']))
      {
      if(is_numeric($_POST['oldlist']))
        {
        $dao = MidasLoader::loadModel("Questionlist", 'reviewosehra')->load($_POST['oldlist']);
        }
      else 
        {
        $dao = MidasLoader::newDao('QuestionlistDao', 'reviewosehra');
        }
      $dao->setName($_POST['newname']);
      $dao->setType($_POST['newtype']);
      $dao->setDescription($_POST['newdescription']);
      $dao->setCategoryId($_POST['newcategory']);
      MidasLoader::loadModel("Questionlist", 'reviewosehra')->save($dao);
      $this->view->questionlist = $dao;
      }
    // Edit/Create Question
    if($this->getRequest()->isPost() && isset($_POST['questiondescription']))
      {
      if(is_numeric($_POST['oldquestion']))
        {
        $dao = MidasLoader::loadModel("Question", 'reviewosehra')->load($_POST['oldquestion']);
        }
      else 
        {
        $dao = MidasLoader::newDao('QuestionDao', 'reviewosehra');
        }
      $dao->setTopicId($_POST['selectedtopic']);
      $dao->setDescription($_POST['questiondescription']);
      $dao->setComment((isset($_POST['questioncomment']) && $_POST['questioncomment'] == 1) ? "1":"0");
      $dao->setAttachfile((isset($_POST['questionattachfile']) && $_POST['questionattachfile'] == 1) ? "1":"0");
      MidasLoader::loadModel("Question", 'reviewosehra')->save($dao);
      $this->view->topic = $dao->getTopic();
      }
      
      
    $this->view->questionslists = MidasLoader::loadModel("Questionlist", 'reviewosehra')->getAll();
    $this->view->categoryTree = MidasLoader::loadComponent("Tree", "journal")->getAllTrees();
    
    if($this->view->question)$this->view->json['question'] = $this->view->question->toArray();
    if($this->view->question)$this->view->topic = $this->view->question->getTopic();
    if($this->view->topic)$this->view->json['topic'] = $this->view->topic->toArray();
    if($this->view->topic)$this->view->questionlist = $this->view->topic->getQuestionlist();
    if($this->view->questionlist)$this->view->json['list'] = $this->view->questionlist->toArray();    
    }
}//end class
