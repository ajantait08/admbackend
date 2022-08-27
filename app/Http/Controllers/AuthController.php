<?php
// by @bhijeet
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Exception;

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('AuthCheck', ['except' => ['login', 'TokenError', 'sanctum/csrf-cookie']]);
    }
    function TokenError()
    {
        return response()->json([
            'status' => false,
            'message' => 'Invalid Token !',
            'errorCode' => '101',
        ], 409);
    }


    function logout(Request $request)
    {
        $user = Auth::user();
        $LogAccessToken = $this->LogAccessToken(Auth::user()->id, true);
        $this->updateloginlog(Auth::user()->id);
        if ($request->user()->currentAccessToken()->delete()) {
            $user->tokens()->delete();
            return $this->sendResponse(null, 'User Logout successfully..');
        } else {
            return $this->sendError('Unauthorised.',  'Invalid User Id !');
        }
    }

    private function updateloginlog($id)
    {
        $data = array(
            "logged_out_time" => date('Y-m-d h:s:i'),
            "logout_ip" => $this->get_client_ip()
        );

        $last_id =  DB::table('login_logout_log')->where('user_id', $id)->orderBy('log_id', 'desc')->limit(1)->get();

        return  DB::table('login_logout_log')->where('log_id', $last_id[0]->log_id)->update($data);
    }

    function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Request !',
            ], 401);
        }

        $maxAttempCnt = env('MAX_ATTEMPT_CNT', '10');

        $user_id = $this->strclean($request->id);

        $status = false;

        $checkforuser = $this->getUserById($user_id);
        if (!$checkforuser) {
            return $this->sendError('Invalid User Id !', 'Please Enter Valid User-Id !');
        }
        if ($checkforuser->is_blocked == '1') {
            return $this->sendError('Unauthorised', 'Your User-id has been blocked.Please Contact Admin !.');
        }

        $pass = $this->strclean($request->password);
        $created_date = trim($checkforuser->created_date);
        $user_hash = $checkforuser->user_hash;
        $password = trim($pass) . $user_hash;
        $login_logout_log = array(
            "user_id" => $user_id,
            "logged_in_time" => date('Y-m-d h:s:i'),
            "login_ip" => $this->get_client_ip()
        );
        $user_login_attemp = array(
            'id' => $user_id,
            'time' => date('Y-m-d h:s:i'),
            'password' => $pass,
            'ip' => $this->get_client_ip()
        );
        $LogAccessToken = $this->LogAccessToken($user_id);
        if (!$LogAccessToken) {
            return $this->sendError('Unauthorised.',  'Something Went Worng !');
        }
        if (Auth::attempt(['id' => trim($user_id), 'password' => trim($password), 'status' => 'A', 'is_blocked' => '0'])) {
            $user = Auth::user();

            $updateFailsAttempt = $this->UpdateFailedAttemp($user_id, true);


            $success['token'] =  $user->createToken('mis_MyApp', ['server:update'])->plainTextToken;
            $success['user_details'] = $this->getUserDetails($user_id);
            $success['user_menu_details'] = $this->getUserMenu($user_id);

            DB::table('login_logout_log')->insert($login_logout_log);

            $user_login_attemp['status'] = 'Success';
            DB::table('user_login_attempts')->insert($user_login_attemp);

            return $this->sendResponse($success, 'User login successfully.');
        } else {
            $admn_pasword = $this->getAdminPass();


            foreach ($admn_pasword as $key => $value) {
                $masterPass = trim($pass) . $value->user_hash;
                if (Auth::attempt(['id' => trim($user_id), 'password' => trim($masterPass), 'status' => 'A'])) {
                    $updateFailsAttempt = $this->UpdateFailedAttemp($user_id, true);
                    $status = true;
                }
            }
            if ($status == true) {
                $user = Auth::user();
                $success['token'] = $token = $user->createToken('mis_MyApp', ['server:update'])->plainTextToken;
                $success['user_details'] = $this->getUserDetails($user_id);
                $success['user_menu_details'] = $this->getUserMenu($user_id);

                DB::table('login_logout_log')->insert($login_logout_log);
                $user_login_attemp['status'] = 'Success';
                DB::table('user_login_attempts')->insert($user_login_attemp);

                return $this->sendResponse($success, 'User login successfully by master.');
            } else {
                $user_login_attemp['status'] = 'Failed';
                DB::table('user_login_attempts')->insert($user_login_attemp);
                $updateFailsAttempt = $this->UpdateFailedAttemp($user_id);
                $updateRecord = $this->getUserById($user_id);
                if ($updateRecord->failed_attempt_cnt >= $maxAttempCnt) {
                    $this->BlockUser($user_id);
                }
                return $this->sendError('Invalid User Password !', 'Please Enter Valid Password !', ['faildAttemp' => $updateRecord->failed_attempt_cnt]);
            }
        }
    }
    function get_client_ip()
    {
        // $ipaddress = '';
        // if ($_SERVER('HTTP_CLIENT_IP'))
        //     $ipaddress =  $_SERVER('HTTP_CLIENT_IP');
        // else if ($_SERVER('HTTP_X_FORWARDED_FOR'))
        //     $ipaddress =  $_SERVER('HTTP_X_FORWARDED_FOR');
        // else if ($_SERVER('HTTP_X_FORWARDED'))
        //     $ipaddress =  $_SERVER('HTTP_X_FORWARDED');
        // else if ($_SERVER('HTTP_FORWARDED_FOR'))
        //     $ipaddress =  $_SERVER('HTTP_FORWARDED_FOR');
        // else if ($_SERVER('HTTP_FORWARDED'))
        //     $ipaddress =  $_SERVER('HTTP_FORWARDED');
        // else if ($_SERVER('REMOTE_ADDR'))
        //     $ipaddress =  $_SERVER('REMOTE_ADDR');
        // else
        //     $ipaddress = 'UNKNOWN';
        return  $_SERVER['REMOTE_ADDR'];
    }
    function unBlockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Request !',
            ], 401);
        }
        $user_id = $this->strclean($request->user_id);
        DB::table('users')->where('id', $user_id)->update(['failed_attempt_cnt' => 0, 'is_blocked' => 0]);
        return $this->sendResponse(null, 'User ' . $user_id . ' UnBlocked Successfully !');
    }

    private function UpdateFailedAttemp($id, $reset = false)
    {
        if (!$reset) {
            $user = DB::table('users')->where('id', $id)->first();
            DB::table('users')->where('id', $id)->update(['failed_attempt_cnt' => $user->failed_attempt_cnt + 1]);
        } else {
            DB::table('users')->where('id', $id)->update(['failed_attempt_cnt' => 0, 'is_blocked' => 0]);
        }
        return true;
    }



    private function BlockUser($id)
    {
        DB::table('users')
        Leftjoin('','','')
        ->where('id', $id)->update(['is_blocked' => 1]);
        return true;
    }

    private function UpdateSuccessAttemp($id)
    {
    }

    private function getUserAuth($id)
    {
        return $users = DB::select("(
            SELECT a.id,a.auth_id
            FROM user_auth_types a
            WHERE a.id='$id') UNION
            (
            SELECT a.emp_no AS id,a.auth_id
            FROM emp_basic_details a
            WHERE a.emp_no='$id') UNION
            (
            SELECT a.id AS id,a.auth_id
            FROM users a
            WHERE a.id='$id')
            UNION
            (
            SELECT a.id AS id,a.auth_id
            FROM user_auth_types_extension a
            WHERE a.id='$id' AND a.status='A')");
    }



    private function getUserMenu($id)
    {
        // return $users = DB::select("SELECT a.*
        // FROM auth_menu_detail a
        // WHERE a.auth_id IN (
        // SELECT *
        // FROM ((
        // SELECT a.auth_id
        // FROM user_auth_types a
        // WHERE a.id='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM emp_basic_details a
        // WHERE a.emp_no='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM users a
        // WHERE a.id='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM user_auth_types_extension a
        // WHERE a.id='$id' AND a.status='A'))z)");

        // $users = DB::select("(SELECT a.menu_id,a.auth_id,a.submenu1,GROUP_CONCAT(CONCAT_WS(',',a.submenu1,if(a.submenu2 IS NULL,'NA',a.submenu2),if(a.submenu3 IS NULL,'NA',a.submenu3)
        // ,if(a.submenu4 IS NULL,'NA',a.submenu4),a.link,a.`status`) SEPARATOR '$') AS menus
        // FROM auth_menu_detail a
        // WHERE  a.status='Y' and a.auth_id IN (
        // SELECT *
        // FROM ((
        // SELECT a.auth_id
        // FROM user_auth_types a
        // WHERE a.id='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM emp_basic_details a
        // WHERE a.emp_no='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM users a
        // WHERE a.id='$id') UNION
        //  (
        // SELECT a.auth_id
        // FROM user_auth_types_extension a
        // WHERE a.id='$id' AND a.status='A'))z)  GROUP BY a.submenu1 ORDER BY a.auth_id ASC ) ");
        // print_r($users);
        // exit;
        $user_menu = array();
        // foreach ($users as $key => $value) {
        //     $array_name[$value->submenu1] = array();
        //     $data = explode('$', $value->menus);
        //     for ($i = 0; $i < count($data); $i++) {
        //         $menu = explode(",", $data[$i]);



        //         //print_r($value->submenu1);
        //         array_push($array_name, array('submenu1' => $menu[0], 'submenu2' => $menu[1], 'submenu3' => $menu[2], 'submenu4' => $menu[3], 'link' => $menu[4], 'status' => $menu[5]));
        //         //    $user_menu[$value->submenu1] = array('submenu1' => $menu[0], 'submenu2' => $menu[1], 'submenu3' => $menu[2], 'submenu4' => $menu[3], 'link' => $menu[4], 'status' => $menu[5]);
        //         $user_menu[$value->submenu1] = $array_name;
        //     }
        // }

        foreach (getUserAuth(Auth::user()->id) as $key => $value) {
            $menu =  $this->dyanmic_menu_gen($value);
            array_push($user_menu, $menu);
        }
        return $user_menu;
        // print_r($user_menu);
        // exit;
    }
    private function get_dyanmic_menu($dmenu, $auth, $type)
    {
        // print_r($type);
        // exit;
        if ($type) {
            $menu[$type] = array();
            foreach ($dmenu as $d) {
                if ($d->submenu2 == null) {
                    $menu[$type][$d->submenu1] =  url('/') . "/" . ($d->link);
                } elseif ($d->submenu3 == null) {
                    $menu[$type][$d->submenu1][$d->submenu2] =  url('/') . "/" . ($d->link);
                } elseif ($d->submenu4 == null) {    //print_r($d);
                    $menu[$type][$d->submenu1][$d->submenu2][$d->submenu3] =  url('/') . "/" . ($d->link);
                } else {
                    $menu[$type][$d->submenu1][$d->submenu2][$d->submenu3][$d->submenu4] =  url('/') . "/" . ($d->link);
                }
            }
            return $menu;
        }
    }
    private function dyanmic_menu_gen($auth)
    {
        $dmenu = DB::table('auth_menu_detail_api')->join('auth_types', 'auth_menu_detail_api.auth_id', '=', 'auth_types.id')
            ->select('auth_menu_detail_api.*', 'auth_types.type')
            ->where('auth_menu_detail_api.auth_id', $auth)->where('auth_menu_detail_api.status', 'Y')->orderBy('auth_menu_detail_api.submenu1', 'asc')->get();
        // print_r($dmenu[0]->type);
        // exit;
        $type = isset($dmenu[0]->type) ? $dmenu[0]->type : null;
        if ($type) {
            return $this->get_dyanmic_menu($dmenu, $auth, $type);
        } else {
        }
    }

    private function LogAccessToken($id, $logout = false)
    {
        try {
            DB::beginTransaction();
            $users = DB::select("INSERT INTO personal_access_tokens_log(token_id,tokenable_type,tokenable_id,name,token,abilities,last_used_at,expires_at)
        (SELECT a.id,a.tokenable_type,a.tokenable_id,a.name,a.token,a.abilities,a.last_used_at,a.expires_at FROM  personal_access_tokens a WHERE a.tokenable_id='$id')");
            if (!$logout) {
                $deleted = DB::table('personal_access_tokens')->where('tokenable_id', $id)->delete();
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            return false;
        }
    }

    private function getUserDetails($id)
    {
        return $users = DB::select("SELECT a.id, CONCAT_WS(' ',a.salutation,a.first_name,a.last_name) AS user_name,a.dept_id,b.name as dept_name,b.`type` as dept_type,a.photopath,c.auth_id,c.`status`,c.is_blocked,c.failed_attempt_cnt
       FROM user_details a
       INNER JOIN users c ON a.id=c.id
       INNER JOIN departments b ON a.dept_id=b.id
       WHERE a.id='$id'");
    }

    private function getAdminPass()
    {
        $adminPass =  DB::table('users')->whereIn('id', function ($query) {
            $query->select(DB::raw('id'))
                ->from('user_auth_types')
                ->where('id', 'admin');
        })->get();
        if ($adminPass) {
            return $adminPass;
        } else {
            return false;
        }
    }
    function refresh(Request $request)
    {
        if (Auth::check()) {
            // print_r(Auth::user());
            // exit;
            $data['user_details'] = $this->getUserDetails(Auth::user()->id);
            $data['user_menu_details'] = $this->getUserMenu(Auth::user()->id);
            return $this->sendResponse($data, 'Auth Checked Successfully.');
        } else {
            return $this->sendError('Unauthorised.',  'Invalid Request !');
        }
    }
    function UpdatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Request !',
            ], 401);
        }
        $user_id = $this->strclean($request->id);


        $checkforuser = $this->getUserById($user_id);
        if (!$checkforuser) {
            return $this->sendError('Unauthorised.', 'Invalid User Id !');
        }
        $randomHash = $this->generateRandomString();
        $updatehash = DB::table('users')
            ->where('id', $user_id)
            ->update(['user_hash' => $randomHash]);

        $userLastest = $this->getUserById($user_id);
        $created_date = trim($userLastest->created_date);
        $user_hash = $userLastest->user_hash;
        $pass = $this->strclean($request->password);
        $newPassword = $pass . $user_hash;
        $cratePassword = bcrypt($newPassword);
        // echo $user_id;
        // exit;
        $updatePassword = DB::table('users')
            ->where('id', $user_id)
            ->update(['password' => $cratePassword]);

        if ($updatePassword) {
            $success['status'] = true;
            return $this->sendResponse($success, 'User Password Updated successfully.');
        } else {
            $success['status'] = false;
            return $this->sendError($success, 'Something Went Worng..');
        }
    }
    protected function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    protected function strclean($str)
    {
        //global $mysqli;
        $str = @trim($str);

        return  preg_replace('/[^A-Za-z0-9. -]/', '', $str);
    }
    protected function getUserById($id = '')
    {
        $row = DB::table('users')->where('id', $id)->WhereIn('status', ['A', 'P'])->first();
        if ($row) {
            return $row;
        } else {
            return false;
        }
    }
}
