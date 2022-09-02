<?php

use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Models\MenuModel;
use Illuminate\Support\Facades\DB;

function timetablecheck($subjects, $tocheck)
{
    echo "demo git 2";
    return false;
}

function getDepartment($type = null, $onlyActive = false)
{
    if ($onlyActive) {
        $onlyActive = 1;
    }
    if ($type) {
        $type = $type;
    }
    $department = DB::table('cbcs_departments')->select('cbcs_departments.*')->when($onlyActive, function ($query) use ($onlyActive) {
        return $query->where('cbcs_departments.status', '=', "$onlyActive");
    })->when($type, function ($query) use ($type) {
        return $query->where('cbcs_departments.type', '=', "$type");
    })->orderBy('cbcs_departments.id', 'asc')->get();
    return $department;
}

function GetSession($onlyActive = false)
{
    if ($onlyActive) {
        $onlyActive = 1;
    }
    $session_year = DB::table('mis_session')->select('mis_session.*')->when($onlyActive, function ($query) use ($onlyActive) {
        return $query->where('mis_session.active', '=', "$onlyActive");
    })->orderBy('mis_session.id', 'asc')->get();
    return $session_year;
}

function GetSessionYear($onlyActive = false)
{
    if ($onlyActive) {
        $onlyActive = 1;
    }
    $session_year = DB::table('mis_session_year')->select('mis_session_year.*')->when($onlyActive, function ($query) use ($onlyActive) {
        return $query->where('mis_session_year.active', '=', "$onlyActive");
    })->orderBy('mis_session_year.id', 'desc')->get();
    return $session_year;
}

function FileUpload($file, $path, $name = null)
{
    if (isset($file) && !empty($file) && $file['error'] == 0) {
        $targetFile = WWW_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $file['name'];
        $targetFile = validateAndSetFileName($targetFile);
        $validFileContent = validateFileContent($file['tmp_name'], $targetFile);
        if ($targetFile && !$validFileContent['bError']) {
            $file_name = str_replace(' ', '_', pathinfo($targetFile, PATHINFO_FILENAME));
            $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
            $timestamp = time();
            $savedFileName = isset($name) ? $name . "." . $ext : $file_name . "_" . $timestamp . "." . $ext;
            $original_file_path = WWW_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $path;
            is_upload_dir_exists($original_file_path);
            $tempFile = $file['tmp_name'];
            $saveOriginalFile = 'uploads' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $savedFileName;
            $tempFile = $file['tmp_name'];
            $targetFile = WWW_ROOT . DIRECTORY_SEPARATOR . $saveOriginalFile;

            @list($width, $height, $type, $attr) = getimagesize($tempFile);
            //saving image for user in folder and database
            $moveSuccessfull = move_uploaded_file($tempFile, $targetFile);
            if ($moveSuccessfull && file_exists($targetFile)) {
                $response['file_name'] = $savedFileName;
                $file_path = 'assets' . DIRECTORY_SEPARATOR . $saveOriginalFile;
                return $file_path;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}
function is_upload_dir_exists($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}
function validateAndSetFileName($fileName)
{
    $response = null;
    $fileName = str_replace(chr(0), '', $fileName);
    $fileName = str_replace('.php', '', $fileName);
    $fileName = str_replace('.sh', '', $fileName);
    $fileName = str_replace('00', '', $fileName);
    $fileName = str_replace(' ', '_', $fileName);
    $allowedExtensions = array('png', 'jpeg', 'jpg', 'pdf', 'csv', 'ics', 'icl', 'xlsx', 'xls', 'mp4', 'mov', 'avi', 'webm', 'wmv', 'm4v', 'flv');
    $file = pathinfo($fileName, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        //do not upload file
        return false;
    } else {
        return $fileName;
    }
}
function validateFileContent($file, $fileName)
{
    $response['bError'] = false;
    $response['errorMsg'] = "";
    $imageExtensions = array('jpeg', 'png', 'jpg');
    $valid_mime_types = array(
        'png' => array("image/png", "image/jpeg", "image/jpg"),
        'mp4' => array("video/mp4", "video/mov", "video/avi", "video/webm", "video/wmv", "video/m4v", "video/flv"),
        'jpeg' => array("image/png", "image/jpeg", "image/jpg"),
        'jpg' => array("image/png", "image/jpeg", "image/jpg"),
        'pdf' => array("application/pdf"),
        'csv' => array("text/plain"),
        'ics' => array("text/calendar"),
        'icl' => array("text/calendar"),
        'xls' => array("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel", "application/zip"),
        'xlsx' => array("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel", "application/zip"),
    );
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (isset($valid_mime_types[$ext])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);
        if (!in_array($mime, $valid_mime_types[$ext])) {
            $response['bError'] = true;
            $response['errorMsg'] = "File extension mismatch";
        }
        if (in_array($ext, $imageExtensions)) {
            $imageSize = getimagesize($file);
            if (empty($imageSize)) {
                $response['bError'] = true;
                $response['errorMsg'] = "Image file not safe!";
            }
        }
    } else {
        $response['bError'] = true;
        $response['errorMsg'] = "Invalid extension file";
    }
    return $response;
}
function paginateArray($data, $perPage = 15)
{
    $page = Paginator::resolveCurrentPage();
    $total = count($data);
    $results = array_slice($data, ($page - 1) * $perPage, $perPage);

    return new LengthAwarePaginator($results, $total, $perPage, $page, [
        'path' => Paginator::resolveCurrentPath(),
    ]);
}

function getMenu()
{
    $user_auths = DB::table('user_auth_type')->select('auth_type')->where('status', 1)->where('user_id', Auth::user()->id)->groupBy('auth_type')->get();
    $menu = array();
    foreach ($user_auths as $i => $auth) {
        $menu[$auth->auth_type] = array();
        $model_menu = dyanmic_menu_gen($auth->auth_type);
        if (isset($model_menu[$auth->auth_type]) && is_array($model_menu[$auth->auth_type])) {
            $menu[$auth->auth_type] = array_merge($menu[$auth->auth_type], $model_menu[$auth->auth_type]);
        }
        if (file_exists(base_path() . "\app\Models\MenuModel.php")) {
            $menu[$auth->auth_type] = array();
            $MenuModel = new MenuModel();
            $model_menu = $MenuModel->getMenu();
            if (isset($model_menu[$auth->auth_type]) && is_array($model_menu[$auth->auth_type])) {
                $menu[$auth->auth_type] = array_merge($menu[$auth->auth_type], $model_menu[$auth->auth_type]);
            }
        }
    }
    return $menu;
}

function dyanmic_menu_gen($auth)
{

    $user_menu = DB::table('auth_menu_detail')->where("auth_id", $auth)->orderBy('auth_id', 'asc')->get();
    return get_dyanmic_menu($user_menu, $auth);
}
function get_dyanmic_menu($dmenu, $auth)
{
    $menu[$auth] = array();
    foreach ($dmenu as $d) {
        if ($d->submenu2 == null) {
            $menu[$auth][$d->submenu1] =  url($d->link);
        } elseif ($d->submenu3 == null) {
            $menu[$auth][$d->submenu1][$d->submenu2] =  url($d->link);
        } elseif ($d->submenu4 == null) {
            $menu[$auth][$d->submenu1][$d->submenu2][$d->submenu3] =  url($d->link);
        } else {
            $menu[$auth][$d->submenu1][$d->submenu2][$d->submenu3][$d->submenu4] =  url($d->link);
        }
    }
    return $menu;
}