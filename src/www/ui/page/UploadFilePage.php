<?php

/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2017 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Page;

use Fossology\UI\Page\UploadPageBase;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class UploadFilePage extends UploadPageBase
{
  const FILE_INPUT_NAME = 'fileInput';
  const PROJECT_INPUT_NAME = 'projectInput';

  public function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("Upload a New File"),
      self::MENU_LIST => "Upload::From File",
      self::DEPENDENCIES => array("agent_unpack", "showjobs"),
      self::PERMISSION => Auth::PERM_WRITE
    ));
  }


  /**
   * @param Request $request
   * @return Response
   */
  protected function handleView(Request $request, $vars)
  {
    $vars['fileInputName'] = self::FILE_INPUT_NAME;
    $vars['projectInputName'] = self::PROJECT_INPUT_NAME;
    return $this->render("upload_file.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @brief Process the upload request.
   */
  protected function handleUpload(Request $request)
  {
    global $MODDIR;
    global $SYSCONFDIR;

    define("UPLOAD_ERR_EMPTY", 5);
    define("UPLOAD_ERR_INVALID_FOLDER_PK", 100);
    define("UPLOAD_ERR_RESEND", 200);
    $uploadErrors = array(
      UPLOAD_ERR_OK => _("No errors."),
      UPLOAD_ERR_INI_SIZE => _("Larger than upload_max_filesize ") . ini_get('upload_max_filesize'),
      UPLOAD_ERR_FORM_SIZE => _("Larger than form MAX_FILE_SIZE."),
      UPLOAD_ERR_PARTIAL => _("Partial upload."),
      UPLOAD_ERR_NO_FILE => _("No file selected."),
      UPLOAD_ERR_NO_TMP_DIR => _("No temporary directory."),
      UPLOAD_ERR_CANT_WRITE => _("Can't write to disk."),
      UPLOAD_ERR_EXTENSION => _("File upload stopped by extension."),
      UPLOAD_ERR_EMPTY => _("File is empty or you don't have permission to read the file."),
      UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder."),
      UPLOAD_ERR_RESEND => _("This seems to be a resent file.")
    );

    echo ("<script>console.log('fwh');</script>");
    echo ("<script>console.log('" . json_encode($request) . "');</script>");

    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    echo ("<script>console.log('folderId');</script>");
    echo ("<script>console.log('" . json_encode($request->get(self::FOLDER_PARAMETER_NAME)) . "');</script>");
    echo ("<script>console.log('" . json_encode($folderId) . "');</script>");
    if ($request !== $result = $request->attributes->get(self::FOLDER_PARAMETER_NAME, $request)) {
      echo ("<script>console.log('" . json_encode($result) . "');</script>");
    }
    $key = self::FOLDER_PARAMETER_NAME;
    // if ($request->query->has($key)) {
    //   // return $request->query->all()[$key];
    //   echo ("<script>console.log('" . json_encode($request->query->all()[$key]) . "');</script>");
    // }
    echo ("<script>console.log('" . json_encode($request->request) . "');</script>");
    echo ("<script>console.log('" . json_encode($request->request->all()) . "');</script>");
    if ($request->request->has($key)) {
      // return $request->request->all()[$key];
      echo ("<script>console.log('" . json_encode($request->request->all()[$key]) . "');</script>");
    }
    // echo("<script>console.log('" . json_encode(null) . "');</script>");


    $projectId = intval($request->get(self::PROJECT_PARAMETER_NAME));
    echo ("<script>console.log('projectId');</script>");
    echo ("<script>console.log('" . json_encode($request) . "');</script>");
    echo ("<script>console.log('" . json_encode($request->get(self::PROJECT_PARAMETER_NAME)) . "');</script>");
    echo ("<script>console.log('" . json_encode($projectId) . "');</script>");

    $descriptions = $request->get(self::DESCRIPTION_INPUT_NAME);
    for ($i = 0; $i < count($descriptions); $i++) {
      $descriptions[$i] = stripslashes($descriptions[$i]);
      $descriptions[$i] = $this->basicShEscaping($descriptions[$i]);
    }
    $uploadedFiles = $request->files->get(self::FILE_INPUT_NAME);
    echo ("<script>console.log('uploadedFiles');</script>");
    echo ("<script>console.log('" . json_encode($request->files->get(self::FILE_INPUT_NAME)) . "');</script>");
    echo ("<script>console.log('" . json_encode($uploadedFiles) . "');</script>");
    $uploadFiles = [];
    for ($i = 0; $i < count($uploadedFiles); $i++) {
      $uploadFiles[] = [
        'file' => $uploadedFiles[$i],
        'description' => $descriptions[$i]
      ];
    }

    echo ("<script>console.log('uploadFiles');</script>");
    echo ("<script>console.log('" . json_encode($uploadFiles) . "');</script>");
    if (empty($uploadedFiles)) {
      echo ("<script>console.log('uploadFiles is empty');</script>");
      return array(false, $uploadErrors[UPLOAD_ERR_NO_FILE], "");
    }

    if (
      $request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
      != $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
    ) {
      return array(false, $uploadErrors[UPLOAD_ERR_RESEND], "");
    }

    foreach ($uploadFiles as $uploadedFile) {
      if (
        $uploadedFile['file']->getSize() == 0 &&
        $uploadedFile['file']->getError() == 0
      ) {
        return array(false, $uploadErrors[UPLOAD_ERR_EMPTY], "");
      } else if ($uploadedFile['file']->getSize() >= UploadedFile::getMaxFilesize()) {
        return array(false, $uploadErrors[UPLOAD_ERR_INI_SIZE] .
          _(" is  really ") . $uploadedFile['file']->getSize() . " bytes.", "");
      }
      if (!$uploadedFile['file']->isValid()) {
        return array(false, $uploadedFile['file']->getErrorMessage(), "");
      }
    }

    if (empty($folderId)) {
      return array(false, $uploadErrors[UPLOAD_ERR_INVALID_FOLDER_PK], "");
    }

    echo ("<script>console.log('after most check');</script>");

    $setGlobal = ($request->get('globalDecisions')) ? 1 : 0;
    echo ("<script>console.log('setGlobal');</script>");
    echo ("<script>console.log('" . json_encode($setGlobal) . "');</script>");

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $projectGroup = $GLOBALS['SysConf']['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';

    $errors = [];
    $success = [];
    foreach ($uploadFiles as $uploadedFile) {

      echo ("<script>console.log('begin job upload');</script>");

      $originalFileName = $uploadedFile['file']->getClientOriginalName();
      $originalFileName = $this->basicShEscaping($originalFileName);
      /* Create an upload record. */
      // $uploadId = JobAddUpload(
      //   $userId,
      //   $groupId,
      //   $originalFileName,
      //   $originalFileName,
      //   $uploadedFile['description'],
      //   $uploadMode,
      //   $folderId,
      //   $publicPermission,
      //   $setGlobal
      // );
      $uploadId = JobAddUploadWithProject(
        $userId,
        $groupId,
        $originalFileName,
        $originalFileName,
        $uploadedFile['description'],
        $uploadMode,
        $folderId,
        $projectId,
        $publicPermission,
        $setGlobal
      );

      echo ("<script>console.log('uploadId');</script>");
      echo ("<script>console.log('" . json_encode($uploadId) . "');</script>");

      if (empty($uploadId)) {
        $errors[] = _("Failed to insert upload record: ") .
          $originalFileName;
        continue;
      }

      try {
        $uploadedTempFile = $uploadedFile['file']->move(
          $uploadedFile['file']->getPath(),
          $uploadedFile['file']->getFilename() . '-uploaded'
        )->getPathname();
      } catch (FileException $e) {
        $errors[] = _("Could not save uploaded file: ") . $originalFileName;
        continue;
      }
      $success[] = [
        "tempfile" => $uploadedTempFile,
        "orignalfile" => $originalFileName,
        "uploadid" => $uploadId
      ];
      echo ("<script>console.log('success array');</script>");
      echo ("<script>console.log('" . json_encode($success) . "');</script>");
    }

    if (!empty($errors)) {
      return [false, implode(" ; ", $errors), ""];
    }

    $messages = [];
    foreach ($success as $row) {
      $uploadedTempFile = $row["tempfile"];
      $originalFileName = $row["orignalfile"];
      $uploadId = $row["uploadid"];

      $wgetAgentCall = "$MODDIR/wget_agent/agent/wget_agent -C -g " .
        "$projectGroup -k $uploadId '$uploadedTempFile' -c '$SYSCONFDIR'";
      $wgetOutput = array();
      exec($wgetAgentCall, $wgetOutput, $wgetReturnValue);
      unlink($uploadedTempFile);

      if ($wgetReturnValue != 0) {
        $message = implode(' ', $wgetOutput);
        if (empty($message)) {
          $message = _("File upload failed. Error:") . $wgetReturnValue;
        }
        $errors[] = $message;
      } else {
        $messages[] = $this->postUploadAddJobs(
          $request,
          $originalFileName,
          $uploadId
        );
      }
      echo ("<script>console.log('messages');</script>");
      echo ("<script>console.log('" . json_encode($messages) . "');</script>");
    }

    if (!empty($errors)) {
      return [false, implode(" ; ", $errors), ""];
    }

    return array(
      true, implode("", $messages), "",
      array_column($success, "uploadid")
    );
  }
}

register_plugin(new UploadFilePage());
