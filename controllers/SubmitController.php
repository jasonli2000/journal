<?php

class Reviewosehra_SubmitController extends Reviewosehra_AppController
{
  // Initialization method. Called before every Action
  function init()
    {
    parent::init();    
    }
   
  function indexAction()
    {  
    $revision_id = $this->_getParam('revision_id');
    $review_id = $this->_getParam('review_id');
    $review = false;
    if(isset($revision_id) && !$this->logged)
      {
      $this->haveToBeLogged();
      return;      
      }
      
    if(isset($review_id))
      {
      $review = MidasLoader::loadModel("Review", 'reviewosehra')->load($review_id);
      if($review)
        {
        $revision_id = $review->getRevisionId();
        }
      }
      
    if(!isset($revision_id) || !is_numeric($revision_id))
      {
      throw new Zend_Exception("revision_id should be a number");
      }
    $revision = MidasLoader::loadModel("ItemRevision")->load($revision_id);
    $itemDao = $revision->getItem();
    if($itemDao === false || !MidasLoader::loadModel("Item")->policyCheck($itemDao, $this->userSession->Dao, MIDAS_POLICY_READ))
      {
      throw new Zend_Exception("This item doesn't exist or you don't have the permissions.");
      }
    $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $itemDao->toArray(), "journal");
    $resourceDao->setRevision($revision);
    
    if($this->_request->isPost())
      {
      $this->disableLayout();
      $this->disableView();
      
      if(!$this->logged)
        {
        throw new Zend_Exception("Please log in");
        return;
        }
        
      $content = $this->_getParam("content");
      $cacheSummary = $this->_getParam("cache_summary");
      $is_complete = $this->_getParam("is_complete");
      if($review)
        {
        if(!$resourceDao->isAdmin($this->userSession->Dao) && $this->userSession->Dao->getKey() != $review->getUserId())
          {
          throw new Zend_Exception("Permission error");
          return;
          }
        $reviewDao = $review;
        }
      else
        {
        $reviewDao = MidasLoader::newDao("ReviewDao", "reviewosehra");
        $reviewDao->setRevisionId($revision->getKey());
        $reviewDao->setUserId($this->userSession->Dao->getKey());
        $contentArray = JsonComponent::decode($content);
        $reviewDao->setType($contentArray['list']['type']);
        }
      $reviewDao->setContent($content);
      $reviewDao->setCacheSummary($cacheSummary);
      $reviewDao->setComplete($is_complete);
      MidasLoader::loadModel("Review", 'reviewosehra')->save($reviewDao);
      echo JsonComponent::encode($this->view->webroot."/reviewosehra/submit/?review_id=".$reviewDao->getKey());
      return;
      }
      
    $isEditable = true;

    // look for existing review    
    $questionslists = MidasLoader::loadModel("Questionlist", 'reviewosehra')->getAll();
    if($review)
      {
      if(!$this->logged || (!$resourceDao->isAdmin($this->userSession->Dao) && $this->userSession->Dao->getKey() != $review->getUserId()))
        {
        $isEditable = false;
        }
      $mainList = JsonComponent::decode($review->getContent());
      }
    else
      {
      $categories = $resourceDao->getCategories();
      $mainListTmp = end($questionslists);
      foreach($categories as $cat)
        {
        foreach($questionslists as $list)
          {
          if($cat == $list->getCategoryId())   $mainListTmp = $list;
          }
        }
      $mainList = $mainListTmp->toArray();
      }
    
    $this->view->isEditable = $isEditable;
    $this->view->resource = $resourceDao;
    $this->view->listArray = $mainList;
    $this->view->json['listArray'] = $mainList;
    $this->view->json['listArray']['revision_id'] = $revision_id;
    $this->view->json['listArray']['review_id'] = $review_id;
    $this->view->lists = $questionslists;
    }
}//end class