<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Exception;


class AdminController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('AuthCheck:admin');
    }

    function addMenu(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_id' => 'required|string',
            'auth_id' => 'required|string',
            'submenu_1' => 'required|string',
            'link' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Invalid Parameters !', 'Please Enter All Valid Details !');
        }
    }

    function addModule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'description' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Invalid Parameters !', 'Please Enter Valid Details !');
        }

        $data = array(
            "id" => $request->id,
            "description" => $request->description,
        );
        try {
            // $response =  DB::table('modules')->insertGetId($data);
            if (DB::table('modules')->insert($data)) {
                $module = DB::table('modules')->orderBy('id', 'asc')->get();
                return $this->sendResponse($module, 'Record Added Successfully.');
            } else {
                return $this->sendError('Failed !', 'Something Went Worng.Please try Again !');
            }
        } catch (Exception $e) {
            return $this->sendError('Exception !', $e);
        }
    }

    function getModules()
    {
        $module = DB::table('modules')->orderBy('id', 'asc')->get();
        return $this->sendResponse($module, 'Module List !');
    }
}