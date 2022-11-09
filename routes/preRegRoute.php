<?php
//by @bhijeet


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\pre_reg\BasicController;
use App\Http\Controllers\AllotmentController;

Route::controller(AllotmentController::class)->group(function () {
  Route::post('allotment', 'allotment');
});

Route::controller(BasicController::class)->group(function () {
    Route::post('stu_details', 'GetStudentDetails');
    Route::get('get_open_close_date', 'GetSessionDetails');
    Route::match(['get', 'post'], 'get_TT_Clash', 'GetTTClash');
    Route::match(['get', 'post'], 'get_Exam_Clash', 'GetExamClash');
    Route::match(['get', 'post'], 'validate_single_login/', 'validateSingleLogin');
    Route::post('validate_selected_course', 'validateSelectedCourse');
    Route::post('check_for_group_tt', 'checkForGroupTT');
    Route::post('drop_backlog_courses', 'DropBacklogCourses');
    Route::post('start_pre_reg', 'StartPreReg');
    Route::post('get_core_courses', 'getCoreCourses');
    Route::post('get_eso_courses', 'getEsoCourses');
    Route::post('save_pre_registration_data', 'saveDataPre');
    Route::post('save_data', 'saveData');
    Route::post('get_de_courses', 'getDeCourses');
    Route::post('get_oe_courses', 'getOeCourses');
    Route::post('get_department', 'getDepartment');
    Route::get('get_session', 'getMisSession');
    Route::get('get_session_year', 'getMisSessionYear');
    Route::post('get_credit_details', 'getCreditDetails');
    Route::post('get_pre_reg_receipt', 'getPreRegDetails');
    Route::post('send_email', 'sendEmailMannualy');
    Route::post('send_email_bulk', 'sendEmailBulkMannualy');
    Route::post('allotment', 'sendEmailBulkMannualy');
});
