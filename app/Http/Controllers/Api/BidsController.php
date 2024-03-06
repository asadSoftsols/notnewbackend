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
                Bids::where('product_id', $product->id)
                ->where('user_id', 27)
                ->delete();

            $bids = new Bids();
            $bids->max_bids = $request->get('max_bids');
            $bids->shipping_charges = $request->get('shipping_charges');
            $bids->time_bids = $request->get('time_bids');
            $bids->estimated_total = $request->get('estimated_total');
            $bids->user_id = 27;//\Auth::user()->id;
            $bids->product_id = $product->id;
            $bids->guid = GuidHelper::getGuid();
            $bids->save();
            if($bids){
                return response()->json(['status'=>'true','message'=>$bids],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Place Bid!'],403);
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
            return Bids::where('product_id', $product->id)
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

}
