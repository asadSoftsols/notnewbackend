<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GuidHelper;
use App\Models\CheckOut;
use App\Models\Product;
use App\Models\SellerData;
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
    public function selfCheckOut(){
    //    return CheckOut::where('user_id', \Auth::user()->id)->first();
    $checkout = CheckOut::where('user_id', \Auth::user()->id)->first();
        $checkoutData = SellerData::where('id', $checkout->store_id)->with(['products' => function ($query) {
            $checkout = CheckOut::where('user_id', \Auth::user()->id)->first();
            $query->where('id', $checkout->product_id);
        }])->first();
        $product = Product::where('id', $checkout->product_id)->first();
        $shippingTotal = $product->shipping_price;
      if($checkoutData){
            return response()->json([
                    'success'=> true,
                    'total'=> $checkout->order_total + $shippingTotal,                    
                    'shipping'=> $shippingTotal,
                    'data'=> $checkoutData,
                ]);  
       }else{
           return response()->json([
                    'success'=> false,
                    'message' => 'Failed to Fetch Data'
                ]);
       }
    }
    public function self()
    {
        // return CheckOut::with(['user'])
        // ->with(['cart'])
        // ->get();
        $userCart = UserCart::where('user_id', \Auth::user()->id)->get();
        $shopId=array();
        foreach($userCart as $cart){
            array_push($shopId, $cart->shop_id);
        }
        $shops = SellerData::whereIn('id', $shopId)
            ->with(['products'])
            ->get();
        $productId=array();
        foreach($userCart as $cart){
            array_push($productId, $cart->product_id);
        }
        $products = Product::whereIn('id', $productId)->get();
        
        $shipping = array();
        foreach($products as $pro){
            array_push($shipping, $pro->shipping_price);
        }
        $shippingTotal = array_sum($shipping);
        
        $total=array();
        foreach($userCart as $cart){
            array_push($total, $cart->price);
        }
        $orderTotal = array_sum($total);
        $totalOrder = $orderTotal + $shippingTotal;

        
       if($shops){
            return response()->json([
                    'success'=> true,
                    'total'=> $totalOrder,                    
                    'shipping'=> $shippingTotal,
                    'data'=> $shops,
                ]);  
       }else{
           return response()->json([
                    'success'=> false,
                    'message' => 'Failed to Fetch Data'
                ]);
       }
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
            $checkout->sub_total = "";//$request->get('sub_total');
            $checkout->quantity = $request->get('quantity');
            $checkout->shipping_total = "";//$request->get('shipping_total');
            $checkout->admin_prices = "";//json_encode($request->get('admin_prices'));
            $checkout->order_total = "";//$request->get('order_total');
            $checkout->product_id = $request->get('product_id');//$request->get('order_total');
            $checkout->store_id = $product->shop_id;//$request->get('order_total');
            
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
