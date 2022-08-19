<?php

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\UI\FolderNav;
use Fossology\Lib\UI\MenuHook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ui_browse_folder extends FO_Plugin
{

    /** @var UploadDao */
    private $uploadDao;
    /** @var FolderDao */
    private $folderDao;

    function __construct()
    {
        $this->Name = "BrosweFolder";
        $this->MenuList = "Browse::Folder view";
        $this->MenuOrder = 80;
        $this->MenuTarget = "";
        $this->MenuTarget = "";
        $this->DBaccess = PLUGIN_DB_READ;
        $this->LoginFlag = 0;

        global $container;
        $this->uploadDao = $container->get('dao.upload');
        $this->folderDao = $container->get('dao.folder');

        parent::__construct();
    }

    function RegisterMenus()
    {
        menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->Name, $this->Name);
        return ($this->State == PLUGIN_STATE_READY);
    }

    /**
     * \brief Given a upload_pk, list every item in it.
     * If it is an individual file, then list the file contents.
     */
    function ShowItem($Upload, $Item, $Show, $Folder, $uploadtree_tablename)
    {
        global $container;
        /** @var DbManager */
        $dbManager = $container->get('db.manager');
        $RowStyle1 = "style='background-color:#ecfaff'";  // pale blue
        $RowStyle2 = "style='background-color:#ffffe3'";  // pale yellow
        $ColorSpanRows = 3;  // Alternate background color every $ColorSpanRows
        $RowNum = 0;

        $V = "";
        /* Use plugin "view" and "download" if they exist. */
        $Uri = Traceback_uri() . "?mod=" . $this->Name . "&folder=$Folder";

        /* there are three types of Browse-Pfile menus */
        /* menu as defined by their plugins */
        $MenuPfile = menu_find("Browse-Pfile", $MenuDepth);
        /* menu but without Compare */
        $MenuPfileNoCompare = menu_remove($MenuPfile, "Compare");
        /* menu with only Tag and Compare */
        $MenuTag = array();
        foreach ($MenuPfile as $value) {
            if (($value->Name == 'Tag') || ($value->Name == 'Compare')) {
                $MenuTag[] = $value;
            }
        }

        $Results = GetNonArtifactChildren($Item, $uploadtree_tablename);
        $ShowSomething = 0;
        $V .= "<table class='text' style='border-collapse: collapse' border=0 padding=0>\n";
        $stmtGetFirstChild = __METHOD__ . '.getFirstChild';
        $dbManager->prepare($stmtGetFirstChild, "SELECT uploadtree_pk FROM $uploadtree_tablename WHERE parent=$1 limit 1");
        foreach ($Results as $Row) {
            if (empty($Row['uploadtree_pk'])) {
                continue;
            }
            $ShowSomething = 1;
            $Name = $Row['ufile_name'];

            /* Set alternating row background color - repeats every $ColorSpanRows rows */
            $RowStyle = (($RowNum++ % (2 * $ColorSpanRows)) < $ColorSpanRows) ? $RowStyle1 : $RowStyle2;
            $V .= "<tr $RowStyle>";

            /* Check for children so we know if the file should by hyperlinked */
            $result = $dbManager->execute($stmtGetFirstChild, array($Row['uploadtree_pk']));
            $HasChildren = $dbManager->fetchArray($result);
            $dbManager->freeResult($result);

            $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'];
            $Link = $HasChildren ? "$Uri&show=$Show&upload=$Upload&item=$Row[uploadtree_pk]" : NULL;

            if ($Show == 'detail') {
                $V .= "<td class='mono'>" . DirMode2String($Row['ufile_mode']) . "</td>";
                if (!Isdir($Row['ufile_mode'])) {
                    $V .= "<td align='right'>&nbsp;&nbsp;" . number_format($Row['pfile_size'], 0, "", ",") . "&nbsp;&nbsp;</td>";
                } else {
                    $V .= "<td>&nbsp;</td>";
                }
            }

            $displayItem = Isdir($Row['ufile_mode']) ? "$Name/" : $Name;
            if (!empty($Link)) {
                $displayItem = "<a href=\"$Link\">$displayItem</a>";
            }
            if (Iscontainer($Row['ufile_mode'])) {
                $displayItem = "<b>$displayItem</b>";
            }
            $V .= "<td>$displayItem</td>\n";

            if (!Iscontainer($Row['ufile_mode'])) {
                $V .= menu_to_1list($MenuPfileNoCompare, $Parm, "<td>", "</td>\n", 1, $Upload);
            } else if (!Isdir($Row['ufile_mode'])) {
                $V .= menu_to_1list($MenuPfile, $Parm, "<td>", "</td>\n", 1, $Upload);
            } else {
                $V .= menu_to_1list($MenuTag, $Parm, "<td>", "</td>\n", 1, $Upload);
            }
        } /* foreach($Results as $Row) */
        $V .= "</table>\n";
        if (!$ShowSomething) {
            $text = _("No files");
            $V .= "<b>$text</b>\n";
        } else {
            $V .= "<hr>\n";
            if (count($Results) == 1) {
                $text = _("1 item");
                $V .= "$text\n";
            } else {
                $text = _("items");
                $V .= count($Results) . " $text\n";
            }
        }
        return ($V);
    }

    /**
     * @brief Given a folderId, list every item in it.
     * If it is an individual file, then list the file contents.
     */
    private function ShowFolder($folderId)
    {
        $rootFolder = $this->folderDao->getDefaultFolder(Auth::getUserId());
        if ($rootFolder == NULL) {
            $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
        }
        /* @var $uiFolderNav FolderNav */
        $uiFolderNav = $GLOBALS['container']->get('ui.folder.nav');

        $folderNav = '<div id="sidetree" class="container justify-content-center" style="min-width: 234px;">';
        if ($folderId != $rootFolder->getId()) {
            $folderNav .= '<div class="treeheader" style="display:inline;"><a class="btn btn-outline-success btn-sm" href="' .
                Traceback_uri() . '?mod=' . $this->Name . '">Top folder</a> | </div>';
        }
        $folderNav .= '<div id="sidetreecontrol" class="treeheader" style="display:inline;">
                     <a class="btn btn-outline-success btn-sm" href="?#">Collapse All</a> |
                     <a class="btn btn-outline-success btn-sm" href="?#">Expand All</a>
                   </div><br/><br/>';
        $folderNav .= '
      <div class="col-sm-20" style="margin-top:-10px;">
        <input id="searchFolderTree" type="text" class="form-control" name="searchFolderTree" placeholder="Search folder" autofocus="autofocus"">
      </div>';
        $folderNav .= $uiFolderNav->showFolderTree($folderId) . '</div>';

        $this->vars['folderNav'] = $folderNav;

        $assigneeArray = $this->getAssigneeArray();
        $this->vars['assigneeOptions'] = $assigneeArray;
        $this->vars['statusOptions'] = $this->uploadDao->getStatusTypeMap();
        $this->vars['folder'] = $folderId;
        $this->vars['folderName'] = $this->folderDao->getFolder($folderId)->getName();
    }


    function Output()
    {
        if ($this->State != PLUGIN_STATE_READY) {
            return 0;
        }

        $this->folderDao->ensureTopLevelFolder();

        $folder_pk = GetParm("folder", PARM_INTEGER);
        $Upload = GetParm("upload", PARM_INTEGER);  // upload_pk to browse
        $Item = GetParm("item", PARM_INTEGER);  // uploadtree_pk to browse

        echo ("<script>console.log('folder_pk');</script>");
        echo ("<script>console.log('" . json_encode($folder_pk) . "');</script>");
        echo ("<script>console.log('Upload');</script>");
        echo ("<script>console.log('" . json_encode($Upload) . "');</script>");
        echo ("<script>console.log('Item');</script>");
        echo ("<script>console.log('" . json_encode($Item) . "');</script>");
        ob_flush();

        /* check if $folder_pk is accessible to logged in user */
        if (!empty($folder_pk) && !$this->folderDao->isFolderAccessible($folder_pk)) {
            $this->vars['message'] = _("Permission Denied");
            return $this->render('include/base.html.twig');
        }

        /* check permission if $Upload is given */
        if (!empty($Upload) && !$this->uploadDao->isAccessible($Upload, Auth::getGroupId())) {
            $this->vars['message'] = _("Permission Denied");
            return $this->render('include/base.html.twig');
        }

        if (empty($folder_pk)) {
            try {
                $folder_pk = $this->getFolderId($Upload);
            } catch (Exception $exc) {
                return $exc->getMessage();
            }
        }

        $output = $this->outputItemHtml($Item, $folder_pk, $Upload);
        if ($output instanceof Response) {
            return $output;
        }

        $this->vars['content'] = $output;
        $modsUploadMulti = MenuHook::getAgentPluginNames('UploadMulti');
        if (!empty($modsUploadMulti)) {
            $multiUploadAgents = array();
            foreach ($modsUploadMulti as $mod) {
                $multiUploadAgents[$mod] = $GLOBALS['Plugins'][$mod]->title;
            }
            $this->vars['multiUploadAgents'] = $multiUploadAgents;
        }
        $this->vars['folderId'] = $folder_pk;

        return $this->render('ui-browse.html.twig');
    }

    private function getFolderId($uploadId)
    {
        $rootFolder = $this->folderDao->getDefaultFolder(Auth::getUserId());
        if ($rootFolder == NULL) {
            $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
        }
        if (empty($uploadId)) {
            return $rootFolder->getId();
        }

        global $container;
        /* @var $dbManager DbManager */
        $dbManager = $container->get('db.manager');
        $uploadExists = $dbManager->getSingleRow(
            "SELECT count(*) cnt FROM upload WHERE upload_pk=$1 " .
                "AND (expire_action IS NULL OR expire_action!='d') AND pfile_fk IS NOT NULL",
            array($uploadId)
        );
        if ($uploadExists['cnt'] < 1) {
            throw new Exception("This upload no longer exists on this system.");
        }

        $folderTreeCte = $this->folderDao->getFolderTreeCte($rootFolder);

        $parent = $dbManager->getSingleRow(
            $folderTreeCte .
                " SELECT ft.folder_pk FROM foldercontents fc LEFT JOIN folder_tree ft ON fc.parent_fk=ft.folder_pk "
                . "WHERE child_id=$2 AND foldercontents_mode=$3 ORDER BY depth LIMIT 1",
            array($rootFolder->getId(), $uploadId, FolderDao::MODE_UPLOAD),
            __METHOD__ . '.parent'
        );
        if (!$parent) {
            throw new Exception("Upload $uploadId missing from foldercontents in your foldertree.");
        }
        return $parent['folder_pk'];
    }

    /**
     * @param int $uploadTreeId
     * @param int $Folder
     * @param int $Upload
     * @return string
     */
    function outputItemHtml($uploadTreeId, $Folder, $Upload)
    {
        global $container;
        $dbManager = $container->get('db.manager');
        $show = 'quick';
        $html = '';
        $uploadtree_tablename = "";
        if (!empty($uploadTreeId)) {
            $sql = "SELECT ufile_mode, upload_fk FROM uploadtree WHERE uploadtree_pk = $1";
            $row = $dbManager->getSingleRow($sql, array($uploadTreeId));
            $Upload = $row['upload_fk'];
            if (!$this->uploadDao->isAccessible($Upload, Auth::getGroupId())) {
                $this->vars['message'] = _("Permission Denied");
                return $this->render('include/base.html.twig');
            }

            if (!Iscontainer($row['ufile_mode'])) {
                $parentItemBounds = $this->uploadDao->getParentItemBounds($Upload);
                if (!$parentItemBounds->containsFiles()) {
                    // Upload with a single file, open license view
                    return new RedirectResponse(Traceback_uri() . '?mod=view-license'
                        . Traceback_parm_keep(array("upload", "item")));
                }
                global $Plugins;
                $View = &$Plugins[plugin_find_id("view")];
                if (!empty($View)) {
                    $this->vars['content'] = $View->ShowView(NULL, "browse");
                    return $this->render('include/base.html.twig');
                }
            }
            $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($row['upload_fk']);
            $html .= Dir2Browse($this->Name, $uploadTreeId, NULL, 1, "Browse", -1, '', '', $uploadtree_tablename) . "\n";
        } else if (!empty($Upload)) {
            $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($Upload);
            $html .= Dir2BrowseUpload(
                $this->Name,
                $Upload,
                NULL,
                1,
                "Browse",
                $uploadtree_tablename
            ) . "\n";
        }

        if (empty($Upload)) {
            $this->vars['show'] = $show;
            $this->ShowFolder($Folder);
            return $html;
        }

        if (empty($uploadTreeId)) {
            try {
                $uploadTreeId = $this->uploadDao->getUploadParent($Upload);
            } catch (Exception $e) {
                $this->vars['message'] = $e->getMessage();
                return $this->render('include/base.html.twig');
            }
        }
        $html .= $this->ShowItem($Upload, $uploadTreeId, $show, $Folder, $uploadtree_tablename);
        $this->vars['content'] = $html;
        return $this->render('include/base.html.twig');
    }

    /**
     * @return array
     */
    private function getAssigneeArray()
    {
        global $container;
        /** @var UserDao $userDao */
        $userDao = $container->get('dao.user');
        $assigneeArray = $userDao->getUserChoices();
        $assigneeArray[Auth::getUserId()] = _('-- Me --');
        $assigneeArray[1] = _('Unassigned');
        $assigneeArray[0] = '';
        return $assigneeArray;
    }
}

$NewPlugin = new ui_browse_folder();
$NewPlugin->Install();
