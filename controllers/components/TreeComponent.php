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

/** Cateogyr Tree  component */
class Journal_TreeComponent extends AppComponent
{  
  /**
   * Get formatted tree
   * @param bool $includeDao include or nor the DAO 
   * (we don't want to do it if we plan to sent the array to the json format)
   * @return array
   */
  public function getAllTrees($includeDao = false, $selected = array(), $showCertification = false)
    {    
    $trees = array();
    $allEntries = MidasLoader::loadModel('Category', 'journal')->getAll();
    
    $cacheFile = UtilityComponent::getTempDirectory()."/treeCache.json";
    
    if(empty($selected) && file_exists($cacheFile) &&  (filemtime($cacheFile) > (time() - 60 * 60 * 24 * 1 ))) // 1 day cache
      {
      $trees = JsonComponent::decode(file_get_contents($cacheFile));
      }
    else
      {
      foreach($allEntries as $entry)
        {      
        if($entry->getParentId() == -1)
          {
          $select = 0;
          if(in_array($entry->getKey(), $selected)) $select = 1;
          if($includeDao) $trees[] = array('dao' => $entry, 'select' => $select, 'title' => $entry->getName(), 'key' => $entry->getKey(), 
              'children' => $this->getChildren($allEntries, $entry, $includeDao, $selected));
          else $trees[] = array('title' => $entry->getName(), 'select' => $select, 'key' => $entry->getKey(), 
              'children' => $this->getChildren($allEntries, $entry, $includeDao, $selected));
          }
        }
      if(empty($selected)) file_put_contents($cacheFile, JsonComponent::encode($trees));
      }
 
    if($showCertification)
      {
      $trees[] = array('dao' => new stdClass(), 'select' => 0, 'title' => "Certified", 'key' => -1, 
            'children' => array(
                array('dao' => new stdClass(), 'select' => 0, 'title' => "Level 1", 'key' => "certified-1", 
                'children' => array()),
                array('dao' => new stdClass(), 'select' => 0, 'title' => "Level 2", 'key' => "certified-2", 
                'children' => array()),
                array('dao' => new stdClass(), 'select' => 0, 'title' => "Level 3", 'key' => "certified-3", 
                'children' => array()),
                array('dao' => new stdClass(), 'select' => 0, 'title' => "Level 4", 'key' => "certified-4", 
                'children' => array())                
            ));
      }
    return $trees;
    }

  /**
   * Get childens recursively
   * @param array $tree
   * @param CategoryDao $parent
   * @param bool $includeDao include or nor the DAO 
   * @return array
   */
  private function getChildren($tree, $parent, $includeDao, $selected)
    {
    $children = array();
    foreach($tree as $entry)
      {
      if($entry->getParentId() == $parent->getKey())
        {
        $select = 0;
        if(in_array($entry->getKey(), $selected)) $select = 1;
        if($includeDao) $children[] = array('dao' => $entry, 'select' => $select, 'title' => $entry->getName(), 'key' => $entry->getKey(), 
            'children' => $this->getChildren($tree, $entry, $includeDao, $selected));
        else $children[] = array('title' => $entry->getName(), 'select' => $select, 'key' => $entry->getKey(), 
            'children' => $this->getChildren($tree, $entry, $includeDao, $selected));
        }
      }
    return $children;
    }
} // end class
