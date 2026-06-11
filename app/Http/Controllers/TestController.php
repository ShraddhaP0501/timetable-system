<?php

namespace App\Http\Controllers;

use App\Models\Subject;

class TestController extends Controller
{
    public function index()
    {
        $subjects = Subject::limit(10)->get();

        return view('index', compact('subjects'));
    }
}