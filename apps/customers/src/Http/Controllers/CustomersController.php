<?php

namespace PlatformApps\Customers\Http\Controllers;

use Illuminate\Routing\Controller;
use PlatformApps\Customers\Http\Requests\StoreCustomerRequest;
use PlatformApps\Customers\Models\Customer;

class CustomersController extends Controller
{
    public function index()
    {
        return view('customers::customers.index', [
            'records' => Customer::latest('id')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('customers::customers.form', ['record' => new Customer()]);
    }

    public function store(StoreCustomerRequest $request)
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index');
    }

    public function edit(Customer $record)
    {
        return view('customers::customers.form', ['record' => $record]);
    }

    public function update(StoreCustomerRequest $request, Customer $record)
    {
        $record->update($request->validated());

        return redirect()->route('customers.index');
    }

    public function destroy(Customer $record)
    {
        $record->delete();

        return redirect()->route('customers.index');
    }
}
