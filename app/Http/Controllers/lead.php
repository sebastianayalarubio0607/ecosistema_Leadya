<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class lead extends Controller
{
    
    private  $mensaje1 = 'hola mundo';
    private  $mensaje2 = 'hola mundo 2';
    public function index()
    {
       
        $mensaje1 = $this->mensaje1;
        $mensaje1 ="hola";
        
      
        return response()->json([

            'mensaje1' => $this->mensaje1,
            'mensaje2' => $this->mensaje2

        ])->send();

        
    }

/*
    public function show($id)
    {

        return response()->json([
            'message' => 'Lead details',
            'id' => $id
        ]);
    }
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Lead created',
            'data' => $request->all()
        ]);
    }
    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'Lead updated',
            'id' => $id,
            'data' => $request->all()
        ]);
    }
    public function destroy($id)
    {
        return response()->json([
            'message' => 'Lead deleted',
            'id' => $id
        ]);
    }
    public function search(Request $request)
    {
        return response()->json([
            'message' => 'Lead search',
            'query' => $request->query('q')
        ]);
    }*/
}
