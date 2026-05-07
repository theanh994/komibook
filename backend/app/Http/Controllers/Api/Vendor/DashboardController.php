<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Book;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $totalRevenue = Order::where('status', 'completed')->sum('total_amount');
        
        $pendingOrders = Order::whereIn('status', ['pending', 'processing'])->count();
        
        $totalBooks = Book::where('status', 'published')->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => $totalRevenue,
                'pending_orders' => $pendingOrders,
                'total_books' => $totalBooks,
            ]
        ]);
    }
}
