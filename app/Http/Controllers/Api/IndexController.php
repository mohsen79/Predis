<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class IndexController extends Controller
{

    public function __construct()
    {
        $this->redis = Redis::connection();
    }

    public function getProductsCount()
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

    public function getTags()
    {
        return response()->json(['tags' => Redis::smembers('tags')]);
    }

    public function getProducts()
    {
        $productsId = Redis::zrange('products', 0, -1);
        $products = [];
        foreach ($productsId as $productId) {
            $products[$productId] = Redis::hgetall("product:$productId");
        }
        return response()->json(['products' => $products], 200);
    }

    public function getProductsByTags(Request $request)
    {
        $productsId = Redis::lrange("tag:$request->tag", 0, -1);
        $products = [];
        foreach ($productsId as $productId) {
            $products[] = Redis::hgetall("product:{$productId}");
        }
        return response()->json(['products' => $products], 200);
    }

    public function getSingleProducts($id)
    {
        $products = Redis::zrange("products", 0, -1);
        if (in_array($id, $products)) {
            $product = Redis::hgetall("product:{$id}");
            return response()->json(['product' => $product], 200);
        } else {
            return response()->json(['error' => 'product not found'], 404);
        }
    }

    public function deleteTag(Request $request)
    {
        $tags = Redis::smembers('tags');
        $productsCount = $this->redis->get('products_id');
        if (!in_array($request->tag, $tags)) {
            abort(404, 'tag not found');
        }
        //delete tag
        Redis::srem('tags', $request->tag);
        //delete tag that has the products id
        $this->redis->del("tag:{$request->tag}");
        for ($i = 1; $i < $productsCount - 1; $i++) {
            $productsTags = $this->redis->smembers("product:{$i}:tags");
            if (in_array($request->tag, $productsTags)) {
                //delete products that has the tag
                $this->redis->srem("product:{$i}:tags", $request->tag);
            }
        }
        return response()->json(['message' => 'tag deleted'], 404);
    }

    public function deleteProduct($id)
    {
        $products = Redis::zrange("products", 0, -1);
        if (!in_array($id, $products)) {
            abort(404, 'product does not exist');
        }
        $this->redis->pipeline(function ($pipe) use ($id) {
            $pipe->zrem('products', $id);
            $pipe->decr('products_id');
            $pipe->del("product:{$id}");
            $pipe->del("product:{$id}:tags");
            $tags = $this->redis->keys('tag:*');
            $tagProducts = [];
            foreach ($tags as $tag) {
                $value = $this->redis->lrange($tag, 0, -1);
                $tagProducts = $value;
                foreach ($tagProducts as $product) {
                    if (in_array($id, $tagProducts)) {
                        $this->redis->lrem($tag, 1, $id);
                    }
                }
            }
        });
        return response()->json(['message' => 'product deleted']);
    }

    public function deleteProductByTag(Request $request)
    {
        $tags = $this->redis->smembers('tags');
        $products = $this->redis->zrange('products', 0, -1);
        if (!in_array($request->tag, $tags)) {
            abort(404, 'tag not found');
        }
        $tagsProcuts = $this->redis->lrange("tag:{$request->tag}", 0, -1);
        $this->redis->multi();
        foreach ($tagsProcuts as $productId) {
            if (in_array($productId, $products)) {
                $this->redis->pipeline(function ($pipe) use ($productId, $request) {
                    $pipe->zrem('products', $productId);
                    $pipe->decr('products_id');
                    $pipe->del("product:{$productId}");
                    $pipe->del("product:{$productId}:tags");
                    $pipe->del("tag:{$request->tag}");
                });
            }
        }
        $this->redis->exec();
        return response()->json(['message' => 'products deleted']);
    }
    //todo refactor tag's products
    //todo user auth
}
