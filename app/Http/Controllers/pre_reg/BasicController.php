<?php

namespace App\Http\Controllers\pre_reg;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Mail\EMailClass;
use Exception;
use Illuminate\Support\Facades\View;

class BasicController extends Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->middleware('AuthCheck:stu', ['except' => ['validateSingleLogin', 'TokenError', 'sanctum/csrf-cookie']]);
  }
  function saveDataPre(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'user' => 'required',
      'courses' => 'required',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Please select Valid Courses !');
    }
    $userdata = $request->user;
    $courses = $request->courses;


    $admn_no = $userdata['admn_no'];
    $session = $userdata['session'];
    $session_year = $userdata['session_year'];
    $branch_id = $userdata['branch_id'];
    $course_id = $userdata['course_id'];
    $semester = $userdata['semester'];

    $core_courses = $courses['core'];
    $OE_courses = $courses['soe'];
    $Eso_courses = $courses['seso'];
    $backdrop_courses = $courses['backdrop'];
    $De_courses = $courses['sde'];
    $this->saveRegistationLog($admn_no, $session_year, $session, "Pre-Registration data saving start.");

    DB::beginTransaction();
    try {
      $checkForRegistration = DB::table('reg_regular_form')
        ->where('admn_no', $admn_no)
        ->where('session_year', $session_year)
        ->where('session', $session)
        ->count();
      if ($checkForRegistration == 0) {
        $reg_reg_fee_data = array(
          "admn_no" => $admn_no,
          "fee_amt" => 0,
          "fee_date" => date('Y-m-d'),
        );

        $reg_reg_fee_save = DB::table('reg_regular_fee')->insertGetId($reg_reg_fee_data);
        if ($reg_reg_fee_save) {
          $reg_regular_data = array(
            "form_id" => $reg_reg_fee_save,
            "admn_no" => $admn_no,
            "course_id" => $course_id,
            "branch_id" => $branch_id,
            "semester" => $semester,
            "section" => null,
            "session_year" => $session_year,
            "session" => $session,
            "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
            "hod_status" => "1",
            "hod_time" => date('Y-m-d H:s:i'),
            "acad_status" => "1",
            "acad_time" => date('Y-m-d H:s:i'),
            "reg_type" => "R"
          );
          $save_reg_regular = DB::table('reg_regular_form')->insertGetId($reg_regular_data);

          if ($save_reg_regular) {
            foreach ($core_courses as $key => $value) {
              // print_r($value);
              // return $this->sendResponse($value['sub_offered_id'], 'Authentication checked Successfully !.');
              // exit;
              $pre_core_data = array(
                "form_id" => $reg_reg_fee_save,
                "admn_no" => $admn_no,
                "sub_offered_id" => $value['sub_offered_id'], //$value->sub_offered_id,
                "subject_code" => $value['sub_code'],
                "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
                "subject_name" => $value['sub_name'],
                "priority" => 1,
                "sub_category" => $value['sub_category'],
                "sub_category_cbcs_offered" => ($course_id == 'jrf') ? $value['sub_category_cbcs_offered'] : $value['sub_category'],
                "course" => $course_id,
                "branch" => $branch_id,
                "session_year" => $session_year,
                "session" => $session,
                "remark2" => 1,
              );
              DB::table('pre_stu_course')->insertGetId($pre_core_data);
            }
            foreach ($backdrop_courses as $key => $value) {
              $pre_backlog_data = array(
                "form_id" => $reg_reg_fee_save,
                "admn_no" => $admn_no,
                "sub_offered_id" => $value['sub_offered_id'],
                "subject_code" =>  $value['sub_code'],
                "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
                "subject_name" => $value['sub_name'],
                "priority" => ((strpos($value['sub_category'], strtoupper("eso")) === false) || (strpos($value['sub_category'], strtoupper("de")) === false) || (strpos($value['sub_category'], strtoupper("oe")) === false)) ? 0 : $value['priority'],
                "sub_category" => $value['sub_category'],
                "sub_category_cbcs_offered" => ((strpos($value['sub_category'], strtoupper("eso")) === false) || (strpos($value['sub_category'], strtoupper("de")) === false) || (strpos($value['sub_category'], strtoupper("oe")) === false)) ? $value['sub_category'] : $value['sub_category_cbcs_offered'],
                "course" => $course_id,
                "branch" => $branch_id,
                "session_year" => $session_year,
                "session" => $session,
                "remark2" => (strpos($value['sub_category'], strtoupper("eso")) === false) ? 0 : 1,
              );
              if ($value['course_component'] != 'OE' && $value['course_component'] != 'ESO' && $value['course_component'] != 'DE') {
                DB::table('pre_stu_course')->insertGetId($pre_backlog_data);
              }
            }
            foreach ($OE_courses as $key => $value) {
              $pre_oe_data = array(
                "form_id" => $reg_reg_fee_save,
                "admn_no" => $admn_no,
                "sub_offered_id" => $value['sub_offered_id'],
                "subject_code" => $value['sub_code'],
                "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
                "subject_name" => $value['sub_name'],
                "priority" =>  $value['priority'],
                "sub_category" => $value['sub_category_new'],
                "sub_category_cbcs_offered" =>  $value['sub_category_cbcs_offered'],
                "course" => $course_id,
                "branch" => $branch_id,
                "session_year" => $session_year,
                "session" => $session,
                "remark2" => 0,
              );
              DB::table('pre_stu_course')->insertGetId($pre_oe_data);
            }
            foreach ($Eso_courses as $key => $value) {
              $pre_eso_data = array(
                "form_id" => $reg_reg_fee_save,
                "admn_no" => $admn_no,
                "sub_offered_id" => $value['sub_offered_id'],
                "subject_code" => $value['sub_code'],
                "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
                "subject_name" =>  $value['sub_name'],
                "priority" =>  $value['priority'],
                "sub_category" => $value['sub_category_new'],
                "sub_category_cbcs_offered" =>  $value['sub_category_cbcs_offered'],
                "course" => $course_id,
                "branch" => $branch_id,
                "session_year" => $session_year,
                "session" => $session,
                "remark2" => ($value['src'] == 'guided') ? 1 : 0,
              );
              DB::table('pre_stu_course')->insertGetId($pre_eso_data);
            }
            foreach ($De_courses as $key => $value) {
              $pre_de_data = array(
                "form_id" => $reg_reg_fee_save,
                "admn_no" => $admn_no,
                "sub_offered_id" => $value['sub_offered_id'],
                "subject_code" => $value['sub_code'],
                "course_aggr_id" => $course_id . "_" . $branch_id . "_" . $session_year,
                "subject_name" =>  $value['sub_name'],
                "priority" => $value['priority'],
                "sub_category" => $value['sub_category_new'],
                "sub_category_cbcs_offered" => $value['sub_category_cbcs_offered'],
                "course" => $course_id,
                "branch" => $branch_id,
                "session_year" => $session_year,
                "session" => $session,
                "remark2" => 0,
              );
              DB::table('pre_stu_course')->insertGetId($pre_de_data);
            }
          }
        }
      } else {
        return $this->sendError('Already Registered', 'Already Registered.');
      }

      DB::commit();
      $this->saveRegistationLog($admn_no, $session_year, $session, "Pre-Registration data saving completed.");
      // DB::table('stu_academic')
      //   ->where('admn_no', $admn_no)
      //   ->update(['semester' => $semester]);

      $sendEmail = $this->SendEmail($reg_reg_fee_save, $admn_no);
      if ($sendEmail) {
        $emailLog = array(
          "admn_no" => $admn_no,
          "form_id" => $reg_reg_fee_save,
          "session_year" => $session_year,
          "session" => $session,
          "send_status" => "Success",
        );
        DB::table('pre_registration_email_log')->insertGetId($emailLog);
      } else {
        $emailLog = array(
          "admn_no" => $admn_no,
          "form_id" => $admn_no,
          "session_year" => $admn_no,
          "session" => $admn_no,
          "send_status" => "Failed",
        );
        DB::table('pre_registration_email_log')->insertGetId($emailLog);
      }
      $pre_data =  DB::table('pre_stu_course')->where('form_id', $reg_reg_fee_save)->get();
      $data['pre_data'] = $pre_data;
      $data['pre_data_id'] = $reg_reg_fee_save;
      return $this->sendResponse($data, 'Pre-Registration Record Saved Successfully !.');
    } catch (\Throwable $e) {
      DB::rollback();
      $this->saveRegistationLog($admn_no, $session_year, $session, "Pre-Registration data saving Failed.");
      return $this->sendError('Something Went Worng !',   throw $e);
      throw $e;
    }



    //  return $this->sendError('Invalid Request !', 'Please try again to login.');
  }

  // function saveData(Request $request)
  // {

  //   echo "okkk";
  //   // print_r($userdetails);
  //   exit;
  //   // $admn_no = "aaaa";

  //   // $sendEmail = $this->SendEmail(294118, '19je0001');
  //   // // print_r($sendEmail);
  //   // // exit;
  //   // if ($sendEmail) {
  //   //   $emailLog = array(
  //   //     "admn_no" => $admn_no,
  //   //     "form_id" => $admn_no,
  //   //     "session_year" => $admn_no,
  //   //     "session" => $admn_no,
  //   //     "send_status" => "Success",
  //   //   );
  //   //   DB::table('pre_registration_email_log')->insertGetId($emailLog);
  //   // } else {
  //   //   $emailLog = array(
  //   //     "admn_no" => $admn_no,
  //   //     "form_id" => $admn_no,
  //   //     "session_year" => $admn_no,
  //   //     "session" => $admn_no,
  //   //     "send_status" => "Failed",
  //   //   );
  //   //   DB::table('pre_registration_email_log')->insertGetId($emailLog);
  //   // }


  //   // $users = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
  //   //   FROM(
  //   //   SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name
  //   //   ,if(a.sex='m','Male','Female') AS gender,a.photopath,c.signpath,a.dept_id,b.course_id,b.branch_id,b.semester AS current_sem,d.session_year,d.`session`,d.form_id
  //   //   FROM user_details a
  //   //   INNER JOIN stu_academic b ON a.id=b.admn_no
  //   //   INNER JOIN reg_regular_form d ON a.id=d.admn_no AND  b.semester=d.semester
  //   //   LEFT JOIN stu_prev_certificate c ON a.id=c.admn_no
  //   //   WHERE a.id='19je0001'

  //   //   GROUP BY a.id
  //   //   ORDER BY d.session_year,d.`session` DESC,d.semester desc
  //   //   )z
  //   //   INNER JOIN cbcs_departments c ON z.dept_id=c.id
  //   //   INNER JOIN cbcs_courses d ON z.course_id=d.id
  //   //   INNER JOIN cbcs_branches e ON z.branch_id=e.id");;

  //   // $data['stu_details'] = $users;

  //   // $regData = DB::table('pre_stu_course')->where('form_id', 294118)->get();
  //   // $data['reg_data'] = $regData;

  //   // $mailData = [
  //   //   'reg_data' => $regData,
  //   //   'stu_details' =>  $users
  //   // ];

  //   // // print_r($data);
  //   // // exit;

  //   // return view('PreRegEmailBody', compact('mailData'));
  // }


  function sendEmailMannualy(Request $request)
  {
    $form_id = $request->form_id;
    $admn_no = $request->admn_no;

    $response =  $this->SendEmail($form_id, $admn_no);
    print_r($response);
    exit;
  }

  function sendEmailBulkMannualy(Request $request)
  {

  $failedEmail = DB::table('pre_registration_email_log')->where('send_status','Failed')->where('resend_status','0')->get();
  foreach($failedEmail  as $key=>$value){
  $user_dt =  DB::table('reg_regular_form')->where('admn_no',$value->admn_no)->where('session_year','2022-2023')->where('session','Winter')->get();
    if($user_dt[0]->form_id){
    $id=$value->id;
    $admn_no=$value->admn_no;
    $form_id=$user_dt[0]->form_id;
    $response =  $this->SendEmail($form_id, $admn_no);
    if($response){
      $emailLog = array(
        "admn_no" => $admn_no,
        "form_id" => $form_id,
        "session_year" => "2022-2023",
        "session" => "Winter",
        "send_status" => "Success",
      );
      DB::table('pre_registration_email_log')->insertGetId($emailLog);
      $affected = DB::table('pre_registration_email_log')
              ->where('id', $id)
              ->update(['resend_status' => 1]);
    }else{
      $emailLog = array(
        "admn_no" => $admn_no,
        "form_id" => $form_id,
        "session_year" => "2022-2023",
        "session" => "Winter",
        "send_status" => "Failed",
      );
      $affected = DB::table('pre_registration_email_log')
              ->where('id', $id)
              ->update(['resend_status' => 2]);
      DB::table('pre_registration_email_log')->insertGetId($emailLog);
    }
  }
}
  //  print_r($failedEmail);

  //  $response =  $this->SendEmail($form_id, $admn_no);
//    print_r($response);
    exit;
  }

  function SendEmail($form_id, $admn_no = null, $log_array = array())
  {
    try {
      $id = isset($admn_no) ? $admn_no : Auth::user()->id;

      $users = DB::select("SELECT m.domain_name,z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
      FROM(
      SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name
      ,if(a.sex='m','Male','Female') AS gender,a.photopath,c.signpath,a.dept_id,b.course_id,b.branch_id,b.semester AS current_sem,d.session_year,d.`session`,d.form_id
      FROM user_details a
      INNER JOIN stu_academic b ON a.id=b.admn_no
      INNER JOIN reg_regular_form d ON a.id=d.admn_no AND d.session_year='2022-2023' AND d.`session`='Winter'
      LEFT JOIN stu_prev_certificate c ON a.id=c.admn_no
      WHERE a.id='$id'

      GROUP BY a.id
      ORDER BY d.session_year,d.`session` DESC,d.semester desc
      )z
      INNER JOIN cbcs_departments c ON z.dept_id=c.id
      INNER JOIN cbcs_courses d ON z.course_id=d.id
      INNER JOIN cbcs_branches e ON z.branch_id=e.id
      left JOIN emaildata m ON z.admn_no=m.admission_no");;

      $data['stu_details'] = $users;

      $regData = DB::table('pre_stu_course')->where('form_id', $form_id)->get();
      $data['reg_data'] = $regData;

      $mailData = [
        'reg_data' => $regData,
        'stu_details' =>  $users
      ];
      $useremail = $users[0]->domain_name;

      // print_r($data);
      // exit;

      // return view('PreRegEmailBody', compact('mailData'));
      // exit;

      //   Mail::to($useremail)->send(new EMailClass($mailData));
    //    Mail::to('abhijeet.upadhyay01@gmail.com')->send(new EMailClass($mailData));
      // Mail::to('kumaraswamy@iitism.ac.in')->send(new EMailClass($mailData));
      return true;
    } catch (Exception $e) {
      //  return false;
      return $this->sendError('Invalid Request !', $e);
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
  function GetStudentDetails(Request $request)
  {

    // $validator = Validator::make($request->all(), [
    //   'session_year' => 'required|string',
    //   'session' => 'required|string',
    // ]);
    // if ($validator->fails()) {
    //   return $this->sendError('Invalid Request !', 'Invalid Request !');
    // }

    $admn_no = Auth::user()->id;
    $users = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
    FROM(
    SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name
    ,if(a.sex='m','Male','Female') AS gender,a.photopath,c.signpath,a.dept_id,b.course_id,b.branch_id,b.semester AS current_sem,d.session_year,d.`session`,d.form_id
    FROM user_details a
    INNER JOIN stu_academic b ON a.id=b.admn_no
    INNER JOIN reg_regular_form d ON a.id=d.admn_no AND  b.semester=d.semester
    LEFT JOIN stu_prev_certificate c ON a.id=c.admn_no
    WHERE a.id='$admn_no'

    GROUP BY a.id
    ORDER BY d.session_year,d.`session` DESC,d.semester desc
    )z
    INNER JOIN cbcs_departments c ON z.dept_id=c.id
    INNER JOIN cbcs_courses d ON z.course_id=d.id
    INNER JOIN cbcs_branches e ON z.branch_id=e.id");
    if ($users) {
      return $this->sendResponse($users, "Recordds");
    } else {
      return $this->sendError('Invalid Request !', 'Pre-Registration is Closed.Please Contact Acadmic Section');
    }


    return $this->sendResponse($admn_no, "Recordds");
  }


  function checkForGroupTT(Request $request)
  {
    $sessionYear = $request->session_year;
    $session = $request->session;
    $sub_offered_id = $request->sub_offered_id;
    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
      'sub_offered_id' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Invalid Request !');
    }
    // echo preg_replace('~\D~', '', $sub_offered_id);
    // exit;
    $checkedForGroup = DB::table('tt_group_subjects')
      ->where('session_year', $sessionYear)
      ->where('session', $session)
      ->where('sub_offered_id', preg_replace('~\D~', '', $sub_offered_id))
      ->count();

    if ($checkedForGroup) {
      $GroupDetails = DB::table('tt_group_subjects')
        ->select(DB::raw('sub_code,sub_offered_id,group_no,max_stu'))
        ->where('session_year', $sessionYear)
        ->where('session', $session)
        ->where('sub_offered_id', preg_replace('~\D~', '', $sub_offered_id))
        ->get();

      foreach ($GroupDetails as $key => $value) {

        $getGroupCnt = DB::table('pre_registration_group_student')
          ->where('session_year', $sessionYear)
          ->where('session', $session)
          ->where('sub_offered_id', $sub_offered_id)
          ->where('group_no', $value->group_no)
          ->count();

        $GroupDetails[$key]->alloted_cnt = $getGroupCnt;
        $GroupDetails[$key]->seat_remaining_cnt = ($value->max_stu) - ($getGroupCnt);

        $group_info_sql = "SELECT a.map_id,b.subj_code,b.subj_code,b.sub_offered_id,b.`day`,b.slot_no,b.venue_id,b.`group` FROM tt_map_cbcs a
       INNER JOIN tt_subject_slot_map_cbcs b ON a.map_id=b.map_id AND b.sub_offered_id='$sub_offered_id' AND b.`group` IS NOT NULL AND b.`group`='$value->group_no'
       WHERE a.session_year='$sessionYear' AND a.`session`='$session'
       UNION
       SELECT a.map_id,b.subj_code,b.subj_code,b.sub_offered_id,b.`day`,b.slot_no,b.venue_id,b.`group` FROM tt_map_old a
       INNER JOIN tt_subject_slot_map_old b ON a.map_id=b.map_id AND b.sub_offered_id='$sub_offered_id' AND b.`group` IS NOT NULL AND b.`group`='$value->group_no'
       WHERE a.session_year='$sessionYear' AND a.`session`='$session'";
        $group_info = DB::select($group_info_sql);
        $GroupDetails[$key]->group_tt = $group_info;
      }

      $data['group_info'] = $GroupDetails;
      return $this->sendResponse($data, "Group Course Time Table");
    } else {
      return $this->sendError('Group Details Not Found.', 'Group Details Not Found !');
    }
  }

  function StartPreReg(Request $request)
  {
    $sessionYear = $request->session_year;
    $session = $request->session;
    $admn_no = Auth::user()->id;

    $this->saveRegistationLog($admn_no, $sessionYear, $session, "Started Pre-Registration.");

    $sql = "SELECT * FROM reg_regular_form rrg
                 INNER JOIN users u ON u.id=rrg.admn_no AND u.status='A'
                 WHERE rrg.admn_no='$admn_no' AND rrg.session_year='$sessionYear'
                 AND rrg.session='$session' AND rrg.acad_status='1' AND
                rrg.hod_status='1' ";
    $checkReg =  DB::select($sql);


    if ($checkReg) {
      return $this->sendError('Already Registered !', 'You have already completed the pre-registration !');
    }

    $data['users'] = $user = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
             FROM(
             SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name
             ,if(a.sex='m','Male','Female') AS gender,a.photopath,a.dept_id,b.course_id,b.branch_id,b.semester AS current_sem
              FROM user_details a
              INNER JOIN stu_academic b ON a.id=b.admn_no
              INNER JOIN users u ON a.id=u.id AND u.`status`='A'
              WHERE a.id='$admn_no')z
              INNER JOIN cbcs_departments c ON z.dept_id=c.id
              INNER JOIN cbcs_courses d ON z.course_id=d.id
              INNER JOIN cbcs_branches e ON z.branch_id=e.id");

    $data['ttBody'] = $this->getTimeTableBody();
    $data['ttBodyNew'] = $this->getTimeTableBodyNew();
    if ($user) {
      return $this->sendResponse($data, "Recordds");
    } else {
      return $this->sendError('Invalid User !', 'User Details Not Found !');
    }
  }

  function saveRegistationLog($admn_no, $session_year, $session, $details)
  {

    $data = array(
      "admn_no" => $admn_no,
      "session_year" => $session_year,
      "session" => $session,
      "step_details" => $details,

    );

    return  DB::table('pre_registration_log')->insertGetId($data);
  }
  function GetSessionDetails(Request $request)
  {

    $admn_no = Auth::user()->id;
    $users = DB::select("SELECT * from sem_date_open_close_tbl where CURDATE() between DATE_FORMAT(normal_start_date, '%Y-%m-%d') and DATE_FORMAT(normal_close_date, '%Y-%m-%d') AND open_for='all' AND exam_type='Regular' union SELECT q.* from (SELECT p.* from (SELECT c.* FROM  stu_academic  c
      WHERE c.admn_no='$admn_no')c
     join (SELECT * from sem_date_open_close_tbl where CURDATE() between DATE_FORMAT(normal_start_date, '%Y-%m-%d') and DATE_FORMAT(normal_close_date, '%Y-%m-%d') AND open_for='specific' AND exam_type='Regular') p ON  lower(p.course)=lower(c.course_id) AND lower(p.branch)=lower(c.branch_id)
     AND  p.semester=(c.semester+1))q
     union SELECT c.* FROM  sem_date_open_close_tbl  c WHERE c.admn_no='$admn_no' AND open_for='indi_stu' AND exam_type='Regular' and CURDATE() between DATE_FORMAT(normal_start_date, '%Y-%m-%d') and DATE_FORMAT(normal_close_date, '%Y-%m-%d')");

    if ($users) {
      return $this->sendResponse($users, "Recordds");
    } else {
      return $this->sendError('Invalid Request !', 'Pre-Registration is Closed.Please Contact Acadmic Section');
    }
  }

  function GetStudentInfo($admn_no = null)
  {
    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;


    $currentSemDetails = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
    FROM(
    SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name,c.form_id,c.semester,c.session_year,c.`session`
    ,if(a.sex='m','Male','Female') AS gender,a.photopath,a.dept_id,b.auth_id,b.course_id,b.branch_id,b.semester AS current_sem
    FROM user_details a
    INNER JOIN stu_academic b ON a.id=b.admn_no
    INNER JOIN reg_regular_form c ON a.id=c.admn_no AND c.hod_status='1' AND c.acad_status='1' AND c.`status`='1'
    INNER JOIN users u ON a.id=u.id AND u.`status`='A'
    WHERE a.id='$admn_no' ORDER BY c.form_id DESC LIMIT 1)z
    INNER JOIN cbcs_departments c ON z.dept_id=c.id
    INNER JOIN cbcs_courses d ON z.course_id=d.id
    INNER JOIN cbcs_branches e ON z.branch_id=e.id");
    return $currentSemDetails;
  }
  function getOeCourses(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Please Select Valid Session Year and Session !');
    }
    $session_year = $request->session_year;
    $session = $request->session;
    $dept_filter = isset($request->dept_id) ? $request->dept_id : null;


    $checkSem = null;
    $cbcs_curriculam_policy_id = null;

    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;

    $this->saveRegistationLog($admn_no, $session_year, $session, "OE Course Selection Started.");

    $stu_info = $this->GetStudentInfo();
    // print_r($stu_info);
    // exit;
    $semester = $stu_info[0]->current_sem + 1;
    $course_id = $stu_info[0]->course_id;
    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;
    $auth_id = $stu_info[0]->auth_id;


    $cbcs_curriculam_policy_id = $this->getCurriculamPolicy($session_year, $session, $course_id, $semester);
    if (!$cbcs_curriculam_policy_id) {
      return $this->sendError('Curriculam Policy Not Found !', 'Curriculam Policy for given combination not found !');
    }
    $oecntsql = "SELECT *  FROM (SELECT CONCAT
    (a.course_component,a.sequence) AS oe_type FROM
    cbcs_coursestructure_policy a WHERE a.course_id='$course_id' AND a.sem='$semester' AND
    a.course_component='OE' AND a.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id
     UNION SELECT b.sub_category FROM
    cbcs_subject_offered b WHERE b.session_year='$session_year' AND b.session='$session' AND
    b.semester='$semester' AND b.course_id='$course_id' AND b.branch_id='$branch_id' AND b.sub_category
    LIKE 'OE%') X GROUP BY  X.oe_type ORDER BY X.oe_type";
    // echo $oecntsql;
    // exit;
    $oe_types = DB::select($oecntsql);
    $oe_type_list = array();
    foreach ($oe_types as $key => $value) {
      array_push($oe_type_list, array("course_category" => $value->oe_type, "isSelected" => 0, "isClicked" => 0, "course_component" => "OE"));
    }
    //  $data['OeCategory'] = $oe_type_list;
    $dept_filter_clouse = "";
    if ($dept_filter) {
      $dept_filter_clouse = " AND a.dept_id='$dept_filter'";
    }
    $course_type = 'OE';
    $extraClouse = "";
    if ($auth_id == 'pg') {
      $extraClouse = " union
      SELECT z.*,'oe' AS cat,'OE' AS course_component,0 AS 'isSelected', 0 AS isClicked
      FROM (
  SELECT z.*, CONCAT('c',z.id) AS sub_offered_id
  FROM (
  SELECT a.id,a.session_year,a.`session`,a.dept_id,a.course_id,a.branch_id,a.semester,a.unique_sub_pool_id,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,a.pre_requisite,a.pre_requisite_subcode, 1 AS pre_requisite_pass,
   a.sub_type,a.sub_category,(CASE WHEN (a.course_id='b.tech' OR
   a.course_id='int.m.tech' OR a.course_id='dualdegree' OR a.course_id='be') THEN 'ug' WHEN a.course_id='jrf' THEN 'jrf' ELSE 'pg' END) AS cl
  FROM cbcs_subject_offered a
  WHERE a.session_year='$session_year' AND a.`session`='$session' $dept_filter_clouse AND (CASE WHEN '$course_type'='OE' THEN
   (a.sub_category LIKE 'DC%') ELSE a.sub_category LIKE '$course_type%' END) AND a.dept_id<>'$dept_id'
  GROUP BY a.sub_code) z
  GROUP BY z.sub_code)z WHERE z.cl='pg'";
    }

    $oe_paper_sql = "SELECT z.*,'oe' as cat,'OE' as course_component,0 as 'isSelected', 0 AS isClicked FROM (SELECT z.*,CONCAT('c',z.id) AS sub_offered_id FROM (SELECT a.id,a.session_year,a.`session`,a.dept_id,a.course_id,a.branch_id,a.semester,a.unique_sub_pool_id,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,a.pre_requisite,a.pre_requisite_subcode,TRUE AS pre_requisite_pass,
    a.sub_type,a.sub_category,(case when (a.course_id='b.tech' OR
    a.course_id='int.m.tech' OR  a.course_id='dualdegree' OR  a.course_id='be') then 'ug' when a.course_id='jrf' then 'jrf'
    ELSE 'pg' END) AS cl
    FROM cbcs_subject_offered a
    WHERE a.session_year='$session_year' AND a.`session`='$session' $dept_filter_clouse AND (
    case when '$course_type'='OE' then
    (a.sub_category LIKE 'OE%' OR a.sub_category LIKE 'DE%')
    ELSE a.sub_category LIKE '$course_type%'
    END ) AND (case when '$course_type'='DE' then a.dept_id='$dept_id' ELSE 1=1 end) AND a.sub_code<>'NA'
    GROUP BY a.sub_code) z
    GROUP BY z.sub_code)z
    WHERE (case when '$course_type'='DE' then (case when '$auth_id'='ug' then z.cl IN ('ug','pg') when '$auth_id'='pg'  then z.cl IN ('pg') ELSE 1=1 END) ELSE 1=1 end)
    $extraClouse
    ";
    // echo $oe_paper_sql;
    // exit;
    $oeCourses = DB::select($oe_paper_sql);

    $passedCategory = array();
    for ($i = 0; $i < count($oe_type_list); $i++) {
      //  print_r($oe_type_list[$i]['course_category']);
      //  exit;
      $CheckAlreadyPassedByCategory = $this->CheckAlreadyPassedByCategory($admn_no, $oe_type_list[$i]['course_category']);
      if ($CheckAlreadyPassedByCategory) {
        array_push($passedCategory, $oe_types[$i]);
        unset($oe_type_list[$i]);
      }
    }
    $oe_type_list = array_values($oe_type_list);
    $data['OeCategory'] = $oe_type_list;

    $core_courses = $this->getCoreCourses($request, true);
    // print_r($core_courses);
    // exit;
    $core_courses_offered_id = array();
    foreach ($core_courses as $key => $value) {
      array_push($core_courses_offered_id, $value->sub_offered_id);
    }
    // print_r($core_courses_offered_id);
    // exit;
    $validFail = array();
    $ttclashCourses = array();
    foreach ($oeCourses as $key => $value) {
      //  echo $key . "" . $value->sub_code;
      $subj = join("', '", explode(',', implode(",", $core_courses_offered_id)));
      $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $session_year, $session, $course_id, 'OE');


      if ($tt_clash) {
        // echo $tt_clash + 1;
        // $oeCourses[$key]->clash_with = $tt_clash->final_clash;
        array_push($ttclashCourses, $value);
        unset($oeCourses[$key]);
      } else {

        $CheckAlreadyPassed = $this->CheckAlreadyPassedByCode($admn_no, $value->sub_code);
        if ($CheckAlreadyPassed) {
          array_push($validFail, $value);
          unset($oeCourses[$key]);
        } else {
          if (!$CheckAlreadyPassed || !$tt_clash) {
            if ($value->pre_requisite == 'yes') {
              $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
              if (!$checkforpre_requisite) {
                //  print_r($value);
                //   echo $key;
                //echo $oeCourses["253"];
                // exit;
                $oeCourses[$key]->pre_requisite_pass = 0;
              }
            }
          }
        }
      }
    }
    // print_r($oeCourses);
    // exit;
    // exit;
    // foreach ($oeCourses as $key => $value) {
    // }
    $oeCourses = array_values($oeCourses);
    $timeTable = array();
    foreach ($oeCourses as $key => $value) {
      $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
      if ($tt) {
        array_push($timeTable, $tt);
      }
    }


    $data['course_component'] = 'OE';
    $data['passed_category'] = $passedCategory;
    $data['oe_courses_cnt'] = count($oeCourses);
    $data['oe_courses'] = $oeCourses;
    $data['passed_coures'] = $validFail;
    $data['timeTable'] = $timeTable;
    $data['timeTable_clashed_courses'] = $ttclashCourses;
    $data['timeTable_clashed_courses_cnt'] = count($ttclashCourses);
    return $this->sendResponse($data, "OE Courses");
  }

  function getDepartment()
  {
    $dept_list = DB::table('cbcs_departments')->where('type', 'academic')
      ->where('status', 1)->get();
    return $this->sendResponse($dept_list, "Department list !");
  }

  function checkPreRequisite($admn_no, $sub_code)
  {
    $old_stu = DB::table('old_stu_course')->where('admn_no', $admn_no)->where('subject_code', $sub_code);
    $cbcs_stu = DB::table('cbcs_stu_course')->where('admn_no', $admn_no)->where('subject_code', $sub_code)->union($old_stu)->count();
    if ($cbcs_stu > 0) {
      return true;
    } else {
      return false;
    }
  }

  function getDeCourses(Request $request)
  {

    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Please Select Valid Session Year and Session !');
    }
    $session_year = $request->session_year;
    $session = $request->session;
    $dept_filter = isset($request->dept_id) ? $request->dept_id : null;

    $checkSem = null;
    $cbcs_curriculam_policy_id = null;

    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;


    $this->saveRegistationLog($admn_no, $session_year, $session, "DE Course Selection Started.");
    // echo $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;
    // exit;
    $stu_info = $this->GetStudentInfo();
    // print_r($stu_info);exit;
    $semester = $stu_info[0]->current_sem + 1;
    $course_id = $stu_info[0]->course_id;
    $auth_id = $stu_info[0]->auth_id;

    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;

    if ($semester <= 8 || $course_id == 'int.m.tech' || strtoupper($course_id) == 'JRF') {
      $checkSem = 'cbcs';
    } else {
      $checkSem = 'old';
    }

    $cbcs_curriculam_policy_id = $this->getCurriculamPolicy($session_year, $session, $course_id, $semester);
    if (!$cbcs_curriculam_policy_id) {
      return $this->sendError('Curriculam Policy Not Found !', 'Curriculam Policy for given combination not found !');
    }
    $course_type = 'DE';
    if ($checkSem == 'cbcs') {

      $decntsql = "SELECT * FROM (SELECT CONCAT
    (a.course_component,a.sequence) AS de_type, CONCAT
    (a.course_component,a.sequence) AS sub_category FROM
    cbcs_coursestructure_policy a WHERE a.course_id='$course_id' AND a.sem='$semester' AND
    a.course_component='DE' AND a.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id
     UNION
     SELECT b.sub_category,b.sub_category AS
    sub_category FROM cbcs_subject_offered b WHERE b.session_year='$session_year' AND
    b.session='$session' AND b.semester='$semester' AND b.course_id='$course_id' AND b.branch_id='$branch_id' AND
    b.sub_category LIKE 'DE%') X GROUP BY  X.de_type ORDER BY X.de_type";
      // echo $decntsql;
      // exit;
      $decnt = DB::select($decntsql);
      $data['de_cnt'] = count($decnt);
      $decategory = array();
      $passedDE = array();
      foreach ($decnt as $key => $value) {
        array_push($decategory,  array("course_category" => $value->sub_category, "isSelected" => 0, "isClicked" => 0, "course_component" => "DE"));
        $CheckAlreadyPassedByCategory = $this->CheckAlreadyPassedByCategory($admn_no, $value->sub_category);
        if ($CheckAlreadyPassedByCategory) {
          array_push($passedDE, $value->sub_category);
          unset($decategory[$key]);
        }
      }
      $data['deCategory'] = $decategory;
      $data['passed_category'] = $passedDE;

      $dept_filter_clouse = "";
      if ($dept_filter) {
        $dept_filter_clouse = " AND a.dept_id='$dept_filter'";
      }

      $deCoursessqlold = "SELECT z.*,CONCAT('c',z.id) AS sub_offered_id
      FROM (
      SELECT a.id,a.session_year,a.`session`,a.dept_id,a.course_id,a.branch_id,a.semester,a.unique_sub_pool_id,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,
       a.sub_type,a.sub_category, MAX(a.maxstu) AS maxstu
      FROM cbcs_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session' $dept_filter_clouse AND (CASE WHEN 'DE'='OE' THEN
       (a.sub_category LIKE 'OE%' OR a.sub_category LIKE 'DE%') ELSE a.sub_category LIKE 'DE%' END)
      GROUP BY a.sub_code) z";


      $deCoursessql = "SELECT z.*,'de' as cat,'DE' as course_component, 0 as 'isSelected', 0 AS isClicked FROM (SELECT z.*,CONCAT('c',z.id) AS sub_offered_id FROM (SELECT a.id,a.session_year,a.`session`,a.course_id,a.branch_id,a.semester,a.unique_sub_pool_id,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,a.pre_requisite,a.pre_requisite_subcode,TRUE AS pre_requisite_pass,
      a.sub_type,a.sub_category, MAX(a.maxstu) AS maxstu,(case when (a.course_id='b.tech' OR
      a.course_id='int.m.tech' OR  a.course_id='dualdegree' OR  a.course_id='be') then 'ug' when a.course_id='jrf' then 'jrf'
      ELSE 'pg' END) AS cl
      FROM cbcs_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session'
      AND (case when '$dept_id'='fme' then a.sub_code NOT IN ('HSD507','HSD507','HSD512','HSD555','HSO508','HSO513','HSC512') ELSE 1=1 end)
      $dept_filter_clouse AND (
      case when '$course_type'='OE' then
      (a.sub_category LIKE 'OE%' OR a.sub_category LIKE 'DE%')
      ELSE a.sub_category LIKE '$course_type%'
      END ) AND (case when '$course_type'='DE' then a.dept_id='$dept_id' ELSE 1=1 end) AND a.sub_code<>'NA'
      GROUP BY a.sub_code) z
      GROUP BY z.sub_code)z
      WHERE (case when '$course_type'='DE' then (case when '$auth_id'='ug' then z.cl IN ('ug','pg') when '$auth_id'='pg'  then z.cl IN ('pg') ELSE 1=1 END) ELSE 1=1 end)
      union
      SELECT z.*,'de' as cat,'DE' as course_component, 0 as 'isSelected', 0 AS isClicked FROM (SELECT z.*,CONCAT('c',z.id) AS sub_offered_id FROM (SELECT a.id,a.session_year,a.`session`,a.course_id,a.branch_id,a.semester,a.unique_sub_pool_id,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,a.pre_requisite,a.pre_requisite_subcode,TRUE AS pre_requisite_pass,
      a.sub_type,a.sub_category, MAX(a.maxstu) AS maxstu,(case when (a.course_id='b.tech' OR
      a.course_id='int.m.tech' OR  a.course_id='dualdegree' OR  a.course_id='be') then 'ug' when a.course_id='jrf' then 'jrf'
      ELSE 'pg' END) AS cl
      FROM cbcs_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session'
      AND (case when '$dept_id'='fme' then a.sub_code NOT IN ('HSD507','HSD507','HSD512','HSD555','HSO508','HSO513','HSC512') ELSE 1=1 end)
      $dept_filter_clouse AND (
      case when '$course_type'='OE' then
      (a.sub_category LIKE 'OE%' OR a.sub_category LIKE 'DE%')
      ELSE a.sub_category LIKE '$course_type%'
      END ) AND (case when '$course_type'='DE' then a.dept_id='$dept_id' ELSE 1=1 end) AND a.sub_code<>'NA'
      GROUP BY a.sub_code) z
      GROUP BY z.sub_code)z
      WHERE (case when '$course_type'='DE' then (case when '$auth_id'='ug' then z.cl IN ('ug','pg') when '$auth_id'='pg'  then z.cl IN ('pg','ug') ELSE 1=1 END) ELSE 1=1 end)
      AND  SUBSTRING(REGEXP_REPLACE(z.sub_code, '[^0-9]', ''), 1, 1)=5
      ";
      // echo $deCoursessql;
      // exit;
      $deCourses = DB::select($deCoursessql);

      $core_courses = $this->getCoreCourses($request, true);
      $core_courses_offered_id = array();
      foreach ($core_courses as $key => $value) {
        array_push($core_courses_offered_id, $value->sub_offered_id);
      }


      $ttclashCourses = array();
      $depassedcourses = array();
      $timeTable = array();
      $subj = join("', '", explode(',', implode(",", $core_courses_offered_id)));
      foreach ($deCourses as $key => $value) {

        $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
        if ($tt) {
          array_push($timeTable, $tt);
        }

        $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $session_year, $session, $course_id, 'ESO');
        if ($tt_clash) {
          unset($deCourses[$key]);
          array_push($ttclashCourses, $value);
        } else {

          $CheckAlreadyPassed = $this->CheckAlreadyPassedByCode($admn_no, $value->sub_code);
          if ($CheckAlreadyPassed) {
            array_push($depassedcourses, $value);
            unset($deCourses[$key]);
          } else {

            if ($value->pre_requisite == 'yes') {
              $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
              if (!$checkforpre_requisite) {
                $deCourses[$key]->pre_requisite_pass = 0;
              }
            }
          }
        }
      }
      $deCourses = array_values($deCourses);
      $data['course_component'] = 'DE';
      $data['de_courses'] = $deCourses;
      $data['de_coures_cnt'] = count($deCourses);
      $data['timeTable_clashed_courses'] = $ttclashCourses;
      $data['timeTable'] = $timeTable;
      $data['passed_courses'] = $depassedcourses;
    } else {
      $decntsql = "SELECT  sub_category AS de_type,COUNT(*) FROM old_subject_offered where
      session_year='$session_year' AND course_id='$course_id' AND branch_id='$branch_id' and semester='$semester'
       and dept_id='$dept_id' AND sub_category LIKE 'DE%' GROUP BY sub_category";
      $decnt = DB::select($decntsql);
      $data['de_courses'] = $decnt;
      $data['deCategory'] = array();
      $data['passed_category'] = array();
    }

    return $this->sendResponse($data, "DE Courses");
  }

  function getEsoCourses(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Please Select Valid Session Year and Session !');
    }
    $session_year = $request->session_year;
    $session = $request->session;

    $checkSem = null;
    $cbcs_curriculam_policy_id = null;

    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;

    $stu_info = $this->GetStudentInfo();

    $semester = $stu_info[0]->current_sem + 1;
    $course_id = $stu_info[0]->course_id;
    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;

    $checkeso = $this->DropBacklogCourses($request, true, 'ESO');


    $cbcs_curriculam_policy_id = $this->getCurriculamPolicy($session_year, $session, $course_id, $semester);
    if (!$cbcs_curriculam_policy_id) {
      return $this->sendError('Curriculam Policy Not Found !', 'Curriculam Policy for given combination not found !');
    }


    $this->saveRegistationLog($admn_no, $session_year, $session, "ESO Course Selection Started.");

    if ($semester <= 5) {

      $esoCnt = DB::table('cbcs_coursestructure_policy')->where('course_id', $course_id)
        ->where('sem', $semester)
        ->where('course_component', 'ESO')->count();
      // echo $esoCnt;
      $data['esoCnt'] = $esoCnt;

      $esoCategory = DB::table('cbcs_coursestructure_policy')
        ->select(DB::raw('CONCAT(UPPER
        (course_component),sequence)  AS eso_type '))
        ->where('course_id', $course_id)
        ->where('sem', $semester)
        ->where('course_component', 'ESO')->get();

      $getGuidedESO = DB::table('cbcs_guided_eso')
        ->where('session_year', $session_year)
        ->where('session', $session)
        ->where('course_id', $course_id)
        ->where('branch_id', $branch_id)
        ->where('semester', $semester)->get();
      $guidedESOType = array();
      foreach ($getGuidedESO as $key => $value) {
        array_push($guidedESOType, $value->eso_type);
      }
      if ($getGuidedESO) {
        $guidedCoursesql = "SELECT 'guided' AS 'src','eso' AS cat,'ESO' AS course_component,0 AS 'isSelected', 0 AS isClicked, f.eso_type, c.*,CONCAT('c',c.id) AS sub_offered_id
        FROM
         (
        SELECT p.*,ge.sub_offered_id,ge.session_year,ge.session,ge.course_id AS crs
        FROM (
        SELECT p.*, CONCAT(UPPER
         (p.course_component),p.sequence) AS eso_type
        FROM
         cbcs_coursestructure_policy p
        WHERE p.course_id='$course_id' AND p.sem='$semester' AND
         p.course_component='ESO' AND p.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)p
        JOIN (
        SELECT ge.*
        FROM cbcs_guided_eso ge
        WHERE ge.session_year='$session_year' AND ge.session='$session' AND ge.course_id='$course_id' AND
         ge.branch_id='$branch_id' AND ge.semester='$semester') ge ON ge.course_id=p.course_id AND
         ge.semester=p.sem AND UPPER(ge.eso_type)=p.eso_type) f
        LEFT JOIN
         cbcs_subject_offered c ON c.id=f.sub_offered_id ";
        // echo $guidedCoursesql;
        // exit;
        $guidedCourses = DB::select($guidedCoursesql);
      }
      // print_r($getGuidedESO);
      // exit;
      // print_r($guidedESOType);
      // exit;
      $esocat = array();
      foreach ($esoCategory as $key => $value) {
        if (in_array($value->eso_type, $guidedESOType)) {
          $isGuided = 1;
          array_push($esocat, array("course_category" => $value->eso_type, "isSelected" => 0, "isClicked" => 0, "course_component" => "ESO", 'isGuided' => $isGuided, 'GuidedCourse' => $guidedCourses));
        } else {
          $isGuided = 0;
          array_push($esocat, array("course_category" => $value->eso_type, "isSelected" => 0, "isClicked" => 0, "course_component" => "ESO", 'isGuided' => $isGuided, 'GuidedCourse' => array()));
        }
      }
      $data['esoCategory'] = $esocat;

      $esosql = "SELECT 'guided'
      AS 'src','eso' as cat ,'ESO' as course_component,0 as 'isSelected', 0 AS isClicked, f.eso_type , c.*,CONCAT('c',c.id) AS sub_offered_id FROM
      (SELECT p.*,ge.sub_offered_id,ge.session_year,ge.session,ge.course_id
      AS crs FROM (SELECT p.*, CONCAT(UPPER
      (p.course_component),p.sequence) AS eso_type FROM
      cbcs_coursestructure_policy p WHERE p.course_id='$course_id' AND p.sem='$semester' AND
      p.course_component='ESO' AND p.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)p
      JOIN (SELECT ge.* FROM cbcs_guided_eso ge
      WHERE ge.session_year='$session_year' AND ge.session='$session' AND ge.course_id='$course_id' AND
      ge.branch_id='$branch_id' AND ge.semester='$semester') ge ON ge.course_id=p.course_id AND
      ge.semester=p.sem AND UPPER(ge.eso_type)=p.eso_type) f LEFT JOIN
      cbcs_subject_offered c ON c.id=f.sub_offered_id UNION ALL
      SELECT 'open' AS 'src',f.eso_type,'core' as cat,'ESO' as course_component,0 as 'isSelected', 0 AS isClicked ,co.*,CONCAT('c',co.id) AS sub_offered_id FROM
      (SELECT p.*,ge.sub_offered_id,ge.session_year,ge.session,ge.course_id
      AS crs FROM (SELECT p.*, CONCAT(UPPER(p.course_component),p.sequence) AS eso_type FROM
      cbcs_coursestructure_policy p WHERE p.course_id='$course_id' AND p.sem='$semester' AND
      p.course_component='ESO' AND p.cbcs_curriculam_policy_id=${cbcs_curriculam_policy_id})p LEFT JOIN (SELECT ge.* FROM
      cbcs_guided_eso ge WHERE ge.session_year='$session_year' AND ge.session='$session' AND
      ge.course_id='$course_id' AND ge.branch_id='$branch_id' AND ge.semester='$semester') ge ON
      ge.course_id=p.course_id AND ge.semester=p.sem AND UPPER
      (ge.eso_type)=p.eso_type HAVING ge.course_id IS NULL) f JOIN
      cbcs_subject_offered co ON co.sub_category=f.eso_type AND
      co.session_year='$session_year' AND co.session='$session' AND (!(co.remarks NOT LIKE '1%'
      AND co.course_id='$course_id' AND co.branch_id='$branch_id' AND co.semester='$semester') OR
      (co.remarks LIKE '1%' AND co.course_id='b.tech' AND co.branch_id='$branch_id'
      AND co.semester=$semester))";



      $esocourses = DB::select($esosql);
      $data['eso_course'] = $esocourses;
    } else {

      $newEsoCount = `SELECT CONCAT(UPPER(p.course_component),p.sequence) AS eso_type
      FROM cbcs_coursestructure_policy p WHERE p.course_id=? AND UPPER(p.course_component)='ESO' AND sem=?`;
      $esoCnt = DB::table('cbcs_coursestructure_policy')->where('course_id', $course_id)
        ->where('sem', $semester)
        ->where('.course_component', 'ESO')->count();
      // echo $esoCnt;
      $data['esoCnt'] = $esoCnt;

      $esoCategory = DB::table('cbcs_coursestructure_policy')
        ->select(DB::raw('CONCAT(UPPER
      (course_component),sequence)  AS eso_type '))
        ->where('course_id', $course_id)
        ->where('sem', $semester)
        ->where('course_component', 'ESO')->get();

      $esocat = array();
      foreach ($esoCategory as $key => $value) {
        array_push($esocat, array("course_category" => $value->eso_type, "isSelected" => 0, "isClicked" => 0, "course_component" => "ESO", 'isGuided' => 0, 'GuidedCourse' => array()));
      }
      $data['esoCategory'] = $esocat;

      if (count($checkeso) > 1) {
      } else {
        $checkguidedsql = "SELECT 'guided' AS src,'eso' as cat,'ESO' as course_component,0 as 'isSelected', 0 AS isClicked,'ESO4' AS eso_type ,a.* from cbcs_guided_eso b
        INNER JOIN cbcs_subject_offered a ON a.id=b.sub_offered_id WHERE b.session_year='$session_year'
        AND b.SESSION='$session' and b.course_id='$course_id' AND b.dept_id='$dept_id' AND b.branch_id='$branch_id'
        AND b.semester='$semester'";
        $checkguided = DB::select($checkguidedsql);

        if (count($checkguided) == 0) {
          $esosql = "SELECT 'open' AS src,'eso' as cat,'ESO' as course_component,0 as 'isSelected', 0 AS isClicked,'ESO4' AS eso_type ,xxx.*,CONCAT('c',xxx.id) AS sub_offered_id FROM (SELECT * from cbcs_subject_offered Where session_year='$session_year'
          AND SESSION='$session' AND sub_category LIKE 'ESO%' AND sub_code NOT IN (SELECT sub_code FROM   cbcs_subject_offered Where session_year='$session_year'
           AND SESSION='$session'  AND sub_category LIKE 'ESO%' AND course_id='$course_id' AND dept_id ='$dept_id'
            AND branch_id='$branch_id' AND remarks ='0000000000' UNION ALL
          SELECT b.sub_code FROM final_semwise_marks_foil_freezed a inner JOIN
          final_semwise_marks_foil_desc_freezed b on a.id=b.foil_id  WHERE a.admn_no='$admn_no'  AND a.semester=3)ORDER BY  semester DESC ) xxx
          GROUP BY sub_code";
          $esocourses = DB::select($esosql);
        } else {
          $esosql = "SELECT 'open' AS src,'eso' as cat ,'ESO' as course_component,0 as 'isSelected', 0 AS isClicked,'ESO4' AS eso_type,yyy.*,CONCAT('c',xxx.id) AS sub_offered_id FROM (SELECT * FROM ( SELECT * from cbcs_subject_offered
          Where session_year='$session_year'  AND SESSION='$session' AND sub_category LIKE 'ESO%'
          AND sub_code NOT IN (SELECT sub_code FROM cbcs_subject_offered
          Where session_year='$session_year'  AND SESSION='$session'  AND sub_category LIKE 'ESO%' AND course_id='$course_id'
          AND dept_id ='$dept_id' AND branch_id='$branch_id' AND remarks ='0000000000' UNION ALL
          SELECT b.sub_code FROM final_semwise_marks_foil_freezed a inner JOIN  final_semwise_marks_foil_desc_freezed b on
          a.id=b.foil_id  WHERE a.admn_no='$admn_no'  AND a.semester=3 )ORDER BY  semester DESC ) xxx GROUP BY sub_code)yyy
          WHERE sub_category
          NOT IN (SELECT sub_category from cbcs_subject_offered Where session_year='$session_year'  AND SESSION='$session'
            AND sub_category='$checkeso[0]->sub_category')";
          echo $esosql;
          exit;
          $esocourses = DB::select($esosql);
        }
      }
    }
    // print_r($esocourses);
    // exit;
    $timeTable = array();
    foreach ($esocourses as $key => $value) {
      $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
      if ($tt) {
        array_push($timeTable, $tt);
      }
    }

    // return $this->sendResponse($data, "ESO Courses");
    $core_courses = $this->getCoreCourses($request, true);
    $core_courses_offered_id = array();
    foreach ($core_courses as $key => $value) {
      array_push($core_courses_offered_id, $value->sub_offered_id);
    }
    //echo explode(',', $core_courses_offered_id);
    //exit;
    $validFail = array();
    $ttclashCourses = array();
    foreach ($esocourses as $key => $value) {
      $esocourses[$key]->pre_requisite_pass = 1;
      $subj = join("', '", explode(',', implode(",", $core_courses_offered_id)));
      $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $session_year, $session, $course_id, 'ESO');
      if ($tt_clash) {
        unset($esocourses[$key]);
        array_push($ttclashCourses, $value);
      }

      $CheckAlreadyPassed = $this->CheckAlreadyPassedByCode($admn_no, $value->sub_code);
      if ($CheckAlreadyPassed) {
        array_push($validFail, $value);
        unset($esocourses[$key]);
      }

      if ($value->pre_requisite == 'yes') {
        $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
        if (!$checkforpre_requisite) {
          $esocourses[$key]['pre_requisite_pass'] = 0;
        }
      }
    }

    $passedCategory = array();
    for ($i = 0; $i < count($esocat); $i++) {

      $CheckAlreadyPassedByCategory = $this->CheckAlreadyPassedByCategory($admn_no, $esocat[$i]['course_category']);
      if ($CheckAlreadyPassedByCategory) {
        array_push($passedCategory, $esocat[$i]);
        unset($esocat[$i]);
      }
    }
    $esocourses = array_values($esocourses);
    $data['course_component'] = 'ESO';
    $data['passed_category'] = $passedCategory;

    $data['eso_course'] = $esocourses;
    $data['eso_course_cnt'] = count($esocourses);
    $data['timeTable_clashed_courses'] = $ttclashCourses;
    $data['timeTable'] = $timeTable;
    $data['passed_coures'] = $validFail;
    return $this->sendResponse($data, "ESO Courses");
  }
  function CheckAlreadyPassedByCategory($admn_no, $sub_category)
  {
    $sql = "SELECT * FROM final_semwise_marks_foil_desc_freezed a WHERE a.admn_no='$admn_no' AND a.mis_sub_id='$sub_category' AND a.grade <> 'F'";
    // echo $sql;
    // exit;
    $CheckAlreadyPassed = DB::select($sql);
    if ($CheckAlreadyPassed) {
      return true;
    } else {
      return false;
    }
  }

  function CheckAlreadyPassedByCode($admn_no, $sub_code)
  {
    $sql = "SELECT * FROM final_semwise_marks_foil_desc_freezed a WHERE a.admn_no='$admn_no' AND a.sub_code='$sub_code' AND a.grade <> 'F'";
    // echo $sql;
    // exit;
    $CheckAlreadyPassed = DB::select($sql);
    if ($CheckAlreadyPassed) {
      return true;
    } else {
      return false;
    }
  }

  function getCoreCourses(Request $request, $flag = false)
  {


    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Please Select Valid Session Year and Session !');
    }

    $session_year = $request->session_year;
    $session = $request->session;

    $checkSem = null;
    $cbcs_curriculam_policy_id = null;

    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;

    if (!$flag) {
      $this->saveRegistationLog($admn_no, $session_year, $session, "Core Course Selection Started.");
    }

    $stu_info = $this->GetStudentInfo();

    $semester = $stu_info[0]->current_sem + 1;
    $course_id = $stu_info[0]->course_id;
    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;
    $timeTable = array();
    $cbcs_curriculam_policy_id = $this->getCurriculamPolicy($session_year, $session, $course_id, $semester);
    $course_course_all = array();
    if (!$cbcs_curriculam_policy_id) {
      if (!$flag) {
        return $this->sendError('Curriculam Policy Not Found !', 'Curriculam Policy for given combination not found !');
      } else {
        return array();
      }
    }
    if ($semester <= 8 || $course_id == 'int.m.tech' || strtoupper($course_id) == 'JRF') {
      $checkSem = 'cbcs';
    } else {
      $checkSem = 'old';
    }
    // echo $checkSem;
    // exit;
    if ($checkSem == 'cbcs') {
      $sql = "SELECT z.*,'core' as cat,1 AS pre_requisite_pass,0 as 'isSelected', 0 AS isClicked FROM (SELECT
      x.status,x.course_component,x.sequence,x.session_year,x.session,x.dept_id,x.course_id,x.branch_id,x.semester,x.sub_name,x.sub_code,x.sub_type,
      x.sub_category AS sub_category,x.sub_category AS sub_category_cbcs_offered,x.sub_offered_id,x.lecture,x.tutorial,x.practical,x.pre_requisite,x.pre_requisite_subcode
      FROM(SELECT  b.status,b.course_component,b.sequence, a.*, '' AS map_id, CONCAT
      ('c',a.id) AS sub_offered_id FROM (SELECT b.* FROM
      cbcs_coursestructure_policy b  WHERE  b.sem=$semester AND b.course_id='$course_id' AND
      (b.course_component LIKE '%DC%' OR b.course_component LIKE '%DP%') AND b.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)b
      left JOIN cbcs_subject_offered a ON b.course_id=a.course_id AND
      b.sem=a.semester AND /*if(a.unique_sub_pool_id NOT IN
      ('NA',''),a.unique_sub_pool_id= CONCAT
      (b.course_component,b.sequence),CONCAT
      (b.course_component,b.sequence)=a.sub_category)*/
      (CASE WHEN a.unique_sub_pool_id NOT IN ('NA','') THEN (CONCAT(SUBSTRING_INDEX(b.course_component,'/', 1),SUBSTRING_INDEX(b.sequence,'/', 1),'/',CONCAT(SUBSTRING_INDEX(b.course_component,'/', -1),SUBSTRING_INDEX(b.sequence,'/', -1)))=concat(SUBSTRING_INDEX(a.unique_sub_pool_id,'/',1),'/',SUBSTRING_INDEX(a.unique_sub_pool_id,'/',-1)))
      ELSE CONCAT(b.course_component,b.sequence)=a.sub_category END)
       and a.session_year='$session_year' AND
      a.session='$session' AND a.semester=$semester AND a.course_id='$course_id' AND a.branch_id='$branch_id' UNION
      ALL SELECT b.status,b.course_component,b.sequence,a.*,'' AS map_id, CONCAT
      ('c',a.id) AS sub_offered_id FROM cbcs_comm_coursestructure_policy b left
      JOIN cbcs_subject_offered a ON b.course_id=a.course_id AND
      b.sem=a.semester AND /*if(a.unique_sub_pool_id NOT IN
      ('NA',''),a.unique_sub_pool_id= CONCAT
      (b.course_component,b.sequence), CONCAT
      (b.course_component,b.sequence)=a.sub_category)*/
      CONCAT(b.course_component,b.sequence)=a.sub_category
      JOIN stu_section_data c
      ON c.session_year=a.session_year AND c.admn_no='$admn_no' and a.session_year='$session_year' AND
      a.session='$session' AND a.semester=$semester AND a.course_id='$course_id' AND a.branch_id='$branch_id' AND
      a.sub_group=if(c.section in ('A','B','C','D'),1,2)) x WHERE
      x.sub_category LIKE 'DC%' OR x.sub_category LIKE 'DP%' OR x.sub_category LIKE 'IC%' OR x.sub_category
      IS null GROUP BY x.sub_code,x.course_component,x.sequence ORDER BY x.sub_type DESC, x.sub_category)z
      WHERE z.session_year IS NOT null";
    } else {
      $sql = "SELECT x.status,1 AS pre_requisite_pass,0 as 'isSelected', 0 AS isClicked,x.sub_category AS sub_category_cbcs_offered,x.course_component,x.session_year,x.session,x.dept_id,x.course_id,x.branch_id,x.semester,x.sub_name,
      x.sub_code,x.sub_type,x.sub_category,x.sub_offered_id,x.lecture,x.tutorial,x.practical,x.pre_requisite,x.pre_requisite_subcode,'core' as cat,0 as 'isSelected'
     FROM(
     SELECT 'na' AS STATUS, 'na' AS course_component
     ,a.*, CONCAT('o',a.id) AS sub_offered_id
     FROM old_subject_offered a
     WHERE a.session_year='$session_year' AND a.session='$session' AND a.semester='$semester' AND
      a.course_id='$course_id' AND a.branch_id='$branch_id' AND (INSTR (a.sub_category,'DC') OR INSTR(a.sub_category,'DP'))) x
     GROUP BY x.sub_code
     ORDER BY x.sub_type DESC, x.sub_category";
    }
    // echo $sql;
    // exit;

    $core_courses =  DB::select($sql);

    if (strtoupper($course_id) == 'JRF' && $semester == 2) {
      $dept_id_pg = $request->dept_id_pg;
      $dept_id_ug = $request->dept_id_ug;
      $final_courses = array();
      foreach ($core_courses as $key => $value) {
        array_push($final_courses, $value->sub_offered_id);
      }

      $extraComponentsql = "SELECT z.final_componet as course_category,0 as isSelected,0 as isClicked,'core' as cat,'extra' as polices  FROM (SELECT (CASE WHEN a.course_component LIKE '%/%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'/',1),
      SUBSTRING_INDEX(a.sequence,'/',1)),'/', CONCAT(SUBSTRING_INDEX(a.course_component,'/',-1),
      SUBSTRING_INDEX(a.sequence,'/',-1))) WHEN a.course_component LIKE '%+%' THEN
      CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'+',1), SUBSTRING_INDEX(a.sequence,'+',1)),'+',
      CONCAT(SUBSTRING_INDEX(a.course_component,'+',-1), SUBSTRING_INDEX(a.sequence,'+',-1))) ELSE
      CONCAT(a.course_component,a.sequence) END) AS final_componet,a.*
      FROM cbcs_coursestructure_policy a
      INNER JOIN cbcs_credit_points_policy b ON a.cbcs_curriculam_policy_id=b.id AND b.id=(
      SELECT MAX(id)
      FROM cbcs_credit_points_policy
      WHERE course_id='jrf')
      WHERE a.course_id='jrf' AND a.sem='2' AND a.course_component LIKE '%/%')z";
      // echo $extraComponentsql;
      // exit;
      $extraComponent =  DB::select($extraComponentsql);

      $data['extraComponent'] = array_merge($core_courses, $extraComponent);

      $dept_id_ug_filter = "";
      if ($dept_id_ug) {
        $dept_id_ug_filter = " and a.dept_id='$dept_id_ug'";
      }


      $uglevelsql = "SELECT REGEXP_REPLACE(z.sub_category, '[^a-zA-Z\-]', '') AS course_component,1 AS pre_requisite_pass,0 AS isSelected, 0 AS isClicked, z.sub_offered_id, z.id,z.session_year,z.session,z.dept_id,z.course_id,z.branch_id,z.semester
      ,z.unique_sub_pool_id,z.unique_sub_id,z.sub_name,z.sub_code,z.lecture,z.tutorial,z.practical,z.credit_hours,z.contact_hours,z.sub_type,z.pre_requisite
      ,z.pre_requisite_subcode,z.no_of_subjects,z.sub_category,z.sub_group,z.criteria,z.pre_requisite
       FROM (SELECT z.*,CONCAT('c',z.id) AS sub_offered_id,'' AS extra FROM (SELECT REGEXP_REPLACE(a.sub_code, '[^0-9]', '') AS s_code, SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1) AS c_level,a.*
      FROM cbcs_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session' and a.sub_code <> 'NA' $dept_id_ug_filter AND a.course_id IN ('b.tech','int.m.tech','dualdegree') AND a.sub_type='Theory' )z
      WHERE z.c_level=4
      UNION
      SELECT z.*,CONCAT('c',z.id) AS sub_offered_id FROM (SELECT REGEXP_REPLACE(a.sub_code, '[^0-9]', '') AS s_code, SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1) AS c_level,a.*
      FROM old_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session' and a.sub_code <> 'NA' $dept_id_ug_filter AND a.course_id IN ('b.tech','int.m.tech','dualdegree') AND a.sub_type='Theory')z
      WHERE z.c_level=4)z 	ORDER BY z.sub_name ";

      // echo $uglevelsql;
      // exit;

      $ug_courses =  DB::select($uglevelsql);
      $ugpassedCourses = array();
      $subj = join("', '", explode(',', implode(",", $final_courses)));
      foreach ($ug_courses as $key => $value) {
        $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
        if ($tt) {
          array_push($timeTable, $tt);
        }
        $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $session_year, $session, $course_id, 'ESO');
        if ($tt_clash) {
          unset($ug_courses[$key]);
          array_push($ttclashCourses, $value);
        } else {

          $CheckAlreadyPassed = $this->CheckAlreadyPassedByCode($admn_no, $value->sub_code);
          if ($CheckAlreadyPassed) {
            array_push($ugpassedCourses, $value);
            unset($ug_courses[$key]);
          } else {

            if ($value->pre_requisite == 'yes') {
              $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
              if (!$checkforpre_requisite) {
                $ug_courses[$key]->pre_requisite_pass = 0;
              }
            }
          }
        }
      }
      $data['ug_passed_courses'] = $ugpassedCourses;
      $data['ug_level_courses'] = $ug_courses;
      $data['ug_level_courses_cnt'] = count($ug_courses);

      $dept_id_pg_filter = "";
      if ($dept_id_pg) {
        $dept_id_pg_filter = " and a.dept_id='$dept_id_pg'";
      }
      $pg_level_sql = "SELECT REGEXP_REPLACE(z.sub_category, '[^a-zA-Z\-]', '') AS course_component,1 AS pre_requisite_pass, 0 AS isSelected, 0
      AS isClicked, FALSE AS isClicked, z.sub_offered_id,
      z.id,z.session_year,z.session,z.dept_id,z.course_id,z.branch_id,z.semester
      ,z.unique_sub_pool_id,z.unique_sub_id,z.sub_name,z.sub_code,z.lecture,z.tutorial,z.practical,z.credit_hours,z.contact_hours,z.sub_type,z.pre_requisite
      ,z.pre_requisite_subcode,z.no_of_subjects,z.sub_category,z.sub_group,z.criteria,z.pre_requisite
      FROM (
      SELECT z.*, CONCAT('c',z.id) AS sub_offered_id,'' AS extra
      FROM (
      SELECT a.*, SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1) AS c_level
      FROM cbcs_subject_offered a
      WHERE a.session_year='$session_year' AND a.`session`='$session' and a.sub_code <> 'NA' $dept_id_pg_filter AND a.course_id NOT IN
        ('jrf') AND a.sub_type='Theory' AND SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1)='5')z UNION
        SELECT z.*, CONCAT('c',z.id) AS sub_offered_id
        FROM (
        SELECT a.*, SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1) AS c_level
        FROM old_subject_offered a
        WHERE a.session_year='$session_year' AND a.`session`='$session' and a.sub_code <> 'NA' $dept_id_pg_filter AND a.course_id NOT IN
          ('jrf') AND a.sub_type='Theory' AND SUBSTRING(REGEXP_REPLACE(a.sub_code, '[^0-9]', ''), 1, 1)='5')z
          )z
          ORDER BY z.sub_name ";
      // echo $pg_level_sql;
      // exit;
      $pg_level_courses =  DB::select($pg_level_sql);
      foreach ($pg_level_courses as $key => $value) {
        $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
        if ($tt) {
          array_push($timeTable, $tt);
        }
        $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $session_year, $session, $course_id, 'ESO');
        if ($tt_clash) {
          unset($pg_level_courses[$key]);
          array_push($ttclashCourses, $value);
        } else {

          $CheckAlreadyPassed = $this->CheckAlreadyPassedByCode($admn_no, $value->sub_code);
          if ($CheckAlreadyPassed) {
            array_push($ugpassedCourses, $value);
            unset($pg_level_courses[$key]);
          } else {

            if ($value->pre_requisite == 'yes') {
              $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
              if (!$checkforpre_requisite) {
                $pg_level_courses[$key]->pre_requisite_pass = 0;
              }
            }
          }
        }
      }
      $data['pg_level_courses'] = $pg_level_courses;
      $data['pg_level_courses_cnt'] = count($pg_level_courses);
      // $core_courses = array_merge($core_courses, $ug_courses);
    }


    $data['core_courses'] = $core_courses;
    $course_course_all =  array_merge($course_course_all, $core_courses);
    //  array_push($allcourses, $core_courses);
    $data['core_courses_cnt'] = count($core_courses);

    if ($semester >= 3 && strtoupper($course_id) == 'JRF') {
      $tusql = "SELECT CONCAT('c',b.id) AS sub_offered_id,b.pre_requisite,b.pre_requisite_subcode,1 AS pre_requisite_pass,null as
      tu_div_value,'core' as cat,0 as 'isSelected', 0 AS isClicked,a.credit_hours as sub_category_cbcs_offered,b.* FROM cbcs_thesis_offered a
      JOIN
      cbcs_subject_offered b ON a.dept_id=b.dept_id WHERE a.dept_id='$dept_id' AND b.session_year='$session_year' AND a.sub_code=b.sub_code
      AND b.session='$session' and (case when $semester > 8 then 8 else b.semester=$semester end) ORDER BY sub_offered_id ASC LIMIT 1";
    } else {
      $tusql = "SELECT p.status, p.eso_type AS course_component,1 AS pre_requisite_pass,c.credit_hours as sub_category_cbcs_offered ,c.*, CONCAT('c',c.id) AS sub_offered_id,'core' as cat,0 as 'isSelected', 0 AS isClicked
      FROM(SELECT p.*, CONCAT(UPPER(p.course_component),p.sequence) AS eso_type FROM cbcs_coursestructure_policy p WHERE p.course_id='$course_id'
      AND p.sem='$semester' AND p.course_component LIKE '%TU%' AND p.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)p LEFT JOIN
      cbcs_subject_offered c ON c.semester=p.sem AND c.course_id=p.course_id AND (CASE WHEN c.unique_sub_pool_id NOT IN('NA','') THEN
      CONCAT(UPPER(p.course_component),p.sequence)=c.unique_sub_pool_id ELSE c.sub_category= CONCAT(UPPER(p.course_component),p.sequence) END)
      AND c.session_year='$session_year' AND c.session='$session' AND c.semester='$semester' AND c.course_id='$course_id'
       AND c.branch_id='$branch_id' HAVING
      (c.sub_category LIKE 'TU%' OR c.sub_category IS NULL)";
    }
    // echo $tusql;
    // exit;

    $tupaper = DB::select($tusql);
    $data['tu_courses'] = $tupaper;
    $data['tu_courses_cnt'] = count($tupaper);
    $course_course_all = array_merge($course_course_all, $tupaper);


    $trsql = "SELECT p.status, p.eso_type AS course_component,1 AS pre_requisite_pass, c.*, CONCAT('c',c.id) AS sub_offered_id,'core' as cat,0 as 'isSelected', 0 AS isClicked
    FROM(SELECT p.*, CONCAT(UPPER(p.course_component),p.sequence) AS eso_type FROM cbcs_coursestructure_policy p WHERE p.course_id='$course_id' AND
    p.sem='$semester' AND p.course_component LIKE '%TR%' AND p.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)p JOIN cbcs_subject_offered c ON c.semester=p.sem AND c.course_id=p.course_id AND
    (CASE WHEN c.unique_sub_pool_id NOT IN('NA','') THEN CONCAT(UPPER(p.course_component),p.sequence)=c.unique_sub_pool_id ELSE
    c.sub_category= CONCAT(UPPER(p.course_component),p.sequence) END) AND c.session_year='$session_year' AND c.session='$session' AND c.semester='$semester' AND
    c.course_id='$course_id' AND c.branch_id='$branch_id' HAVING (c.sub_category LIKE 'TR%' OR c.sub_category IS NULL)";

    $trpaper = DB::select($trsql);
    $data['tr_courses'] = $trpaper;
    $data['tr_courses_cnt'] = count($trpaper);
    $course_course_all = array_merge($course_course_all, $trpaper);


    $tupcntsql = "SELECT * FROM (SELECT CONCAT(a.course_component,a.sequence) AS tpu_type,
    a.status FROM  cbcs_coursestructure_policy a WHERE a.course_id='$course_id' AND a.sem='$semester' AND a.course_component='TPU'
    AND a.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id UNION
    SELECT b.sub_category,NULL AS status FROM cbcs_subject_offered b  WHERE b.session_year='$session_year' AND b.session='$session' AND
    b.semester='$semester' AND b.course_id='$course_id' AND b.branch_id='$branch_id' AND b.sub_category LIKE 'TPU%') X GROUP BY  X.tpu_type ORDER BY X.tpu_type";

    $tpucntpaper = DB::select($tupcntsql);

    $tupsql = "SELECT CONCAT('c',a.id) AS
    sub_offered_id,CONCAT('c',a.id) AS
    id,a.semester,a.sub_name,a.sub_code,a.lecture,a.tutorial,a.practical,a.sub_type,a.pre_requisite,a.pre_requisite_subcode, 1 AS pre_requisite_pass,a.sub_type,a.sub_category,a.sub_category
    AS sub_cat,'core' as cat,0 as 'isSelected', 0 AS isClicked FROM cbcs_subject_offered a LEFT JOIN
    cbcs_coursestructure_policy b ON a.course_id=b.course_id AND
    a.semester=b.sem AND if(a.unique_sub_pool_id IN ('NA',''), CONCAT
    (b.course_component,b.sequence)=a.sub_category,a.unique_sub_pool_id=
    CONCAT(b.course_component,b.sequence) AND b.cbcs_curriculam_policy_id=$cbcs_curriculam_policy_id)
    WHERE a.session_year='$session_year' AND
    a.session='$session' AND a.semester='$semester' AND a.course_id='$course_id' AND a.branch_id='$branch_id' AND
    a.sub_category LIKE 'TPU%' GROUP BY a.id ORDER BY a.sub_category;";

    $tpu_courses = $tpucntpaper = DB::select($tupsql);

    $data['tpu_courses_cnt'] = count($tpucntpaper);
    $data['tpu_courses'] = $tpu_courses;
    $course_course_all = array_merge($course_course_all, $tpu_courses);

    // print_r($course_course_all);
    // exit;



    foreach ($course_course_all as $key => $value) {
      $tt = $this->getTimeTable($session_year, $session, $value->sub_offered_id);
      if ($tt) {
        array_push($timeTable, $tt);
      }
      if ($value->pre_requisite == 'yes') {
        $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
        if (!$checkforpre_requisite) {
          $course_course_all[$key]->pre_requisite_pass = 0;
        }
      }
    }
    $data['timeTable'] = $timeTable;


    $data['all_core_courses'] = $course_course_all;
    if ($flag) {
      return $course_course_all;
    }
    // exit;
    return $this->sendResponse($data, "Core Courses");
  }

  function getCurriculamPolicy($session_year, $session, $course_id, $semester)
  {

    if ($semester <= 8 || $course_id == 'int.m.tech') {
      $sql = "SELECT a.cbcs_curriculam_policy_id
    FROM cbcs_coursestructure_policy a
    JOIN cbcs_subject_offered b ON
     a.course_id=b.course_id AND a.sem=b.semester AND a.cbcs_curriculam_policy_id=b.minstu
    WHERE a.course_id='$course_id' AND a.sem='$semester' AND
     b.session_year='$session_year' AND b.session='$session'
    GROUP BY a.id
    ORDER BY a.cbcs_curriculam_policy_id DESC
    LIMIT 1";
      // echo $sql;
      // exit;
      $cp =  DB::select($sql);

      // print_r($cp);
      // exit;
      if ($cp) {
        $cbcs_curriculam_policy_id = $cp[0]->cbcs_curriculam_policy_id;
      } else {
        return false;
      }
    } else {
      $cbcs_curriculam_policy_id = 14;
    }
    return $cbcs_curriculam_policy_id;
  }

  function getPreRegDetails(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Invalid Session Year or Session !');
    }
    $stu_info = $this->GetStudentInfo();

    $session_year = $request->session_year;
    $session = $request->session;
    $admn_no = Auth::user()->id;

    $this->saveRegistationLog($admn_no, $session_year, $session, "Downloaded Pre-registration Receipt.");

    $reg_info = DB::table('reg_regular_form')
      ->join('pre_stu_course', 'pre_stu_course.form_id', 'reg_regular_form.form_id')
      ->join('reg_regular_fee', 'reg_regular_fee.form_id', 'reg_regular_form.form_id')
      ->join('users', function ($join) {
        $join->on('reg_regular_form.admn_no', '=', 'users.id')
          ->where('users.status', 'A');
      })
      ->where('reg_regular_form.admn_no', $admn_no)
      ->where('reg_regular_form.session_year', $session_year)
      ->where('reg_regular_form.session', $session)
      ->count();

    // echo $reg_info;
    if ($reg_info > 0) {


      // $data['stu_details'] = DB::table('reg_regular_form')
      // ->join('pre_stu_course', 'pre_stu_course.form_id', 'reg_regular_form.form_id')
      // ->join('reg_regular_fee', 'reg_regular_fee.form_id', 'reg_regular_form.form_id')
      // ->join('users', function ($join) {
      //   $join->on('reg_regular_form.admn_no', '=', 'users.id')
      //     ->where('users.status', 'A');
      // })
      // ->select ('reg_regular_form.*')
      // ->where('reg_regular_form.admn_no', $admn_no)
      // ->where('reg_regular_form.session_year', $session_year)
      // ->where('reg_regular_form.session', $session)
      // ->groupBy('reg_regular_form.admn_no')
      // ->get();

      $users_info = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
      FROM(
      SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name
      ,if(a.sex='m','Male','Female') AS gender,a.photopath,c.signpath,a.dept_id,b.course_id,b.branch_id,d.semester AS current_sem,d.session_year,d.`session`,d.form_id,d.hod_time
      FROM user_details a
      INNER JOIN stu_academic b ON a.id=b.admn_no
      INNER JOIN reg_regular_form d ON a.id=d.admn_no
      AND d.session_year='$session_year' and d.session='$session'
      LEFT JOIN stu_prev_certificate c ON a.id=c.admn_no
      WHERE a.id='$admn_no'
      GROUP BY a.id
      )z
      INNER JOIN cbcs_departments c ON z.dept_id=c.id
      INNER JOIN cbcs_courses d ON z.course_id=d.id
      INNER JOIN cbcs_branches e ON z.branch_id=e.id");



      $data['stu_details'] = $users_info;

      $sql = "SELECT
      p.id,p.sub_offered_id,p.subject_code,p.course_aggr_id,p.subject_name,p.sub_category_cbcs_offered,p.session_year,p.session,(CASE
      WHEN (p.sub_category LIKE 'DC%' || p.sub_category LIKE 'DP%') THEN
      p.sub_category/*SUBSTRING(p.sub_category,1,2)*/ ELSE p.sub_category END) AS
      sub_category,p.priority,k.* FROM
      (SELECT
      rrg.form_id,rrg.admn_no,rrg.course_id,rrg.branch_id,rrg.semester,rrg.session_year,rrg.session,rrg.hod_time AS reg_time
      FROM reg_regular_form rrg
      INNER JOIN users u ON
       u.id=rrg.admn_no WHERE rrg.admn_no='$admn_no' AND rrg.session_year='$session_year' AND
       rrg.session='$session' AND rrg.acad_status='1' AND rrg.hod_status='1' AND
       u.status='A' GROUP BY rrg.admn_no,rrg.form_id DESC LIMIT 1)k INNER
       JOIN pre_stu_course p ON p.form_id=k.form_id ORDER BY
       p.sub_category,p.priority";
      // echo $sql;
      // exit;
      $data['reg_details'] = $reg_details = DB::select($sql);
      if (count($reg_details) == 0) {
        return $this->sendError('Record Not Found !', 'Registration details not found for ' . $session_year . " - " . $session);
      }

      $form_id = $users_info[0]->form_id;
      $data['drop_courses'] = DB::select("SELECT *,if(a.sub_id = 'NA',a.course_aggr_id,a.sub_id) AS dropped_as FROM stu_exam_absent_mark a WHERE a.form_id='$form_id' ");



      return $this->sendResponse($data, "Pre-Registation Details");
    } else {
      return $this->sendError('Not Found', 'Registration details not found for ' . $session_year . " - " . $session);
    }
  }

  function DropBacklogCourses(Request $request, $flag = false, $type = null)
  {
    $validator = Validator::make($request->all(), [
      'session_year' => 'required|string',
      'session' => 'required|string',
    ]);
    if ($validator->fails()) {
      return $this->sendError('Invalid Request !', 'Invalid Request !');
    }
    $stu_info = $this->GetStudentInfo();

    $sessionYear = isset($request->session_year) ? $request->session_year : $stu_info[0]->session_year;
    $session = isset($request->session) ? $request->session : $stu_info[0]->session;


    $semester = $stu_info[0]->current_sem + 1;
    $course_id = $stu_info[0]->course_id;
    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;
    $admn_no = Auth::user()->id;

    $this->saveRegistationLog($admn_no, $sessionYear, $session, "Backlog/Drop Course Selection Started.");

    $failedEso = array();

    $currentSemDetails = DB::select("SELECT z.*,c.name AS dept_name,d.name AS course_name,e.name AS branch_name
    FROM(
    SELECT a.id AS admn_no, CONCAT_WS(' ',a.first_name,a.middle_name,a.last_name) AS stu_name,c.form_id,c.semester,c.session_year,c.`session`
    ,if(a.sex='m','Male','Female') AS gender,a.photopath,a.dept_id,b.course_id,b.branch_id,b.semester AS current_sem
    FROM user_details a
    INNER JOIN stu_academic b ON a.id=b.admn_no
    INNER JOIN reg_regular_form c ON a.id=c.admn_no AND c.hod_status='1' AND c.acad_status='1' AND c.`status`='1'
    INNER JOIN users u ON a.id=u.id AND u.`status`='A'
    WHERE a.id='$admn_no' ORDER BY c.form_id DESC LIMIT 1)z
    INNER JOIN cbcs_departments c ON z.dept_id=c.id
    INNER JOIN cbcs_courses d ON z.course_id=d.id
    INNER JOIN cbcs_branches e ON z.branch_id=e.id");
    //  $currentSemDetails[0]->session_year;




    $sem_str = ("$session" == 'Monsoon') ? " and  a.session_yr<='$sessionYear' and !(a.session_yr='$sessionYear' and  (a.session='Winter'  or a.session='Summer'))"
      : (("$session" == 'Winter') ? " and  a.session_yr<='$sessionYear'	and !( a.session_yr='$sessionYear' and   a.session='Summer' )" : "and a.session_yr<='$sessionYear' )");

    $sql = "SELECT 'backdrop' as cat,'not_offered' AS check_offered,'N' AS currstatus,v.*, IFNULL(cso.id,oso.id) AS sub_offered_id,
    IF(v.alternate_sub_code=oso.sub_code, CONCAT('o',oso.id),IF(v.alternate_sub_code=cso.sub_code, CONCAT('c',cso.id),'')) AS sub_offered_id,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.sub_code ELSE cso.sub_code END) IS NULL,s.subject_id,
    (CASE WHEN cso.sub_code IS NULL THEN oso.sub_code ELSE cso.sub_code END)) AS subcode,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.sub_name ELSE cso.sub_name END) IS NULL,s.name,
    (CASE WHEN cso.sub_code IS NULL THEN oso.sub_name ELSE cso.sub_name END)) AS sub_name,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.lecture ELSE cso.lecture END) IS NULL, s.lecture,
    (CASE WHEN cso.sub_code IS NULL THEN oso.lecture ELSE cso.lecture END)) AS lecture,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.practical ELSE cso.practical END) IS NULL, s.practical,
    (CASE WHEN cso.sub_code IS NULL THEN oso.practical ELSE cso.practical END)) AS practical,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.tutorial ELSE cso.tutorial END) IS NULL, s.tutorial,
    (CASE WHEN cso.sub_code IS NULL THEN oso.tutorial ELSE cso.tutorial END)) AS tutorial,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.sub_type ELSE cso.sub_type END) IS NULL, s.type,
    (CASE WHEN cso.sub_code IS NULL THEN oso.sub_type ELSE cso.sub_type END)) AS sub_type,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.credit_hours ELSE cso.credit_hours END) IS NULL, s.credit_hours,
    (CASE WHEN cso.sub_code IS NULL THEN oso.credit_hours ELSE cso.credit_hours END)) AS credit_hours,
    IF((CASE WHEN cso.sub_code IS NULL THEN oso.contact_hours ELSE cso.contact_hours END) IS NULL, s.contact_hours,
    (CASE WHEN cso.sub_code IS NULL THEN oso.contact_hours ELSE cso.contact_hours END)) AS contact_hours,
    IF(v.alternate_sub_code=oso.sub_code, oso.sub_category,
    IF(v.alternate_sub_code=cso.sub_code, cso.sub_category,'')) AS sub_category,IF(v.alternate_sub_code=oso.sub_code, oso.sub_category,
IF(v.alternate_sub_code=cso.sub_code, cso.sub_category,'')) AS final_component, REGEXP_SUBSTR(SUBSTRING_INDEX(cso.sub_category, '/',
1),'[A-Za-z]+') AS course_component,'backdrop' as cat,0 as 'isSelected', 0 AS isClicked
    FROM
    (
    SELECT *
    FROM(
    SELECT *
    FROM(
    SELECT v.*
    FROM(
    SELECT y.session_yr, y.session, y.dept, y.course,
    y.branch, y.semester, fd.mis_sub_id,/*fd.mis_sub_id as final_component,REGEXP_SUBSTR(SUBSTRING_INDEX(fd.mis_sub_id, '/', 1),'[A-Za-z]+') AS course_component, */fd.sub_code, fd.grade, y.admn_no,
    IF(ac.alternate_subject_code IS NOT NULL,ac.old_subject_code,
    IF(acl.alternate_subject_code IS NOT NULL, acl.old_subject_code, fd.sub_code)) AS newsub,
    IF(ac.alternate_subject_code IS NOT NULL,ac.alternate_subject_code,
    IF(acl.alternate_subject_code IS NOT NULL, acl.alternate_subject_code, fd.sub_code)) AS alternate_sub_code
    FROM (
    SELECT x.*
    FROM
    (
    SELECT a.hstatus, a.session_yr, a.session, a.admn_no, a.dept, a.course, a.branch, a.semester, a.id,
    a.status,a.ctotcrpts,
    a.ctotcrhr, a.core_ctotcrpts, a.core_ctotcrhr, a.tot_cr_hr, a.tot_cr_pts, a.core_tot_cr_hr, a.core_tot_cr_pts,
    a.published_on, a.actual_published_on,a.exam_type
    FROM final_semwise_marks_foil_freezed AS a
    WHERE a.admn_no = '$admn_no' AND a.actual_published_on IS NOT NULL AND UPPER(a.course) <> 'MINOR' AND (a.semester != '0' AND a.semester != '-1') AND a.course <> 'jrf' AND a.session_yr<='$sessionYear' AND
          !(a.session_yr='$sessionYear' AND (a.session='Winter' OR a.session='Summer'))
    ORDER BY a.admn_no,a.semester,
          a.actual_published_on DESC
    LIMIT 100000000)x
    GROUP BY x.admn_no, x.semester, IF(x.session_yr>= '2019-2020',
          x.session_yr, NULL), IF(x.session_yr >= '2019-2020', x.session, NULL)
    ORDER BY x.admn_no,x.semester, x.actual_published_on DESC
    LIMIT 100000000) y
    JOIN final_semwise_marks_foil_desc_freezed fd ON fd.foil_id = y.id AND fd.admn_no = y.admn_no AND
          (CASE WHEN y.session = 'Summer' THEN fd.current_exam = 'Y' WHEN y.session = 'Winter' AND y.exam_type != 'R' THEN fd.current_exam = 'Y' WHEN y.session = 'Monsoon' AND y.exam_type != 'R' THEN fd.current_exam = 'Y' ELSE
          1=1 END)
    LEFT JOIN alternate_course ac ON ac.admn_no = y.admn_no AND ac.old_subject_code = fd.sub_code
    LEFT JOIN alternate_course_all acl ON acl.old_subject_code = fd.sub_code
    ORDER BY y.admn_no,newsub, fd.cr_pts DESC, y.session_yr DESC
    LIMIT 10000000)v
    GROUP BY v.newsub
    HAVING v.grade in ('I', 'F')
    ORDER BY v.admn_no, v.session_yr, v.dept, v.course, v.branch,
          v.semester,v.newsub
    LIMIT 10000000)v
    WHERE v.alternate_sub_code NOT IN (
    SELECT fd.sub_code
    FROM (
    SELECT *
    FROM final_semwise_marks_foil_freezed a
    WHERE a.admn_no ='$admn_no' AND a.actual_published_on IS NOT NULL AND UPPER(a.course) <> 'MINOR' AND (a.semester != '0' AND a.semester != '-1') AND a.course <> 'jrf' AND
              a.session_yr<='$sessionYear'
    ORDER BY a.admn_no, a.semester, a.actual_published_on DESC
    LIMIT
                100000000)k
    JOIN final_semwise_marks_foil_desc_freezed fd ON fd.foil_id=k.id AND
                fd.admn_no=k.admn_no AND fd.grade NOT IN ('F','I')
    GROUP BY fd.sub_code UNION
    SELECT
                s.subject_code AS sub_code
    FROM stu_waive_off_course s
    WHERE s.admn_no='$admn_no' UNION
    SELECT
                b.old_subject_code
    FROM (
    SELECT b.*
    FROM alternate_course b
    WHERE b.admn_no='$admn_no') b
    JOIN
                final_semwise_marks_foil_desc_freezed a ON a.admn_no='$admn_no' AND a.grade NOT IN ('F','I') AND
                b.alternate_subject_code=a.sub_code)
    GROUP BY v.alternate_sub_code)v
    WHERE v.alternate_sub_code NOT IN (
    SELECT fd.sub_code
    FROM
          (
    SELECT *
    FROM final_semwise_marks_foil_freezed a
    WHERE a.admn_no ='$admn_no' AND a.actual_published_on IS NOT NULL AND UPPER(a.course) <> 'MINOR' AND (a.semester != '0' AND a.semester != '-1') AND a.course <>
              'jrf' AND a.session_yr<='$sessionYear'
    ORDER BY a.admn_no, a.semester, a.actual_published_on DESC
    LIMIT
                100000000) k
    JOIN final_semwise_marks_foil_desc_freezed fd ON fd.foil_id=k.id AND
                fd.admn_no=k.admn_no AND fd.grade NOT IN ('F','I')
    GROUP BY fd.sub_code))v
    LEFT JOIN
                cbcs_subject_offered cso ON cso.sub_code=v.alternate_sub_code AND v.course=cso.course_id AND(CASE WHEN v.course <> 'comm' THEN v.branch = cso.branch_id ELSE 1 = 1 END)
                /*AND v.session_yr = cso.session_year AND v.session = cso.session*/
    LEFT JOIN old_subject_offered oso ON oso.sub_code = v.sub_code AND v.course = oso.course_id AND v.branch = oso.branch_id /*AND v.session_yr = oso.session_year AND v.session = oso.session*/
    LEFT JOIN subjects s ON (CASE WHEN v.mis_sub_id IS NOT NULL THEN s.id WHEN v.mis_sub_id IS NULL AND(cso.sub_code IS NULL AND oso.sub_code IS NULL) THEN s.subject_id END) = (CASE WHEN
                v.mis_sub_id IS NOT NULL THEN v.mis_sub_id WHEN v.mis_sub_id IS NULL AND(cso.sub_code IS NULL AND oso.sub_code IS NULL) THEN v.sub_code END)
    GROUP BY
                alternate_sub_code";

    // echo $sql;
    // exit;

    $backlog = DB::select($sql);
    if ($course_id == 'jrf') {
      $backlog = array();
    }
    $data['backlog_cnt'] = count($backlog);

    $sem_str2 = ("$session" == 'Monsoon') ? " and  rg.session_year<='$sessionYear' and !(rg.session_year='$sessionYear' and  (rg.session='Winter'  or rg.session='Summer') )"
      : (("$session" == 'Winter') ? " and  rg.session_year<='$sessionYear'	and !( rg.session_year='$sessionYear' and   rg.session='Summer' )"
        : " and rg.session_year<='${sessionYear}' ");

    $backtimeTable = array();
    $timeTable = array();
    foreach ($backlog as $key => $value) {
      $subcode = $value->subcode;
      $sub_category = $value->sub_category;

      $currentstudying = $this->CheckForCurrentStudying($sessionYear, $session, $admn_no, $subcode, $sem_str2);

      if ($currentstudying) {
        $backlog[$key]->currstatus = 'Y';
      }

      $CheckAlreadyPassedByCategory = $this->CheckAlreadyPassedByCategory($admn_no, $value->final_component);
      if ($CheckAlreadyPassedByCategory) {
        $backlog[$key]->currstatus = 'Y';
      }
      $isEso = (($value->course_component == 'ESO') || ($value->course_component == 'DE') || ($value->course_component == 'OE')) ? true : false;

      if (!$isEso) {
        $checkforoffer = $this->checkForOffered($sessionYear, $session, $subcode);
        if ($checkforoffer) {
          $backlog[$key]->check_offered = 'offered';
          $backlog[$key]->sub_offered_id = $checkforoffer[0]->sub_offered_id;
          $backlog[$key]->sub_name = $checkforoffer[0]->sub_name;
          $backlog[$key]->sub_category = ($checkforoffer[0]->unique_sub_pool_id != 'NA' || $checkforoffer[0]->unique_sub_pool_id != 'NA') ? $checkforoffer[0]->unique_sub_pool_id : $checkforoffer[0]->sub_category;
        }
      } else {
        $backlog[$key]->check_offered = 'offered';
      }

      $tt = $this->getTimeTable($sessionYear, $session, $value->sub_offered_id);
      if ($tt) {
        array_push($backtimeTable, $tt);
        array_push($timeTable, $tt);
      }
      if ($flag) {
        if (strpos(strtoupper($sub_category), strtoupper($type)) !== false) {
          array_push($failedEso, $value);
        }
      }
    }

    $data['backlogTT'] = $backtimeTable;
    $data['backlog'] = $backlog;

    $sqlDrop = "SELECT 'backdrop' as cat, 0 as 'isSelected', 0 AS isClicked, 'not_offered' AS check_offered,'N' as currstatus,z.*,IF(ac.alternate_subject_code IS NOT NULL, ac.alternate_subject_code, IF(acl.alternate_subject_code IS NOT NULL, acl.alternate_subject_code, z.sub_code)) AS sub_code,IF(ac.alternate_subject_code IS NOT NULL, ac.alternate_subject_name, IF(acl.alternate_subject_code IS NOT NULL, acl.alternate_subject_name, z.sub_name)) AS sub_name FROM (SELECT *
    FROM (
    SELECT z.*,cs.sub_offered_id,cs.subject_code,cs.subject_name,cs.sub_category AS stu_sub_category
    FROM (
    SELECT *
    FROM (
    SELECT z.*,co.sub_name,co.sub_code,co.sub_category,co.dept_id AS dept_id_offer,co.course_id AS course_id_offer,co.branch_id AS branch_id_offer,
    co.pre_requisite,co.pre_requisite_subcode,co.sub_type,co.lecture,co.tutorial,co.practical
    FROM (
    SELECT *
    FROM (
    SELECT a.admn_no,a.form_id,(CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug' ) THEN 'comm' ELSE a.course_id END) AS course_id,
    (CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.branch_id END) AS branch_id,a.semester,a.session_year,a.`session`,b.dept_id,
    (CASE WHEN c.section IN ('A','B','C','D') THEN 1 ELSE 2 END) AS stu_group
    FROM reg_regular_form a
    INNER JOIN user_details b ON a.admn_no=b.id
    INNER JOIN stu_academic d ON a.admn_no=d.admn_no
    left JOIN stu_section_data c ON a.admn_no=c.admn_no
    WHERE a.admn_no='$admn_no' and a.status<>'0')z
    INNER JOIN (
    SELECT a.course_id AS course_idp,a.sem,a.cbcs_curriculam_policy_id,a.course_component,a.sequence,a.`status`,(CASE WHEN a.course_component LIKE '%/%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'/',1), SUBSTRING_INDEX(a.sequence,'/',1)),'/', CONCAT(SUBSTRING_INDEX(a.course_component,'/',-1), SUBSTRING_INDEX(a.sequence,'/',-1))) WHEN a.course_component LIKE '%+%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'+',1), SUBSTRING_INDEX(a.sequence,'+',1)),'+', CONCAT(SUBSTRING_INDEX(a.course_component,'+',-1), SUBSTRING_INDEX(a.sequence,'+',-1))) ELSE CONCAT(a.course_component,a.sequence) END) AS final_component
    FROM cbcs_coursestructure_policy a  WHERE a.cbcs_curriculam_policy_id=(SELECT MAX(cbcs_curriculam_policy_id) FROM cbcs_coursestructure_policy WHERE course_id='$course_id'))a ON a.course_idp=z.course_id AND a.sem=z.semester /*(case when a.course_component LIKE '%ESO%' then 1=1 else a.sem=z.semester END) */

    UNION

    SELECT *
    FROM (
    SELECT a.admn_no,a.form_id,(CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.course_id END) AS course_id,
    (CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.branch_id END) AS branch_id,a.semester,a.session_year,a.`session`,b.dept_id,
    (CASE WHEN c.section IN ('A','B','C','D') THEN 1 ELSE 2 END) AS stu_group
    FROM reg_regular_form a
    INNER JOIN user_details b ON a.admn_no=b.id
    INNER JOIN stu_academic d ON a.admn_no=d.admn_no
    left JOIN stu_section_data c ON a.admn_no=c.admn_no
    WHERE a.admn_no='$admn_no' and a.status<>'0')z
    INNER JOIN (
    SELECT a.course_id AS course_idp,a.sem,a.cbcs_curriculam_policy_id,a.course_component,a.sequence,a.`status`,(CASE WHEN a.course_component LIKE '%/%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'/',1), SUBSTRING_INDEX(a.sequence,'/',1)),'/', CONCAT(SUBSTRING_INDEX(a.course_component,'/',-1), SUBSTRING_INDEX(a.sequence,'/',-1))) WHEN a.course_component LIKE '%+%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'+',1), SUBSTRING_INDEX(a.sequence,'+',1)),'+', CONCAT(SUBSTRING_INDEX(a.course_component,'+',-1), SUBSTRING_INDEX(a.sequence,'+',-1))) ELSE CONCAT(a.course_component,a.sequence) END) AS final_component
    FROM cbcs_comm_coursestructure_policy a)a ON a.course_idp=z.course_id AND (case when (a.course_component LIKE 'ESO%' OR a.course_component LIKE 'OE%')

    then 1=1 else a.sem=z.semester END)  /*AND a.cbcs_curriculam_policy_id=z.stu_group*/)z
    INNER JOIN cbcs_subject_offered co ON co.course_id=z.course_id AND co.`session` <> 'summer'
    /*AND co.branch_id=z.branch_id */

    AND (case when (z.final_component LIKE 'ESO%' OR z.final_component LIKE 'OE%') then 1=1 else co.branch_id=z.branch_id end)

    AND z.session=co.`session` AND z.session_year=co.session_year AND co.sub_category=z.final_component
    AND (CASE WHEN co.sub_group <> 0 THEN z.stu_group=co.sub_group ELSE 1=1 END))z
    GROUP BY z.sub_code,z.sub_category


    UNION
    SELECT *
    FROM (
    SELECT z.*,co.sub_name,co.sub_code,co.sub_category,co.dept_id AS dept_id_offer,co.course_id AS course_id_offer,co.branch_id AS branch_id_offer,
    co.pre_requisite,co.pre_requisite_subcode,co.sub_type,co.lecture,co.tutorial,co.practical
    FROM (
    SELECT *
    FROM (
    SELECT a.admn_no,a.form_id,(CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.course_id END) AS course_id,
    (CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.branch_id END) AS branch_id,a.semester,a.session_year,a.`session`,b.dept_id,
    (CASE WHEN c.section IN ('A','B','C','D') THEN 1 ELSE 2 END) AS stu_group
    FROM reg_regular_form a
    INNER JOIN user_details b ON a.admn_no=b.id
    INNER JOIN stu_academic d ON a.admn_no=d.admn_no
    left JOIN stu_section_data c ON a.admn_no=c.admn_no
    WHERE a.admn_no='$admn_no' and a.status<>'0')z
    inner JOIN (
    SELECT a.course_id AS course_idp,a.sem,a.cbcs_curriculam_policy_id,a.course_component,a.sequence,a.`status`,(CASE WHEN a.course_component LIKE '%/%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'/',1), SUBSTRING_INDEX(a.sequence,'/',1)),'/', CONCAT(SUBSTRING_INDEX(a.course_component,'/',-1), SUBSTRING_INDEX(a.sequence,'/',-1))) WHEN a.course_component LIKE '%+%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'+',1), SUBSTRING_INDEX(a.sequence,'+',1)),'+', CONCAT(SUBSTRING_INDEX(a.course_component,'+',-1), SUBSTRING_INDEX(a.sequence,'+',-1))) ELSE CONCAT(a.course_component,a.sequence) END) AS final_component
    FROM cbcs_coursestructure_policy a WHERE a.cbcs_curriculam_policy_id=(SELECT MAX(cbcs_curriculam_policy_id) FROM cbcs_coursestructure_policy WHERE course_id='$course_id'))a ON a.course_idp=z.course_id AND (case when (a.course_component LIKE 'ESO%' OR a.course_component LIKE 'OE%') then 1=1 else a.sem=z.semester END)
    UNION
    SELECT *
    FROM (
    SELECT a.admn_no,a.form_id,(CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.course_id END) AS course_id,
    (CASE WHEN (a.semester IN (1,2) AND a.course_id <> 'jrf' AND d.auth_id='ug') THEN 'comm' ELSE a.branch_id END) AS branch_id,a.semester,a.session_year,a.`session`,b.dept_id,
    (CASE WHEN c.section IN ('A','B','C','D') THEN 1 ELSE 2 END) AS stu_group
    FROM reg_regular_form a
    INNER JOIN user_details b ON a.admn_no=b.id
    INNER JOIN stu_academic d ON a.admn_no=d.admn_no
    left JOIN stu_section_data c ON a.admn_no=c.admn_no
    WHERE a.admn_no='$admn_no' and a.status<>'0')z
    INNER JOIN (
    SELECT a.course_id AS course_idp,a.sem,a.cbcs_curriculam_policy_id,a.course_component,a.sequence,a.`status`,(CASE WHEN a.course_component LIKE '%/%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'/',1), SUBSTRING_INDEX(a.sequence,'/',1)),'/', CONCAT(SUBSTRING_INDEX(a.course_component,'/',-1), SUBSTRING_INDEX(a.sequence,'/',-1))) WHEN a.course_component LIKE '%+%' THEN CONCAT(CONCAT(SUBSTRING_INDEX(a.course_component,'+',1), SUBSTRING_INDEX(a.sequence,'+',1)),'+', CONCAT(SUBSTRING_INDEX(a.course_component,'+',-1), SUBSTRING_INDEX(a.sequence,'+',-1))) ELSE CONCAT(a.course_component,a.sequence) END) AS final_component
    FROM cbcs_comm_coursestructure_policy a)a ON a.course_idp=z.course_id AND a.sem=z.semester /*AND a.cbcs_curriculam_policy_id=z.stu_group*/)z
    INNER JOIN old_subject_offered co ON co.course_id=z.course_id AND co.`session` <> 'summer'
     /*AND z.dept_id=(case when co.semester IN (1,2) then 1=1 else co.dept_id end)*/
     AND co.branch_id=z.branch_id AND z.session=co.`session` AND z.session_year=co.session_year AND co.sub_category=z.final_component
     AND (CASE WHEN co.sub_group <> 0 THEN z.stu_group=co.sub_group ELSE 1=1 END))z
    GROUP BY z.sub_category)z

    LEFT JOIN
    (SELECT * FROM (SELECT * FROM old_stu_course a
    WHERE a.admn_no='$admn_no'
    UNION
    SELECT * FROM cbcs_stu_course a
    WHERE a.admn_no='$admn_no') z)cs
     ON z.admn_no=cs.admn_no AND cs.sub_category=z.sub_category #AND cs.subject_code=z.sub_code
    )
    z
    WHERE z.stu_sub_category IS NULL AND (z.stu_sub_category,z.admn_no) NOT IN (SELECT t.sub_category,t.admn_no FROM stu_waive_off_course t WHERE t.admn_no=z.admn_no )
    GROUP BY z.final_component)z
    LEFT JOIN alternate_course ac ON ac.admn_no = z.admn_no AND ac.old_subject_code = z.sub_code
    LEFT JOIN alternate_course_all acl ON acl.old_subject_code = z.sub_code";
    // echo $sqlDrop;
    // exit;
    $dropCourses = DB::select($sqlDrop);
    if ($course_id == 'jrf') {
      $dropCourses = array();
    }
    $data['drop_cnt'] = count($dropCourses);

    $core_courses = $this->getCoreCourses($request, true);
    // print_r($core_courses);
    // exit;
    $core_courses_offered_id = array();
    foreach ($core_courses as $key => $value) {
      array_push($core_courses_offered_id, $value->sub_offered_id);
    }

    $dropTT = array();
    $validFail = array();
    $ttclashCourses = array();
    foreach ($dropCourses as $key => $value) {
      $subcode = $value->sub_code;
      $sub_category = $value->sub_category;
      $currentstudying = $this->CheckForCurrentStudying($sessionYear, $session, $admn_no, $subcode, $sem_str2);

      if ($currentstudying) {
        $dropCourses[$key]->currstatus = 'Y';
      }

      $checkforoffer = $this->checkForOffered($sessionYear, $session, $subcode);
      if ($checkforoffer) {
        $dropCourses[$key]->check_offered = 'offered';
        $dropCourses[$key]->sub_offered_id = $checkforoffer[0]->sub_offered_id;
        $dropCourses[$key]->sub_name = $checkforoffer[0]->sub_name;
        $dropCourses[$key]->sub_category = ($checkforoffer[0]->unique_sub_pool_id != 'NA' || $checkforoffer[0]->unique_sub_pool_id != 'NA') ? $checkforoffer[0]->unique_sub_pool_id : $checkforoffer[0]->sub_category;

        $subj = join("', '", explode(',', implode(",", $core_courses_offered_id)));
        $tt_clash = $this->GetTTClash($request, $subj, $value->sub_offered_id, $sessionYear, $session, $course_id, $value->course_component);
        if ($tt_clash) {
          // unset($dropCourses[$key]);
          array_push($ttclashCourses, $value);
        }

        if ($value->pre_requisite == 'yes') {
          $checkforpre_requisite = $this->checkPreRequisite($admn_no, $value->pre_requisite_subcode);
          if (!$checkforpre_requisite) {
            $dropCourses[$key]['pre_requisite_pass'] = 0;
          }
        }
      }
      $isEso = (($value->course_component == 'ESO') || ($value->course_component == 'DE') || ($value->course_component == 'OE')) ? true : false;
      if ($isEso) {
        $dropCourses[$key]->check_offered = 'offered';
      }

      $tt = $this->getTimeTable($sessionYear, $session, $value->sub_offered_id);
      if ($tt) {
        array_push($dropTT, $tt);
        array_push($timeTable, $tt);
      }
      if ($flag) {
        // echo  $sub_category . "<br>" . $type;
        // echo strpos($sub_category, strtoupper("ESO4"));

        if (strpos(strtoupper($sub_category), strtoupper($type)) !== false) {
          array_push($failedEso, $value);
        }
      }
    }
    $data['drop'] = $dropCourses;
    $data['drop_tt'] = $dropTT;
    $data['drop_timeTable_clashed_courses'] = $ttclashCourses;
    $data['timeTable'] = $timeTable;

    if ($flag) {
      return $failedEso;
      exit;
    }

    return $this->sendResponse($data, "Backlog and Drop Course Details !");
  }
  function getMisSession()
  {
    $data['session'] =    GetSession();
    return $this->sendResponse($data, "MIS Session");
  }
  function getMisSessionYear()
  {

    $data['session_year'] =    GetSessionYear();
    return $this->sendResponse($data, "MIS Session Year");
  }

  function getCreditDetails(Request $request)
  {
    $stu_info = $this->GetStudentInfo();
    $semester = $stu_info[0]->current_sem + 1;
    $currentSemester = $stu_info[0]->current_sem;
    $course_id = $stu_info[0]->course_id;
    $branch_id = $stu_info[0]->branch_id;
    $dept_id = $stu_info[0]->dept_id;
    $admn_no = isset($admn_no) ? $admn_no : Auth::user()->id;

    $genCreditsql = "SELECT A.course_comp,course_credit,B.earn,earn_pts,sub_cate FROM (
      SELECT b.* FROM cbcs_credit_points_master a
      JOIN cbcs_curriculam_master b on a.id=b.cbcs_credit_points_master
      WHERE a.course_id='$course_id' AND branch_id='$branch_id' ) A
      left JOIN (

        SELECT b.mis_sub_id,SUM(cr_pts) AS earn,SUM(b.cr_hr) earn_pts,
        if(LENGTH(b.mis_sub_id)>6,
        CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Za-z]+')),
        REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+')
          ) AS sub_cate
        FROM final_semwise_marks_foil_freezed a
        JOIN final_semwise_marks_foil_desc_freezed b ON a.id=b.foil_id
        WHERE a.course IN ('$course_id','comm')  AND a.admn_no = '$admn_no' AND b.grade NOT IN ('F','I') AND a.actual_published_on in (
        SELECT MAX(actual_published_on) FROM final_semwise_marks_foil_freezed WHERE course IN ('$course_id','comm')  AND admn_no = '$admn_no'
        GROUP BY session_yr,session,dept,course,branch,semester)
        GROUP BY if(
        LENGTH(b.mis_sub_id)>6,
        CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Z]+')),
        REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+')
          )
        ) B
      ON A.course_comp = B.sub_cate";
    // echo $genCreditsql;
    // exit;
    $data['genCredit'] =  DB::select($genCreditsql);

    $data['checkDDMM']  =  $checkDDMM =     DB::table('major_minor_dual_final')->where('admn_no', $admn_no)->where('status', 1)->get();
    if (count($checkDDMM) > 0) {
      foreach ($checkDDMM as $key => $value) {
        if ($value->applied_for == 'doublemajor') {
          $doublemajorsql =  $sql = "SELECT A.course_comp,course_credit,B.earn,earn_pts,sub_cate FROM (
            SELECT a.dept_id,a.course_id,a.branch_id,a.category AS course_comp,sum(a.credit_hours) AS course_credit
            from cbcs_doublemajor_course_offered a
            WHERE  a.dept_id='$value->opt_dept_id' AND course_id='$value->opt_course_id' AND branch_id='$value->opt_branch_id'
            GROUP BY a.category ) A
            left JOIN (

              SELECT b.mis_sub_id,SUM(cr_pts) AS earn,SUM(b.cr_hr) earn_pts,
              if(LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Za-z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+')
                ) AS sub_cate
              FROM final_semwise_marks_foil_freezed a
              JOIN final_semwise_marks_foil_desc_freezed b ON a.id=b.foil_id
              WHERE a.course IN ('doublemajor')  AND a.admn_no = '$admn_no' AND b.grade NOT IN ('F','I') AND a.actual_published_on in (
              SELECT MAX(actual_published_on) FROM final_semwise_marks_foil_freezed WHERE course IN ('doublemajor')  AND admn_no = '$admn_no'
              GROUP BY session_yr,session,dept,course,branch,semester)
              GROUP BY if(
              LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+')
                )
              ) B
            ON A.course_comp = B.sub_cate";
          $data['doublemajor'] = DB::select($doublemajorsql);
        } else {
          $data['doublemajor'] = array();
        }

        if ($value->applied_for == 'dualdegree_categoryB' || $value->applied_for == 'dualdegree_categoryA') {
          $explode_data = explode('_', $value->applied_for);
          $category_type = $explode_data[1];

          $dualdegreesql = "SELECT A.course_comp,course_credit,B.earn,earn_pts,sub_cate FROM (
            SELECT a.dept_id,a.course_id,a.branch_id,a.category AS course_comp,sum(a.credit_hours) AS course_credit
            from cbcs_dualdegree_course_offered a
            WHERE a.category_type='$category_type' AND a.dept_id='$value->opt_dept_id' AND course_id='$value->opt_course_id'
             AND branch_id='$value->opt_branch_id'
            GROUP BY a.category ) A
            left JOIN (

              SELECT b.mis_sub_id,SUM(cr_pts) AS earn,SUM(b.cr_hr) earn_pts,
              if(LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Za-z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+')
                ) AS sub_cate
              FROM final_semwise_marks_foil_freezed a
              JOIN final_semwise_marks_foil_desc_freezed b ON a.id=b.foil_id
              WHERE a.course IN ('$value->applied_for')  AND a.admn_no = '$admn_no' AND b.grade NOT IN ('F','I') AND a.actual_published_on in (
              SELECT MAX(actual_published_on) FROM final_semwise_marks_foil_freezed WHERE course IN ('$value->applied_for')  AND admn_no = '$admn_no'
              GROUP BY session_yr,session,dept,course,branch,semester)
              GROUP BY if(
              LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+')
                )
              ) B
            ON A.course_comp = B.sub_cate";
          $data['dualdegrees'] = DB::select($dualdegreesql);
        } else {
          $data['dualdegrees'] = array();
        }
        if ($value->applied_for == 'minor') {
          $minorsql = "SELECT A.course_comp,course_credit,B.earn,earn_pts,sub_cate FROM (
            SELECT a.dept_id,a.category AS course_comp,sum(a.credit_hours) AS course_credit from cbcs_minor_course_offered a
            WHERE  a.dept_id='$value->opt_dept_id'
            GROUP BY a.category ) A
            left JOIN (

              SELECT b.mis_sub_id,SUM(cr_pts) AS earn,SUM(b.cr_hr) earn_pts,

              if(LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Za-z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Za-z]+')
                ) AS sub_cate
              FROM final_semwise_marks_foil_freezed a
              JOIN final_semwise_marks_foil_desc_freezed b ON a.id=b.foil_id
              WHERE a.course IN ('minor')  AND a.admn_no = '$admn_no' AND b.grade NOT IN ('F','I') AND a.actual_published_on in (
              SELECT MAX(actual_published_on) FROM final_semwise_marks_foil_freezed WHERE course IN ('minor')  AND admn_no = '$admn_no'
              GROUP BY session_yr,session,dept,course,branch,semester)
              GROUP BY if(
              LENGTH(b.mis_sub_id)>6,
              CONCAT(REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+'),'/',REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', -1),'[A-Z]+')),
              REGEXP_SUBSTR(SUBSTRING_INDEX(b.mis_sub_id, '/', 1),'[A-Z]+')
                )
              ) B
            ON A.course_comp = B.sub_cate";
          $data['minor'] = DB::select($minorsql);
        } else {
          $data['minor'] = array();
        }
      }
    } else {
      $data['checkDDMM'] = false;
    }

    if ($currentSemester == '1' || $currentSemester == '2') {
      $table_name = 'cbcs_comm_coursestructure_policy';
    } else {
      $table_name = 'cbcs_coursestructure_policy';
    }
    $currentsql = "SELECT z.*,x.course_component,x.sequence
    FROM cbcs_coursestructure_policy x
    LEFT JOIN (
    SELECT z.*
    FROM (
    SELECT z.*, SUM(z.credit_hours) AS tot_credit_hours, SUM(z.contact_hours) AS tot_contact_hours,
	 GROUP_CONCAT(DISTINCT CONCAT_WS('|',z.subject_code,z.subject_name,z.sub_category,z.sub_category,z.credit_hours)
    ORDER BY z.subject_code ASC) AS sub_details
    FROM (
    SELECT a.*,b.subject_code,b.subject_name,b.sub_category,
	 (case when c.course_id='jrf' then b.sub_category_cbcs_offered else c.credit_hours END) AS credit_hours,
	 c.contact_hours, REGEXP_SUBSTR(SUBSTRING_INDEX(b.sub_category, '/', -1),'[A-Za-z]+') AS sub_cat
    FROM (
    SELECT a.form_id,a.admn_no,a.course_id,a.branch_id,a.semester,b.auth_id
    FROM reg_regular_form a
    INNER JOIN stu_academic b ON a.admn_no=b.admn_no AND a.semester=b.semester
    WHERE a.admn_no='$admn_no'
    ORDER BY a.session_year,a.`session` DESC,a.semester desc
    LIMIT 1)a
    INNER JOIN cbcs_stu_course b ON a.form_id=b.form_id
    INNER JOIN cbcs_subject_offered c ON b.sub_offered_id=c.id
	 UNION
    SELECT a.*,b.subject_code,b.subject_name,b.sub_category,c.credit_hours,
	 (case when c.course_id='jrf' then b.sub_category_cbcs_offered else c.credit_hours END) AS credit_hours,
	 REGEXP_SUBSTR(SUBSTRING_INDEX(b.sub_category, '/', -1),'[A-Za-z]+') AS sub_cat
    FROM (
    SELECT a.form_id,a.admn_no,a.course_id,a.branch_id,a.semester,b.auth_id
    FROM reg_regular_form a
    INNER JOIN stu_academic b ON a.admn_no=b.admn_no AND a.semester=b.semester
    WHERE a.admn_no='$admn_no'
    ORDER BY a.session_year,a.`session` DESC,a.semester desc
    LIMIT 1)a
    INNER JOIN old_stu_course b ON a.form_id=b.form_id
    INNER JOIN old_subject_offered c ON b.sub_offered_id=c.id)z
    GROUP BY z.sub_cat)z)z ON x.course_component=z.sub_cat  AND (case when z.course_id='jrf' then 1=1 else x.sem=z.semester end)  AND
	 (case when z.semester <= 2 AND z.auth_id='ug' then x.course_id='comm' else x.course_id=z.course_id END)
    WHERE z.admn_no IS NOT NULL
    GROUP BY z.sub_cat";
    // echo  $currentsql;
    // exit;
    $data['currentCredit'] = DB::select($currentsql);;


    return $this->sendResponse($data, "Credit Information !");
  }

  function getTimeTable($session_year, $session, $sub_offered_id)
  {

    $sql = "SELECT t3.subj_code AS sub_code,t3.day,t3.slot_no,t3.sub_offered_id
    FROM tt_map_cbcs t1
    INNER JOIN tt_structure_master t2 ON
     t2.tt_id=t1.tt_id
    INNER JOIN tt_subject_slot_map_cbcs t3 ON t3.map_id=t1.map_id and t3.status='1' AND (case when t3.`group` IS NULL then 1=1 ELSE t3.`group`=1 end)
    WHERE t1.session_year='$session_year' AND t1.session='$session' AND t3.sub_offered_id='$sub_offered_id'
    /* AND t1.course_id='m.sc.tech' */
    GROUP BY t3.day,t3.slot_no UNION ALL
    SELECT c.subj_code AS sub_code,c.day,c.slot_no,c.sub_offered_id
    FROM tt_map_old a
    INNER JOIN tt_structure_master b ON b.tt_id=a.tt_id
    INNER JOIN
     tt_subject_slot_map_old c ON c.map_id=a.map_id and c.status='1' AND (case when c.`group` IS NULL then 1=1 ELSE c.`group`=1 end)
    WHERE a.session_year='$session_year' AND a.session='$session' AND c.sub_offered_id='$sub_offered_id'
    /*AND a.course_id='m.sc.tech'*/
    GROUP BY c.day,c.slot_no";

    // echo $sql;
    // exit;

    $timetable =  DB::select($sql);
    if ($timetable) {
      return $timetable;
    } else {
      return false;
    }
  }
  function getTimeTableBodyNew()
  {
    $timeTable = array(
      "timetable_body" => null,
      "timetable_header" => null,
    );
    $tablebodysql = "SELECT '' as sub_code,t3.id,SUBSTRING(t3.day,1,2) AS day,GROUP_CONCAT(t2.slot_no ORDER BY t2.slot_no) AS slot,CONCAT(t3.day,',',GROUP_CONCAT(t2.slot_no ORDER BY t2.slot_no)) AS slot_json,t1.*,t2.* FROM tt_structure_master t1  INNER JOIN tt_slot_master t2 ON t1.tt_id=t2.tt_id INNER JOIN tt_days_master t3 ON t3.id WHERE t1.active=1 AND t3.id<=t1.num_days GROUP BY t3.id";
    $tablebody =  DB::select($tablebodysql);
    $timeTable['timetable_body'] = $tablebody;


    $newTTBody = array();
    foreach ($tablebody as $key => $value) {
      $tableheadersql = "SELECT '' as sub_code,t1.slot_no,t1.tt_id,t1.start_time,t1.end_time,t2.bef_break,t2.aft_break FROM tt_slot_master t1 INNER JOIN tt_structure_master t2 ON t2.tt_id=t1.tt_id WHERE t2.active=1";
      $tableheader =  DB::select($tableheadersql);
      $newTTBody[$value->day] = $tableheader;
    }

    $tableheadersql = "SELECT '' as sub_code,t1.slot_no,t1.tt_id,t1.start_time,t1.end_time,t2.bef_break,t2.aft_break FROM tt_slot_master t1 INNER JOIN tt_structure_master t2 ON t2.tt_id=t1.tt_id WHERE t2.active=1";
    $tableheader =  DB::select($tableheadersql);
    $timeTable['timetable_header'] = $tableheader;
    return $newTTBody;
  }

  function getTimeTableBody()
  {
    $timeTable = array(
      "timetable_body" => null,
      "timetable_header" => null,
    );
    $tablebodysql = "SELECT '' as sub_code,t3.id,SUBSTRING(t3.day,1,2) AS day,GROUP_CONCAT(t2.slot_no ORDER BY t2.slot_no) AS slot,CONCAT(t3.day,',',GROUP_CONCAT(t2.slot_no ORDER BY t2.slot_no)) AS slot_json,t1.*,t2.* FROM tt_structure_master t1  INNER JOIN tt_slot_master t2 ON t1.tt_id=t2.tt_id INNER JOIN tt_days_master t3 ON t3.id WHERE t1.active=1 AND t3.id<=t1.num_days GROUP BY t3.id";
    $tablebody =  DB::select($tablebodysql);
    $timeTable['timetable_body'] = $tablebody;

    $tableheadersql = "SELECT '' as sub_code,t1.slot_no,t1.tt_id,t1.start_time,t1.end_time,t2.bef_break,t2.aft_break FROM tt_slot_master t1 INNER JOIN tt_structure_master t2 ON t2.tt_id=t1.tt_id WHERE t2.active=1";
    $tableheader =  DB::select($tableheadersql);
    $timeTable['timetable_header'] = $tableheader;
    return $timeTable;
  }

  function CheckForCurrentStudyingByCategory($admn_no, $subcode, $sem_str2)
  {
    $currentstudyingsql = "select
    rg.session_year,rg.session,x.subject_code FROM (select * from cbcs_stu_course a WHERE a.admn_no = '$admn_no'    UNION ALL select * from
    old_stu_course a WHERE a.admn_no = '$admn_no')x join (SELECT rg.form_id,rg.admn_no,rg.session_year,rg.session from  reg_regular_form rg WHERE
    rg.hod_status = '1' AND rg.acad_status = '1' AND rg.admn_no = '$admn_no' ${sem_str2} ORDER BY rg.session_year desc,(case
    when(rg.session = 'Monsoon') then  '1' when(rg.session = 'Monsoon') then '2' when rg.session = 'Winter' then  '3' when
    rg.session= 'Winter' then  '4' when(rg.session = 'Summer') then  '5' END) DESC LIMIT 1)rg ON rg.form_id = x.form_id AND
    rg.admn_no = x.admn_no HAVING x.subject_code IN('$subcode') ";

    // echo $currentstudyingsql;
    // exit;

    $currentstudying =  DB::select($currentstudyingsql);
    if ($currentstudying) {
      return $currentstudying;
    } else {
      return false;
    }
  }

  function CheckForCurrentStudying($sessionYear, $session, $admn_no, $subcode, $sem_str2)
  {
    $currentstudyingsql = "select
    rg.session_year,rg.session,x.subject_code FROM (select * from cbcs_stu_course a WHERE a.admn_no = '$admn_no'    UNION ALL select * from
    old_stu_course a WHERE a.admn_no = '$admn_no')x join (SELECT rg.form_id,rg.admn_no,rg.session_year,rg.session from  reg_regular_form rg WHERE
    rg.hod_status = '1' AND rg.acad_status = '1' AND rg.admn_no = '$admn_no' ${sem_str2} ORDER BY rg.session_year desc,(case
    when(rg.session = 'Monsoon') then  '1' when(rg.session = 'Monsoon') then '2' when rg.session = 'Winter' then  '3' when
    rg.session= 'Winter' then  '4' when(rg.session = 'Summer') then  '5' END) DESC LIMIT 1)rg ON rg.form_id = x.form_id AND
    rg.admn_no = x.admn_no HAVING x.subject_code IN('$subcode') ";

    // echo $currentstudyingsql;
    // exit;

    $currentstudying =  DB::select($currentstudyingsql);
    if ($currentstudying) {
      return $currentstudying;
    } else {
      return false;
    }
  }

  function checkForOffered($sessionYear, $session, $subcode)
  {
    $checkforoffer = DB::select("SELECT * FROM (SELECT a.*,CONCAT('c',a.id) AS sub_offered_id,'' AS map_id FROM
    cbcs_subject_offered a WHERE a.session_year='$sessionYear' AND a.session='$session' UNION
    ALL SELECT a.*,CONCAT('o',a.id) AS sub_offered_id FROM old_subject_offered a WHERE a.session_year='$sessionYear' AND
    a.session='$session' ) x WHERE x.sub_code='$subcode' GROUP
    BY x.sub_code");
    if ($checkforoffer) {
      return $checkforoffer;
    } else {
      return false;
    }
  }

  function GetExamClash(Request $request)
  {
    $core_sub_offered_ids = $request->core_sub_offered_id;
    $core_sub_offered_id = join("', '", explode(',', implode(",", explode(',', $core_sub_offered_ids))));

    $sub_offered_id = $request->sub_offered_id;
    $session_year = $request->session_year;
    $session = $request->session;


    $examClashsql = "SELECT y.*, (y.clash_paper) AS final_clash
    FROM (
    SELECT x.*, GROUP_CONCAT(DISTINCT x.course_type) AS clash_paper, COUNT(DISTINCT x.course_type) AS clashcnt, GROUP_CONCAT(map_id) AS clash_map_id
    FROM (
    SELECT a.id AS map_id,a.`session`,a.session_year,a.course_id,a.semester,b.`day`,b.time,b.slot,b.course_type
    FROM tt_exam_template a
    INNER JOIN tt_exam_template_slot b ON a.id=b.template_id
    WHERE a.`session`='$session' AND a.session_year='$session_year' AND ((a.course_id,b.course_type,a.semester) IN (
    SELECT a.course_id,a.sub_category,a.semester
    FROM (
    SELECT a.id AS sub_offered_id,a.sub_code,a.sub_category AS sub_category, a.semester,a.course_id,a.branch_id
    FROM cbcs_subject_offered a
    WHERE a.session_year='$session_year' AND a.`session`='$session' AND CONCAT('c',a.id) IN ('$core_sub_offered_id'))a) OR (a.course_id,b.course_type,a.semester) IN (
    SELECT a.course_id,if(a.unique_sub_pool_id='NA',a.sub_category,a.unique_sub_pool_id) AS sub_category,a.semester
    FROM cbcs_subject_offered a
    WHERE a.session_year='$session_year' AND a.`session`='$session' AND a.id='$sub_offered_id ' UNION
    SELECT a.course_id,if(a.unique_sub_pool_id='NA',a.sub_category,a.unique_sub_pool_id) AS sub_category,a.semester
    FROM old_subject_offered a
    WHERE a.session_year='$session_year' AND a.`session`='$session' AND a.id='$sub_offered_id '))) x
    GROUP BY x.day,x.slot
    HAVING clashcnt > 1)y
    GROUP BY y.clash_paper";

    // echo $examClashsql;
    // exit;
    $check = DB::select($examClashsql);
    $data['clash_details'] = $check;
    if ($check) {
      return $this->sendResponse($data, "Time Table Clash Details !");
    } else {
      return $this->sendResponse($data, "Time Table Clash Details !");
    }
  }

  function GetTTClash(Request $request, $core_sub_offered_id = '', $sub_offered_id = '', $session_year = '', $session = '', $course_id = '', $course_type = '', $flag = false)
  {

    if ($request->isMethod('get')) {

      $flag = true;
    }


    if ($flag) {
      $core_sub_offered_ids = $request->core_sub_offered_id;
      $core_sub_offered_id = join("', '", explode(',', implode(",", explode(',', $core_sub_offered_ids))));

      $sub_offered_id = $request->sub_offered_id;
      $session_year = $request->session_year;
      $session = $request->session;
      $course_id = $request->course_id;
      $course_type = $request->course_type;
      $flag = true;
    }

    // if ($flag) {
    //   echo "okkk";
    // } else {
    //   echo "nooo";
    // }
    $ttsql = "SELECT y.*, (y.clash_paper) AS final_clash,
    REPLACE(GROUP_CONCAT(y.sec),'notcomm','') AS sections
    FROM (
    SELECT x.*, GROUP_CONCAT(DISTINCT x.subj_code) AS clash_paper,
          COUNT(DISTINCT x.subj_code) AS clashcnt,
          GROUP_CONCAT(map_id) AS clash_map_id,
          GROUP_CONCAT((CASE WHEN x.section IS NULL THEN 'notcomm' ELSE x.section END)) AS sec
    FROM (
    SELECT CONCAT('c',x.map_id) AS map_id,x.day,x.slot_no,x.subj_code,z.course_id,z.branch_id,z.section
    FROM tt_subject_slot_map_cbcs x
    INNER JOIN tt_map_cbcs z ON x.map_id=z.map_id and (case when '$course_type'='eso' then z.course_id='$course_id' else 1=1 end)
    INNER JOIN cbcs_subject_offered cs ON x.subj_code=cs.sub_code AND z.session=cs.session AND z.session_year=cs.session_year
    WHERE x.status=1 AND (case when x.`group` IS NULL then 1=1 ELSE x.`group`=1 end) and
    ( x.sub_offered_id IN ('$core_sub_offered_id','$sub_offered_id') ) AND z.session_year='$session_year' AND z.session='$session'
    UNION
    SELECT CONCAT('o',x.map_id) AS map_id,x.day,x.slot_no,x.subj_code,z.course_id,z.branch_id,z.section
    FROM tt_subject_slot_map_old x
    INNER JOIN tt_map_old z ON x.map_id=z.map_id
    INNER JOIN old_subject_offered cs ON x.subj_code=cs.sub_code AND z.session=cs.session AND z.session_year=cs.session_year
    WHERE  x.status=1 AND (case when x.`group` IS NULL then 1=1 ELSE x.`group`=1 end) and ( x.sub_offered_id IN
    ('$core_sub_offered_id','$sub_offered_id') ) AND z.session_year='$session_year' AND z.session='$session'
    ORDER BY DAY,slot_no,subj_code
    LIMIT 1000) x
    GROUP BY x.day,x.slot_no
    HAVING clashcnt > 1 AND INSTR(sec,'notcomm')>0
          )y
    GROUP BY y.clash_paper";

    // echo $ttsql;
    // exit;
    $check = DB::select($ttsql);
    $data['clash_details'] = $check;
    if ($check) {
      if ($flag) {
        return $this->sendResponse($data, "Time Table Clash Details !");
      } else {
        return true;
      }
    } else {


      if ($flag) {
        return  $this->sendError('Time Table not clash', 'Time Table not clash !');
      } else {
        $this->sendError('Time Table not clash', 'Time Table not clash !');
        return false;
      }
    }
  }
  function validateSelectedCourse(Request $request)
  {
    $pre_subjects = $request->pre_subjects;
    $selected_subject = $request->selected_subject;
    $session_year = $request->session_year;
    $session = $request->session;
    $course_id = $request->course_id;
    $course_category = $request->course_category;
    $admn_no = Auth::user()->id;
    $CheckalreadyPassCourse = $this->alreadyPassCourse($admn_no, $selected_subject);
    if (!$CheckalreadyPassCourse) {
      return $this->sendError('Invalid Course !', 'This Course is already Cleared !');
    }
    $checkExamClash = $this->checkExamClash($admn_no, $session_year, $session, $sub_offered_id, 1);
  }
  private function checkExamClash($admn_no, $session_year, $session, $sub_offered_id)
  {
  }
  private function alreadyPassCourse($admn_no, $sub_code)
  {

    $check = DB::select("SELECT * FROM final_semwise_marks_foil_desc_freezed a WHERE a.admn_no='$admn_no' AND a.sub_code='$sub_code' AND a.grade <> 'F'");
    if ($check) {
      return true;
    } else {
      return false;
    }
  }
}
