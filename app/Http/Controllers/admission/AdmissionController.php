<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Mail\EMailClass;
use Exception;
use Illuminate\Support\Facades\View;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Hash;


$google_recaptcha_url = 'https://www.google.com/recaptcha/admin/';
$google_recaptcha_secret = '6Lf8isYkAAAAAOfbFb9gtMSO7lgBUCjM7PdHzq3b';

class AdmissionController extends Controller
{
    public function __construct(){
      $this->middleware('AuthCheck:stu,emp',['except' => ['check_admnno','sanctum/csrf-cookie']]);
    }

    // function check_postman(){
    //     echo 'hiii';
    // }
    // function check_admnno(Request $request){
    //     //return $this->sendResponse($request->datanew['admn_no'],'Check Request');
    //     //print_r($request->datanew); exit;
    //     //echo $request->admn_no; exit;
    //     $validator = Validator::make($request->datanew,[
    //         'admn_no' => 'required|unique:convocation,admn_no',
    //         'google_captcha' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->messages()
    //             ], 405);
    //             }
    //         else {
    //             $data = array('secret' => env('GOOGLE_RECAPTCHA_SECRET'),
    //             'response' => $request->datanew['google_captcha'],
    //             //'response' => $request->datanew['admn_no'],
    //             'remoteip' => $request->ip());
    //         try {
    //             $verify = curl_init();
    //             curl_setopt($verify, CURLOPT_URL,
    //             "https://www.google.com/recaptcha/api/siteverify");
    //             curl_setopt($verify, CURLOPT_POST, true);
    //             curl_setopt($verify, CURLOPT_POSTFIELDS,
    //                         http_build_query($data));
    //             curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
    //             curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    //             $response = curl_exec($verify);
    //             $response1 = json_decode($response)->success;
    //             if($response1 === true)
    //             {
    //             $admn_no = $this->strclean($request->datanew['admn_no']);
    //             $sql = "SELECT count('*') as row_cnt , name , date_of_birth from convocation_final_tbl where admn_no = '$admn_no'";
    //             $rows = DB::select($sql);
    //             if ($rows[0]->row_cnt > 0) {
    //             #$success['token'] = $user->createToken('mis_MyApp',['server:update'])->plainTextToken;
    //             $success['status'] = true;
    //             $success['date_of_birth'] = $rows[0]->date_of_birth;
    //             $success['student_name'] = $rows[0]->name;
    //             return $this->sendResponse($success,'Admission No. Exists');
    //             }
    //             else {
    //                 $error['status'] = false;
    //                 return $this->sendResponse($error,'Admission No. Does Not Exists');
    //                 }
    //            }
    //         else {
    //             $error['status'] = false;
    //             return $this->sendResponse($error,'Google Recaptcha Error');
    //         }
    //         }
    //         catch (\Exception $e) {
    //             $error['status'] = false;
    //             return $this->sendResponse($error,'Google Recaptcha Error');
    //         }
    //         }
    //         }

            // function check_admnno_login(Request $request){
            //     $validator = Validator::make($request->data,[
            //         'mobile_no' => 'required|digits:10',
            //         'email' => 'required|email',
            //         'admn_no' => 'required|alpha_num'
            //     ]);

            //     if ($validator->fails()) {
            //         return response()->json([
            //             'status' => 'error',
            //             'message' => $validator->messages()
            //         ],405);
            //     }
            //     else{
            //         $admn_no = $this->strclean($request->data['admn_no']);
            //         $sql = "SELECT count('*') as row_cnt from convocation where admn_no = '$admn_no'";
            //         $rows = DB::select($sql);
            //         if ($rows[0]->row_cnt > 0) {
            //             $sql = "SELECT count('*') as row_cnt from convocation_final_tbl where admn_no = '$admn_no' and allowed_to_proceed = 'N'";
            //             $rows = DB::select($sql);
            //             if($rows[0]->row_cnt > 0) {
            //                 $error['status'] = false;
            //                 return $this->sendResponse($error, 'Library Dues Error');
            //             }
            //             else{
            //                $sql = "select count('*') as row_cnt , name , programme_name from convocation_final_tbl where admn_no = '$admn_no'";
            //                $rows = DB::select($sql);
            //                if ($rows[0]->row_cnt > 0) {
            //                   $success['status'] = true;
            //                   $success['name'] = $rows[0]->name;
            //                   $success['programme_name'] = $rows[0]->programme_name;
            //                   return $this->sendResponse($success,'Admission No. Exists');
            //                }
            //                else {
            //                 $error['status'] = false;
            //                 return $this->sendResponse($error,'Admission No. Does Not Exists');
            //                }
            //             }
            //         }
            //         else{
            //             $error['status'] = false;
            //             return $this->sendResponse($error, 'Please Register First to Proceed');
            //         }
            //     }
            // }

