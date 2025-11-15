<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController
{
    public function index(Request $request)
    {
        // $name = $request->input('name');
        // $email = $request->input('email');
        $all = $request->all();
        // return 'Tên là: ' . $name . '<br/>' . 'Email là: ' . $email;
        return view('pot', ['data' => $all]);
    }

    public function index2(Request $request)
    {
        // $query = $request->query('key');
        // return 'Tìm Kiếm là: ' . $query;
        return view('tintuc');
    }
};
