<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/stats
     *
     * Trả về các con số thống kê tổng quan và so sánh thực tế cho Admin tối cao.
     * Hỗ trợ số lượng cột động tương ứng với mốc thời gian (Year = 12 cột, Quarter = 3 cột, Month = 4 cột, 24h = 6 cột).
     */
    public function stats(Request $request): JsonResponse
    {
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $hasGlobalPeriodParam = $request->has('period');
        $globalPeriod = $request->query('period', '6_months');
        $revenuePeriod = $request->query('revenue_period', $globalPeriod);
        $accountPeriod = $request->query('account_period', $globalPeriod);
        $hasRevenuePeriodParam = $request->has('revenue_period') || $hasGlobalPeriodParam;
        $hasAccountPeriodParam = $request->has('account_period') || $hasGlobalPeriodParam;

        [$currentStart, $currentEnd, $prevStart, $prevEnd, $prevPeriodLabel] = $this->resolveDateRanges($globalPeriod, $hasGlobalPeriodParam);
        [$revStart, $revEnd] = $this->resolveDateRanges($revenuePeriod, $hasRevenuePeriodParam);
        [$accStart, $accEnd] = $this->resolveDateRanges($accountPeriod, $hasAccountPeriodParam);

        // Dynamic stats for selected global period vs previous period
        $currUsers = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevUsers = User::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currVendors = Vendor::withoutGlobalScopes()->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevVendors = Vendor::withoutGlobalScopes()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currUsedSellers = UsedBookSellerProfile::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevUsedSellers = UsedBookSellerProfile::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currBooks = Book::withoutGlobalScopes()->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $prevBooks = Book::withoutGlobalScopes()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currOrders = Order::withoutGlobalScopes()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();
        $prevOrders = Order::withoutGlobalScopes()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $currRevenue = (int) Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->sum('total_amount');
        $prevRevenue = (int) Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        // Totals overall
        $totalUsers = User::count();
        $totalVendors = Vendor::withoutGlobalScopes()->count();
        $totalUsedBookSellers = UsedBookSellerProfile::count();
        $totalBooks = Book::withoutGlobalScopes()->count();
        $totalOrders = Order::withoutGlobalScopes()->where('status', '!=', 'draft')->count();
        $totalRevenue = (int) Order::withoutGlobalScopes()->where('status', 'completed')->sum('total_amount');

        $pendingOrders = Order::withoutGlobalScopes()
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        // 1. Chart 1 Trend Data (Commerce Revenue & Orders with Dynamic Columns)
        $revIntervals = $this->buildTrendIntervals($revenuePeriod, $revStart, $revEnd);
        $revMinStart = $revIntervals[0]['start'];
        $revMaxEnd = end($revIntervals)['end'];

        $ordersForTrend = Order::withoutGlobalScopes()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$revMinStart, $revMaxEnd])
            ->get(['status', 'total_amount', 'created_at']);

        $commerceTrend = [
            'period' => $revenuePeriod,
            'labels' => array_column($revIntervals, 'label'),
            'orders' => array_map(function ($int) use ($ordersForTrend) {
                return $ordersForTrend
                    ->where('status', 'completed')
                    ->filter(fn (Order $o) => $o->created_at >= $int['start'] && $o->created_at <= $int['end'])
                    ->count();
            }, $revIntervals),
            'revenue' => array_map(function ($int) use ($ordersForTrend) {
                return (int) $ordersForTrend
                    ->where('status', 'completed')
                    ->filter(fn (Order $o) => $o->created_at >= $int['start'] && $o->created_at <= $int['end'])
                    ->sum('total_amount');
            }, $revIntervals),
        ];

        // 2. Chart 2 Trend Data (Account Growth with Dynamic Columns)
        $accIntervals = $this->buildTrendIntervals($accountPeriod, $accStart, $accEnd);
        $accMinStart = $accIntervals[0]['start'];
        $accMaxEnd = end($accIntervals)['end'];

        $usersForTrend = User::whereBetween('created_at', [$accMinStart, $accMaxEnd])->get(['created_at']);
        $usedBookSellersForTrend = UsedBookSellerProfile::whereBetween('created_at', [$accMinStart, $accMaxEnd])->get(['created_at']);
        $vendorsForTrend = Vendor::withoutGlobalScopes()->whereBetween('created_at', [$accMinStart, $accMaxEnd])->get(['created_at']);

        $accountGrowth = [
            'period' => $accountPeriod,
            'labels' => array_column($accIntervals, 'label'),
            'users' => array_map(fn ($int) => $usersForTrend->filter(fn ($r) => $r->created_at >= $int['start'] && $r->created_at <= $int['end'])->count(), $accIntervals),
            'used_book_sellers' => array_map(fn ($int) => $usedBookSellersForTrend->filter(fn ($r) => $r->created_at >= $int['start'] && $r->created_at <= $int['end'])->count(), $accIntervals),
            'vendors' => array_map(fn ($int) => $vendorsForTrend->filter(fn ($r) => $r->created_at >= $int['start'] && $r->created_at <= $int['end'])->count(), $accIntervals),
        ];

        $bookDistribution = [
            'by_type' => [
                'physical' => Book::withoutGlobalScopes()->where('type', 'physical')->count(),
                'ebook' => Book::withoutGlobalScopes()->where('type', 'ebook')->count(),
            ],
            'by_status' => Book::withoutGlobalScopes()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ];

        $orderStatusDistribution = Order::withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $topBooks = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->where('orders.status', 'completed')
            ->select('books.id', 'books.title', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.price * order_items.quantity) as revenue'))
            ->groupBy('books.id', 'books.title')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        if ($topBooks->isEmpty()) {
            $topBooks = Book::withoutGlobalScopes()
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (Book $b) => (object)[
                    'id' => $b->id,
                    'title' => $b->title,
                    'quantity' => 0,
                    'revenue' => (int) $b->price,
                ]);
        }

        $topCategories = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->leftJoin('categories', 'books.category_id', '=', 'categories.id')
            ->where('orders.status', 'completed')
            ->select(DB::raw('COALESCE(categories.name, "Khác") as name'), DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.price * order_items.quantity) as revenue'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        if ($topCategories->isEmpty()) {
            $topCategories = Category::query()
                ->limit(5)
                ->get()
                ->map(fn (Category $c) => (object)[
                    'name' => $c->name,
                    'quantity' => 0,
                    'revenue' => 0,
                ]);
        }

        $topVendors = Order::withoutGlobalScopes()
            ->where('orders.status', 'completed')
            ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->select('vendors.id', 'vendors.shop_name', DB::raw('SUM(orders.total_amount) as revenue'), DB::raw('COUNT(orders.id) as total_orders'))
            ->groupBy('vendors.id', 'vendors.shop_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        if ($topVendors->isEmpty()) {
            $topVendors = Vendor::withoutGlobalScopes()
                ->limit(5)
                ->get()
                ->map(fn (Vendor $v) => (object)[
                    'id' => $v->id,
                    'shop_name' => $v->shop_name,
                    'revenue' => 0,
                    'total_orders' => 0,
                ]);
        }

        $pendingPayouts = DB::table('payout_requests')->where('status', 'pending')->count();
        $pendingBooks = Book::withoutGlobalScopes()->where('status', 'draft')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $globalPeriod,
                'revenue_period' => $revenuePeriod,
                'account_period' => $accountPeriod,
                'prev_period_label' => $prevPeriodLabel,
                'total_users' => $totalUsers,
                'total_vendors' => $totalVendors,
                'total_used_book_sellers' => $totalUsedBookSellers,
                'total_books' => $totalBooks,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'pending_books' => $pendingBooks,
                'pending_payouts' => $pendingPayouts,
                'comparison' => [
                    'users' => $this->calcComparison($currUsers, $prevUsers),
                    'vendors' => $this->calcComparison($currVendors, $prevVendors),
                    'used_book_sellers' => $this->calcComparison($currUsedSellers, $prevUsedSellers),
                    'books' => $this->calcComparison($currBooks, $prevBooks),
                    'orders' => $this->calcComparison($currOrders, $prevOrders),
                    'revenue' => $this->calcComparison($currRevenue, $prevRevenue),
                ],
                'commerce_trend' => $commerceTrend,
                'account_growth' => $accountGrowth,
                'book_distribution' => $bookDistribution,
                'order_status_distribution' => $orderStatusDistribution,
                'top_books' => $topBooks,
                'top_categories' => $topCategories,
                'top_vendors' => $topVendors,
                'operational_queues' => [
                    'pending_orders' => $pendingOrders,
                    'pending_vendors' => Vendor::withoutGlobalScopes()->where('status', 'inactive')->count(),
                    'draft_books' => $pendingBooks,
                ],
            ],
        ]);
    }

    /**
     * Build dynamic period intervals matching the selected time range.
     * Returns array of items: [['label' => '...', 'start' => CarbonImmutable, 'end' => CarbonImmutable]]
     */
    private function buildTrendIntervals(string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $intervals = [];

        if ($period === 'day') {
            // 6 time blocks during the 24h day
            for ($h = 0; $h < 24; $h += 4) {
                $blockStart = $start->addHours($h);
                $blockEnd = $start->addHours($h + 4)->subSecond();
                $intervals[] = [
                    'label' => sprintf('%02dh-%02dh', $h, $h + 4),
                    'start' => $blockStart,
                    'end' => $blockEnd,
                ];
            }
        } elseif ($period === 'month') {
            // Daily slices for every day of the month (28, 30, or 31 days)
            $daysInMonth = $start->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dStart = $start->setDay($d)->startOfDay();
                $dEnd = $start->setDay($d)->endOfDay();
                $intervals[] = [
                    'label' => sprintf('%02d/%02d', $d, $start->month),
                    'start' => $dStart,
                    'end' => $dEnd,
                ];
            }
        } elseif ($period === '6_months') {
            // 6 monthly slices leading up to $end
            $endMonth = $end->startOfMonth();
            for ($m = 5; $m >= 0; $m--) {
                $mStart = $endMonth->subMonths($m)->startOfMonth();
                $mEnd = $mStart->endOfMonth();
                $intervals[] = [
                    'label' => $mStart->format('m/Y'),
                    'start' => $mStart,
                    'end' => $mEnd,
                ];
            }
        } elseif ($period === 'year') {
            // 12 months for the full year
            $yearStart = $start->startOfYear();
            for ($m = 1; $m <= 12; $m++) {
                $mStart = $yearStart->setMonth($m)->startOfMonth();
                $mEnd = $mStart->endOfMonth();
                $intervals[] = [
                    'label' => 'T' . $m,
                    'start' => $mStart,
                    'end' => $mEnd,
                ];
            }
        } else {
            // Quarter format: Q1-2026, Q2-2026, Q3-2026, Q4-2025
            // 3 months of that quarter
            $mStart = $start->startOfMonth();
            for ($i = 0; $i < 3; $i++) {
                $currMStart = $mStart->addMonths($i)->startOfMonth();
                $currMEnd = $currMStart->endOfMonth();
                $intervals[] = [
                    'label' => $currMStart->format('m/Y'),
                    'start' => $currMStart,
                    'end' => $currMEnd,
                ];
            }
        }

        return $intervals;
    }

    /**
     * Parse date ranges for requested period and calculate previous period.
     */
    private function resolveDateRanges(string $period, bool $hasPeriodParam = true): array
    {
        $now = CarbonImmutable::now();

        if (!$hasPeriodParam) {
            $currStart = CarbonImmutable::create(2026, 1, 1)->startOfDay();
            $currEnd = $now->endOfMonth();
            $prevStart = CarbonImmutable::create(2025, 1, 1)->startOfDay();
            $prevEnd = CarbonImmutable::create(2025, 12, 31)->endOfDay();
            $prevLabel = 'Năm 2025';
            return [$currStart, $currEnd, $prevStart, $prevEnd, $prevLabel];
        }

        if ($period === 'day') {
            $currStart = $now->startOfDay();
            $currEnd = $now->endOfDay();
            $prevStart = $now->subDay()->startOfDay();
            $prevEnd = $now->subDay()->endOfDay();
            $prevLabel = 'Hôm qua';
        } elseif ($period === 'month') {
            $currStart = $now->startOfMonth();
            $currEnd = $now->endOfMonth();
            $prevStart = $now->subMonth()->startOfMonth();
            $prevEnd = $now->subMonth()->endOfMonth();
            $prevLabel = 'Tháng trước';
        } elseif ($period === '6_months') {
            $currStart = $now->subMonths(5)->startOfMonth();
            $currEnd = $now->endOfMonth();
            $prevStart = $now->subMonths(11)->startOfMonth();
            $prevEnd = $now->subMonths(6)->endOfMonth();
            $prevLabel = '6 tháng trước';
        } elseif ($period === 'year') {
            $currStart = CarbonImmutable::create(2026, 1, 1)->startOfDay();
            $currEnd = CarbonImmutable::create(2026, 12, 31)->endOfDay();
            $prevStart = CarbonImmutable::create(2025, 1, 1)->startOfDay();
            $prevEnd = CarbonImmutable::create(2025, 12, 31)->endOfDay();
            $prevLabel = 'Năm 2025';
        } else {
            // Quarter format: Q1-2026, Q2-2026, Q3-2026, Q4-2025
            if (preg_match('/^Q([1-4])-(\d{4})$/', $period, $matches)) {
                $q = (int) $matches[1];
                $year = (int) $matches[2];

                $startMonth = ($q - 1) * 3 + 1;
                $currStart = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
                $currEnd = $currStart->addMonths(2)->endOfMonth();

                if ($q === 1) {
                    $prevStart = CarbonImmutable::create($year - 1, 10, 1)->startOfDay();
                    $prevEnd = $prevStart->addMonths(2)->endOfMonth();
                    $prevLabel = 'Quý 4-' . ($year - 1);
                } else {
                    $prevStart = CarbonImmutable::create($year, $startMonth - 3, 1)->startOfDay();
                    $prevEnd = $prevStart->addMonths(2)->endOfMonth();
                    $prevLabel = 'Quý ' . ($q - 1) . '-' . $year;
                }
            } else {
                $currStart = CarbonImmutable::create(2026, 1, 1)->startOfDay();
                $currEnd = CarbonImmutable::create(2026, 12, 31)->endOfDay();
                $prevStart = CarbonImmutable::create(2025, 1, 1)->startOfDay();
                $prevEnd = CarbonImmutable::create(2025, 12, 31)->endOfDay();
                $prevLabel = 'Năm 2025';
            }
        }

        return [$currStart, $currEnd, $prevStart, $prevEnd, $prevLabel];
    }

    /**
     * Calculate comparison math: current vs previous.
     */
    private function calcComparison(float $current, float $prev): array
    {
        $diff = $current - $prev;
        $pct = $prev > 0 ? round(($diff / $prev) * 100, 1) : ($current > 0 ? 100 : 0);
        $trend = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neutral');

        return [
            'current' => $current,
            'previous' => $prev,
            'diff' => $diff,
            'pct' => $pct,
            'trend' => $trend,
        ];
    }
}
