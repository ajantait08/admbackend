<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use PDF;
use Illuminate\Http\Request;

class AllotmentController extends Controller
{
    public function __construct()
    {
      //  $this->middleware('auth:sanctum',);
      //  $this->middleware('AuthCheck:stu,emp');
    }

    function allotments(Request $request){
          echo"page ". $request->page;
                  return $this->sendResponse($request->page,"Recordds");
    }
}
