<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCart;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserCartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return UserCart::with(['user'])
        ->with(['products'])
        ->with(['shop'])
        ->where('user_id', Auth::user()->id)
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
    protected function validator(array $data)
    {
         return Validator::make($data, [
            'price' => ['required'],
            'quantity' => ['required', 'integer', 'max:10'],
            // 'user_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            // 'attributes' => ['required'],
        ]);
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
            $validator = $this->validator($request->all());
            $cart = "";
            if (!$validator->fails()) {
                $cartExits = UserCart::where('user_id', Auth::user()->id)
                ->where('product_id',$request->get('product_id'))->first();
                if(!empty($cartExits)){
                    $cartQty = $cartExits->quantity;
                    $cartQty =  $cartQty + $request->get('quantity');
                    // $cartQty =  $cartQty + $request->get('quantity');
                    $cart = UserCart::where('user_id', Auth::user()->id)
                    ->where('product_id',$request->get('product_id'))->update(
                        [
                            'product_id' => $request->get('product_id'),
                            'price' =>   $request->get('price'),
                            'quantity' =>   $cartQty,//$request->get('quantity'),
                            'attributes' => $request->get('attributes') ? $request->get('attributes') : '{}',
                            'user_id' =>  Auth::user()->id,
                            'shop_id' =>  $request->get('shop_id')
                        ]);
                }else{
                    $cart = new UserCart();
                    $cart->product_id =  $request->get('product_id');
                    // $cart->name =  $request->get('name');
                    $cart->price =  $request->get('price');
                    $cart->quantity =  $request->get('quantity');
                    $cart->attributes = json_encode($request->get('attributes')) ? json_encode($request->get('attributes')) : '{}';
                    $cart->user_id =  Auth::user()->id;//$request->get('user_id');//\Auth::user()->id,;
                    $cart->shop_id =  $request->get('shop_id');
                   //$cart->fill($request->all())->save();
                   $cart->save();
                }
                return response()->json([
                    'success' => true,
                    'cart' => $cart,
                    'message' => "Product has been added to Cart"
                ], 200);
            }
            return response()->json([
                'success' => false,
                'errors' => $this,
                'message' => $validator->getMessageBag()
            ], 401);
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
        $cart = UserCart::where('id', Auth::user()->id)->first();
        return $cart;

    }

    public function self()
    {
        // $user = Auth::user();
        // $cart = UserCart::where('user_id', $user->id)
        //     ->with(['products'])
        //     ->where('ordered', false)->get();
        //     $products = [];
        //     foreach($cart as $c){
        //         $products['height'] = '10';//$c->products->height;
        //         $products['width'] = '10';//$c->products->width;
        //         $products['length'] = '10';//$c->products->length;
        //         $products['actual_weight'] ='10';// $c->products->weight;
        //         // array_push($products, $c->products);
        //     }
        // return [
        //     'products'=>$products,
        //     'cart'=>$cart
        // ];
        $user = Auth::user();
        $cart = UserCart::where('user_id', $user->id)
            ->with(['products'])
            ->with(['user'])
            ->with(['shop'])
            ->with(['savelater'])
            ->get();
            // ->where('ordered', false)->get();
            $products = [];
            foreach($cart as $c){
                $products['height'] = '10';//$c->products->height;
                $products['width'] = '10';//$c->products->width;
                $products['length'] = '10';//$c->products->length;
                $products['actual_weight'] ='10';// $c->products->weight;
                // array_push($products, $c->products);
            }
            if($cart){
                return response()->json(['status'=> true,'data' =>$cart], 200);       
            }else{
                return response()->json(['status'=> false,'data' =>"Unable to Get Cart"], 400);       
            } 
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // $userCart =UserCart::where('id', $id)->first();

        // $userCart = UserCart::where('id', $id)
        // ->update($request->all());
        //     return response()->json([
        //     'success' => true,
        //     'cart' => $userCart,
        //     'message' => "Cart Updated"
        //     ], 200);
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
        $userCart =UserCart::where('id', $id)->first();
        $product = Product::where('id', $userCart->product_id)->first();
        $price = "";
        if($product->auctioned){
           $price = $product->bids * $request->get('quantity');     
        }else if($product->selling_now){
           $price = $product->price * $request->get('quantity');
        }
        $update = UserCart::where('id', $id)->update([
            "quantity"=>$request->get('quantity'),
            "price"=>$price,
        ]);
        return UserCart::where('id', $id)->first();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userCart = UserCart::where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => "Item Deleted"
        ], 200);
    }
    public function clear()
    {
        UserCart::where('user_id', Auth::user()->id)->delete();
        return response()->json([
            'success' => true,
            'message' => "Cart has been Clear"
        ], 200);
       
    }
    /**
     * Count total Coupns of User.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function count()
    {
       return UserCart::where('user_id', Auth::user()->id)->count();
    }
}
