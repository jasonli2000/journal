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

class Journal_SubmitController extends Journal_AppController
{
  function init()
    {

    }
    
  /** The first step of the submission process is  to select an issue */
  function selectissueAction()
    {
    if(!$this->logged)
      {
      $this->renderScript("login.phtml");
      return;
      }
    $this->view->issues = MidasLoader::loadModel("Issue", 'journal')->findActiveIssues();
    foreach($this->view->issues as $key => $issue)
      {
      $community = MidasLoader::loadModel("Folder")->getCommunity(MidasLoader::loadModel("Folder")->getRoot($issue));
      if(isset($_GET['community']) && $community->getKey() !=  $_GET['community']) unset($this->view->issues[$key]);
      if(!$community || !MidasLoader::loadModel("Community")->policyCheck($community, $this->userSession->Dao))
        {
        unset($this->view->issues[$key]);
        }
      }
    }
    
  /** Form allowing the user to submit an article*/
  function indexAction()
    {    
    if(!$this->logged)
      {
      $this->renderScript("login.phtml");
      return;
      }
    // load resource if it exists
    $item_id = $this->_getParam('itemId');  
    $revision_id = $this->_getParam('revisionId');  
    $issueId = $this->_getParam('issue');  
    
    // New Revision
    if(isset($item_id))
      {
      $item = MidasLoader::loadModel("Item")->load($item_id);      
      if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE))
        {
        throw new Zend_Exception("Permissions error.");
        }        
      $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $item->toArray(), "journal");
      $resourceDao->setRevision("New");
      $folder = end($item->getFolders());
      $this->view->json['showlicence'] = 1;
      }   
    // Edit
    else if(isset($revision_id))
      {
      $revision = MidasLoader::loadModel("ItemRevision")->load($revision_id);    
      if(!$revision)
        {
        throw new Zend_Exception("Unable to find revision.");
        }        
      $item = $revision->getItem();      
      $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $item->toArray(), "journal");
      $resourceDao->setRevision($revision);
      if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE) ||
              !$resourceDao->isAdmin($this->userSession->Dao))
        {
        throw new Zend_Exception("Permissions error.");
        }    
      $folder = end($item->getFolders());
      $this->view->json['showlicence'] = 0;
      }
    // New Resource
    else
      {
      $resourceDao = MidasLoader::newDao('ResourceDao', 'journal');
      $resourceDao->setRevision("New");
      $folder = MidasLoader::loadModel("Folder")->load($issueId);
      if(!$folder)throw new Zend_Exception("Unable to find issuse.");      
      $this->view->json['showlicence'] = 1;
      }
      
    $this->view->issueDao = MidasLoader::loadModel("Folder")->initDao("Issue", $folder->toArray(), "journal");
      
    // Process form result
    if($this->_request->isPost())
      {
      $resourceDao->setName($_POST['title']);
      $resourceDao->setDescription($_POST['description']);
      $resourceDao->setType($_POST['type']);     
      $resourceDao->setThumbnailId(new Zend_Db_Expr('NULL'));
      $anonymousGroup = MidasLoader::loadModel("Group")->load(MIDAS_GROUP_ANONYMOUS_KEY);
      $isNew = !$resourceDao->saved;
      // Create or update resource
      MidasLoader::loadModel("Item")->save($resourceDao);
      $itemRevisionDao = $resourceDao->getRevision();
      
      // create a new revision
      if(!$itemRevisionDao->saved)
        {        
        $itemRevisionDao->setChanges("");
        $itemRevisionDao->setUser_id($this->userSession->Dao->getKey());
        $itemRevisionDao->setDate(date('c'));
        $itemRevisionDao->setLicenseId(null);
        $lastExistingRevision = MidasLoader::loadModel("Item")->getLastRevision($resourceDao);
        MidasLoader::loadModel("Item")->addRevision($resourceDao, $itemRevisionDao);
        if($lastExistingRevision) // If new revision, copy previous bitstreams
          {
          $bitstreams = $lastExistingRevision->getBitstreams();
          foreach($bitstreams as $bitstream)
            {
            $type = MidasLoader::loadComponent("Bitstream", "journal")->getType($bitstream);
            $bitstream->saved = false;
            $bitstream->bitstream_id = null;
            if($bitstream->getName() == "")continue;
            MidasLoader::loadModel("ItemRevision")->addBitstream($itemRevisionDao, $bitstream);
            MidasLoader::loadComponent("Bitstream", "journal")->setType($bitstream, $type);
            if($type == BITSTREAM_TYPE_THUMBNAIL) $resourceDao->setLogo($bitstream);
            }
          }
        }  
        
      MidasLoader::loadModel('Folder')->addItem($this->view->issueDao, $resourceDao, false);
      $resourceDao->enable();
      
      // Make sure the journal and issue editor and the author can manage the resource
      $resourceDao->setSubmitter($this->userSession->Dao);
      if($isNew)
        {
        $resourceDao->initHandle();
        $adminGroup = $resourceDao->getAdminGroup();
        MidasLoader::loadModel("Itempolicygroup")->createPolicy($adminGroup, $resourceDao, MIDAS_POLICY_ADMIN);
        MidasLoader::loadModel("Itempolicyuser")->createPolicy($this->userSession->Dao, $resourceDao, MIDAS_POLICY_WRITE);
        
        $policies = $this->view->issueDao->getFolderpolicygroup();
        foreach($policies as $policy)
          {
          if($policy->getPolicy() == MIDAS_POLICY_ADMIN)
            {
            MidasLoader::loadModel("Itempolicygroup")->createPolicy($policy->getGroup(), $resourceDao, MIDAS_POLICY_ADMIN);
            }
          }
        }            
      
      $resourceDao->setInstitution($_POST['institution']);     
      $resourceDao->setAuthors(array($_POST['firstname'], $_POST['lastname']));     
      $resourceDao->setCategories($_POST['category']);     
      $resourceDao->setCopyright($_POST['copyright']);     
      $resourceDao->setDisclaimer($_POST['disclaimer']);     
      $resourceDao->setTags($_POST['tag']);     
      $resourceDao->setRelated($_POST['related']);     
      $resourceDao->setGrant($_POST['grant']);     
              
      // Update search index
      Zend_Registry::get('notifier')->callback('CALLBACK_CORE_ITEM_SAVED', array(
          'item' => $resourceDao,
          'metadataChanged' => true));
      
      $this->_redirect("/journal/submit/upload?revisionId=".$itemRevisionDao->getKey());
      return;
      }
      
    // send the variables to the view 
    $this->view->resource = $resourceDao;
    $this->view->disclaimers = MidasLoader::loadModel("Disclaimer", "journal")->getAll();
    
    // fetch all the keywords and send them to the view
    $this->view->json['trees'] = MidasLoader::loadComponent("Tree", "journal")->getAllTrees(false, $resourceDao->getCategories());
    }    
    
  /** Check if github repository exists. If yes, add it*/
  function addgithubhandlerAction()
    {
    $this->disableLayout();
    $this->disableView();
    
    $revision_id = $this->_getParam('revisionId');  
    $github = $this->_getParam('github');  
    $githubArray = explode("/", $github);
      
    // Check if github exists
    $client = new Zend_Http_Client("https://api.github.com/repos/".$githubArray[3]."/".$githubArray[4]);
    $returnJson = $client->request()->getBody();    
    $return = JsonComponent::decode($returnJson);    
    if($return['message'] == "Not Found")
      {
      echo JsonComponent::encode(array(0, "The repository doesn't exist or the URL is invalid."));
      return;
      }
    
    // load resource if it exists
    $revision = MidasLoader::loadModel("ItemRevision")->load($revision_id);    
    if(!$revision)
      {
      throw new Zend_Exception("Unable to find revision.");
      }        
    $item = $revision->getItem(); 
    if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE))
      {
      throw new Zend_Exception("Permissions error.");
      }       
    if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE))
      {
      throw new Zend_Exception("Permissions error.");
      }  
      
    $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $revision->getItem()->toArray(), "journal");
    $resourceDao->setGithub($github);
    
    // Add bitstream to the revision
    Zend_Loader::loadClass('BitstreamDao', BASE_PATH . '/core/models/dao');
    $bitstreamDao = new BitstreamDao;
    $bitstreamDao->setName($githubArray[3]."/".$githubArray[4]);
    $bitstreamDao->setPath("https://api.github.com/repos/".$githubArray[3]."/".$githubArray[4]);
    $bitstreamDao->setMimetype('url');
    $bitstreamDao->setSizebytes(0);
    $bitstreamDao->setChecksum(' ');

    $assetstoreDao = MidasLoader::loadModel('Assetstore')->getDefault();
    $bitstreamDao->setAssetstoreId($assetstoreDao->getKey());

    MidasLoader::loadModel('ItemRevision')->addBitstream($revision, $bitstreamDao);
    MidasLoader::loadComponent("Bitstream", "journal")->setType($bitstreamDao, BITSTREAM_TYPE_SOURCECODE_GITHUB);      
    
    Zend_Registry::get('notifier')->notifyEvent('EVENT_JOURNAL_UPLOAD_GITHUB', array($bitstreamDao->toArray()));
    echo JsonComponent::encode(array(1, ""));
    }
    
  /** Upload management. See jquery uploader library */
  function uploadhandlerAction()
    {
    error_reporting(E_ALL | E_STRICT);
    $this->disableLayout();
    $this->disableView();
    require(dirname(__FILE__).'/components/UploadHandler.php');
    if(!file_exists($this->getTempDirectory()."/uploaded"))
      {
      mkdir($this->getTempDirectory()."/uploaded");
      }
    new UploadHandler(array('upload_dir' => $this->getTempDirectory()."/uploaded/",  'user_dirs' => true,'orient_image' => false));
    }
    
  /** delete a resource */
  function deleteAction()
    {
    $this->requireAdminPrivileges();
    $item_id = $this->_getParam('itemId');  
    $revision_id = $this->_getParam('revisionId');  
    
    if(isset($item_id))
      {
      $item = MidasLoader::loadModel("Item")->load($item_id);      
      MidasLoader::loadModel("Item")->delete($item);
      $this->_redirect("/");
      }
    elseif(isset($revision_id))
      {
      $revision = MidasLoader::loadModel("ItemRevision")->load($revision_id);      
      $item = $revision->getItem();
      MidasLoader::loadModel("ItemRevision")->delete($revision);
      $revisionOld = MidasLoader::loadModel("Item")->getLastRevision($item);
      $this->_redirect("/journal/view/".$revisionOld->getKey());
      }
    }
    
  /** Page where the user select what to upload */
  function uploadAction()
    {
    $revision_id = $this->_getParam('revisionId');  
    $processUpload = $this->_getParam('processUpload');  
    $type = $this->_getParam('type');  

    // load resource if it exists
    $revision = MidasLoader::loadModel("ItemRevision")->load($revision_id);    
    if(!$revision)
      {
      throw new Zend_Exception("Unable to find revision.");
      }        
    $item = $revision->getItem(); 
    if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE))
      {
      throw new Zend_Exception("Permissions error.");
      }       
    if(!MidasLoader::loadModel("Item")->policyCheck($item, $this->userSession->Dao, MIDAS_POLICY_WRITE))
      {
      throw new Zend_Exception("Permissions error.");
      }        
    $resourceDao = MidasLoader::loadModel("Item")->initDao("Resource", $item->toArray(), "journal");
    $resourceDao->setRevision($revision);
    
    $issue =  end($resourceDao->getFolders());
    $community =  MidasLoader::loadModel("Folder")->getCommunity(MidasLoader::loadModel("Folder")->getRoot($issue));
    $memberGroup = $community->getMemberGroup();
    
    // Check if public or private (If private, it means it requires approval
    $private = true;
    foreach($resourceDao->getItempolicygroup() as $policy)
      {
      if($policy->getGroupId() == $memberGroup->getKey())
        {
        $private = false;
        break;
        }
      }
    
    if(isset($processUpload))
      {
      require(dirname(__FILE__).'/components/UploadHandler.php');
      $upload_handler = new UploadHandler(array('upload_dir' => $this->getTempDirectory()."/uploaded/", 'user_dirs' => true, 'orient_image' => false),false);
      $files = $upload_handler->get(false);
      foreach($files['files'] as $file)
        {   
        $filepath = $this->getTempDirectory()."/uploaded/".  session_id()."/".$file->name;
        if(file_exists($filepath))
          {
          MidasLoader::loadComponent('Upload')->createNewRevision($this->userSession->Dao, $file->name, $filepath,
                                                      "", $item->getKey(), $revision->getRevision(), null,
                                                      '', false);
          $bitstreams = MidasLoader::loadModel("Item")->getLastRevision($resourceDao)->getBitstreams();
          foreach($bitstreams as $bitstream)
            {
            if(is_numeric($type) && $bitstream->getName() == $file->name && (strtotime($bitstream->getDate()) + 5) >= strtotime(date("c")))
              {
              MidasLoader::loadComponent("Bitstream", "journal")->setType($bitstream, $type);
              if($type == BITSTREAM_TYPE_THUMBNAIL) $resourceDao->setLogo($bitstream);
              }
            }
          }
        }
      }
      
    $bitstreams = $resourceDao->getRevision()->getBitstreams(); // the bitstreams are the files
    
    if($this->_request->isPost())
      {
      $this->disableLayout();
      $this->disableView();
      if(isset($_POST['deletebitstream']))
        {
        $bitstream = MidasLoader::loadModel("Bitstream")->load($_POST['deletebitstream']);
        if($bitstream && $bitstream->getItemrevision()->getItem()->getKey() == $resourceDao->getKey())
          {
          MidasLoader::loadModel("Bitstream")->delete($bitstream);
          }
        return;
        }
      if(isset($_POST['finish']))
        {
        if($private && $resourceDao->isAdmin($this->userSession->Dao)) // Approve
          {
          $anonymousGroup = MidasLoader::loadModel("Group")->load(MIDAS_GROUP_ANONYMOUS_KEY);
          if($community->getPrivacy() != MIDAS_COMMUNITY_PRIVATE) 
            {
            MidasLoader::loadModel("Itempolicygroup")->createPolicy($anonymousGroup, $resourceDao, MIDAS_POLICY_READ);
            }
          MidasLoader::loadModel("Itempolicygroup")->createPolicy($memberGroup, $resourceDao, MIDAS_POLICY_READ);
          MidasLoader::loadComponent("Notification", "journal")->newArticle($resourceDao);
          
          // Delete cache file
          $cacheFile = UtilityComponent::getTempDirectory()."/homeSearch.json";
          if(file_exists($cacheFile))
            {
            unlink($cacheFile);
            }
          }
        elseif($private) // Send for approval
          {
          $this->view = MidasLoader::loadComponent("Notification", "journal")->sendForApproval($resourceDao);
          }
        if (isset($_POST['source-license']))
          {
          $resourceDao->setSourceLicense($_POST['source-license']);
          }
        $this->_redirect("/journal/view/".$resourceDao->getRevision()->getKey());
        return;
        }
      }
      
    // sent to theview
    $this->view->isPrivate = $private;
    $this->view->isAdmin = $resourceDao->isAdmin($this->userSession->Dao);
    $this->view->resource = $resourceDao;
    $this->view->bitstreams = $bitstreams;
    $this->view->json['resource'] = $resourceDao->toArray();
    $this->view->json['revision'] = $revision_id;
    }
}//end class
