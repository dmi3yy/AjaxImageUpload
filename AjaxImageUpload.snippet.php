<?php
define('AIU_BASE_PATH', MODX_BASE_PATH.'assets/snippets/AjaxImageUpload/');
define('AIU_CACHE_PATH', MODX_BASE_PATH.'assets/cache/AjaxImageUpload/');

include AIU_BASE_PATH.'includes/fileuploader/fileuploader.class.php';
include AIU_BASE_PATH.'includes/PhpThumbFactory/ThumbLib.inc.php';

/***************************/
/* Set/Read Snippet Params */
/***************************/

// default: &language=`english` &allowedExtensions=`jpg,jpeg,png,gif` &maxFilesizeMb=`8` &uid=`site-specific` &maxFiles=`3` &thumbX=`100` &=`100` &mode=`form` &ajaxId=`0`

$language = isset($language) ? $language : 'english';
// comma separated list of valid extensions
$allowedExtensions = isset($allowedExtensions) ? $allowedExtensions : 'jpg,jpeg,png,gif';
$maxFilesizeMb = isset($maxFilesizeMb) ? intval($maxFilesizeMb) : 8;
$formUid = isset($uid) ? $uid : md5($modx->config['site_url']);
$maxFiles = isset($maxFiles) ? $maxFiles : '3';
$thumbX = isset($thumbX) ? $thumbX : '100';
$thumbY = isset($thumbY) ? $thumbY : '100';
$mode = isset($mode) ? $mode : 'form';
$ajaxId = isset($ajaxId) ? intval($ajaxId) : 0;
$addJquery = isset($addJquery) ? intval($addJquery) : 1;
$addJscript = isset($addJscript) ? intval($addJquery) : 1;
$addCss = isset($addCss) ? intval($addJquery) : 1;

function includeFile($name, $type = 'config', $defaultName = 'default', $fileType = '.inc.php') {
	$folder = (substr($type, -1) != 'y') ? $type.'s/' : substr($folder, 0, -1).'ies/';
	$allowedConfigs = glob(AIU_BASE_PATH.$folder.'*.'.$type.$fileType);
	foreach ($allowedConfigs as $config) {
		$configs[] = preg_replace('=.*/'.$folder.'([^.]*).'.$type.$fileType.'=', '$1', $config);
	}
	if (in_array($name, $configs)) {
		return AIU_BASE_PATH.$folder.$name.'.'.$type.$fileType;
	} else {
		if (file_exists(AIU_BASE_PATH.$folder.$defaultName.'.'.$type.$fileType)) {
			return AIU_BASE_PATH.$folder.$defaultName.'.'.$type.$fileType;
		} else {
			$modx->messageQuit('Default AjaxImageUpload '.$type.' file "'.AIU_BASE_PATH.$folder.$defaultName.'.'.$type.'.inc.php" not found. Did you upload all snippet files?');
		}
	}
}

if (!file_exists(MODX_BASE_PATH.'assets/cache/AjaxImageUpload')) {
	mkdir(MODX_BASE_PATH.'assets/cache/AjaxImageUpload', 0755);
}

