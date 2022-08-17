<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
use Fossology\Lib\Dao\ProjectDao;

class project_create extends FO_Plugin
{
  function __construct()
  {

    // echo ("<script>console.log('project_create __construct begin');</script>");

    $this->Name = "project_create";
    $this->Title = _("Create a new Fossology project");
    $this->MenuList = "Organize::Project::Create";
    $this->Dependency = array ();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
  }

  /**
   * \brief Given a parent project ID, a name and description,
   * create the named project under the parent.
   *
   * Includes idiot checking since the input comes from stdin.
   *
   * @param $parentId - parent project id
   * @param $newProject - new project name
   * @param $desc - new project discription
   *
   * @return int 1 if created, 0 if failed
   */
  public function create($parentId, $newProject, $desc)
  {

    echo ("<script>console.log('create begin');</script>");

    $projectName = trim($newProject);
    if (empty($projectName)) {
      return (0);
    }

    /* @var $projectDao ProjectDao*/
    $projectDao = $GLOBALS['container']->get('dao.project');

    $parentExists = $projectDao->getProject($parentId);
    if (! $parentExists) {
      return (0);
    }

    $projectWithSameNameUnderParent = $projectDao->getProjectId($projectName, $parentId);
    if (! empty($projectWithSameNameUnderParent)) {
      return 4;
    }

    $projectDao->createProject($projectName, $desc, $parentId);
    return (1);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {

    echo ("<script>console.log('Output begin');</script>");

    /* If this is a POST, then process the request. */
    $ParentId = GetParm('parentid', PARM_INTEGER);
    $NewProject = GetParm('newname', PARM_TEXT);
    $Desc = GetParm('description', PARM_TEXT);

    echo ("<script>console.log('ParentId');</script>");
    echo ("<script>console.log('" . json_encode($ParentId) . "');</script>");
    echo ("<script>console.log('NewProject');</script>");
    echo ("<script>console.log('" . json_encode($NewProject) . "');</script>");
    echo ("<script>console.log('Desc');</script>");
    echo ("<script>console.log('" . json_encode($Desc) . "');</script>");

    if (! empty($ParentId) && ! empty($NewProject)) {

      echo ("<script>console.log('in if');</script>");

      $rc = $this->create($ParentId, $NewProject, $Desc);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $text = _("Project");
        $text1 = _("Created");
        $this->vars['message'] = "$text " . htmlentities($NewProject) . " $text1";
      } else if ($rc == 4) {
        $text = _("Project");
        $text1 = _("Exists");
        $this->vars['message'] = "$text " . htmlentities($NewProject) . " $text1";
      }
    }

    echo ("<script>console.log('before GetUserRootProject');</script>");
    $test = GetUserRootFolder();
    echo ("<script>console.log('test');</script>");
    echo ("<script>console.log('" . json_encode($test) . "');</script>");
    $testCommonProject = Project2Path(1);
    echo ("<script>console.log('testCommonProject');</script>");
    echo ("<script>console.log('" . json_encode($testCommonProject) . "');</script>");

    $root_project_pk = GetUserRootProject();

    echo ("<script>console.log('root_project_pk');</script>");
    echo ("<script>console.log('" . json_encode($root_project_pk) . "');</script>");

    $formVars["projectOptions"] = ProjectListOption($root_project_pk, 0);

    return $this->renderString("admin-project-create-form.html.twig",$formVars);
  }
}

$NewPlugin = new project_create();
$NewPlugin->Initialize();