            function register_user(Request $request){

                // echo '<pre>';
                // print_r($request->data);
                // echo '</pre>';
                // exit;


                return $this->sendResponse($request->datanew,'User registration details');

                // $validator = Validator::make($request->datanew,[
                //     'admn_no' => 'required|unique:convocation,admn_no',
                //     'date_of_birth_new' => 'required',
                //     'student_name_new' => 'required',
                //     'email_address' => 'required',
                //     'mobile_number' => 'required|min:10|digits:10',
                //     'aadhar_number' => 'required|digits:12'
                // ]);

                // if ($validator->fails()) {
                //     //echo 'validation passed'; exit;
                //     return response()->json([
                //             'status' => 'error',
                //             'message' => $validator->messages()],
                //             405
                //     );
                //         }
                // else {
                //     $user = User::create([
                //         'name' => $request->datanew['student_name_new'],
                //         'email' => $request->datanew['email_address'],
                //         'password' => Hash::make($request->datanew['mobile_number'])
                //     ]);

                //     #return $this->sendResponse($user,'User registration details');

                //     $token = $user->createToken($user->name.'_Token')->plainTextToken;
                //     $reg_conv = array(
                //         'admn_no' => $request->datanew['admn_no'],
                //         'email' => $request->datanew['email_address'],
                //         'mobile' => $request->datanew['mobile_number'],
                //         'aadhar_no' => $request->datanew['aadhar_number'],
                //         'dob' => $request->datanew['date_of_birth_new'],
                //         'sname' => $request->datanew['student_name_new'],
                //     );

                //     DB::table('convocation')->insert($reg_conv);

                //     $success['status'] = true;
                //     $success['email'] =  $request->datanew['email_address'];
                //     $success['token'] = $token;

                //     return $this->sendResponse($success, 'User Registered successfully.');
                // }
            }

        // function login_user(Request $request){


        //         //return $this->sendResponse($request->datanew['aadhar_number'],'User registration details');

        //         $validator = Validator::make($request->datanew,[
        //             'admn_no' => 'required|alpha_num',
        //             'mobile_no' => 'required|min:10|digits:10',
        //             'email' => 'required|email',
        //             'student_name' => 'required',
        //             'programme_name' => 'required',
        //             'google_captcha' => 'required'
        //         ]);

        //         if ($validator->fails()) {
        //             //echo 'validation passed'; exit;
        //             return response()->json([
        //                     'status' => 'error',
        //                     'message' => $validator->messages()],
        //                     405
        //             );
        //                 }
        //         else {
        //             // start the authentication process here
        //             $user = User::where('email',$request->datanew['email'])->first();
        //             if(!$user || ! Hash::check($request->datanew['mobile_no'],$user->password)){
        //                 return response()->json([
        //                     'status' => 'error',
        //                     'message' => 'Invalid Credentials'
        //                 ],405);
        //             }
        //             else{
        //             //check if the user has already registered in convocation final table.
        //             $admn_no = $this->strclean($request->datanew['admn_no']);
        //             $sql = "SELECT count('*') as row_cnt from convocation_stu_details where admn_no = '$admn_no'";
        //             $rows = DB::select($sql);
        //             if ($rows[0]->row_cnt > 0) {
        //                // check if the user has already registered then he completed the payment process.
        //                $sql = "SELECT count('*') as row_cnt from convocation_stu_details where pay_date != ''";
        //                $rows = DB::select($sql);
        //                if ($rows[0]->row_cnt > 0) {
        //                   // if the payment is completed then redirect to the already registered page.
        //                   $success['status'] = 'true';
        //                   $success['return_page_no'] = 3;
        //                   //$success['']
        //                   return $this->sendResponse($success['message'],'User already Registered');
        //                }
        //                else {
        //                  // else redirect to the payment page.
        //                   $success['message'] = 'Please Complete the payment process here';
        //                   return $this->sendResponse($success['message'],'Complate Payment Process Here');
        //                }
        //             }
        //             else{
        //                 //if the user has not registered then redirect to the innitial registeration page after login.
        //                 $success['status'] = 'true';
        //                 return $this->sendResponse($success['message'],'Initial Registration Process');
        //             }

        //         }

        //         }
        //     }

    //    function create_products(Request $request){
    //     /* To hard-code the request parameters
    //     return Product::create(
    //         ['name' => 'Product One',
    //         'slug' => 'product-one',
    //         'description' => 'This is Product One',
    //         'price' => 99.99]
    //     );
    //     */
    //     $validator = Validator::make($request->all(),[
    //          'name' => 'required',
    //          'slug' => 'required',
    //          'price' => 'required'
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Invalid Request !'
    //             ], 401);
    //             }

    //     return Product::create($request->all());
    //    }

    //    function get_products(){
    //      return Product::all();
    //    }

    //     protected function strclean($str)
    // {
    //     //global $mysqli;
    //     $str = @trim($str);

    //     return  preg_replace('/[^A-Za-z0-9. -]/', '', $str);
    // }
    }
