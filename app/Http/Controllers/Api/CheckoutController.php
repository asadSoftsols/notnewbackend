<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GuidHelper;
use App\Models\CheckOut;
use App\Models\Product;
use App\Models\UserCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return CheckOut::with(['user'])
        ->with(['cart'])
        ->get();

    }

    public function self()
    {
        return CheckOut::with(['user'])
        ->with(['cart'])
        ->get();

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            
            // CheckOut::where('user_id', \Auth::user()->id)->delete();
            
            // $cart = UserCart::where('id',$request->get('cart_id'))->first();
            $product= Product::where('id', $request->get('product_id'))->first();
            $checkout = new CheckOut();
            $checkout->guid = GuidHelper::getGuid();
            $checkout->cart_id = "";//$request->get('cart_id');
            $checkout->user_id = \Auth::user()->id;
            $checkout->dicount_code = "";//$request->get('dicount_code');
            $checkout->items_number = "";//$request->get('items_number');
            $checkout->sub_total = $request->get('sub_total');
            $checkout->quantity = $request->get('quantity');
            $checkout->shipping_total = $request->get('shipping_total');
            $checkout->admin_prices = "";//json_encode($request->get('admin_prices'));
            $checkout->order_total = "";//$request->get('order_total');
            $checkout->product_id = $request->get('product_id');//$request->get('order_total');
            $checkout->store_id = $product->shop_id;//$request->get('shop_id');
            $checkout->save();
            if($checkout){
                return response()->json([
                    'success'=> true,
                    'message' => 'Checkout Added!'
                ]);
            }else{
                return response()->json([
                    'success'=> false,
                    'message' => 'Unable to Add Checkout!'
                ]);
            }
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $checkout = CheckOut::where('id', $id)->first();
        $checkout->delete();
        return back()->with('success', 'Checkout deleted');

    }
}
