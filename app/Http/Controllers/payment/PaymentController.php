<?php

namespace App\Http\Controllers\payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Mail\EMailClass;
use Exception;
use Illuminate\Support\Facades\View;

class PaymentController extends Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->middleware('AuthCheck:stu,emp', ['except' => ['validateSingleLogin', 'TokenError', 'sanctum/csrf-cookie']]);
  }



  function testAPI() {

    return $users = DB::select("SELECT a.id, CONCAT_WS(' ',a.salutation,a.first_name,a.last_name) AS name
    FROM user_details a where a.id='9062'");

        if ($user === null) {
            // user doesn't exist
            return $this->sendError('Invalid Request !', 'Please try again.');
        }
        else {
            return $this->sendResponse($users, 'API testing.');
        }
    
  }

  function register_api(Request $request) {

    if($request === null) {
      return $this->sendError('User data not found !', 'Please try again.');
    }
    else {

      $payment_user_api = array(
        "username" => $request->username,
        "password" => $request->password,
        "email" => $request->email
      );

      $last_id = DB::table('payment_user_api')->insertGetId($payment_user_api);

      return $this->sendResponse($request->all(), 'User inserted id : '.$last_id);
    }

  }


  function validateSingleLogin(Request $request)
  {
    $id = $request->id;

    if (base64_decode($id, true)) {
      $admn_no = base64_decode($id);
      $getmaxid = DB::table('login_logout_log')
        ->select('log_id')
        ->where('user_id', $admn_no)
        ->where('login_from', 'Pbeta')
        ->orWhere('login_from', 'Parent')
        ->orderBy('log_id', 'desc')->first();



      // echo  $getmaxid->log_id;
      // exit;
      $checklogin = DB::table('login_logout_log')
        ->where('log_id', $getmaxid->log_id)
        ->where('user_id', $admn_no)
        ->whereNotNull('logged_in_time')
        ->whereNull('logged_out_time')
        ->where('login_from', 'Pbeta')
        ->orWhere('login_from', 'Parent')
        ->orderBy('log_id', 'desc')->count();

      // print_r($checklogin);
      // exit;
      if ($checklogin > 0) {
        if (Auth::loginUsingId(base64_decode($id))) {
          $user = Auth::user();
          $success['token'] =  $user->createToken('mis_MyApp', ['server:update'])->plainTextToken;
          $success['user_details'] = $this->getUserDetails(base64_decode($id));
          return $this->sendResponse($success, 'Authentication checked Successfully !.');
          exit;
        } else {
          return $this->sendError('Invalid Request !', 'Invalid User !');
        }
      } else {
        return $this->sendError('Invalid Request !', 'Please login to Parent Portal to Start Pre-Registration.');
      }
    } else {
      return $this->sendError('Invalid Request !', 'Please try again to login.');
    }
    exit;
  }
  private function getUserDetails($id)
  {
    return $users = DB::select("SELECT a.id, CONCAT_WS(' ',a.salutation,a.first_name,a.last_name) AS user_name,d.course_id,d.branch_id,d.semester,a.dept_id,b.name as dept_name,b.`type` as dept_type,a.photopath,c.auth_id,c.`status`,c.is_blocked,c.failed_attempt_cnt
       FROM user_details a
       INNER JOIN users c ON a.id=c.id
       INNER JOIN departments b ON a.dept_id=b.id
       INNER JOIN stu_academic d ON a.id=d.admn_no
       WHERE a.id='$id'");
  }


}
