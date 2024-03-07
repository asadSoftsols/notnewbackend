<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Helpers\GuidHelper;
use App\Models\Bids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class BidsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
            $product = Product::where('guid', $request->get('id'))->first();
            $maxBids = Bids::where('product_id', $product->id)->max('max_bids');
            // return [
            //     'get-bids' => (int)$request->get('max_bids'),
            //     'max-bids' => $maxBids,
            // ];
            // $getBids = $request->get('max_bids');
            // if((int)$getBids < (int)$maxBids){
            //     return response()->json(['status'=>'prohibited','data'=>'Your Bid is less then Already Bid'], 200);
            // }else{
                Bids::where('product_id', $product->id)
                ->where('user_id', \Auth::user()->id)
                ->delete();

                $bids = new Bids();
                $bids->max_bids = $request->get('max_bids');
                $bids->shipping_charges = $request->get('shipping_charges');
                $bids->time_bids = $request->get('time_bids');
                $bids->estimated_total = $request->get('estimated_total');
                $bids->user_id = \Auth::user()->id;
                $bids->product_id = $product->id;
                $bids->guid = GuidHelper::getGuid();
                $bids->save();
                if($bids){
                    return response()->json(['status'=>'true','data'=>$bids],200);
                }else{
                    return response()->json(['status'=>'false','message'=>'Unable to Place Bid!'],403);
                }
            // }
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
        //
    }
    /**
     * Get Maximum Bids
     */
    public function getMaxBids($id)
    {
        $bids = Bids::get();
        if(count($bids) > 0){
            $product = Product::where('guid',$id)->first();
            return Bids::where('user_id', \Auth::user()->id)
            //where('product_id', $product->id)
            ->max('max_bids');
        }else{
            return '0';
        }   
    }

    public function getProductBids($id)
    {
        $product = Product::where('guid',$id)->first();
        $bids = Bids::where('product_id', $product->id)->count();      
        if($bids > 0){
            return $bids;
        }else{
            return 0;
        }
    }
    
    public function getTotalBidsProduct($id)
    {
        $product = Product::where('guid',$id)->first();
        $bids = Bids::where('product_id', $product->id)
            ->with('product')
            ->with('user')
            ->get();
        $data =[];      
        foreach($bids as $bid){
            array_push($data, $bid->max_bids);
        }
        $totalBids = array_sum($data);

        if($totalBids){
            return response()->json(['status'=> true,'data' =>$totalBids], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Bids of this Product!"], 400);       
        }
    }

    public function getBidsUserProduct($id)
    {
        $product = Product::where('guid',$id)->first();
        $bids = Bids::where('product_id', $product->id)
            ->where('user_id', \Auth::user()->id)
            ->with('product')
            ->with('user')
            ->first();
        if($bids){
            return response()->json(['status'=> true,'data' =>$bids], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Bids of this Product!"], 400);       
        }
    }

    public function confirmedBids(Request $request){
        
        $product = Product::where('guid', $request->get("product_id"))->first();
        
        $bids = Bids::where('product_id', $product->id)->update([
            'confirmed' => true
        ]);

        if($bids){
            return response()->json(['status'=> true,'data' =>'Your Bid has been Confirmed!'], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Seller"], 400);       
        }
    }

}