include (includeFile($language, 'language', 'english'));
$allowedExtensions = explode(',', $allowedExtensions);
$sizeLimit = intval($maxFilesizeMb) * 1024 * 1024;
switch($mode) {
	// the AJAX part of the snippet
	case 'ajax' : {
		// delete uploaded images
		if (isset($_GET['delete'])) {
			$result = array();
			$formUid = (isset($_GET['uid'])) ? htmlentities(trim($_GET['uid']), ENT_NOQUOTES) : $formUid;
			if (strtolower($_GET['delete']) == 'all') {
				// deelete all uploaded files/thumbs & clean session
				if (is_array($_SESSION['AjaxImageUpload'][$formUid])) {
					foreach ($_SESSION['AjaxImageUpload'][$formUid] as $key => $file) {
						unlink($file['path'].$file['uniqueName']);
						unlink($file['path'].$file['thumbName']);
					}
				}
				$_SESSION['AjaxImageUpload'][$formUid] = array();
				$result['success'] = TRUE;
				$result['session'] = $_SESSION['AjaxImageUpload'][$formUid];
			} else {
				// delete one uploaded file/thumb & remove session entry
				$fileId = intval($_GET['delete']);
				if (isset($_SESSION['AjaxImageUpload'][$formUid][$fileId])) {
					$file = $_SESSION['AjaxImageUpload'][$formUid][$fileId];
					unlink($file['path'].$file['uniqueName']);
					unset($_SESSION['AjaxImageUpload'][$formUid][$fileId]);
					$result['success'] = TRUE;
					$result['session'] = $_SESSION['AjaxImageUpload'][$formUid];
				} else {
					$result['error'] = sprintf($language['notFound'], $maxFiles);
				}
			}
		} else {
			// upload the image(s)
			$uploader = new qqFileUploader($allowedExtensions, $sizeLimit);
			$formUid = (isset($_GET['uid'])) ? htmlentities(trim($_GET['uid']), ENT_NOQUOTES) : $formUid;
			// to pass data through iframe you will need to encode all html tags
			$result = $uploader->handleUpload(AIU_CACHE_PATH, TRUE, $language);
			// file successful uploaded
			if ($result['success']) {
				$originalName = $uploader->filename.'.'.$uploader->extension;
				$path = $uploader->path;
				// check if count of uploaded files are below max file count
				if (count($_SESSION['AjaxImageUpload'][$formUid]) < $maxFiles) {
					// create unique filename and unique thumbname
					$uniqueName = md5($uploader->filename.time()).'.'.$uploader->extension;
					$thumbName = md5($uploader->filename.time().'.thumb').'.'.$uploader->extension;
					// generate thumbname
					$thumb = PhpThumbFactory::create($path.$originalName);
					$thumb->adaptiveResize($thumbX, $thumbY);
					$thumb->save($path.$thumbName);
					rename($path.$originalName, $path.$uniqueName);
					// fill session
					$_SESSION['AjaxImageUpload'][$formUid][] = array('originalName' => $originalName, 'uniqueName' => $uniqueName, 'thumbName' => $thumbName, 'path' => $path);
					// prepare returned values (filename & fileid)
					$result['filename'] = str_replace(MODX_BASE_PATH, $modx->config['site_url'], $path.$thumbName);
					$result['fileid'] = end(array_keys($_SESSION['AjaxImageUpload'][$formUid]));
				} else {
					unset($result['success']);
					// error message
					$result['error'] = sprintf($language['maxFiles'], $maxFiles);
					// delete uploaded file
					unlink($path.$originalName);
				}
			}
		}
		echo htmlspecialchars(json_encode($result), ENT_NOQUOTES);
		exit ;
	}
	case 'form' : {
		if ($ajaxId) {
			if ($addJquery) {
				$modx->regClientStartupScript('http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js');
			}
			if ($addCss) {
				$modx->regClientCSS('assets/snippets/AjaxImageUpload/AjaxImageUpload.css');
			}
			if ($addJscript) {
				$modx->regClientStartupScript('assets/snippets/AjaxImageUpload/includes/fileuploader/fileuploader.js');
				$modx->regClientStartupScript('assets/snippets/AjaxImageUpload/AjaxImageUpload.js');
			}
			$scriptSettings = file_get_contents(includeFile('script'.ucfirst($language), 'template', 'script', '.html'));
			$placeholder = array();
			$placeholder['ajaxId'] = $modx->makeUrl($ajaxId);
			$placeholder['dropArea'] = $language['dropArea'];
			$placeholder['uploadButton'] = $language['uploadButton'];
			$placeholder['clearButton'] = $language['clearButton'];
			$placeholder['cancel'] = $language['cancel'];
			$placeholder['failed'] = $language['failed'];
			$placeholder['thumbX'] = $thumbX;
			$placeholder['thumbY'] = $thumbY;
			$placeholder['allowedExtensions'] = (count($allowedExtensions)) ? "'".implode("', '", $allowedExtensions)."'" : '[]';
			$placeholder['sizeLimit'] = $sizeLimit;
			$placeholder['uid'] = $formUid;
			$placeholder['typeError'] = $language['typeError'];
			$placeholder['sizeError'] = $language['sizeError'];
			$placeholder['minSizeError'] = $language['minSizeError'];
			$placeholder['emptyError'] = $language['emptyError'];
			$placeholder['onLeave'] = $language['onLeave'];
			foreach ($placeholder as $key => $value) {
				$scriptSettings = str_replace('[+'.$key.'+]', $value, $scriptSettings);
			}
			$modx->regClientStartupScript($scriptSettings);
			$output = file_get_contents(includeFile('uploadSection'.ucfirst($language), 'template', 'uploadSection', '.html'));
			$imageTpl = file_get_contents(includeFile('image'.ucfirst($language), 'template', 'image', '.html'));
			$imageList = array();
			$placeholder = array();
			$placeholder['thumbX'] = $thumbX;
			$placeholder['thumbY'] = $thumbY;
			$placeholder['deleteButton'] = $language['deleteButton'];
			;
			if (is_array($_SESSION['AjaxImageUpload'][$formUid])) {
				foreach ($_SESSION['AjaxImageUpload'][$formUid] as $id => $image) {
					$placeholder['id'] = $id;
					$placeholder['thumbName'] = str_replace(MODX_BASE_PATH, $modx->config['site_url'], $image['path'].$image['thumbName']);
					$imageElement = $imageTpl;
					foreach ($placeholder as $key => $value) {
						$imageElement = str_replace('[+'.$key.'+]', $value, $imageElement);
					}
					$imageList[] = $imageElement;
				}
			}
			$output = str_replace('[+images+]', implode("\r\n", $imageList), $output);
			return $output;
			break;
		} else {
			return;
		}
	}
}
?>