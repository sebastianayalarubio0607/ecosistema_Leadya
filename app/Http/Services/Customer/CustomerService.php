<?php

namespace App\Http\Services\Customer;


class CustomerService
{

    /** Aplica el ID de cliente desde el encabezado de la solicitud 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Request
    */
    public function applyCustomerIdFromHeader($request)
    {
        if ($request->hasHeader('X-Customer-ID')) {
        $customerId = (int) $request->header('X-Customer-ID');
        $request->merge(['customer_id' => $customerId]); // 👈 ya queda disponible como entero
    }
        return $request;
    }
    
}
