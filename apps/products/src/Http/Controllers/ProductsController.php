<?php

namespace PlatformApps\Products\Http\Controllers;

use Illuminate\Routing\Controller;
use PlatformApps\Products\Http\Requests\StoreProductRequest;
use PlatformApps\Products\Models\Product;

class ProductsController extends Controller
{
    public function index()
    {
        return view('products::products.index', [
            'records' => Product::latest('id')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('products::products.form', ['record' => new Product()]);
    }

    public function store(StoreProductRequest $request)
    {
        Product::create($request->validated());

        return redirect()->route('products.index');
    }

    public function edit(Product $record)
    {
        return view('products::products.form', ['record' => $record]);
    }

    public function update(StoreProductRequest $request, Product $record)
    {
        $record->update($request->validated());

        return redirect()->route('products.index');
    }

    public function destroy(Product $record)
    {
        $record->delete();

        return redirect()->route('products.index');
    }
}
