<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

include_once('api.php');

class ProjectAPI extends CDashAPI
{
  /** Return the list of all public projects */
  private function ListProjects()
    {
    include_once('../cdash/common.php');
    $query = pdo_query("SELECT id,name FROM project WHERE public=1 ORDER BY name ASC");
    while($query_array = pdo_fetch_array($query))
      {
      $project['id'] = $query_array['id'];
      $project['name'] = $query_array['name'];
      $projects[] = $project;
      }
    return $projects;
    } // end function ListProjects

  /**
   * Authenticate to the web API as a project admin
   * @param project the name of the project
   * @param key the web API key for that project
   */
  function Authenticate()
    {
    include_once('../cdash/common.php');
    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>"You must specify a project parameter.");
      }
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }
    if(!isset($this->Parameters['key']) || $this->Parameters['key'] == '')
      {
      return array('status'=>false, 'message'=>"You must specify a key parameter.");
      }

    $key = $this->Parameters['key'];
    $query = pdo_query("SELECT webapikey FROM project WHERE id=$projectid");
    if(pdo_num_rows($query) == 0)
      {
      return array('status'=>false, 'message'=>"Invalid projectid.");
      }
    $row = pdo_fetch_array($query);
    $realKey = $row['webapikey'];

    if($key != $realKey)
      {
      return array('status'=>false, 'message'=>"Incorrect API key passed.");
      }
    $token = create_web_api_token($projectid);
    return array('status'=>true, 'token'=>$token);
    }

  /**
   * List all files for a given project
   * @param project the name of the project
   * @param key the web API key for that project
   * @param [match] regular expression that files must match
   * @param [mostrecent] include this if you only want the most recent match
   */
  function ListFiles()
    {
    include_once('../cdash/common.php');
    include_once('../models/project.php');

    global $CDASH_DOWNLOAD_RELATIVE_URL;

    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>'You must specify a project parameter.');
      }
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }

    $Project = new Project();
    $Project->Id = $projectid;
    $files = $Project->GetUploadedFilesOrUrls();

    if(!$files)
      {
      return array('status'=>false, 'message'=>'Error in Project::GetUploadedFilesOrUrls');
      }
    $filteredList = array();
    foreach($files as $file)
      {
      if($file['isurl'])
        {
        continue; // skip if filename is a URL
        }
      if(isset($this->Parameters['match']) && !preg_match('/'.$this->Parameters['match'].'/', $file['filename']))
        {
        continue; //skip if it doesn't match regex
        }
      $filteredList[] = array_merge($file, array('url'=>$CDASH_DOWNLOAD_RELATIVE_URL.'/'.$file['sha1sum'].'/'.$file['filename']));

      if(isset($this->Parameters['mostrecent']))
        {
        break; //user requested only the most recent file
        }
      }

    return array('status'=>true, 'files'=>$filteredList);
    }

  /**
   * Add a group to the project as a project admin
   * @param project the name of the project
   * @param group the name of the group
   */
  function AddGroup()
    {
    include_once('../cdash/common.php');
    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>"You must specify a project parameter.");
      }

    $projectid = get_project_id($this->Parameters['project']);

    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }

    if(!isset($this->Parameters['group']))
      {
      return array('status'=>false, 'message'=>"You must specify a group parameter.");
      }

    if(!isset($this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'You must specify a token parameter.');
      }

    // Perform the authentication (make sure user has project admin priviledges)
    if(!web_api_authenticate($projectid, $this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'Invalid API token.');
      }

    $status = true;
    $errorMessage = '';

    $Group = htmlspecialchars(pdo_real_escape_string($this->Parameters['group']));

    // Avoid creating a group that is Nightly, Experimental or Continuous
    if($Group == "Nightly" || $Group == "Experimental" || $Group == "Continuous")
      {
      $status = false;
      $errorMessage = "You cannot create a group named 'Nightly','Experimental' or 'Continuous'";
      }
    else
      {
      // Insert the new group

      $res = pdo_single_row_query("SELECT count(*) as c FROM buildgroup WHERE name = '$Group'");
      $groupsWithTheSameName = $res['c'];

      if ($groupsWithTheSameName > 0)
        {
        $status = false;
        $errorMessage = "There is already a group with the same name.";
        }
      else
        {

        // Find the last position available
        $groupposition_array = pdo_fetch_array(pdo_query("SELECT bg.position,bg.starttime FROM buildgroup AS g, buildgroupposition AS bg
                                                          WHERE g.id=bg.buildgroupid AND g.projectid='$projectid'
                                                          AND bg.endtime='1980-01-01 00:00:00' ORDER BY bg.position DESC LIMIT 1"));
        $newposition = $groupposition_array["position"]+1;
        $starttime = '1980-01-01 00:00:00';
        $endtime = '1980-01-01 00:00:00';

        // Insert the new group
        $sql = "INSERT INTO buildgroup (name,projectid,starttime,endtime,description) VALUES ('$Group','$projectid','$starttime','$endtime','')";
        if(pdo_query("$sql"))
          {
          $newgroupid = pdo_insert_id("buildgroup");

          // Create a new position for this group
          pdo_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime) VALUES ('$newgroupid','$newposition','$starttime','$endtime')");
          }
        else
          {
          $status = false;
          $errorMessage = pdo_error();
          }
        }
      } // end not Nightly or Experimental or Continuous

    return array('status'=>$status, 'error'=>$errorMessage);
    }


  /**
   * Remove every group with the given name from the project as a project admin
   * @param project the name of the project
   * @param group the name of the group
   */
  function RemoveGroup()
    {
    include_once('../cdash/common.php');
    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>"You must specify a project parameter.");
      }

    $projectid = get_project_id($this->Parameters['project']);

    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }

    if(!isset($this->Parameters['group']))
      {
      return array('status'=>false, 'message'=>"You must specify a group parameter.");
      }

    if(!isset($this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'You must specify a token parameter.');
      }

    // Perform the authentication (make sure user has project admin priviledges)
    if(!web_api_authenticate($projectid, $this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'Invalid API token.');
      }

    $status = true;
    $errorMessage = '';

    $Group = htmlspecialchars(pdo_real_escape_string($this->Parameters['group']));

    $oldBuildGroups = pdo_query("SELECT id FROM buildgroup WHERE name = '$Group'");
    while($oldBuildGroups_array = pdo_fetch_array($oldBuildGroups))
      {

      $Groupid = $oldBuildGroups_array['id'];

      // We delete all the build2grouprule associated with the group
      pdo_query("DELETE FROM build2grouprule WHERE groupid='$Groupid'");

      // We delete the buildgroup
      pdo_query("DELETE FROM buildgroup WHERE id='$Groupid'");

      // Restore the builds that were associated with this group
      $oldbuilds = pdo_query("SELECT id,type FROM build WHERE id IN (SELECT buildid AS id FROM build2group WHERE groupid='$Groupid')");
      $errorMessage .= pdo_error();
      while($oldbuilds_array = pdo_fetch_array($oldbuilds))
        {
        // Move the builds
        $buildid = $oldbuilds_array["id"];
        $buildtype = $oldbuilds_array["type"];

        // Find the group corresponding to the build type
        $query = pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'");
        if(pdo_num_rows($query) == 0)
          {
          $query = pdo_query("SELECT id FROM buildgroup WHERE name='Experimental' AND projectid='$projectid'");
          }
        $errorMessage .= pdo_error();
        $grouptype_array = pdo_fetch_array($query);
        $grouptype = $grouptype_array["id"];

        pdo_query("UPDATE build2group SET groupid='$grouptype' WHERE buildid='$buildid'");

        $errorMessage .= pdo_error();
        }

      // We delete the buildgroupposition and update the position of the other groups
      pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid='$Groupid'");

      $buildgroupposition = pdo_query("SELECT bg.buildgroupid FROM buildgroupposition as bg, buildgroup as g
                                        WHERE g.projectid='$projectid' AND bg.buildgroupid=g.id ORDER BY bg.position ASC");

      $p = 1;
      while($buildgroupposition_array = pdo_fetch_array($buildgroupposition))
        {
        $buildgroupid = $buildgroupposition_array["buildgroupid"];
        pdo_query("UPDATE buildgroupposition SET position='$p' WHERE buildgroupid='$buildgroupid'");
        $p++;
        }

      }

    return array('status'=>$status, 'error'=>$errorMessage);
    }


  /** Run function */
  function Run()
    {
    switch($this->Parameters['task'])
      {
      case 'list': return $this->ListProjects();
      case 'login': return $this->Authenticate();
      case 'files': return $this->ListFiles();
      case 'addGroup': return $this->AddGroup();
      case 'removeGroup': return $this->RemoveGroup();
      }
    }
}

?>
