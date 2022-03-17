<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class IndexController extends Controller
{
    public function getProducts()
    {
        if (Redis::exists('products_id')) {
            return Redis::get('products_id');
        } else {
            return response()->json(['message' => 'there is no products'], 200);
        }
    }

    public function storeProduct(Request $request)
    {
        $data = $request->all();
        $productsId = Redis::incr('products_id');
        $data["id"] = $productsId;
        Redis::zadd('products', time(), $productsId);
        Redis::hmset("product:$productsId", $data);
        // $date = Carbon::createFromTimestamp(time())->format('m/d/Y');
        return response()->json(['message' => 'product added'], 200);
    }

    public function addTag(Request $request)
    {
        Redis::sadd('tags', $request->name);
        return response()->json(['message' => 'tag added'], 200);
    }

    public function productsTags(Request $request, $productId)
    {
        $products = collect(Redis::zrange("products", 0, -1));
        $tags = collect(Redis::smembers('tags'));
        if ($products->contains($productId)) {
            if ($tags->contains($request->tag)) {
                Redis::sAdd("product:{$productId}:tags", $request->tag);
                return response()->json(['message' => 'tagg assigned'], 200);
            } else {
                return response()->json(['message' => 'tag not found'], 404);
            }
        } else {
            return response()->json(['message' => 'product not found'], 404);
        }
    }

    public function addProductsToTags($productId, Request $request)
    {
        $products = collect(Redis::zrange("products", 0, -1));
        if ($products->contains($productId)) {
            foreach ($request->tags as $tag) {
                Redis::rpush("tag:$tag", $productId);
            }
            return response()->json(['message' => 'product granted the tags'], 200);
        } else {
            return response()->json(['error' => 'post not found'], 404);
        }
    }

}
